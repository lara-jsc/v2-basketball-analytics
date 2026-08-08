<?php

namespace App\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGamePlayerDelegation;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiveGameDelegationWriter
{
    /**
     * Replace an assistant's exclusive player assignments for one live game.
     *
     * @param  list<int>  $playerIds
     */
    /**
     * Replace an assistant's exclusive player assignments for one live game.
     *
     * Players from either team roster (home or opponent) are delegatable.
     * The shot-only restriction for opponent players is enforced in the event gates,
     * not here.
     *
     * @param  list<int>  $playerIds
     */
    public function write(
        LiveGame $liveGame,
        User $mainCoach,
        int $teamId,
        int $assistantCoachUserId,
        array $playerIds,
    ): void {
        if ($assistantCoachUserId === (int) $mainCoach->id) {
            throw ValidationException::withMessages([
                'assistant_coach_user_id' => 'Assistant coach must differ from the main coach.',
            ]);
        }

        $assistant = User::query()
            ->where('id', $assistantCoachUserId)
            ->where('team_id', $teamId)
            ->whereNotNull('email_verified_at')
            ->first();

        if ($assistant === null) {
            throw ValidationException::withMessages([
                'assistant_coach_user_id' => 'Assistant coach must be an email-verified coach on this team.',
            ]);
        }

        $playerIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $playerIds)));

        if ($playerIds === []) {
            throw ValidationException::withMessages([
                'delegated_player_ids' => 'Select at least one player for the assistant.',
            ]);
        }

        $bothTeamIds = array_unique(array_filter([
            (int) $liveGame->home_team_id,
            (int) $liveGame->opponent_team_id,
        ]));

        $validCount = Player::query()
            ->whereIn('team_id', $bothTeamIds)
            ->where('is_active', true)
            ->whereIn('id', $playerIds)
            ->count();

        if ($validCount !== count($playerIds)) {
            throw ValidationException::withMessages([
                'delegated_player_ids' => 'Delegated players must be active roster players in this game.',
            ]);
        }

        DB::transaction(function () use ($liveGame, $assistantCoachUserId, $playerIds): void {
            LiveGamePlayerDelegation::query()
                ->where('live_game_id', $liveGame->id)
                ->whereIn('player_id', $playerIds)
                ->delete();

            LiveGamePlayerDelegation::query()
                ->where('live_game_id', $liveGame->id)
                ->where('coach_user_id', $assistantCoachUserId)
                ->delete();

            foreach ($playerIds as $playerId) {
                LiveGamePlayerDelegation::query()->create([
                    'live_game_id' => $liveGame->id,
                    'coach_user_id' => $assistantCoachUserId,
                    'player_id' => $playerId,
                ]);
            }
        });
    }
}
