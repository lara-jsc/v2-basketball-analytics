<?php

namespace Tests\Unit\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameAlert;
use App\Models\LiveGameEvent;
use App\Models\Player;
use App\Services\LiveGame\LiveGameAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveGameAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_hot_player_alert_for_three_makes_in_the_current_quarter(): void
    {
        [$game, $player] = $this->gameWithPlayer(['current_period' => 2]);
        $this->event($game, 'shot_made', $player, ['points' => 2], period: 1);
        $this->event($game, 'shot_made', $player, ['points' => 2], period: 2);
        $this->event($game, 'shot_made', $player, ['points' => 3], period: 2);
        $this->event($game, 'shot_made', $player, ['points' => 2], period: 2);

        $this->sync($game);

        $this->assertAlert($game, 'hot_player', $player, ['made_shots' => 3, 'period' => 2]);
    }

    public function test_it_creates_a_cold_player_alert_for_three_misses_without_a_make(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->event($game, 'shot_missed', $player, ['points' => 2]);
        $this->event($game, 'shot_missed', $player, ['points' => 3]);
        $this->event($game, 'shot_missed', $player, ['points' => 2]);

        $this->sync($game);

        $this->assertAlert($game, 'cold_player', $player, ['miss_streak' => 3]);
    }

    public function test_it_creates_a_foul_trouble_alert_for_three_fouls_before_the_fourth_quarter(): void
    {
        [$game, $player] = $this->gameWithPlayer(['current_period' => 3]);
        $this->event($game, 'foul', $player, ['kind' => 'personal']);
        $this->event($game, 'foul', $player, ['kind' => 'technical']);
        $this->event($game, 'foul', $player, ['kind' => 'flagrant']);

        $this->sync($game);

        $this->assertAlert($game, 'foul_trouble', $player, ['fouls' => 3]);
    }

    public function test_it_requires_four_fouls_for_foul_trouble_in_the_fourth_quarter(): void
    {
        [$game, $player] = $this->gameWithPlayer(['current_period' => 4]);
        $this->event($game, 'foul', $player);
        $this->event($game, 'foul', $player);
        $this->event($game, 'foul', $player);

        $this->sync($game);

        $this->assertDatabaseMissing('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'foul_trouble',
            'player_id' => $player->id,
            'resolved_at' => null,
        ]);

        $this->event($game, 'foul', $player);
        $this->sync($game);

        $this->assertAlert($game, 'foul_trouble', $player, ['fouls' => 4]);
    }

    public function test_it_creates_an_opponent_run_and_timeout_prompt_after_eight_unanswered_points(): void
    {
        [$game] = $this->gameWithPlayer();
        $this->event($game, 'opponent_score', null, ['points' => 3], 'opponent');
        $this->event($game, 'opponent_score', null, ['points' => 2], 'opponent');
        $this->event($game, 'opponent_score', null, ['points' => 3], 'opponent');

        $this->sync($game);

        $this->assertAlert($game, 'opponent_run', null, ['points' => 8]);
        $this->assertAlert($game, 'timeout_prompt', null, ['reasons' => ['opponent_run']]);
    }

    public function test_it_creates_a_team_drought_and_timeout_prompt_after_four_empty_own_possessions(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->event($game, 'shot_missed', $player, ['points' => 2]);
        $this->event($game, 'turnover', $player);
        $this->event($game, 'free_throw_missed', $player);
        $this->event($game, 'shot_missed', $player, ['points' => 3]);

        $this->sync($game);

        $this->assertAlert($game, 'team_drought', null, ['empty_possessions' => 4]);
        $this->assertAlert($game, 'timeout_prompt', null, ['reasons' => ['team_drought']]);
    }

    public function test_it_creates_substitution_prompts_for_players_in_foul_trouble_or_on_a_cold_streak(): void
    {
        [$game, $coldPlayer] = $this->gameWithPlayer();
        $foulPlayer = Player::factory()->for($game->homeTeam)->create();
        $this->event($game, 'shot_missed', $coldPlayer, ['points' => 2]);
        $this->event($game, 'shot_missed', $coldPlayer, ['points' => 2]);
        $this->event($game, 'shot_missed', $coldPlayer, ['points' => 2]);
        $this->event($game, 'foul', $foulPlayer);
        $this->event($game, 'foul', $foulPlayer);
        $this->event($game, 'foul', $foulPlayer);

        $this->sync($game);

        $this->assertAlert($game, 'substitution_prompt', $coldPlayer, ['reason' => 'cold_player']);
        $this->assertAlert($game, 'substitution_prompt', $foulPlayer, ['reason' => 'foul_trouble']);
    }

    public function test_it_resolves_alerts_that_are_no_longer_active_after_rebuild(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->event($game, 'shot_missed', $player, ['points' => 2]);
        $this->event($game, 'shot_missed', $player, ['points' => 2]);
        $this->event($game, 'shot_missed', $player, ['points' => 2]);
        $this->sync($game);

        $activeAlert = LiveGameAlert::query()
            ->where('live_game_id', $game->id)
            ->where('type', 'cold_player')
            ->where('player_id', $player->id)
            ->whereNull('resolved_at')
            ->firstOrFail();
        $activePrompt = LiveGameAlert::query()
            ->where('live_game_id', $game->id)
            ->where('type', 'substitution_prompt')
            ->where('player_id', $player->id)
            ->whereNull('resolved_at')
            ->firstOrFail();

        $this->event($game, 'shot_made', $player, ['points' => 2]);
        $this->sync($game);

        $this->assertNotNull($activeAlert->fresh()->resolved_at);
        $this->assertDatabaseMissing('live_game_alerts', [
            'live_game_id' => $game->id,
            'type' => 'cold_player',
            'player_id' => $player->id,
            'resolved_at' => null,
        ]);
        $this->assertNotNull($activePrompt->fresh()->resolved_at);
    }

    private function sync(LiveGame $game): void
    {
        app(LiveGameAlertService::class)->sync(
            $game,
            $game->events()->orderBy('sequence')->get(),
        );
    }

    /** @return array{LiveGame, Player} */
    private function gameWithPlayer(array $attributes = []): array
    {
        $game = LiveGame::factory()->create($attributes);
        $player = Player::factory()->for($game->homeTeam)->create();

        return [$game, $player];
    }

    /** @param array<string, mixed> $payload */
    private function event(LiveGame $game, string $type, ?Player $player = null, array $payload = [], string $teamScope = 'own', int $period = 1): LiveGameEvent
    {
        return LiveGameEvent::query()->create([
            'live_game_id' => $game->id,
            'sequence' => (int) $game->events()->max('sequence') + 1,
            'type' => $type,
            'team_scope' => $teamScope,
            'player_id' => $player?->id,
            'period' => $period,
            'clock_seconds_remaining' => 600,
            'occurred_at' => now(),
            'payload' => $payload,
        ]);
    }

    /** @param array<string, mixed> $context */
    private function assertAlert(LiveGame $game, string $type, ?Player $player, array $context, bool $active = true): void
    {
        $query = LiveGameAlert::query()
            ->where('live_game_id', $game->id)
            ->where('type', $type)
            ->where('player_id', $player?->id);

        if ($active) {
            $query->whereNull('resolved_at');
        }

        $alert = $query->firstOrFail();

        $this->assertSame($context, $alert->context);
    }
}
