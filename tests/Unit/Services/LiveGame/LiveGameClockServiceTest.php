<?php

namespace Tests\Unit\Services\LiveGame;

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\User;
use App\Services\LiveGame\LiveGameClockService;
use App\Services\LiveGame\LiveGameStateBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LiveGameClockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([LiveGameStateUpdated::class]);
    }

    public function test_start_makes_a_setup_game_live_and_returns_a_server_clock_snapshot(): void
    {
        $this->travelTo('2026-08-02 12:00:00');
        $game = LiveGame::factory()->withBothLineups()->create();

        $snapshot = $this->handle($game, ['action' => 'start']);

        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
            'clock_seconds_remaining' => 600,
        ]);
        $game->refresh();
        $this->assertTrue($game->started_at->equalTo(now()));
        $this->assertSame('2026-08-02', $game->game_date->toDateString());
        $this->assertSame([
            'period' => 1,
            'period_length_seconds' => 600,
            'seconds_remaining' => 600,
            'running' => true,
            'server_now' => now()->toISOString(),
        ], $snapshot['clock']);
        Event::assertDispatched(LiveGameStateUpdated::class);
    }

    public function test_stop_persists_elapsed_server_time_and_stops_the_clock(): void
    {
        $this->travelTo('2026-08-02 12:00:00');
        $game = LiveGame::factory()->withBothLineups()->create();
        $this->handle($game, ['action' => 'start']);

        $this->travel(90)->seconds();
        $snapshot = $this->handle($game, ['action' => 'stop']);

        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'clock_seconds_remaining' => 510,
            'clock_running' => false,
            'clock_started_at' => null,
        ]);
        $this->assertSame(510, $snapshot['clock']['seconds_remaining']);
        $this->assertFalse($snapshot['clock']['running']);
    }

    public function test_a_running_snapshot_uses_elapsed_server_time(): void
    {
        $this->travelTo('2026-08-02 12:00:00');
        $game = LiveGame::factory()->withBothLineups()->create();
        $this->handle($game, ['action' => 'start']);

        $this->travel(90)->seconds();
        $snapshot = app(LiveGameStateBuilder::class)->build($game);

        $this->assertSame(510, $snapshot['clock']['seconds_remaining']);
        $this->assertTrue($snapshot['clock']['running']);
    }

    public function test_start_is_idempotent_for_a_running_clock(): void
    {
        $this->travelTo('2026-08-02 12:00:00');
        $game = LiveGame::factory()->withBothLineups()->create();
        $this->handle($game, ['action' => 'start']);
        $game->refresh();
        $startedAt = $game->clock_started_at;

        $this->travel(90)->seconds();
        $snapshot = $this->handle($game, ['action' => 'start']);

        $game->refresh();
        $this->assertSame(510, $snapshot['clock']['seconds_remaining']);
        $this->assertTrue($snapshot['clock']['running']);
        $this->assertTrue($game->clock_started_at->equalTo($startedAt));
        $this->assertSame(600, $game->clock_seconds_remaining);
    }

    public function test_an_expired_running_clock_is_persisted_as_stopped_at_zero(): void
    {
        $this->travelTo('2026-08-02 12:00:00');
        $game = LiveGame::factory()->withBothLineups()->create(['clock_seconds_remaining' => 5]);
        $this->handle($game, ['action' => 'start']);

        $this->travel(5)->seconds();
        $snapshot = $this->handle($game, ['action' => 'stop']);

        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'clock_seconds_remaining' => 0,
            'clock_running' => false,
            'clock_started_at' => null,
        ]);
        $this->assertSame(0, $snapshot['clock']['seconds_remaining']);
    }

    public function test_an_opponent_main_coach_may_stop_the_clock(): void
    {
        $this->travelTo('2026-08-02 12:00:00');
        $game = LiveGame::factory()->withBothLineups()->create(['status' => LiveGame::STATUS_LIVE]);
        $this->handle($game, ['action' => 'start']);

        $opponentCoach = User::factory()->forTeam($game->opponentTeam)->create();
        $game->forceFill(['opponent_main_coach_user_id' => $opponentCoach->id])->save();

        $this->travel(90)->seconds();
        $snapshot = app(LiveGameClockService::class)->handle($game->fresh(), $opponentCoach, ['action' => 'stop']);

        $this->assertFalse($snapshot['clock']['running']);
        $this->assertSame(510, $snapshot['clock']['seconds_remaining']);
    }

    public function test_an_opponent_main_coach_may_not_start_the_clock(): void
    {
        $game = LiveGame::factory()->withBothLineups()->create(['status' => LiveGame::STATUS_LIVE]);
        $opponentCoach = User::factory()->forTeam($game->opponentTeam)->create();
        $game->forceFill(['opponent_main_coach_user_id' => $opponentCoach->id])->save();

        $this->expectException(ValidationException::class);

        app(LiveGameClockService::class)->handle($game->fresh(), $opponentCoach, ['action' => 'start']);
    }

    public function test_a_coach_who_is_not_a_main_coach_may_not_stop_the_clock(): void
    {
        $game = LiveGame::factory()->withBothLineups()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
            'clock_started_at' => now(),
        ]);
        $stranger = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(LiveGameClockService::class)->handle($game->fresh(), $stranger, ['action' => 'stop']);
    }

    public function test_starting_an_expired_clock_reports_that_the_period_has_ended(): void
    {
        $game = LiveGame::factory()->withBothLineups()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_seconds_remaining' => 0,
            'clock_running' => false,
        ]);

        try {
            $this->handle($game, ['action' => 'start']);
            $this->fail('Expected a ValidationException for starting an expired clock.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('has ended', $exception->errors()['game'][0]);
        }

        $this->assertFalse($game->fresh()->clock_running);
    }

    public function test_set_period_stops_the_clock_and_uses_the_supplied_remaining_seconds(): void
    {
        $game = LiveGame::factory()->create(['clock_running' => true, 'clock_started_at' => now()]);

        $snapshot = $this->handle($game, [
            'action' => 'set_period',
            'period' => 2,
            'clock_seconds_remaining' => 300,
        ]);

        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'current_period' => 2,
            'clock_seconds_remaining' => 300,
            'clock_running' => false,
            'clock_started_at' => null,
        ]);
        $this->assertSame(2, $snapshot['clock']['period']);
        $this->assertSame(300, $snapshot['clock']['seconds_remaining']);
    }

    public function test_reset_period_stops_the_clock_and_restores_the_configured_period_length(): void
    {
        $game = LiveGame::factory()->create([
            'period_length_seconds' => 480,
            'clock_seconds_remaining' => 123,
            'clock_running' => true,
            'clock_started_at' => now(),
        ]);

        $snapshot = $this->handle($game, ['action' => 'reset_period']);

        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'clock_seconds_remaining' => 480,
            'clock_running' => false,
            'clock_started_at' => null,
        ]);
        $this->assertSame(480, $snapshot['clock']['seconds_remaining']);
    }

    public function test_set_period_rejects_backward_and_out_of_range_transitions(): void
    {
        $game = LiveGame::factory()->create(['current_period' => 2]);

        $this->assertValidationException(
            fn (): array => $this->handle($game, ['action' => 'set_period', 'period' => 1]),
        );
        $this->assertValidationException(
            fn (): array => $this->handle($game, ['action' => 'set_period', 'period' => 5]),
        );

        $this->assertDatabaseHas('live_games', ['id' => $game->id, 'current_period' => 2]);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function handle(LiveGame $game, array $input): array
    {
        $user = $game->creator ?? User::factory()->create();
        if ($game->created_by_user_id === null) {
            $game->forceFill(['created_by_user_id' => $user->id])->save();
        }

        return app(LiveGameClockService::class)->handle($game->fresh(), $user, $input);
    }

    /** @param callable(): array<string, mixed> $callback */
    private function assertValidationException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected clock action to fail validation.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors()['period']);
        }
    }
}
