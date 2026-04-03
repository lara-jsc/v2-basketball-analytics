<?php

namespace App\Jobs;

use App\Repositories\ComparisonRepository;
use App\Services\ComparisonAggregatorService;
use App\Services\PythonEngineService;
use App\Services\WinProbabilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ComputeWinProbability implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $teamAId,
        public readonly int $teamBId,
    ) {}

    public function handle(
        ComparisonRepository $compRepo,
        ComparisonAggregatorService $aggregator,
        PythonEngineService $engine,
        WinProbabilityService $winProbService,
    ): void {
        $playersA = $compRepo->activPlayersWithStats($this->teamAId);
        $playersB = $compRepo->activPlayersWithStats($this->teamBId);

        $payload = [
            'team_a_stats' => $aggregator->aggregateStats($playersA),
            'team_b_stats' => $aggregator->aggregateStats($playersB),
        ];

        try {
            $result = $engine->call('win_probability', $payload);
        } catch (RuntimeException $e) {
            Log::error('ComputeWinProbability: engine error', [
                'teamA' => $this->teamAId,
                'teamB' => $this->teamBId,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $winProbService->store($this->teamAId, $this->teamBId, $result);
    }

    public function failed(Throwable $e): void
    {
        Log::error('ComputeWinProbability job failed', [
            'teamA' => $this->teamAId,
            'teamB' => $this->teamBId,
            'error' => $e->getMessage(),
        ]);
    }
}
