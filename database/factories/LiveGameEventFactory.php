<?php

namespace Database\Factories;

use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveGameEvent>
 */
class LiveGameEventFactory extends Factory
{
    protected $model = LiveGameEvent::class;

    public function definition(): array
    {
        return [
            'live_game_id' => LiveGame::factory(),
            'sequence' => 1,
            'type' => 'two_pt_made',
            'team_scope' => 'own',
            'player_id' => null,
            'period' => 1,
            'clock_seconds_remaining' => 600,
            'occurred_at' => now(),
            'payload' => null,
            'voids_event_id' => null,
            'recorded_by_user_id' => null,
        ];
    }
}
