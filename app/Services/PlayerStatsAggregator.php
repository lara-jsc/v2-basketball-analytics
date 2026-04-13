<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;

class PlayerStatsAggregator
{
    /**
     * Compute all player_stats fields from a collection of PlayerHistory rows.
     *
     * Returns an array ready to upsert into player_stats for the given player.
     * plus_minus is always set to null — ComputePlayerPlusMinus Job re-derives it.
     *
     * @param  Collection<int, \App\Models\PlayerHistory>  $histories
     * @return array<string, mixed>
     */
    public function compute(int $playerId, Collection $histories): array
    {
        $gp = $histories->count();

        if ($gp === 0) {
            return $this->zeroed($playerId);
        }

        $gs  = $histories->where('is_started', true)->count();
        $min = $this->avg($histories, 'minutes_played');
        $pts = $this->avg($histories, 'points');
        $reb = $this->avg($histories, 'rebounds');
        $dr  = $this->avg($histories, 'defensive_rebounds');
        $or  = $this->avg($histories, 'offensive_rebounds');
        $ast = $this->avg($histories, 'assists');
        $stl = $this->avg($histories, 'steals');
        $blk = $this->avg($histories, 'blocks');
        $tov = $this->avg($histories, 'turnovers');
        $pf  = $this->avg($histories, 'personal_fouls');

        // Cumulative counts (not per-game averages)
        $flag  = $histories->sum('flagrant_fouls');
        $tech  = $histories->sum('technical_fouls');
        $eject = $histories->sum('ejections');
        $dq    = $histories->sum('disqualifications');

        // Shot totals
        $fgm  = (int) $histories->sum('field_goals_made');
        $fga  = (int) $histories->sum('field_goals_attempted');
        $ftm  = (int) $histories->sum('free_throws_made');
        $fta  = (int) $histories->sum('free_throws_attempted');
        $tpm  = (int) $histories->sum('three_pointers_made');
        $tpa  = (int) $histories->sum('three_pointers_attempted');

        // Percentage strings for display
        $fg       = "{$fgm}-{$fga}";
        $ft       = "{$ftm}-{$fta}";
        $three_pt = "{$tpm}-{$tpa}";

        // Shooting percentages (null-safe)
        $fg_pct      = $fga > 0 ? round($fgm / $fga, 4) : null;
        $ft_pct      = $fta > 0 ? round($ftm / $fta, 4) : null;
        $three_p_pct = $tpa > 0 ? round($tpm / $tpa, 4) : null;

        // Ratio stats (null-safe — avoid division by zero)
        $ast_to = ($tov > 0) ? round($ast / $tov, 2) : null;
        $stl_to = ($tov > 0) ? round($stl / $tov, 2) : null;

        // Scoring efficiency: avg pts per fg attempt
        $fga_avg = $gp > 0 && $fga > 0 ? ($fga / $gp) : null;
        $sc_eff  = ($fga_avg !== null && $fga_avg > 0 && $pts !== null)
            ? round($pts / $fga_avg, 2)
            : null;

        // Shooting efficiency: (FGM + 0.5*3PM + 0.44*FTM - FGA) / FGA
        $sh_eff = $fga > 0
            ? round(($fgm + 0.5 * $tpm + 0.44 * $ftm - $fga) / $fga, 4)
            : null;

        // Per-game averages for advanced stat formulas
        $fgaAvg    = $gp > 0 ? $fga / $gp : 0.0;
        $fgmAvg    = $gp > 0 ? $fgm / $gp : 0.0;
        $ftaAvg    = $gp > 0 ? $fta / $gp : 0.0;
        $ftmAvg    = $gp > 0 ? $ftm / $gp : 0.0;
        $threePmAvg = $gp > 0 ? $tpm / $gp : 0.0;
        $toPg      = $tov ?? 0.0;

        // EFF = Pts + Reb + Ast + Stl + Blk − MissedFG − MissedFT − TO
        $missedFg = $fgaAvg - $fgmAvg;
        $missedFt = $ftaAvg - $ftmAvg;
        $eff = round(
            ($pts ?? 0.0) + ($reb ?? 0.0) + ($ast ?? 0.0) + ($stl ?? 0.0) + ($blk ?? 0.0)
            - $missedFg - $missedFt - $toPg,
            2
        );

        // eFG% = (FGM + 0.5 × 3PM) / FGA — null if FGA == 0
        $efg_pct = $fgaAvg > 0
            ? round(min(($fgmAvg + 0.5 * $threePmAvg) / $fgaAvg, 1.0), 4)
            : null;

        // TS% = Pts / (2 × (FGA + 0.44 × FTA)) — null if denominator == 0
        $tsDenominator = 2 * ($fgaAvg + 0.44 * $ftaAvg);
        $ts_pct = $tsDenominator > 0
            ? round(min(($pts ?? 0.0) / $tsDenominator, 1.0), 4)
            : null;

        // Double-double / triple-double counts
        [$dd2, $td3] = $this->doubleDoubles($histories);

        // Most frequent position (mode)
        $pc = $this->modePosition($histories);

        return [
            'player_id'          => $playerId,
            'gp'                 => $gp,
            'gs'                 => $gs,
            'min'                => $min,
            'pts'                => $pts,
            'reb'                => $reb,
            'dr'                 => $dr,
            'offensive_rebounds' => $or,
            'ast'                => $ast,
            'stl'                => $stl,
            'blk'                => $blk,
            'to_per_game'        => $tov,
            'pf'                 => $pf,
            'flag'               => $flag,
            'tech'               => $tech,
            'eject'              => $eject,
            'dq'                 => $dq,
            'fg'                 => $fg,
            'ft'                 => $ft,
            'three_pt'           => $three_pt,
            'fg_pct'             => $fg_pct,
            'ft_pct'             => $ft_pct,
            'three_p_pct'        => $three_p_pct,
            'ast_to'             => $ast_to,
            'stl_to'             => $stl_to,
            'sc_eff'             => $sc_eff,
            'sh_eff'             => $sh_eff,
            'eff'                => $eff,
            'efg_pct'            => $efg_pct,
            'ts_pct'             => $ts_pct,
            'dd2'                => $dd2,
            'td3'                => $td3,
            'pc'                 => $pc,
            'plus_minus'         => null,  // cleared; re-computed by ComputePlayerPlusMinus Job
        ];
    }

    /**
     * Returns zeroed/null stats for a player with no history rows.
     * Clears stale averages rather than leaving them in player_stats.
     *
     * @return array<string, mixed>
     */
    private function zeroed(int $playerId): array
    {
        return [
            'player_id'          => $playerId,
            'gp'                 => 0,
            'gs'                 => 0,
            'min'                => null,
            'pts'                => null,
            'reb'                => null,
            'dr'                 => null,
            'offensive_rebounds' => null,
            'ast'                => null,
            'stl'                => null,
            'blk'                => null,
            'to_per_game'        => null,
            'pf'                 => null,
            'flag'               => 0,
            'tech'               => 0,
            'eject'              => 0,
            'dq'                 => 0,
            'fg'                 => '0-0',
            'ft'                 => '0-0',
            'three_pt'           => '0-0',
            'fg_pct'             => null,
            'ft_pct'             => null,
            'three_p_pct'        => null,
            'ast_to'             => null,
            'stl_to'             => null,
            'sc_eff'             => null,
            'sh_eff'             => null,
            'eff'                => null,
            'efg_pct'            => null,
            'ts_pct'             => null,
            'dd2'                => 0,
            'td3'                => 0,
            'pc'                 => null,
            'plus_minus'         => null,
        ];
    }

    /**
     * Average of a nullable numeric column across all rows.
     *
     * @param  Collection<int, \App\Models\PlayerHistory>  $histories
     */
    private function avg(Collection $histories, string $column): ?float
    {
        $values = $histories->pluck($column)->filter(fn ($v) => $v !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->avg(), 2);
    }

    /**
     * Count double-doubles and triple-doubles across all game rows.
     * Qualifying stats: points, rebounds, assists, steals, blocks.
     *
     * @param  Collection<int, \App\Models\PlayerHistory>  $histories
     * @return array{0: int, 1: int}  [dd2_count, td3_count]
     */
    private function doubleDoubles(Collection $histories): array
    {
        $dd2 = 0;
        $td3 = 0;

        foreach ($histories as $game) {
            $qualifiers = 0;

            foreach (['points', 'rebounds', 'assists', 'steals', 'blocks'] as $stat) {
                if (($game->{$stat} ?? 0) >= 10) {
                    $qualifiers++;
                }
            }

            if ($qualifiers >= 3) {
                $td3++;
                $dd2++;  // a triple-double is also a double-double
            } elseif ($qualifiers >= 2) {
                $dd2++;
            }
        }

        return [$dd2, $td3];
    }

    /**
     * Statistical mode of position_played — most frequently recorded position.
     * Returns null if all values are null.
     *
     * @param  Collection<int, \App\Models\PlayerHistory>  $histories
     */
    private function modePosition(Collection $histories): ?string
    {
        $counts = $histories
            ->pluck('position_played')
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->countBy()
            ->all();

        if (empty($counts)) {
            return null;
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }
}
