<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Calls the Python analytics engine to compute win probability and win rate
 * for a head-to-head team matchup.
 *
 * Python contract:
 *   Input  (stdin JSON): { "team_a_stats": { avg_pts, avg_reb, ... }, "team_b_stats": { ... } }
 *   Output (stdout JSON): { "team_a_win_probability": float, "team_b_win_probability": float,
 *                           "team_a_win_rate": float, "team_b_win_rate": float }
 *
 * Dispatched from WinProbabilityService when the Team Comparison page is loaded.
 */
class ComputeWinProbability implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $teamAId,
        public readonly int $teamBId,
    ) {}

    /**
     * Execute the job.
     * Full implementation in Phase 3 — see WinProbabilityService.
     */
    public function handle(): void
    {
        // TODO (Phase 3): inject WinProbabilityService, call $service->compute($this->teamAId, $this->teamBId)
    }
}
