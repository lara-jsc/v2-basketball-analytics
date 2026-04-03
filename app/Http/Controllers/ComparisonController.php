<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectTeamsRequest;
use App\Models\Team;
use App\Repositories\ComparisonRepository;
use App\Repositories\TeamRepository;
use App\Services\ComparisonAggregatorService;
use App\Services\LineupService;
use App\Services\PlayerMatchupService;
use App\Services\WinProbabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComparisonController extends Controller
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly ComparisonRepository $compRepository,
        private readonly ComparisonAggregatorService $aggregator,
        private readonly WinProbabilityService $winProbService,
        private readonly LineupService $lineupService,
        private readonly PlayerMatchupService $matchupService,
    ) {}

    /**
     * Team selector — list available teams for the user to pick two.
     */
    public function index(): Response
    {
        return Inertia::render('Comparison/Index', [
            'teams' => $this->teamRepository->all(),
        ]);
    }

    /**
     * Redirect from the selector form to the comparison show page.
     */
    public function select(SelectTeamsRequest $request): RedirectResponse
    {
        ['team_a' => $teamA, 'team_b' => $teamB] = $request->validated();

        return redirect()->route('comparison.show', [$teamA, $teamB]);
    }

    /**
     * Main comparison page — two tabs (team stats + player matchup).
     *
     * On first load: dispatches Python jobs if results not cached.
     * Frontend polls via router.reload({ only: [...] }) until non-null.
     */
    public function show(Team $teamA, Team $teamB, Request $request): Response
    {
        $playersA = $this->compRepository->activPlayersWithStats($teamA->id);
        $playersB = $this->compRepository->activPlayersWithStats($teamB->id);

        // Win probability — cached; dispatches job if missing
        $winProbability = $this->winProbService->getOrDispatch($teamA->id, $teamB->id);

        // Lineup for home (teamA) vs opponent (teamB) — cached per ordered matchup
        $lineup = $this->lineupService->getOrDispatch($teamA->id, $teamB->id);

        // Player matchup — only when both players are selected via query params
        $selectedAId = $request->integer('player_a') ?: null;
        $selectedBId = $request->integer('player_b') ?: null;
        $matchup     = null;

        if ($selectedAId !== null && $selectedBId !== null) {
            $matchup = $this->matchupService->getOrDispatch($selectedAId, $selectedBId);
        }

        return Inertia::render('Comparison/Show', [
            'teamA'          => $teamA,
            'teamB'          => $teamB,
            'playersA'       => $playersA->values(),
            'playersB'       => $playersB->values(),
            'teamAStats'     => $this->aggregator->aggregateStats($playersA),
            'teamBStats'     => $this->aggregator->aggregateStats($playersB),
            'teamAPlusMinus' => $this->aggregator->teamPlusMinus($playersA),
            'teamBPlusMinus' => $this->aggregator->teamPlusMinus($playersB),
            'winProbability' => $winProbability,
            'lineup'         => $lineup,
            'matchup'        => $matchup,
            'selectedAId'    => $selectedAId,
            'selectedBId'    => $selectedBId,
        ]);
    }
}
