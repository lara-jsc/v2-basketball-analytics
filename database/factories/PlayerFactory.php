<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'team_id'              => Team::factory(),
            'first_name'           => fake()->firstName(),
            'last_name'            => fake()->lastName(),
            'jersey_number'        => fake()->unique()->numberBetween(0, 99),
            'role'                 => fake()->randomElement(['Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center']),
            'height_feet'          => fake()->randomFloat(2, 5.5, 7.5),
            'weight_kg'            => fake()->randomFloat(2, 70, 130),
            'profile_picture_path' => null,
            'is_active'            => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forTeam(Team $team): static
    {
        return $this->state(['team_id' => $team->id]);
    }
}
