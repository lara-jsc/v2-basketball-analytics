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
     * Replace all delegated player assignments for one team-side in one live game.
     *
     * @param  list<array{coach_user_id:int, player_ids:list<int>}>  $assignments
     */
    public function writeAssignments(
        LiveGame $liveGame,
        User $mainCoach,
        int $teamId,
        array $assignments,
    ): void {
        $assistantCoachIds = collect($assignments)
            ->pluck('coach_user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (in_array((int) $mainCoach->id, $assistantCoachIds, true)) {
            throw ValidationException::withMessages([
                'assistant_assignments' => 'Assistant coach must differ from the main coach.',
            ]);
        }

        $verifiedAssistantIds = User::query()
            ->whereIn('id', $assistantCoachIds)
            ->where('team_id', $teamId)
            ->whereNotNull('email_verified_at')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        sort($assistantCoachIds);
        $sortedVerifiedAssistantIds = $verifiedAssistantIds;
        sort($sortedVerifiedAssistantIds);

        if ($assistantCoachIds !== $sortedVerifiedAssistantIds) {
            throw ValidationException::withMessages([
                'assistant_assignments' => 'Assistant coaches must be email-verified coaches on this team.',
            ]);
        }

        $teamPlayerIds = Player::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $allPlayerIds = collect($assignments)
            ->pluck('player_ids')
            ->flatten()
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        if (array_values(array_unique($allPlayerIds)) !== $allPlayerIds) {
            throw ValidationException::withMessages([
                'assistant_assignments' => 'A player can be assigned to only one assistant coach.',
            ]);
        }

        $teamPlayerLookup = array_flip($teamPlayerIds);
        foreach ($allPlayerIds as $playerId) {
            if (! isset($teamPlayerLookup[$playerId])) {
                throw ValidationException::withMessages([
                    'assistant_assignments' => 'Delegated players must be active roster players on this team.',
                ]);
            }
        }

        $rows = [];
        foreach ($assignments as $assignment) {
            foreach ($assignment['player_ids'] as $playerId) {
                $rows[] = [
                    'live_game_id' => $liveGame->id,
                    'coach_user_id' => $assignment['coach_user_id'],
                    'player_id' => $playerId,
                ];
            }
        }

        DB::transaction(function () use ($liveGame, $teamPlayerIds, $rows): void {
            LiveGamePlayerDelegation::query()
                ->where('live_game_id', $liveGame->id)
                ->whereIn('player_id', $teamPlayerIds)
                ->delete();

            foreach ($rows as $row) {
                LiveGamePlayerDelegation::query()->create($row);
            }
        });
    }
}
