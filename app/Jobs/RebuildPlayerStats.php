<?php

namespace App\Jobs;

use App\Models\PlayerStat;
use App\Repositories\PlayerHistoryRepository;
use App\Services\PlayerStatsAggregator;
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
    ): void {
        $histories = $repository->rawForPlayer($this->playerId);
        $statsData = $aggregator->compute($this->playerId, $histories);

        /** @var PlayerStat $stat */
        $stat = PlayerStat::updateOrCreate(
            ['player_id' => $this->playerId],
            $statsData,
        );

        ComputePlayerPlusMinus::dispatch($this->playerId);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('RebuildPlayerStats job failed', [
            'playerId' => $this->playerId,
            'error'    => $exception->getMessage(),
        ]);
    }
}
