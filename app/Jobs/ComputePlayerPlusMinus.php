<?php

namespace App\Jobs;

use App\Models\PlayerHistory;
use App\Models\PlayerStat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Computes the season aggregate plus/minus for a single player
 * by summing the per-game plus_minus values stored in player_histories.
 *
 * Formula: plus_minus = SUM(player_histories.plus_minus) WHERE player_id = $playerId
 *
 * This is the standard NBA-style accumulated plus/minus — do NOT average.
 * If no records have plus_minus data, the stored value is left unchanged
 * (stays null on first run) — UI renders "—".
 *
 * On success: writes result to player_stats.plus_minus.
 */
class ComputePlayerPlusMinus implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $playerId,
    ) {}

    public function handle(): void
    {
        $hasData = PlayerHistory::where('player_id', $this->playerId)
            ->whereNotNull('plus_minus')
            ->exists();

        if (! $hasData) {
            Log::info('ComputePlayerPlusMinus: no plus_minus data for player — leaving null', [
                'playerId' => $this->playerId,
            ]);
            return;
        }

        $sum = (float) PlayerHistory::where('player_id', $this->playerId)
            ->whereNotNull('plus_minus')
            ->sum('plus_minus');

        PlayerStat::updateOrCreate(
            ['player_id' => $this->playerId],
            ['plus_minus' => $sum],
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ComputePlayerPlusMinus job failed', [
            'playerId' => $this->playerId,
            'error'    => $exception->getMessage(),
        ]);
    }
}
