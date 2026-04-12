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

    /** @var list<string> */
    private static array $roles = [
        'Point Guard', 'Shooting Guard', 'Small Forward',
        'Power Forward', 'Center',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id'               => Team::factory(),
            'first_name'            => $this->faker->firstName(),
            'last_name'             => $this->faker->lastName(),
            'jersey_number'         => $this->faker->unique()->numberBetween(0, 99),
            'role'                  => $this->faker->randomElement(self::$roles),
            'height_feet'           => $this->faker->randomFloat(2, 5.5, 7.5),
            'weight_kg'             => $this->faker->randomFloat(2, 75, 130),
            'profile_picture_path'  => null,
            'is_active'             => true,
        ];
    }
}
