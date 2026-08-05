<?php

namespace App\Http\Controllers;

use App\Models\LiveGame;
use App\Models\User;
use App\Repositories\ComparisonRepository;
use App\Services\LiveGame\LiveGameControlResolver;
use App\Services\LiveGame\LiveGameLineupApplier;
use App\Services\LiveGame\LiveLineupEligibilityFilter;
use App\Services\LiveGame\LiveLineupSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LiveGameSuggestionController extends Controller
{
    /**
     * Return the two ranked lineups for the viewer's bench, dispatching the job on a miss.
     *
     * Follows the dispatch-and-poll shape the comparison page already uses: a cache miss
     * answers `pending` rather than blocking on a Python subprocess.
     */
    public function store(
        Request $request,
        LiveGame $liveGame,
        LiveGameControlResolver $controlResolver,
        ComparisonRepository $comparisonRepository,
        LiveLineupEligibilityFilter $eligibilityFilter,
        LiveLineupSuggestionService $suggestions,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $control = $controlResolver->resolve($liveGame, $user);

        if ($control->isSpectator() || $control->teamId === null) {
            abort(403, 'Only team coaches can request a lineup suggestion.');
        }

        if ($liveGame->status !== LiveGame::STATUS_LIVE) {
            throw ValidationException::withMessages([
                'game' => 'Lineup suggestions are only available while the game is live.',
            ]);
        }

        $roster = $comparisonRepository->activPlayersWithStats($control->teamId);

        // The job derives this identically to decide what to rank; the controller repeats it
        // so the response can name the fixed players and the coach's open slot count.
        $eligibility = $eligibilityFilter->filter($liveGame, $control->side, $roster, $control->controlledPlayerIds);
        $suggestion = $suggestions->getOrDispatch($liveGame, $control->teamId, (int) $user->id);

        return response()->json([
            'pending' => $suggestion === null,
            'team_id' => $control->teamId,
            'suggestion' => $suggestion,
            'reasons' => $eligibility->reasons,
            'fixed_player_ids' => $eligibility->fixedPlayerIds,
            'slot_count' => $eligibility->slotCount(),
            'controlled_player_ids' => $control->controlledPlayerIds,
        ]);
    }

    /**
     * Apply one of the two suggested lineups as a batch of substitutions.
     */
    public function apply(
        Request $request,
        LiveGame $liveGame,
        LiveGameControlResolver $controlResolver,
        LiveGameLineupApplier $applier,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'player_ids' => ['required', 'array', 'size:5'],
            'player_ids.*' => ['integer', 'distinct'],
        ]);

        $control = $controlResolver->resolve($liveGame, $user);

        if ($control->isSpectator()) {
            abort(403, 'Only team coaches can apply a lineup.');
        }

        $playerIds = array_map(static fn (mixed $id): int => (int) $id, $validated['player_ids']);

        return response()->json($applier->apply($liveGame, $user, $control, $playerIds));
    }
}
