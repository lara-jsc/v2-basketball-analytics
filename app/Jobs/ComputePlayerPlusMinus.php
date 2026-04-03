<?php

namespace App\Jobs;

use App\Models\PlayerStat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Calls the Python analytics engine to compute Box Plus-Minus (BPM) for a
 * single player stat row, then persists the result to player_stats.plus_minus.
 *
 * Python contract:
 *   Input  (stdin JSON): { "player_id": int, "stats": { pts, ast, reb, fg_pct, ... } }
 *   Output (stdout JSON): { "player_id": int, "plus_minus": float }
 *
 * Dispatched by ProcessCsvImport after each player row is saved.
 * All downstream features read the stored value — never re-compute at render time.
 */
class ComputePlayerPlusMinus implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $playerStatId,
    ) {}

    /**
     * Execute the job.
     * Full implementation in Phase 1 — see PythonEngineService.
     */
    public function handle(): void
    {
        // TODO (Phase 1): inject PythonEngineService, call $service->computeBpm($this->playerStatId)
    }
}
