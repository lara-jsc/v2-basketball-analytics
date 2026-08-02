<?php

namespace Tests\Unit\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameAlert;
use App\Models\LiveGameEvent;
use App\Models\Player;
use App\Services\LiveGame\LiveGameProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveGameProjectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_projects_made_and_missed_shots(): void
    {
        [$game, $player] = $this->gameWithPlayer();

        $this->event($game, 'shot_made', $player, ['points' => 3]);
        $this->event($game, 'shot_missed', $player, ['points' => 2]);

        $this->project($game);

        $this->assertDatabaseHas('live_games', ['id' => $game->id, 'home_score' => 3]);
        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $player->id,
            'points' => 3,
            'field_goals_made' => 1,
            'field_goals_attempted' => 2,
            'three_pointers_made' => 1,
            'three_pointers_attempted' => 1,
        ]);
    }

    public function test_it_projects_made_and_missed_free_throws(): void
    {
        [$game, $player] = $this->gameWithPlayer();

        $this->event($game, 'free_throw_made', $player);
        $this->event($game, 'free_throw_missed', $player);

        $this->project($game);

        $this->assertDatabaseHas('live_games', ['id' => $game->id, 'home_score' => 1]);
        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $player->id,
            'points' => 1,
            'free_throws_made' => 1,
            'free_throws_attempted' => 2,
        ]);
    }

    public function test_it_projects_rebounds_assists_fouls_and_turnovers(): void
    {
        [$game, $player] = $this->gameWithPlayer();

        $this->event($game, 'rebound', $player, ['kind' => 'offensive']);
        $this->event($game, 'rebound', $player, ['kind' => 'defensive']);
        $this->event($game, 'assist', $player);
        $this->event($game, 'foul', $player);
        $this->event($game, 'foul', $player, ['kind' => 'technical']);
        $this->event($game, 'foul', $player, ['kind' => 'flagrant']);
        $this->event($game, 'turnover', $player);

        $this->project($game);

        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $player->id,
            'rebounds' => 2,
            'offensive_rebounds' => 1,
            'defensive_rebounds' => 1,
            'assists' => 1,
            'personal_fouls' => 1,
            'technical_fouls' => 1,
            'flagrant_fouls' => 1,
            'turnovers' => 1,
        ]);
    }

    public function test_it_projects_aggregate_opponent_scoring(): void
    {
        [$game] = $this->gameWithPlayer();

        $this->event($game, 'opponent_score', null, ['points' => 3], 'opponent');
        $this->event($game, 'opponent_score', null, ['points' => 1], 'opponent');

        $this->project($game);

        $this->assertDatabaseHas('live_games', ['id' => $game->id, 'home_score' => 0, 'opponent_score' => 4]);
    }

    public function test_it_rebuilds_active_players_from_starters_and_substitutions(): void
    {
        [$game, $starter] = $this->gameWithPlayer();
        $bench = Player::factory()->for($game->homeTeam)->create();

        $this->event($game, 'substitution', null, [
            'player_out_id' => $starter->id,
            'player_in_id' => $bench->id,
        ], 'game');

        $this->project($game);

        $game->refresh();

        $this->assertSame([$bench->id], $game->active_player_ids);
        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $starter->id,
            'is_starter' => true,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $bench->id,
            'is_starter' => false,
            'is_active' => true,
        ]);
    }

    public function test_it_closes_and_opens_lineup_stints_at_the_substitution_position(): void
    {
        [$game, $starter] = $this->gameWithPlayer();
        $bench = Player::factory()->for($game->homeTeam)->create();

        $this->event($game, 'substitution', null, [
            'player_out_id' => $starter->id,
            'player_in_id' => $bench->id,
        ], 'game', null, 1, 420);

        $this->project($game);

        $this->assertDatabaseHas('live_game_lineup_stints', [
            'live_game_id' => $game->id,
            'player_id' => $starter->id,
            'start_period' => 1,
            'start_clock_seconds_remaining' => 600,
            'end_period' => 1,
            'end_clock_seconds_remaining' => 420,
            'duration_seconds' => 180,
        ]);
        $this->assertDatabaseHas('live_game_lineup_stints', [
            'live_game_id' => $game->id,
            'player_id' => $bench->id,
            'start_period' => 1,
            'start_clock_seconds_remaining' => 420,
            'end_period' => null,
            'end_clock_seconds_remaining' => null,
        ]);
    }

    public function test_it_derives_minutes_for_closed_and_active_stints_from_event_and_clock_positions(): void
    {
        $this->travelTo('2026-08-02 12:00:00');
        [$game, $starter] = $this->gameWithPlayer();
        $bench = Player::factory()->for($game->homeTeam)->create();
        $game->update([
            'clock_running' => true,
            'clock_started_at' => now(),
        ]);
        $this->event($game, 'substitution', null, [
            'player_out_id' => $starter->id,
            'player_in_id' => $bench->id,
        ], 'game', null, 1, 420);

        $this->travel(300)->seconds();

        $this->project($game);

        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $starter->id,
            'minutes_seconds' => 180,
        ]);
        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $bench->id,
            'minutes_seconds' => 120,
        ]);
        $this->assertDatabaseHas('live_game_lineup_stints', [
            'live_game_id' => $game->id,
            'player_id' => $bench->id,
            'duration_seconds' => 120,
            'end_period' => null,
        ]);
    }

    public function test_it_attributes_plus_minus_only_to_players_active_for_each_score(): void
    {
        [$game, $starter] = $this->gameWithPlayer();
        $bench = Player::factory()->for($game->homeTeam)->create();
        $this->event($game, 'shot_made', $starter, ['points' => 2], 'own', null, 1, 560);
        $this->event($game, 'substitution', null, [
            'player_out_id' => $starter->id,
            'player_in_id' => $bench->id,
        ], 'game', null, 1, 540);
        $this->event($game, 'opponent_score', null, ['points' => 3], 'opponent', null, 1, 500);
        $this->event($game, 'shot_made', $bench, ['points' => 3], 'own', null, 1, 480);

        $this->project($game);

        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $starter->id,
            'plus_minus' => 2,
        ]);
        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $bench->id,
            'plus_minus' => 0,
        ]);
        $this->assertDatabaseHas('live_game_lineup_stints', [
            'live_game_id' => $game->id,
            'player_id' => $starter->id,
            'plus_minus' => 2,
        ]);
        $this->assertDatabaseHas('live_game_lineup_stints', [
            'live_game_id' => $game->id,
            'player_id' => $bench->id,
            'plus_minus' => 0,
        ]);
    }

    public function test_timeout_has_no_projection_effect(): void
    {
        [$game, $player] = $this->gameWithPlayer();

        $this->event($game, 'timeout', null, ['team' => 'own'], 'game');
        $this->project($game);

        $game->refresh();

        $this->assertSame(0, $game->home_score);
        $this->assertSame(0, $game->opponent_score);
        $this->assertDatabaseCount('live_game_player_stats', 1);
        $this->assertDatabaseHas('live_game_player_stats', ['live_game_id' => $game->id, 'player_id' => $player->id]);
    }

    public function test_it_ignores_voided_events_and_correction_stat_effects(): void
    {
        [$game, $player] = $this->gameWithPlayer();

        $madeShot = $this->event($game, 'shot_made', $player, ['points' => 2]);
        $this->event($game, 'correction', null, [], 'game', $madeShot->id);

        $this->project($game);

        $this->assertDatabaseHas('live_games', ['id' => $game->id, 'home_score' => 0]);
        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $player->id,
            'points' => 0,
            'field_goals_made' => 0,
            'field_goals_attempted' => 0,
        ]);
    }

    public function test_it_does_not_create_or_sustain_alerts_from_voided_events(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $firstMake = $this->event($game, 'shot_made', $player, ['points' => 2]);
        $secondMake = $this->event($game, 'shot_made', $player, ['points' => 2]);
        $thirdMake = $this->event($game, 'shot_made', $player, ['points' => 3]);

        $this->project($game);

        $this->assertDatabaseHas('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'hot_player',
            'player_id' => $player->id,
            'resolved_at' => null,
        ]);

        $this->event($game, 'correction', null, [], 'game', $firstMake->id);
        $this->event($game, 'correction', null, [], 'game', $secondMake->id);
        $this->event($game, 'correction', null, [], 'game', $thirdMake->id);

        $this->project($game);

        $this->assertDatabaseMissing('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'hot_player',
            'player_id' => $player->id,
            'resolved_at' => null,
        ]);
    }

    public function test_it_does_not_create_alerts_from_events_voided_before_projection(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $firstMake = $this->event($game, 'shot_made', $player, ['points' => 2]);
        $secondMake = $this->event($game, 'shot_made', $player, ['points' => 2]);
        $thirdMake = $this->event($game, 'shot_made', $player, ['points' => 3]);
        $firstOpponentScore = $this->event($game, 'opponent_score', null, ['points' => 3], 'opponent');
        $secondOpponentScore = $this->event($game, 'opponent_score', null, ['points' => 2], 'opponent');
        $thirdOpponentScore = $this->event($game, 'opponent_score', null, ['points' => 3], 'opponent');

        foreach ([$firstMake, $secondMake, $thirdMake, $firstOpponentScore, $secondOpponentScore, $thirdOpponentScore] as $event) {
            $this->event($game, 'correction', null, [], 'game', $event->id);
        }

        $this->project($game);

        $this->assertDatabaseMissing('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'hot_player',
            'player_id' => $player->id,
            'resolved_at' => null,
        ]);
        $this->assertDatabaseMissing('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'opponent_run',
            'resolved_at' => null,
        ]);
    }

    public function test_it_retains_unchanged_active_alerts_across_projection_rebuilds(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->event($game, 'shot_made', $player, ['points' => 2]);
        $this->event($game, 'shot_made', $player, ['points' => 2]);
        $this->event($game, 'shot_made', $player, ['points' => 3]);

        $this->project($game);

        $originalAlert = LiveGameAlert::query()
            ->where('live_game_id', $game->id)
            ->where('type', 'hot_player')
            ->where('player_id', $player->id)
            ->whereNull('resolved_at')
            ->firstOrFail();

        $this->project($game);

        $this->assertDatabaseCount('live_game_alerts', 1);
        $currentAlert = LiveGameAlert::query()
            ->where('live_game_id', $game->id)
            ->where('type', 'hot_player')
            ->where('player_id', $player->id)
            ->whereNull('resolved_at')
            ->firstOrFail();
        $this->assertSame($originalAlert->id, $currentAlert->id);
        $this->assertTrue($originalAlert->triggered_at->equalTo($currentAlert->triggered_at));
    }

    public function test_it_resolves_an_opponent_run_alert_after_an_own_score(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->event($game, 'opponent_score', null, ['points' => 3], 'opponent');
        $this->event($game, 'opponent_score', null, ['points' => 2], 'opponent');
        $this->event($game, 'opponent_score', null, ['points' => 3], 'opponent');
        $this->project($game);

        $this->assertDatabaseHas('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'opponent_run',
            'resolved_at' => null,
        ]);

        $this->event($game, 'shot_made', $player, ['points' => 2]);
        $this->project($game);

        $this->assertDatabaseMissing('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'opponent_run',
            'resolved_at' => null,
        ]);
    }

    public function test_it_resolves_a_team_drought_alert_after_an_own_score(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->event($game, 'shot_missed', $player, ['points' => 2]);
        $this->event($game, 'turnover', $player);
        $this->event($game, 'free_throw_missed', $player);
        $this->event($game, 'shot_missed', $player, ['points' => 3]);
        $this->project($game);

        $this->assertDatabaseHas('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'team_drought',
            'resolved_at' => null,
        ]);

        $this->event($game, 'free_throw_made', $player);
        $this->project($game);

        $this->assertDatabaseMissing('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'team_drought',
            'resolved_at' => null,
        ]);
    }

    public function test_it_resolves_a_hot_player_alert_when_the_current_period_changes(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->event($game, 'shot_made', $player, ['points' => 2]);
        $this->event($game, 'shot_made', $player, ['points' => 2]);
        $this->event($game, 'shot_made', $player, ['points' => 3]);
        $this->project($game);

        $this->assertDatabaseHas('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'hot_player',
            'player_id' => $player->id,
            'resolved_at' => null,
        ]);

        $game->update(['current_period' => 2]);
        $this->project($game);

        $this->assertDatabaseMissing('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'hot_player',
            'player_id' => $player->id,
            'resolved_at' => null,
        ]);
    }

    public function test_it_resolves_a_three_foul_alert_when_the_fourth_quarter_threshold_applies(): void
    {
        [$game, $player] = $this->gameWithPlayer(['current_period' => 3]);
        $this->event($game, 'foul', $player);
        $this->event($game, 'foul', $player);
        $this->event($game, 'foul', $player);
        $this->project($game);

        $this->assertDatabaseHas('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'foul_trouble',
            'player_id' => $player->id,
            'resolved_at' => null,
        ]);

        $game->update(['current_period' => 4]);
        $this->project($game);

        $this->assertDatabaseMissing('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'foul_trouble',
            'player_id' => $player->id,
            'resolved_at' => null,
        ]);
    }

    public function test_it_projects_opponent_player_made_shots_into_opponent_score(): void
    {
        $game = LiveGame::factory()->create();
        $homePlayer = Player::factory()->for($game->homeTeam)->create();
        $opponentPlayer = Player::factory()->for($game->opponentTeam)->create();
        $game->update([
            'starting_player_ids' => [$homePlayer->id],
            'active_player_ids' => [$homePlayer->id],
            'opponent_starting_player_ids' => [$opponentPlayer->id],
            'opponent_active_player_ids' => [$opponentPlayer->id],
        ]);

        $this->event($game, 'shot_made', $homePlayer, ['points' => 2]);
        $this->event($game, 'shot_made', $opponentPlayer, ['points' => 3]);

        $this->project($game);

        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'home_score' => 2,
            'opponent_score' => 3,
        ]);
        $game->refresh();
        $this->assertSame([$homePlayer->id], $game->active_player_ids);
        $this->assertSame([$opponentPlayer->id], $game->opponent_active_player_ids);
    }

    private function project(LiveGame $game): void
    {
        app(LiveGameProjectionService::class)->rebuild($game);
    }

    /** @return array{LiveGame, Player} */
    private function gameWithPlayer(): array
    {
        $game = LiveGame::factory()->create();
        $player = Player::factory()->for($game->homeTeam)->create();
        $game->update(['starting_player_ids' => [$player->id]]);

        return [$game, $player];
    }

    /** @param array<string, mixed> $payload */
    private function event(LiveGame $game, string $type, ?Player $player = null, array $payload = [], string $teamScope = 'own', ?int $voidsEventId = null, int $period = 1, int $clockSecondsRemaining = 600): LiveGameEvent
    {
        return LiveGameEvent::query()->create([
            'live_game_id' => $game->id,
            'sequence' => (int) $game->events()->max('sequence') + 1,
            'type' => $type,
            'team_scope' => $teamScope,
            'player_id' => $player?->id,
            'period' => $period,
            'clock_seconds_remaining' => $clockSecondsRemaining,
            'occurred_at' => now(),
            'payload' => $payload,
            'voids_event_id' => $voidsEventId,
        ]);
    }
}
