<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerHistory>
 *
 * Constraints enforced:
 *  - field_goals_attempted  >= field_goals_made
 *  - three_pointers_attempted >= three_pointers_made
 *  - free_throws_attempted  >= free_throws_made
 *  - points derived from (fg_made × 2) + three_made + ft_made
 *  - team_points_on_court / opp_points_on_court are independent (40–70 range)
 *    (stored as plus_minus = team_pts - opp_pts when seeding)
 */
class PlayerHistoryFactory extends Factory
{
    protected $model = PlayerHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fgm  = $this->faker->numberBetween(3, 12);
        $fga  = $this->faker->numberBetween($fgm, $fgm + 8);

        $tpm  = $this->faker->numberBetween(0, 4);
        $tpa  = $this->faker->numberBetween($tpm, $tpm + 4);

        $ftm  = $this->faker->numberBetween(0, 6);
        $fta  = $this->faker->numberBetween($ftm, $ftm + 3);

        // Derived: 2-pt buckets contribute 2 pts each; 3-pt buckets 3 pts;
        // free throws 1 pt each. (FGM includes 3PM, so 2pt_made = fgm - tpm.)
        $twoPtMade = $fgm - $tpm;
        $points    = ($twoPtMade * 2) + ($tpm * 3) + $ftm;

        $offReb = $this->faker->numberBetween(0, 3);
        $defReb = $this->faker->numberBetween(1, 7);

        $teamPts = $this->faker->numberBetween(40, 70);
        $oppPts  = $this->faker->numberBetween(40, 70);

        return [
            'player_id'                => Player::factory(),
            'playing_team_id'          => Team::factory(),
            'opponent_team_id'         => Team::factory(),
            'game_date'                => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'position_played'          => $this->faker->randomElement(['PG', 'SG', 'SF', 'PF', 'C']),
            'minutes_played'           => $this->faker->randomFloat(1, 10, 38),
            'points'                   => $points,
            'field_goals_made'         => $fgm,
            'field_goals_attempted'    => $fga,
            'three_pointers_made'      => $tpm,
            'three_pointers_attempted' => $tpa,
            'free_throws_made'         => $ftm,
            'free_throws_attempted'    => $fta,
            'offensive_rebounds'       => $offReb,
            'defensive_rebounds'       => $defReb,
            'rebounds'                 => $offReb + $defReb,
            'assists'                  => $this->faker->numberBetween(0, 10),
            'steals'                   => $this->faker->numberBetween(0, 4),
            'blocks'                   => $this->faker->numberBetween(0, 3),
            'turnovers'                => $this->faker->numberBetween(0, 5),
            'personal_fouls'           => $this->faker->numberBetween(0, 5),
            'flagrant_fouls'           => 0,
            'technical_fouls'          => 0,
            'ejections'                => 0,
            'disqualifications'        => 0,
            'is_started'               => $this->faker->boolean(70),
            'notes'                    => null,
            'plus_minus'               => $teamPts - $oppPts,
        ];
    }
}
