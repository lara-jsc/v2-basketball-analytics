<?php

namespace App\Services;

/**
 * Pure-math statistics service for basketball analytics.
 *
 * All methods accept a flat array of game history records (each element is an
 * associative array whose keys match PlayerHistory column names). They perform
 * no I/O and are safe to unit-test without a database connection.
 *
 * Plus/Minus design note:
 *   The per-game plus_minus value is stored directly on PlayerHistory rows
 *   (supplied by the user or CSV import). This service sums those stored values,
 *   which matches the behaviour of the ComputePlayerPlusMinus Job.
 */
class PlayerStatisticsService
{
    /**
     * Compute accumulated plus/minus by summing the stored per-game plus_minus
     * value from each history record.
     *
     * Formula: Σ plus_minus per game (NBA-style accumulated, not averaged).
     * Records where plus_minus is null are treated as 0 (not excluded).
     *
     * @param  array<int, array<string, mixed>>  $histories
     */
    public function computePlusMinus(array $histories): int
    {
        if (empty($histories)) {
            return 0;
        }

        $sum = 0;

        foreach ($histories as $game) {
            $sum += (int) ($game['plus_minus'] ?? 0);
        }

        return $sum;
    }

    /**
     * Compute basketball Efficiency Rating (EFF) across all game records.
     *
     * Formula: (Pts + Reb + Ast + Stl + Blk) − (MissedFG + MissedFT + TO)
     *   where MissedFG = field_goals_attempted − field_goals_made
     *         MissedFT = free_throws_attempted − free_throws_made
     *
     * @param  array<int, array<string, mixed>>  $histories
     */
    public function computeEfficiency(array $histories): float
    {
        if (empty($histories)) {
            return 0.0;
        }

        $positive = 0.0;
        $negative = 0.0;

        foreach ($histories as $game) {
            $pts = (float) ($game['points']  ?? 0);
            $reb = (float) ($game['rebounds'] ?? 0);
            $ast = (float) ($game['assists']  ?? 0);
            $stl = (float) ($game['steals']   ?? 0);
            $blk = (float) ($game['blocks']   ?? 0);

            $fgm = (float) ($game['field_goals_made']      ?? 0);
            $fga = (float) ($game['field_goals_attempted'] ?? 0);
            $ftm = (float) ($game['free_throws_made']      ?? 0);
            $fta = (float) ($game['free_throws_attempted'] ?? 0);
            $tov = (float) ($game['turnovers']              ?? 0);

            $positive += $pts + $reb + $ast + $stl + $blk;
            $negative += ($fga - $fgm) + ($fta - $ftm) + $tov;
        }

        return round($positive - $negative, 2);
    }

    /**
     * Compute Effective Field Goal Percentage (eFG%) across all game records.
     *
     * Formula: (FGM + 0.5 × 3PM) / FGA
     * Returns 0.0 when total FGA is zero to avoid division by zero.
     *
     * @param  array<int, array<string, mixed>>  $histories
     */
    public function computeEfgPercent(array $histories): float
    {
        $fgm = 0.0;
        $tpm = 0.0;
        $fga = 0.0;

        foreach ($histories as $game) {
            $fgm += (float) ($game['field_goals_made']       ?? 0);
            $tpm += (float) ($game['three_pointers_made']    ?? 0);
            $fga += (float) ($game['field_goals_attempted']  ?? 0);
        }

        if ($fga === 0.0) {
            return 0.0;
        }

        return round(($fgm + 0.5 * $tpm) / $fga, 4);
    }

    /**
     * Compute True Shooting Percentage (TS%) across all game records.
     *
     * Formula: Pts / (2 × (FGA + 0.44 × FTA))
     * Returns 0.0 when the denominator is zero to avoid division by zero.
     *
     * @param  array<int, array<string, mixed>>  $histories
     */
    public function computeTrueShooting(array $histories): float
    {
        $pts = 0.0;
        $fga = 0.0;
        $fta = 0.0;

        foreach ($histories as $game) {
            $pts += (float) ($game['points']                 ?? 0);
            $fga += (float) ($game['field_goals_attempted']  ?? 0);
            $fta += (float) ($game['free_throws_attempted']  ?? 0);
        }

        $denominator = 2.0 * ($fga + 0.44 * $fta);

        if ($denominator === 0.0) {
            return 0.0;
        }

        return round($pts / $denominator, 4);
    }
}
