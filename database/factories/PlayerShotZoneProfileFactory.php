<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerShotZoneProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerShotZoneProfile>
 */
class PlayerShotZoneProfileFactory extends Factory
{
    protected $model = PlayerShotZoneProfile::class;

    public function definition(): array
    {
        // Generate plausible zone splits consistent with box-score totals.
        $paintAttempted = $this->faker->numberBetween(20, 60);
        $midAttempted = $this->faker->numberBetween(10, 40);
        $cornerLeftAttempted = $this->faker->numberBetween(5, 25);
        $cornerRightAttempted = $this->faker->numberBetween(5, 25);
        $aboveBreakAttempted = $this->faker->numberBetween(10, 50);

        return [
            'player_id' => Player::factory(),
            'paint_made' => (int) round($paintAttempted * 0.55),
            'paint_attempted' => $paintAttempted,
            'mid_range_made' => (int) round($midAttempted * 0.40),
            'mid_range_attempted' => $midAttempted,
            'corner_3_left_made' => (int) round($cornerLeftAttempted * 0.38),
            'corner_3_left_attempted' => $cornerLeftAttempted,
            'corner_3_right_made' => (int) round($cornerRightAttempted * 0.38),
            'corner_3_right_attempted' => $cornerRightAttempted,
            'above_break_3_made' => (int) round($aboveBreakAttempted * 0.35),
            'above_break_3_attempted' => $aboveBreakAttempted,
            'imported_at' => now(),
        ];
    }

    public function forPlayer(Player $player): static
    {
        return $this->state(['player_id' => $player->id]);
    }
}
