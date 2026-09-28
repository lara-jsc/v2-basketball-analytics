<?php

namespace App\Jobs;

use App\Models\Player;
use App\Models\PlayerStat;
use App\Repositories\PlayerHistoryRepository;
use App\Services\PlayerStatsAggregator;
use App\Services\WinProbabilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rebuilds the player_stats row for a player from their full game history.
 *
 * Flow:
 *  1. Fetch all player_histories rows for the player.
 *  2. Run PlayerStatsAggregator to compute averages, ratios, and derived fields.
 *  3. Upsert the player_stats row (one row per player).
 *  4. Dispatch ComputePlayerPlusMinus with the stat row's ID.
 *  5. Invalidate the team's win-probability/lineup cache version.
 *
 * Dispatched after every write to player_histories (store, update, destroy)
 * and after each player's rows are processed during a CSV import.
 */
class RebuildPlayerStats implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $playerId,
    ) {}

    public function handle(
        PlayerHistoryRepository $repository,
        PlayerStatsAggregator $aggregator,
        WinProbabilityService $winProbabilityService,
    ): void {
        $histories = $repository->rawForPlayer($this->playerId);
        $statsData = $aggregator->compute($this->playerId, $histories);

        /** @var PlayerStat $stat */
        $stat = PlayerStat::updateOrCreate(
            ['player_id' => $this->playerId],
            $statsData,
        );

        ComputePlayerPlusMinus::dispatch($stat->id);

        // Season averages feed win probability and lineups. Bumping the team version here,
        // after the save, covers every history write path: CRUD, file import, live finalize.
        $teamId = Player::query()->whereKey($this->playerId)->value('team_id');
        if ($teamId !== null) {
            $winProbabilityService->invalidateForTeam((int) $teamId);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('RebuildPlayerStats job failed', [
            'playerId' => $this->playerId,
            'error' => $exception->getMessage(),
        ]);
    }
}
