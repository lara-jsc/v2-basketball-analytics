<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\PlayerShotZoneProfile;
use Illuminate\Database\Seeder;

/**
 * Seeds one player_shot_zone_profiles row per player with history.
 *
 * Zone splits are derived from the player's own aggregated box-score totals so
 * that ShotZoneRepository::profileReconcilesWithHistory always passes: the
 * remainder-based distribution guarantees paint + mid_range equals 2PT exactly,
 * and the three 3PT zones sum to 3PA exactly.
 */
class ShotZoneProfileSeeder extends Seeder
{
    /** Share of two-point volume attributed to the paint. */
    private const PAINT_SHARE = 0.60;

    /** Share of three-point volume attributed to each corner. */
    private const CORNER_SHARE = 0.20;

    public function run(): void
    {
        foreach (Player::query()->cursor() as $player) {
            $totals = PlayerHistory::query()
                ->where('player_id', $player->id)
                ->selectRaw('
                    SUM(field_goals_made) as fgm,
                    SUM(field_goals_attempted) as fga,
                    SUM(three_pointers_made) as tpm,
                    SUM(three_pointers_attempted) as tpa
                ')
                ->first();

            if ($totals === null || $totals->fga === null) {
                continue;
            }

            $threeMade = (int) $totals->tpm;
            $threeAttempted = (int) $totals->tpa;
            $twoMade = (int) $totals->fgm - $threeMade;
            $twoAttempted = (int) $totals->fga - $threeAttempted;

            [$paintMade, $midRangeMade] = $this->splitTwoPoint($twoMade);
            [$paintAttempted, $midRangeAttempted] = $this->splitTwoPoint($twoAttempted);

            [$cornerLeftMade, $cornerRightMade, $aboveBreakMade] = $this->splitThreePoint($threeMade);
            [$cornerLeftAttempted, $cornerRightAttempted, $aboveBreakAttempted] = $this->splitThreePoint($threeAttempted);

            PlayerShotZoneProfile::updateOrCreate(
                ['player_id' => $player->id],
                [
                    'paint_made' => $paintMade,
                    'paint_attempted' => $paintAttempted,
                    'mid_range_made' => $midRangeMade,
                    'mid_range_attempted' => $midRangeAttempted,
                    'corner_3_left_made' => $cornerLeftMade,
                    'corner_3_left_attempted' => $cornerLeftAttempted,
                    'corner_3_right_made' => $cornerRightMade,
                    'corner_3_right_attempted' => $cornerRightAttempted,
                    'above_break_3_made' => $aboveBreakMade,
                    'above_break_3_attempted' => $aboveBreakAttempted,
                    'imported_at' => now(),
                ],
            );
        }
    }

    /**
     * @return array{int, int} [paint, midRange]
     */
    private function splitTwoPoint(int $total): array
    {
        $paint = (int) floor($total * self::PAINT_SHARE);

        return [$paint, $total - $paint];
    }

    /**
     * @return array{int, int, int} [cornerLeft, cornerRight, aboveBreak]
     */
    private function splitThreePoint(int $total): array
    {
        $corner = (int) floor($total * self::CORNER_SHARE);

        return [$corner, $corner, $total - $corner * 2];
    }
}
