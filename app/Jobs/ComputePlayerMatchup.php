<?php

namespace App\Jobs;

use App\Models\Player;
use App\Services\PlayerMatchupService;
use App\Services\PythonEngineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Calls the Python engine to compute head-to-head edge scores for two players.
 *
 * Python contract:
 *   Input:  { "command": "player_matchup", "payload": { "player_a": {...}, "player_b": {...} } }
 *   Output: { "player_a_edge_score": float, "player_b_edge_score": float,
 *             "stronger_stats_a": [...], "stronger_stats_b": [...] }
 */
class ComputePlayerMatchup implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $playerAId,
        public readonly int $playerBId,
    ) {}

    public function handle(PythonEngineService $engine, PlayerMatchupService $matchupService): void
    {
        $playerA = Player::with(['stats' => fn ($q) => $q->latest()->limit(1)])->find($this->playerAId);
        $playerB = Player::with(['stats' => fn ($q) => $q->latest()->limit(1)])->find($this->playerBId);

        if ($playerA === null || $playerB === null) {
            Log::warning('ComputePlayerMatchup: player not found', [
                'playerA' => $this->playerAId,
                'playerB' => $this->playerBId,
            ]);
            return;
        }

        $payload = [
            'player_a' => $this->statPayload($playerA),
            'player_b' => $this->statPayload($playerB),
        ];

        try {
            $result = $engine->call('player_matchup', $payload);
        } catch (RuntimeException $e) {
            Log::error('ComputePlayerMatchup: engine error', [
                'playerA' => $this->playerAId,
                'playerB' => $this->playerBId,
                'error'   => $e->getMessage(),
            ]);
            return;
        }

        $matchupService->store($this->playerAId, $this->playerBId, $result);
    }

    /** @return array<string, mixed> */
    private function statPayload(Player $player): array
    {
        $s = $player->stats->first();

        return [
            'player_id'          => $player->id,
            'pts'                => $s?->pts ?? 0,
            'ast'                => $s?->ast ?? 0,
            'reb'                => $s?->reb ?? 0,
            'blk'                => $s?->blk ?? 0,
            'stl'                => $s?->stl ?? 0,
            'fg_pct'             => $s?->fg_pct ?? 0,
            'three_p_pct'        => $s?->three_p_pct ?? 0,
            'dr'                 => $s?->dr ?? 0,
            'offensive_rebounds' => $s?->offensive_rebounds ?? 0,
            'min'                => $s?->min ?? 0,
            'plus_minus'         => $s?->plus_minus ?? 0,
        ];
    }

    public function failed(Throwable $e): void
    {
        Log::error('ComputePlayerMatchup job failed', [
            'playerA' => $this->playerAId,
            'playerB' => $this->playerBId,
            'error'   => $e->getMessage(),
        ]);
    }
}
