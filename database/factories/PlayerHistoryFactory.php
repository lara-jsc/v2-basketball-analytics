<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerHistory>
 */
class PlayerHistoryFactory extends Factory
{
    protected $model = PlayerHistory::class;

    public function definition(): array
    {
        $fgMade = $this->faker->numberBetween(2, 12);
        $fgAttempted = $fgMade + $this->faker->numberBetween(2, 10);
        $tpMade = $this->faker->numberBetween(0, 4);
        $tpAttempted = $tpMade + $this->faker->numberBetween(0, 6);
        $ftMade = $this->faker->numberBetween(0, 8);
        $ftAttempted = $ftMade + $this->faker->numberBetween(0, 4);
        $offReb = $this->faker->numberBetween(0, 4);
        $defReb = $this->faker->numberBetween(1, 9);

        return [
            'player_id' => Player::factory(),
            'playing_team_id' => Team::factory(),
            'opponent_team_id' => Team::factory(),
            'game_date' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'position_played' => $this->faker->randomElement(['PG', 'SG', 'SF', 'PF', 'C']),
            'minutes_played' => $this->faker->randomFloat(1, 10, 40),
            'points' => $fgMade * 2 + $tpMade + $ftMade,
            'field_goals_made' => $fgMade,
            'field_goals_attempted' => $fgAttempted,
            'three_pointers_made' => $tpMade,
            'three_pointers_attempted' => $tpAttempted,
            'free_throws_made' => $ftMade,
            'free_throws_attempted' => $ftAttempted,
            'offensive_rebounds' => $offReb,
            'defensive_rebounds' => $defReb,
            'rebounds' => $offReb + $defReb,
            'assists' => $this->faker->numberBetween(0, 12),
            'steals' => $this->faker->numberBetween(0, 4),
            'blocks' => $this->faker->numberBetween(0, 4),
            'turnovers' => $this->faker->numberBetween(0, 6),
            'personal_fouls' => $this->faker->numberBetween(0, 5),
            'flagrant_fouls' => 0,
            'technical_fouls' => $this->faker->numberBetween(0, 1),
            'ejections' => 0,
            'disqualifications' => 0,
            'is_started' => $this->faker->boolean(70),
            'notes' => null,
        ];
    }

    public function forPlayer(Player $player): static
    {
        return $this->state(['player_id' => $player->id]);
    }

    public function forTeams(Team $playingTeam, Team $opponentTeam): static
    {
        return $this->state([
            'playing_team_id' => $playingTeam->id,
            'opponent_team_id' => $opponentTeam->id,
        ]);
    }
}
