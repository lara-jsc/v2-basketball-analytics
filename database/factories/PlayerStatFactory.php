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
        $fgMade = $this->faker->numberBetween(3, 12);
        $fgAttempts = $fgMade + $this->faker->numberBetween(3, 10);
        $ftMade = $this->faker->numberBetween(0, 8);
        $ftAttempts = $ftMade + $this->faker->numberBetween(0, 4);
        $tpMade = $this->faker->numberBetween(0, 4);
        $tpAttempts = $tpMade + $this->faker->numberBetween(0, 5);

        return [
            'player_id' => Player::factory(),
            'pc' => $this->faker->randomElement(['PG', 'SG', 'SF', 'PF', 'C']),
            'pts' => $this->faker->randomFloat(2, 5, 30),
            'reb' => $this->faker->randomFloat(2, 1, 15),
            'ast' => $this->faker->randomFloat(2, 0, 12),
            'blk' => $this->faker->randomFloat(2, 0, 4),
            'stl' => $this->faker->randomFloat(2, 0, 4),
            'fg_pct' => round($fgMade / $fgAttempts, 2),
            'fg' => "{$fgMade}-{$fgAttempts}",
            'ft_pct' => $ftAttempts > 0 ? round($ftMade / $ftAttempts, 2) : 0,
            'ft' => "{$ftMade}-{$ftAttempts}",
            'three_p_pct' => $tpAttempts > 0 ? round($tpMade / $tpAttempts, 2) : 0,
            'three_pt' => "{$tpMade}-{$tpAttempts}",
            'dr' => $this->faker->randomFloat(2, 1, 10),
            'offensive_rebounds' => $this->faker->randomFloat(2, 0, 5),
            'ast_to' => $this->faker->randomFloat(2, 0.5, 4),
            'stl_to' => $this->faker->randomFloat(2, 0.3, 3),
            'to_per_game' => $this->faker->randomFloat(2, 0.5, 5),
            'min' => $this->faker->randomFloat(2, 15, 40),
            'pf' => $this->faker->randomFloat(2, 0, 5),
            'gp' => $this->faker->numberBetween(10, 82),
            'gs' => $this->faker->numberBetween(0, 82),
            'sc_eff' => $this->faker->randomFloat(2, 0.4, 1.5),
            'sh_eff' => $this->faker->randomFloat(2, 0.4, 1.5),
            'dd2' => $this->faker->numberBetween(0, 20),
            'td3' => $this->faker->numberBetween(0, 5),
            'dq' => 0,
            'eject' => 0,
            'flag' => 0,
            'tech' => $this->faker->numberBetween(0, 3),
            'plus_minus' => $this->faker->randomFloat(2, -15, 20),
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
