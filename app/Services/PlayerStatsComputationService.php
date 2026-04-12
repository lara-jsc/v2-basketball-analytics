<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\PlayerStat;
use App\Repositories\PlayerHistoryRepository;

/**
 * Synchronous service for recomputing player_stats from player_histories.
 *
 * Use this for manual recomputation (artisan command, admin action).
 * The async queue path uses RebuildPlayerStats + ComputePlayerPlusMinus Jobs.
 *
 * Static per-game helpers (computeGameEff, computeGameEfg, computeGameTs) work
 * directly on a PlayerHistory instance and are safe to call without DI.
 */
class PlayerStatsComputationService
{
    public function __construct(
        private readonly PlayerHistoryRepository $repository,
        private readonly PlayerStatsAggregator $aggregator,
    ) {}

    /**
     * Recompute and persist all stats for a single player.
     * Returns the freshly saved PlayerStat row.
     */
    public function computeForPlayer(int $playerId): PlayerStat
    {
        $histories = $this->repository->rawForPlayer($playerId);
        $statsData = $this->aggregator->compute($playerId, $histories);

        // For sync recomputation, resolve plus_minus directly rather than
        // leaving it null (as the async job flow does).
        $statsData['plus_minus'] = $histories
            ->whereNotNull('plus_minus')
            ->sum('plus_minus') ?: null;

        /** @var PlayerStat $stat */
        $stat = PlayerStat::updateOrCreate(
            ['player_id' => $playerId],
            $statsData,
        );

        return $stat->fresh() ?? $stat;
    }

    /**
     * Recompute stats for all players (chunked to avoid memory exhaustion).
     */
    public function computeAll(): void
    {
        Player::query()
            ->select('id')
            ->chunkById(100, function ($players): void {
                foreach ($players as $player) {
                    $this->computeForPlayer($player->id);
                }
            });
    }

    // -------------------------------------------------------------------------
    // Static Per-Game Helpers — for verification and testing
    // -------------------------------------------------------------------------

    /**
     * Efficiency (EFF) for a single game.
     *
     * Formula: Pts + Reb + Ast + Stl + Blk − MissedFG − MissedFT − TO
     */
    public static function computeGameEff(PlayerHistory $history): float
    {
        return $history->efficiency;
    }

    /**
     * Effective Field Goal % (eFG%) for a single game.
     *
     * Formula: (FGM + 0.5 × 3PM) / FGA
     */
    public static function computeGameEfg(PlayerHistory $history): float
    {
        return $history->efg_percent;
    }

    /**
     * True Shooting % (TS%) for a single game.
     *
     * Formula: Pts / (2 × (FGA + 0.44 × FTA))
     */
    public static function computeGameTs(PlayerHistory $history): float
    {
        return $history->ts_percent;
    }
}
