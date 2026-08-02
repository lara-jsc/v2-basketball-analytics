<?php

namespace Database\Factories;

use App\Models\LiveGame;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveGame>
 */
class LiveGameFactory extends Factory
{
    protected $model = LiveGame::class;

    public function definition(): array
    {
        return [
            'home_team_id' => Team::factory(),
            'opponent_team_id' => Team::factory(),
            'created_by_user_id' => User::factory(),
            'status' => 'setup',
            'game_date' => fake()->date(),
            'period_length_seconds' => 600,
            'current_period' => 1,
            'clock_seconds_remaining' => 600,
            'clock_running' => false,
            'clock_started_at' => null,
            'home_score' => 0,
            'opponent_score' => 0,
            'starting_player_ids' => null,
            'active_player_ids' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
