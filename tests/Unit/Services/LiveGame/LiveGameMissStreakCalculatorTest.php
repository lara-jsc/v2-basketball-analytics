<?php

namespace Tests\Unit\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\Player;
use App\Services\LiveGame\LiveGameMissStreakCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveGameMissStreakCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_made_shot_resets_the_miss_streak(): void
    {
        $game = LiveGame::factory()->create();
        $player = Player::factory()->for($game->homeTeam)->create();

        $events = collect([
            $this->event($game, $player, 'shot_missed', 1),
            $this->event($game, $player, 'shot_missed', 2),
            $this->event($game, $player, 'shot_made', 3, ['points' => 2]),
            $this->event($game, $player, 'shot_missed', 4),
        ]);

        $streaks = app(LiveGameMissStreakCalculator::class)->compute($events);

        $this->assertSame(1, $streaks[$player->id] ?? 0);
    }

    public function test_three_consecutive_misses_yield_a_streak_of_three(): void
    {
        $game = LiveGame::factory()->create();
        $player = Player::factory()->for($game->homeTeam)->create();

        $events = collect([
            $this->event($game, $player, 'shot_missed', 1),
            $this->event($game, $player, 'shot_missed', 2),
            $this->event($game, $player, 'shot_missed', 3),
        ]);

        $streaks = app(LiveGameMissStreakCalculator::class)->compute($events);

        $this->assertSame(3, $streaks[$player->id] ?? 0);
    }

    public function test_free_throw_misses_do_not_increment_fg_miss_streak(): void
    {
        $game = LiveGame::factory()->create();
        $player = Player::factory()->for($game->homeTeam)->create();

        $events = collect([
            $this->event($game, $player, 'shot_missed', 1),
            $this->event($game, $player, 'free_throw_missed', 2),
        ]);

        $streaks = app(LiveGameMissStreakCalculator::class)->compute($events);

        $this->assertSame(1, $streaks[$player->id] ?? 0);
    }

    /** @param array<string, mixed> $payload */
    private function event(LiveGame $game, Player $player, string $type, int $sequence, array $payload = []): LiveGameEvent
    {
        return LiveGameEvent::query()->create([
            'live_game_id' => $game->id,
            'sequence' => $sequence,
            'type' => $type,
            'team_scope' => 'own',
            'player_id' => $player->id,
            'period' => 1,
            'clock_seconds_remaining' => 600,
            'occurred_at' => now(),
            'payload' => $payload,
        ]);
    }
}
