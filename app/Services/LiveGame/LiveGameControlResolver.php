<?php

namespace App\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGamePlayerDelegation;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Works out which of a bench's players a given coach may act on.
 *
 * Extracted from LiveGameController::show(), which was the only place that knew the rule.
 * The lineup-suggestion endpoint needs the identical answer — a suggestion the coach
 * cannot apply is worse than no suggestion — and duplicating fifty lines of delegation
 * arithmetic across two controllers would guarantee the two drifted apart.
 */
class LiveGameControlResolver
{
    /**
     * @param  Collection<int, Player>|null  $rosterPlayers  The viewer's own side, if already loaded.
     */
    public function resolve(LiveGame $game, User $user, ?Collection $rosterPlayers = null): LiveGameControl
    {
        $side = $game->sideFor($user);

        if ($side === null) {
            return new LiveGameControl(null, null, [], false);
        }

        $isOpponentSide = $side === LiveGame::SIDE_OPPONENT;

        $teamId = $isOpponentSide
            ? (int) $game->opponent_team_id
            : (int) $game->home_team_id;

        $mainCoachUserId = $isOpponentSide
            ? $game->opponent_main_coach_user_id
            : $game->home_main_coach_user_id;

        $roster = $rosterPlayers ?? $this->roster($teamId);
        $rosterPlayerIds = $roster->pluck('id')->map(fn (mixed $id): int => (int) $id)->values();

        $delegations = LiveGamePlayerDelegation::query()
            ->where('live_game_id', $game->id)
            ->whereIn('player_id', $rosterPlayerIds->all())
            ->get(['coach_user_id', 'player_id']);

        $isMainCoach = $mainCoachUserId !== null && (int) $mainCoachUserId === (int) $user->id;

        if ($mainCoachUserId === null) {
            // Defensive default: if we haven't determined main-coach yet, don't block UI/recording.
            $controlledPlayerIds = $rosterPlayerIds->all();
        } elseif ($isMainCoach) {
            $delegatedAwayIds = $delegations
                ->reject(fn ($row): bool => (int) $row->coach_user_id === (int) $mainCoachUserId)
                ->pluck('player_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values();

            $controlledPlayerIds = $rosterPlayerIds->diff($delegatedAwayIds)->values()->all();
        } else {
            $controlledPlayerIds = $delegations
                ->filter(fn ($row): bool => (int) $row->coach_user_id === (int) $user->id)
                ->pluck('player_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return new LiveGameControl($side, $teamId, $controlledPlayerIds, $isMainCoach);
    }

    /** @return Collection<int, Player> */
    private function roster(int $teamId): Collection
    {
        return Player::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->orderBy('jersey_number')
            ->get();
    }
}
