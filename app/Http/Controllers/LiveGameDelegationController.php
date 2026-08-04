<?php

namespace App\Http\Controllers;

use App\Models\LiveGame;
use App\Models\LiveGamePlayerDelegation;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LiveGameDelegationController extends Controller
{
    public function store(Request $request, LiveGame $liveGame): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $side = $liveGame->sideFor($user);

        if ($side === null) {
            abort(403);
        }

        if ($liveGame->status !== LiveGame::STATUS_SETUP) {
            abort(422, 'Delegations can only be updated while the game is in setup.');
        }

        $teamId = $side === LiveGame::SIDE_OPPONENT
            ? (int) $liveGame->opponent_team_id
            : (int) $liveGame->home_team_id;

        $mainCoachUserId = $side === LiveGame::SIDE_OPPONENT
            ? $liveGame->opponent_main_coach_user_id
            : $liveGame->home_main_coach_user_id;

        if ($mainCoachUserId === null || (int) $mainCoachUserId !== (int) $user->id) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'assistant_coach_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('team_id', $teamId),
            ],
            'player_ids' => ['required', 'array'],
            'player_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('players', 'id')->where('team_id', $teamId)->where('is_active', true),
            ],
        ]);

        $validator->after(function ($validator) use ($request, $liveGame): void {
            /** @var User $user */
            $user = $request->user();
            $side = $liveGame->sideFor($user);
            if ($side === null) {
                return;
            }

            $mainCoachUserId = $side === LiveGame::SIDE_OPPONENT
                ? $liveGame->opponent_main_coach_user_id
                : $liveGame->home_main_coach_user_id;

            $assistantId = (int) ($request->input('assistant_coach_user_id') ?? 0);
            if ($mainCoachUserId !== null && (int) $assistantId === (int) $mainCoachUserId) {
                $validator->errors()->add('assistant_coach_user_id', 'Assistant coach must differ from the main coach.');
            }

            $assistant = User::query()->find($assistantId);
            if ($assistant === null || $assistant->email_verified_at === null) {
                $validator->errors()->add('assistant_coach_user_id', 'Assistant coach must be an email-verified coach.');
            }
        });

        $validated = $validator->validate();
        $assistantCoachId = (int) $validated['assistant_coach_user_id'];
        /** @var array<int> $playerIds */
        $playerIds = array_values(array_map(static fn (mixed $id): int => (int) $id, $validated['player_ids']));

        DB::transaction(function () use ($liveGame, $assistantCoachId, $playerIds): void {
            // Enforce exclusivity: remove any existing delegations for these players first.
            if ($playerIds !== []) {
                LiveGamePlayerDelegation::query()
                    ->where('live_game_id', $liveGame->id)
                    ->whereIn('player_id', $playerIds)
                    ->delete();
            }

            // Replace assignments for this assistant.
            LiveGamePlayerDelegation::query()
                ->where('live_game_id', $liveGame->id)
                ->where('coach_user_id', $assistantCoachId)
                ->delete();

            foreach ($playerIds as $playerId) {
                LiveGamePlayerDelegation::query()->create([
                    'live_game_id' => $liveGame->id,
                    'coach_user_id' => $assistantCoachId,
                    'player_id' => $playerId,
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }
}

