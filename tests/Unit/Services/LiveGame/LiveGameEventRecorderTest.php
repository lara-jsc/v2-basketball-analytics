<?php

namespace Tests\Unit\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\Player;
use App\Models\User;
use App\Services\LiveGame\LiveGameEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LiveGameEventRecorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    public function test_it_rejects_recording_against_a_setup_game(): void
    {
        [$game, $player, $user] = $this->gameWithStarter();

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, $this->ownEvent($player)),
            'game',
        );

        $this->assertDatabaseCount('live_game_events', 0);
    }

    public function test_it_rejects_non_correction_events_against_a_finished_game(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_FINISHED);

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, $this->ownEvent($player)),
            'game',
        );

        $this->assertDatabaseCount('live_game_events', 0);
    }

    public function test_it_rejects_a_substitution_when_the_outgoing_player_is_inactive(): void
    {
        [$game, $starter, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $bench = Player::factory()->for($game->homeTeam)->create();

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, $this->substitution($bench, $starter)),
            'payload.player_out_id',
        );

        $this->assertDatabaseCount('live_game_events', 0);
    }

    public function test_it_rejects_a_substitution_when_the_incoming_player_is_already_active(): void
    {
        [$game, $starter, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $otherStarter = Player::factory()->for($game->homeTeam)->create();
        $bench = Player::factory()->for($game->homeTeam)->create();
        $game->update(['starting_player_ids' => [$starter->id, $otherStarter->id]]);

        $this->record($game, $user, $this->substitution($starter, $bench));

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, $this->substitution($otherStarter, $bench)),
            'payload.player_in_id',
        );

        $this->assertDatabaseCount('live_game_events', 1);
    }

    public function test_it_records_running_clock_substitutions_with_the_effective_clock_and_correct_stint_duration(): void
    {
        $this->travelTo('2026-08-02 12:00:00');
        [$game, $starter, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $bench = Player::factory()->for($game->homeTeam)->create();
        $game->update([
            'clock_running' => true,
            'clock_started_at' => now(),
        ]);

        $this->travel(90)->seconds();
        $this->record($game, $user, $this->substitution($starter, $bench));

        $this->assertDatabaseHas('live_game_events', [
            'live_game_id' => $game->id,
            'type' => 'substitution',
            'clock_seconds_remaining' => 510,
        ]);
        $this->assertDatabaseHas('live_game_lineup_stints', [
            'live_game_id' => $game->id,
            'player_id' => $starter->id,
            'duration_seconds' => 90,
        ]);
    }

    public function test_it_rejects_an_own_player_event_for_a_benched_player_until_a_substitution_activates_them(): void
    {
        [$game, $starter, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $bench = Player::factory()->for($game->homeTeam)->create();

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, $this->ownEvent($bench)),
            'player_id',
        );

        $this->record($game, $user, $this->substitution($starter, $bench));
        $this->record($game, $user, $this->ownEvent($bench));

        $this->assertDatabaseHas('live_game_events', [
            'live_game_id' => $game->id,
            'type' => 'shot_made',
            'player_id' => $bench->id,
        ]);
    }

    /** @return array{LiveGame, Player, User} */
    private function gameWithStarter(string $status = LiveGame::STATUS_SETUP): array
    {
        $game = LiveGame::factory()->create(['status' => $status]);
        $player = Player::factory()->for($game->homeTeam)->create();
        $user = User::factory()->create();
        $game->update(['starting_player_ids' => [$player->id]]);

        return [$game, $player, $user];
    }

    /** @return array<string, mixed> */
    private function ownEvent(Player $player): array
    {
        return [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 2],
        ];
    }

    /** @return array<string, mixed> */
    private function substitution(Player $playerOut, Player $playerIn): array
    {
        return [
            'type' => 'substitution',
            'team_scope' => 'game',
            'payload' => [
                'player_out_id' => $playerOut->id,
                'player_in_id' => $playerIn->id,
            ],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function record(LiveGame $game, User $user, array $input): array
    {
        return app(LiveGameEventRecorder::class)->record($game, $user, $input);
    }

    /** @param callable(): array<string, mixed> $callback */
    private function assertValidationException(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail('Expected event recording to fail validation.');
        } catch (ValidationException $exception) {
            $this->assertTrue($exception->errors()[$field] !== []);
        }
    }
}
