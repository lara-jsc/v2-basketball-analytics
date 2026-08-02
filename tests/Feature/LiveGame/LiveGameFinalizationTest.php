<?php

namespace Tests\Feature\LiveGame;

use App\Events\LiveGameStateUpdated;
use App\Jobs\RebuildPlayerStats;
use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\LiveGamePlayerStat;
use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\User;
use App\Services\LiveGame\LiveGameFinalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LiveGameFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finishing_a_live_game_creates_history_and_queues_stat_rebuilds(): void
    {
        Event::fake([LiveGameStateUpdated::class]);
        Queue::fake();
        $this->travelTo('2026-08-02 12:00:00');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $game = LiveGame::factory()->create([
            'status' => LiveGame::STATUS_LIVE,
            'game_date' => '2026-08-02',
            'clock_running' => true,
            'clock_started_at' => now()->subSeconds(90),
        ]);
        $player = Player::factory()->for($game->homeTeam)->create();
        $game->update([
            'starting_player_ids' => [$player->id],
            'active_player_ids' => [$player->id],
        ]);
        $this->recordThreePointShot($game, $player);

        $this->actingAs($user)->post(route('live-games.finish', $game))
            ->assertRedirect(route('live-games.index'));

        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'status' => LiveGame::STATUS_FINISHED,
            'clock_running' => false,
        ]);
        $this->assertFinalizedHistory($game, $player, 1.50);
        Queue::assertPushed(RebuildPlayerStats::class, fn (RebuildPlayerStats $job): bool => $job->playerId === $player->id && $job->afterCommit === true);
    }

    public function test_finalizer_rebuilds_stale_live_projections_before_writing_history(): void
    {
        Queue::fake();
        $game = LiveGame::factory()->create([
            'status' => LiveGame::STATUS_LIVE,
            'game_date' => '2026-08-02',
            'clock_seconds_remaining' => 510,
        ]);
        $player = Player::factory()->for($game->homeTeam)->create();
        $game->update([
            'starting_player_ids' => [$player->id],
            'active_player_ids' => [$player->id],
        ]);
        $this->recordThreePointShot($game, $player, 510);
        LiveGamePlayerStat::query()->create([
            'live_game_id' => $game->id,
            'player_id' => $player->id,
            'is_starter' => true,
            'is_active' => true,
            'points' => 99,
        ]);

        app(LiveGameFinalizer::class)->finalize($game);

        $this->assertFinalizedHistory($game, $player, 1.50);
        Queue::assertPushed(RebuildPlayerStats::class, fn (RebuildPlayerStats $job): bool => $job->playerId === $player->id);
    }

    public function test_finishing_a_game_finalizes_each_participating_player(): void
    {
        Event::fake([LiveGameStateUpdated::class]);
        Queue::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $game = LiveGame::factory()->create([
            'status' => LiveGame::STATUS_LIVE,
            'game_date' => '2026-08-02',
        ]);
        $starter = Player::factory()->for($game->homeTeam)->create();
        $benchPlayer = Player::factory()->for($game->homeTeam)->create();
        $game->update([
            'starting_player_ids' => [$starter->id],
            'active_player_ids' => [$starter->id],
        ]);
        $this->recordThreePointShot($game, $starter);
        $this->recordThreePointShot($game, $benchPlayer, 510, 2);

        $this->actingAs($user)->post(route('live-games.finish', $game))
            ->assertRedirect(route('live-games.index'));

        $this->assertDatabaseCount('player_histories', 2);
        $this->assertDatabaseHas('player_histories', ['player_id' => $starter->id, 'points' => 3]);
        $this->assertDatabaseHas('player_histories', ['player_id' => $benchPlayer->id, 'points' => 2]);
        Queue::assertPushed(RebuildPlayerStats::class, 2);
    }

    public function test_a_correction_after_finish_refinalizes_affected_player_history(): void
    {
        Event::fake([LiveGameStateUpdated::class]);
        Queue::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $game = LiveGame::factory()->create([
            'status' => LiveGame::STATUS_LIVE,
            'game_date' => '2026-08-02',
        ]);
        $player = Player::factory()->for($game->homeTeam)->create();
        $game->update([
            'starting_player_ids' => [$player->id],
            'active_player_ids' => [$player->id],
        ]);
        $scoredEvent = $this->recordThreePointShot($game, $player);

        $this->actingAs($user)->post(route('live-games.finish', $game))
            ->assertRedirect(route('live-games.index'));

        $this->actingAs($user)->postJson(route('live-games.correction', $game), [
            'voids_event_id' => $scoredEvent->id,
        ])->assertOk();

        $this->assertDatabaseHas('player_histories', [
            'player_id' => $player->id,
            'points' => 0,
            'field_goals_made' => 0,
            'field_goals_attempted' => 0,
        ]);
        Queue::assertPushed(RebuildPlayerStats::class, 2);
    }

    public function test_a_correction_after_finish_removes_a_voided_non_starter_history_and_rebuilds_them(): void
    {
        Event::fake([LiveGameStateUpdated::class]);
        Queue::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $game = LiveGame::factory()->create([
            'status' => LiveGame::STATUS_LIVE,
            'game_date' => '2026-08-02',
        ]);
        $starter = Player::factory()->for($game->homeTeam)->create();
        $benchPlayer = Player::factory()->for($game->homeTeam)->create();
        $game->update([
            'starting_player_ids' => [$starter->id],
            'active_player_ids' => [$starter->id],
        ]);
        $scoredEvent = $this->recordThreePointShot($game, $benchPlayer);

        $this->actingAs($user)->post(route('live-games.finish', $game))
            ->assertRedirect(route('live-games.index'));

        $this->actingAs($user)->postJson(route('live-games.correction', $game), [
            'voids_event_id' => $scoredEvent->id,
        ])->assertOk();

        $this->assertDatabaseCount('player_histories', 1);
        $this->assertDatabaseMissing('player_histories', ['player_id' => $benchPlayer->id]);
        Queue::assertPushed(RebuildPlayerStats::class, 4);
    }

    private function recordThreePointShot(LiveGame $game, Player $player, int $clockSecondsRemaining = 600, int $points = 3): LiveGameEvent
    {
        return LiveGameEvent::factory()->create([
            'live_game_id' => $game->id,
            'sequence' => (int) $game->events()->max('sequence') + 1,
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'clock_seconds_remaining' => $clockSecondsRemaining,
            'payload' => ['points' => $points],
        ]);
    }

    private function assertFinalizedHistory(LiveGame $game, Player $player, float $minutesPlayed): void
    {
        $this->assertDatabaseHas('player_histories', [
            'player_id' => $player->id,
            'playing_team_id' => $game->home_team_id,
            'opponent_team_id' => $game->opponent_team_id,
            'minutes_played' => $minutesPlayed,
            'points' => 3,
            'field_goals_made' => 1,
            'field_goals_attempted' => 1,
            'three_pointers_made' => 1,
            'three_pointers_attempted' => 1,
            'is_started' => true,
            'notes' => "Finalized from live game #{$game->id}",
        ]);

        $history = PlayerHistory::query()->where('player_id', $player->id)->sole();
        $this->assertSame('2026-08-02', $history->game_date->toDateString());
    }
}
