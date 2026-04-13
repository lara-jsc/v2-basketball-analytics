<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerStat>
 */
class PlayerStatFactory extends Factory
{
    protected $model = PlayerStat::class;

    public function definition(): array
    {
        $fgMade     = fake()->numberBetween(3, 12);
        $fgAttempts = $fgMade + fake()->numberBetween(3, 10);
        $ftMade     = fake()->numberBetween(0, 8);
        $ftAttempts = $ftMade + fake()->numberBetween(0, 4);
        $tpMade     = fake()->numberBetween(0, 4);
        $tpAttempts = $tpMade + fake()->numberBetween(0, 5);

        return [
            'player_id'          => Player::factory(),
            'pc'                 => fake()->randomElement(['PG', 'SG', 'SF', 'PF', 'C']),
            'pts'                => fake()->randomFloat(2, 5, 30),
            'reb'                => fake()->randomFloat(2, 1, 15),
            'ast'                => fake()->randomFloat(2, 0, 12),
            'blk'                => fake()->randomFloat(2, 0, 4),
            'stl'                => fake()->randomFloat(2, 0, 4),
            'fg_pct'             => round($fgMade / $fgAttempts, 2),
            'fg'                 => "{$fgMade}-{$fgAttempts}",
            'ft_pct'             => $ftAttempts > 0 ? round($ftMade / $ftAttempts, 2) : 0,
            'ft'                 => "{$ftMade}-{$ftAttempts}",
            'three_p_pct'        => $tpAttempts > 0 ? round($tpMade / $tpAttempts, 2) : 0,
            'three_pt'           => "{$tpMade}-{$tpAttempts}",
            'dr'                 => fake()->randomFloat(2, 1, 10),
            'offensive_rebounds' => fake()->randomFloat(2, 0, 5),
            'ast_to'             => fake()->randomFloat(2, 0.5, 4),
            'stl_to'             => fake()->randomFloat(2, 0.3, 3),
            'to_per_game'        => fake()->randomFloat(2, 0.5, 5),
            'min'                => fake()->randomFloat(2, 15, 40),
            'pf'                 => fake()->randomFloat(2, 0, 5),
            'gp'                 => fake()->numberBetween(10, 82),
            'gs'                 => fake()->numberBetween(0, 82),
            'sc_eff'             => fake()->randomFloat(2, 0.4, 1.5),
            'sh_eff'             => fake()->randomFloat(2, 0.4, 1.5),
            'dd2'                => fake()->numberBetween(0, 20),
            'td3'                => fake()->numberBetween(0, 5),
            'dq'                 => 0,
            'eject'              => 0,
            'flag'               => 0,
            'tech'               => fake()->numberBetween(0, 3),
            'plus_minus'         => fake()->randomFloat(2, -15, 20),
        ];
    }

    public function withoutPlusMinus(): static
    {
        return $this->state(['plus_minus' => null]);
    }

    public function forPlayer(Player $player): static
    {
        return $this->state(['player_id' => $player->id]);
    }
}
