<?php

namespace App\Jobs;

use App\Repositories\ComparisonRepository;
use App\Services\ComparisonAggregatorService;
use App\Services\LineupService;
use App\Services\PythonEngineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RecommendLineup implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $homeTeamId,
        public readonly int $opponentTeamId,
    ) {}

    public function handle(
        ComparisonRepository $compRepo,
        ComparisonAggregatorService $aggregator,
        PythonEngineService $engine,
        LineupService $lineupService,
    ): void {
        $homePlayers = $compRepo->activPlayersWithStats($this->homeTeamId);
        $opponentPlayers = $compRepo->activPlayersWithStats($this->opponentTeamId);

        $payload = [
            'home_team_players' => $aggregator->toEnginePayload($homePlayers),
            'opponent_team_players' => $aggregator->toEnginePayload($opponentPlayers),
        ];

        try {
            $result = $engine->call('lineup', $payload);
        } catch (RuntimeException $e) {
            Log::error('RecommendLineup: engine error', [
                'home' => $this->homeTeamId,
                'opponent' => $this->opponentTeamId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $lineupService->store($this->homeTeamId, $this->opponentTeamId, $result);
    }

    public function failed(Throwable $e): void
    {
        Log::error('RecommendLineup job failed', [
            'home' => $this->homeTeamId,
            'opponent' => $this->opponentTeamId,
            'error' => $e->getMessage(),
        ]);
    }
}
