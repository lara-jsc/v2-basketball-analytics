<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Requests\UpdateTeamStaffingRequest;
use App\Http\Requests\UploadTeamLogoRequest;
use App\Models\Team;
use App\Models\User;
use App\Repositories\CsvImportRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\TeamRepository;
use App\Services\CsvTemplateService;
use App\Services\TeamService;
use App\Services\TeamStaffingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamController extends Controller
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly PlayerRepository $playerRepository,
        private readonly CsvImportRepository $csvImportRepository,
        private readonly TeamService $teamService,
        private readonly CsvTemplateService $csvTemplateService,
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
    public function show(Team $team): Response
    {
        $team->load([
            'mainCoach:id,name,email',
            'assistantCoaches:id,name,email',
        ]);

        $coachOptions = User::query()
            ->where('team_id', $team->id)
            ->whereNotNull('email_verified_at')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Teams/Show', [
            'team' => $team,
            'players' => fn () => $this->playerRepository->forTeamWithLatestStats($team->id),
            'latestImport' => fn () => $this->csvImportRepository->latestForTeam($team->id),
            'playersExportUrl' => route('teams.players.export', $team),
            'coachOptions' => $coachOptions,
            'mainCoach' => $team->mainCoach,
            'assistantCoaches' => $team->assistantCoaches,
        ]);
    }

    public function exportPlayers(Team $team): StreamedResponse
    {
        $search = trim((string) request('search', ''));
        $players = $this->playerRepository->forTeam($team->id)
            ->filter(function ($player) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                return str_contains(
                    strtolower("{$player->first_name} {$player->last_name}"),
                    strtolower($search),
                );
            })
            ->values();

        $content = $this->csvTemplateService->generateRosterExportContent($players);
        $filename = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $team->code ?: $team->name) ?: 'team');

        return response()->streamDownload(
            static function () use ($content): void {
                echo $content;
            },
            "{$filename}-players.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * Show the team edit form.
     */
    public function edit(Team $team): Response
    {
        return Inertia::render('Teams/Edit', [
            'team' => $team,
        ]);
    }

    /**
     * Update team details.
     */
    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $this->teamService->update($team, $request->validated());

        return redirect()
            ->route('teams.show', $team->id)
            ->with('success', 'Team updated successfully.');
    }

    public function updateStaffing(
        UpdateTeamStaffingRequest $request,
        Team $team,
        TeamStaffingService $teamStaffingService,
    ): RedirectResponse {
        $payload = $request->staffingPayload();

        $teamStaffingService->update(
            $team,
            $payload['main_coach_user_id'],
            $payload['assistant_coach_user_ids'],
        );

        return redirect()
            ->route('teams.show', $team->id)
            ->with('success', 'Coach staffing updated.');
    }

    /**
     * Toggle team active/inactive status.
     */
    public function toggleActive(Team $team): RedirectResponse
    {
        $updated = $this->teamService->toggleActive($team);

        $label = $updated->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->route('teams.show', $team->id)
            ->with('success', "Team {$label}.");
    }

    /**
     * Upload / replace the team logo.
     */
    public function uploadLogo(UploadTeamLogoRequest $request, Team $team): RedirectResponse
    {
        $this->teamService->updateLogo($team, $request->file('logo'));

        return redirect()
            ->route('teams.show', $team->id)
            ->with('success', 'Team logo updated.');
    }
}
