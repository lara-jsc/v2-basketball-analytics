<?php

namespace App\Jobs;

use App\Models\PlayerStat;
use App\Services\PythonEngineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Computes Box Plus-Minus (BPM) for a single player stat row via the Python engine.
 *
 * Python contract:
 *   Input  (stdin JSON): { "command": "bpm", "payload": { "player_id": int, "stats": {...} } }
 *   Output (stdout JSON): { "player_id": int, "plus_minus": float }
 *
 * On success: writes result to player_stats.plus_minus.
 * On failure: logs the error; plus_minus stays null — UI renders "—".
 */
class ComputePlayerPlusMinus implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $playerStatId,
    ) {}

    public function handle(PythonEngineService $engine): void
    {
        /** @var PlayerStat|null $stat */
        $stat = PlayerStat::find($this->playerStatId);

        if ($stat === null) {
            Log::warning('ComputePlayerPlusMinus: PlayerStat not found', ['id' => $this->playerStatId]);

            return;
        }

        $payload = [
            'player_id' => $stat->player_id,
            'stats' => [
                'pts' => $stat->pts,
                'ast' => $stat->ast,
                'reb' => $stat->reb,
                'fg_pct' => $stat->fg_pct,
                'three_p_pct' => $stat->three_p_pct,
                'blk' => $stat->blk,
                'stl' => $stat->stl,
                'to_per_game' => $stat->to_per_game,
                'min' => $stat->min,
            ],
        ];

        try {
            $result = $engine->call('bpm', $payload);
        } catch (RuntimeException $e) {
            Log::error('ComputePlayerPlusMinus: Python engine error', [
                'playerStatId' => $this->playerStatId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (! isset($result['plus_minus'])) {
            Log::error('ComputePlayerPlusMinus: missing plus_minus in engine response', [
                'playerStatId' => $this->playerStatId,
                'response' => $result,
            ]);

            return;
        }

        $stat->update(['plus_minus' => (float) $result['plus_minus']]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ComputePlayerPlusMinus job failed', [
            'playerStatId' => $this->playerStatId,
            'error' => $exception->getMessage(),
        ]);
    }
}
