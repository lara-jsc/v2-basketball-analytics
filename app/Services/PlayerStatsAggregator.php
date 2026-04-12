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

        // True Shooting % (TS%) — season totals, stored as decimal (e.g. 0.6506)
        $total_pts = (float) $histories->sum('points');
        $ts_denom  = 2.0 * ($fga + 0.44 * $fta);
        $sc_eff    = $ts_denom > 0 ? round($total_pts / $ts_denom, 4) : null;

        // Effective FG% (eFG%) — season totals, stored as decimal (e.g. 0.6944)
        $sh_eff = $fga > 0 ? round(($fgm + 0.5 * $tpm) / $fga, 4) : null;

        // Efficiency Rating (EFF) — per-game average
        $total_reb = (float) $histories->sum('rebounds');
        $total_ast = (float) $histories->sum('assists');
        $total_stl = (float) $histories->sum('steals');
        $total_blk = (float) $histories->sum('blocks');
        $total_tov = (float) $histories->sum('turnovers');
        $total_eff = $total_pts + $total_reb + $total_ast + $total_stl + $total_blk
            - ($fga - $fgm)   // missed field goals
            - ($fta - $ftm)   // missed free throws
            - $total_tov;
        $eff = $gp > 0 ? round($total_eff / $gp, 2) : null;

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
