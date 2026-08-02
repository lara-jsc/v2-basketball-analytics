<?php

namespace App\Http\Controllers;

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Services\LiveGame\LiveGameClockService;
use App\Services\LiveGame\LiveGameEventRecorder;
use App\Services\LiveGame\LiveGameStateBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    public function create(): Response
    {
        return Inertia::render('LiveGames/Create', [
            'teams' => $this->activeTeamsWithPlayers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'home_team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('is_active', true)],
            'opponent_team_id' => ['required', 'integer', 'different:home_team_id', Rule::exists('teams', 'id')->where('is_active', true)],
            'period_length_seconds' => ['required', 'integer', 'between:60,1200'],
            'starting_player_ids' => ['required', 'array', 'size:5'],
            'starting_player_ids.*' => ['integer', 'distinct', Rule::exists('players', 'id')],
        ])->validate();

        $homePlayerCount = Player::query()
            ->where('team_id', $validated['home_team_id'])
            ->where('is_active', true)
            ->whereIn('id', $validated['starting_player_ids'])
            ->count();

        if ($homePlayerCount !== 5) {
            return back()->withErrors(['starting_player_ids' => 'Select five active players from the home team.'])->withInput();
        }

        /** @var User $user */
        $user = $request->user();

        $game = LiveGame::query()->create([
            'home_team_id' => $validated['home_team_id'],
            'opponent_team_id' => $validated['opponent_team_id'],
            'created_by_user_id' => $user->id,
            'status' => LiveGame::STATUS_SETUP,
            'period_length_seconds' => $validated['period_length_seconds'],
            'clock_seconds_remaining' => $validated['period_length_seconds'],
            'starting_player_ids' => array_values($validated['starting_player_ids']),
            'active_player_ids' => array_values($validated['starting_player_ids']),
        ]);

        return redirect()->route('live-games.show', $game)->with('success', 'Live game setup created.');
    }

    public function show(LiveGame $liveGame, LiveGameStateBuilder $stateBuilder): Response
    {
        $liveGame->load(['homeTeam:id,name,code,logo_path', 'opponentTeam:id,name,code,logo_path']);

        return Inertia::render('LiveGames/Show', [
            'liveGame' => $liveGame,
            'snapshot' => $stateBuilder->build($liveGame),
            'teams' => [$liveGame->homeTeam, $liveGame->opponentTeam],
            'players' => $liveGame->homeTeam
                ->players()
                ->where('is_active', true)
                ->orderBy('jersey_number')
                ->get(),
        ]);
    }

    public function start(Request $request, LiveGame $liveGame, LiveGameClockService $clock): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $clock->handle($liveGame, $user, ['action' => 'start']);

        return redirect()->route('live-games.show', $liveGame);
    }

    public function finish(Request $request, LiveGame $liveGame, LiveGameClockService $clock, LiveGameStateBuilder $stateBuilder): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $clock->handle($liveGame, $user, ['action' => 'stop']);

        $liveGame->forceFill([
            'status' => LiveGame::STATUS_FINISHED,
            'clock_running' => false,
            'clock_started_at' => null,
            'finished_at' => now(),
        ])->save();

        event(new LiveGameStateUpdated($liveGame->id, $stateBuilder->build($liveGame)));

        return redirect()->route('live-games.index')->with('success', 'Live game finished.');
    }

    public function correction(Request $request, LiveGame $liveGame, LiveGameEventController $events)
    {
        $request->merge(['type' => 'correction', 'team_scope' => 'game']);

        return $events->store($request, $liveGame, app(LiveGameEventRecorder::class));
    }

    /** @return Collection<int, Team> */
    private function activeTeamsWithPlayers()
    {
        return Team::query()
            ->where('is_active', true)
            ->with(['players' => fn ($query) => $query->where('is_active', true)->orderBy('jersey_number')])
            ->orderBy('name')
            ->get();
    }
}
