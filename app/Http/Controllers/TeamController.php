<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Repositories\CsvImportRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\TeamRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly PlayerRepository $playerRepository,
        private readonly CsvImportRepository $csvImportRepository,
    ) {}

    /**
     * List all teams.
     */
    public function index(): Response
    {
        return Inertia::render('Teams/Index', [
            'teams' => $this->teamRepository->all(),
        ]);
    }

    /**
     * Show the create team form.
     */
    public function create(): Response
    {
        return Inertia::render('Teams/Create');
    }

    /**
     * Store a new team and redirect to its show page.
     */
    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $team = $this->teamRepository->create($request->validated());

        return redirect()
            ->route('teams.show', $team->id)
            ->with('success', "Team '{$team->name}' created successfully.");
    }

    /**
     * Show a team with its players and latest import status.
     */
    public function show(int $team): Response
    {
        $teamModel = $this->teamRepository->findOrFail($team);

        return Inertia::render('Teams/Show', [
            'team'         => $teamModel,
            'players'      => fn () => $this->playerRepository->forTeamWithLatestStats($team),
            'latestImport' => fn () => $this->csvImportRepository->latestForTeam($team),
        ]);
    }
}
