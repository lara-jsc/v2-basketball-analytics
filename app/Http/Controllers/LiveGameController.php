<?php

namespace App\Http\Controllers;

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Services\LiveGame\LiveGameClockService;
use App\Services\LiveGame\LiveGameControlResolver;
use App\Services\LiveGame\LiveGameDelegationWriter;
use App\Services\LiveGame\LiveGameEventRecorder;
use App\Services\LiveGame\LiveGameFinalizer;
use App\Services\LiveGame\LiveGameInviteNotifier;
use App\Services\LiveGame\LiveGameInviteReader;
use App\Services\LiveGame\LiveGameStateBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LiveGameController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('LiveGames/Index', [
            'liveGames' => LiveGame::query()
                ->with(['homeTeam:id,name,code,logo_path', 'opponentTeam:id,name,code,logo_path'])
                ->latest()
                ->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $homeTeam = null;
        if ($user->team_id !== null) {
            $homeTeam = Team::query()
                ->where('id', $user->team_id)
                ->where('is_active', true)
                ->with([
                    'players' => fn ($query) => $query->where('is_active', true)->orderBy('jersey_number'),
                    'assistantCoaches:id,name',
                ])
                ->first();
        }

        $opponentTeams = Team::query()
            ->where('is_active', true)
            ->when($user->team_id !== null, fn ($query) => $query->where('id', '!=', $user->team_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'logo_path']);

        $preselectedPlayerIds = $this->parsePreselectedPlayerIds(
            (string) $request->query('player_ids', ''),
            $homeTeam?->id,
        );

        $preselectedOpponentTeamId = $this->parsePreselectedOpponentTeamId(
            $request->query('opponent_team_id'),
            $opponentTeams->pluck('id')->all(),
        );

        $assistantCoachOptions = [];
        $defaultAssistantCoachUserId = null;
        if ($user->team_id !== null) {
            $assistantCoachOptions = User::query()
                ->where('team_id', $user->team_id)
                ->where('id', '!=', $user->id)
                ->whereNotNull('email_verified_at')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all();

            if ($homeTeam !== null && $homeTeam->assistantCoaches->count() === 1) {
                $defaultAssistantCoachUserId = (int) $homeTeam->assistantCoaches->first()->id;
            }
        }

        return Inertia::render('LiveGames/Create', [
            'homeTeam' => $homeTeam,
            'opponentTeams' => $opponentTeams,
            'preselectedPlayerIds' => $preselectedPlayerIds,
            'preselectedOpponentTeamId' => $preselectedOpponentTeamId,
            'assistantCoachOptions' => $assistantCoachOptions,
            'defaultAssistantCoachUserId' => $defaultAssistantCoachUserId,
        ]);
    }

    public function store(Request $request, LiveGameDelegationWriter $delegationWriter, LiveGameInviteNotifier $inviteNotifier): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->team_id === null) {
            throw ValidationException::withMessages([
                'home_team_id' => 'You must be assigned to a team to create a live game.',
            ]);
        }

        $homeTeamId = (int) $user->team_id;

        $validated = Validator::make($request->all(), [
            'opponent_team_id' => [
                'required',
                'integer',
                Rule::notIn([$homeTeamId]),
                Rule::exists('teams', 'id')->where('is_active', true),
            ],
            'period_length_seconds' => ['required', 'integer', 'between:60,1200'],
            'starting_player_ids' => ['required', 'array', 'size:5'],
            'starting_player_ids.*' => ['integer', 'distinct', Rule::exists('players', 'id')],
            'assistant_assignments' => ['nullable', 'array'],
            'assistant_assignments.*.coach_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('team_id', $homeTeamId),
            ],
            'assistant_assignments.*.player_ids' => ['required', 'array', 'min:1'],
            'assistant_assignments.*.player_ids.*' => ['integer', Rule::exists('players', 'id')],
        ])->validate();

        $assistantAssignments = $this->normalizedAssistantAssignments($validated);
        $this->assertAssistantAssignments($assistantAssignments);

        $homePlayerCount = Player::query()
            ->where('team_id', $homeTeamId)
            ->where('is_active', true)
            ->whereIn('id', $validated['starting_player_ids'])
            ->count();

        if ($homePlayerCount !== 5) {
            return back()->withErrors(['starting_player_ids' => 'Select five active players from your team.'])->withInput();
        }

        $game = LiveGame::query()->create([
            'home_team_id' => $homeTeamId,
            'opponent_team_id' => $validated['opponent_team_id'],
            'created_by_user_id' => $user->id,
            'home_main_coach_user_id' => $user->id,
            'status' => LiveGame::STATUS_SETUP,
            'game_date' => now()->toDateString(),
            'period_length_seconds' => $validated['period_length_seconds'],
            'clock_seconds_remaining' => $validated['period_length_seconds'],
            'starting_player_ids' => array_values($validated['starting_player_ids']),
            'active_player_ids' => array_values($validated['starting_player_ids']),
            'opponent_starting_player_ids' => null,
            'opponent_active_player_ids' => null,
            'opponent_main_coach_user_id' => null,
        ]);

        if ($assistantAssignments !== []) {
            $delegationWriter->writeAssignments(
                $game,
                $user,
                $homeTeamId,
                $assistantAssignments,
            );
        }

        $inviteNotifier->notifyCreated($game, $user);

        return redirect()->route('live-games.show', $game)->with('success', 'Live game setup created. Share the link so the opponent coach can submit their lineup.');
    }

    public function show(
        Request $request,
        LiveGame $liveGame,
        LiveGameStateBuilder $stateBuilder,
        LiveGameInviteReader $inviteReader,
        LiveGameControlResolver $controlResolver,
    ): Response {
        $liveGame->load(['homeTeam:id,name,code,logo_path', 'opponentTeam:id,name,code,logo_path']);

        /** @var User $user */
        $user = $request->user();

        $inviteReader->markReadForLiveGame($user, $liveGame);
        $homePlayers = $liveGame->homeTeam
            ->players()
            ->where('is_active', true)
            ->orderBy('jersey_number')
            ->get();

        $opponentPlayers = $liveGame->opponentTeam
            ->players()
            ->where('is_active', true)
            ->orderBy('jersey_number')
            ->get();

        $viewerSide = $liveGame->sideFor($user);
        $control = $controlResolver->resolve(
            $liveGame,
            $user,
            $viewerSide === LiveGame::SIDE_OPPONENT ? $opponentPlayers : $homePlayers,
        );
        $controlledPlayerIds = $control->controlledPlayerIds;
        $isMainCoach = $control->isMainCoach;
        $teamCoaches = [];

        if ($control->teamId !== null) {
            $teamCoaches = User::query()
                ->where('team_id', $control->teamId)
                ->whereNotNull('email_verified_at')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return Inertia::render('LiveGames/Show', [
            'liveGame' => $liveGame,
            'snapshot' => $stateBuilder->build($liveGame),
            'teams' => [$liveGame->homeTeam, $liveGame->opponentTeam],
            'homePlayers' => $homePlayers,
            'opponentPlayers' => $opponentPlayers,
            'players' => $homePlayers,
            'viewerSide' => $viewerSide,
            'isCreator' => $liveGame->isCreator($user),
            'can_control_clock' => $liveGame->isCreator($user) || $liveGame->isMainCoach($user),
            'clock_shared' => $liveGame->home_main_coach_user_id !== null
                && $liveGame->opponent_main_coach_user_id !== null,
            'controlled_player_ids' => $controlledPlayerIds,
            'is_main_coach' => $isMainCoach,
            'team_coaches' => $teamCoaches,
        ]);
    }

    public function submitLineup(
        Request $request,
        LiveGame $liveGame,
        LiveGameStateBuilder $stateBuilder,
        LiveGameDelegationWriter $delegationWriter,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $side = $liveGame->sideFor($user);

        if ($side === null) {
            abort(403);
        }

        if ($liveGame->status !== LiveGame::STATUS_SETUP) {
            throw ValidationException::withMessages([
                'game' => 'Lineups can only be submitted while the game is in setup.',
            ]);
        }

        $teamId = $side === LiveGame::SIDE_OPPONENT
            ? (int) $liveGame->opponent_team_id
            : (int) $liveGame->home_team_id;

        $validated = Validator::make($request->all(), [
            'starting_player_ids' => ['required', 'array', 'size:5'],
            'starting_player_ids.*' => ['integer', 'distinct', Rule::exists('players', 'id')],
            'assistant_assignments' => ['nullable', 'array'],
            'assistant_assignments.*.coach_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('team_id', $teamId),
            ],
            'assistant_assignments.*.player_ids' => ['required', 'array', 'min:1'],
            'assistant_assignments.*.player_ids.*' => ['integer', Rule::exists('players', 'id')],
        ])->validate();

        $assistantAssignments = $this->normalizedAssistantAssignments($validated);
        $this->assertAssistantAssignments($assistantAssignments);

        $playerCount = Player::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->whereIn('id', $validated['starting_player_ids'])
            ->count();

        if ($playerCount !== 5) {
            return back()->withErrors(['starting_player_ids' => 'Select five active players from your team.'])->withInput();
        }

        $ids = array_values($validated['starting_player_ids']);

        if ($side === LiveGame::SIDE_OPPONENT) {
            $updates = [
                'opponent_starting_player_ids' => $ids,
                'opponent_active_player_ids' => $ids,
            ];

            if ($liveGame->opponent_main_coach_user_id === null) {
                $updates['opponent_main_coach_user_id'] = $user->id;
            }

            $liveGame->forceFill($updates)->save();
        } else {
            $liveGame->forceFill([
                'starting_player_ids' => $ids,
                'active_player_ids' => $ids,
            ])->save();
        }

        $mainCoachUserId = $side === LiveGame::SIDE_OPPONENT
            ? (int) $liveGame->fresh()->opponent_main_coach_user_id
            : (int) $liveGame->home_main_coach_user_id;

        $canWriteDelegation = $mainCoachUserId === (int) $user->id
            && $assistantAssignments !== [];

        if ($canWriteDelegation) {
            $delegationWriter->writeAssignments(
                $liveGame,
                $user,
                $teamId,
                $assistantAssignments,
            );
        }

        event(new LiveGameStateUpdated($liveGame->id, $stateBuilder->build($liveGame->fresh())));

        return redirect()->route('live-games.show', $liveGame)->with('success', 'Starting five submitted.');
    }

    public function start(Request $request, LiveGame $liveGame, LiveGameClockService $clock): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $clock->handle($liveGame, $user, ['action' => 'start']);

        return redirect()->route('live-games.show', $liveGame);
    }

    public function finish(Request $request, LiveGame $liveGame, LiveGameClockService $clock, LiveGameFinalizer $finalizer, LiveGameStateBuilder $stateBuilder): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $liveGame->isCreator($user)) {
            throw ValidationException::withMessages([
                'game' => 'Only the game creator can finish the live game.',
            ]);
        }

        $snapshot = DB::transaction(function () use ($liveGame, $clock, $finalizer, $stateBuilder): array {
            $game = LiveGame::query()->lockForUpdate()->findOrFail($liveGame->id);

            if ($game->status === LiveGame::STATUS_FINISHED) {
                throw ValidationException::withMessages([
                    'game' => 'The live game is already finished.',
                ]);
            }

            $game->forceFill([
                'clock_seconds_remaining' => $clock->effectiveSecondsRemaining($game),
                'clock_running' => false,
                'clock_started_at' => null,
            ])->save();

            $finalizer->finalize($game);

            $game->forceFill([
                'status' => LiveGame::STATUS_FINISHED,
                'finished_at' => now(),
            ])->save();

            return $stateBuilder->build($game);
        });

        event(new LiveGameStateUpdated($liveGame->id, $snapshot));

        return redirect()->route('live-games.index')->with('success', 'Live game finished.');
    }

    public function correction(Request $request, LiveGame $liveGame, LiveGameEventController $events)
    {
        $request->merge(['type' => 'correction', 'team_scope' => 'game']);

        return $events->store($request, $liveGame, app(LiveGameEventRecorder::class));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array{coach_user_id:int, player_ids:list<int>}>
     */
    private function normalizedAssistantAssignments(array $validated): array
    {
        return collect($validated['assistant_assignments'] ?? [])
            ->map(function (array $assignment): array {
                return [
                    'coach_user_id' => (int) $assignment['coach_user_id'],
                    'player_ids' => array_values(array_unique(array_map(
                        static fn (mixed $id): int => (int) $id,
                        $assignment['player_ids'] ?? [],
                    ))),
                ];
            })
            ->filter(fn (array $assignment): bool => $assignment['player_ids'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{coach_user_id:int, player_ids:list<int>}>  $assignments
     */
    private function assertAssistantAssignments(array $assignments): void
    {
        $coachIds = [];
        $playerIds = [];

        foreach ($assignments as $assignment) {
            if (in_array($assignment['coach_user_id'], $coachIds, true)) {
                throw ValidationException::withMessages([
                    'assistant_assignments' => 'Each assistant coach may appear only once.',
                ]);
            }

            $coachIds[] = $assignment['coach_user_id'];

            foreach ($assignment['player_ids'] as $playerId) {
                if (in_array($playerId, $playerIds, true)) {
                    throw ValidationException::withMessages([
                        'assistant_assignments' => 'A player can be assigned to only one assistant coach.',
                    ]);
                }

                $playerIds[] = $playerId;
            }
        }
    }

    /** @return list<int> */
    private function parsePreselectedPlayerIds(string $raw, ?int $homeTeamId): array
    {
        if ($homeTeamId === null || $raw === '') {
            return [];
        }

        $ids = collect(explode(',', $raw))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $validIds = Player::query()
            ->where('team_id', $homeTeamId)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $validLookup = array_flip($validIds);

        return array_values(array_slice(
            array_values(array_filter($ids, fn (int $id): bool => isset($validLookup[$id]))),
            0,
            5,
        ));
    }

    /** @param  list<int>  $allowedOpponentIds */
    private function parsePreselectedOpponentTeamId(mixed $raw, array $allowedOpponentIds): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;

        return in_array($id, array_map('intval', $allowedOpponentIds), true) ? $id : null;
    }
}
