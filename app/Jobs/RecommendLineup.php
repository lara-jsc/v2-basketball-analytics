<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Calls the Python analytics engine to generate an optimal starting lineup
 * for the home team against a specific opponent.
 *
 * Python contract:
 *   Input  (stdin JSON): { "home_team_players": [...], "opponent_team_players": [...] }
 *   Output (stdout JSON): { "recommended_lineup": [...], "confidence": float }
 *
 * Result is cached per team matchup key.
 * Cache is invalidated on new CSV upload for either team.
 * Only is_active = true players are included in both input arrays.
 */
class RecommendLineup implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $homeTeamId,
        public readonly int $opponentTeamId,
    ) {}

    /**
     * Execute the job.
     * Full implementation in Phase 3 — see LineupService.
     */
    public function handle(): void
    {
        // TODO (Phase 3): inject LineupService, call $service->recommend($this->homeTeamId, $this->opponentTeamId)
    }
}
