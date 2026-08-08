<?php

namespace App\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGamePlayerDelegation;
use App\Models\User;
use App\Notifications\LiveGameInviteNotification;
use Illuminate\Support\Collection;

class LiveGameInviteNotifier
{
    public function notifyCreated(LiveGame $liveGame, User $creator): void
    {
        $liveGame->loadMissing(['homeTeam:id,name', 'opponentTeam:id,name']);

        $homeTeamName = $liveGame->homeTeam?->name ?? 'Home';
        $opponentTeamName = $liveGame->opponentTeam?->name ?? 'Opponent';

        $homePlayerIds = $liveGame->homeTeam
            ? $liveGame->homeTeam->players()->pluck('id')->all()
            : [];

        $delegatedHomeCoachIds = LiveGamePlayerDelegation::query()
            ->where('live_game_id', $liveGame->id)
            ->when(
                $homePlayerIds !== [],
                fn ($query) => $query->whereIn('player_id', $homePlayerIds),
            )
            ->pluck('coach_user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, User> $recipients */
        $recipients = User::query()
            ->whereNotNull('email_verified_at')
            ->whereIn('team_id', [(int) $liveGame->home_team_id, (int) $liveGame->opponent_team_id])
            ->where('id', '!=', $creator->id)
            ->get();

        foreach ($recipients as $recipient) {
            $kind = $this->kindFor($recipient, $liveGame, $delegatedHomeCoachIds);
            $recipient->notify(new LiveGameInviteNotification(
                $liveGame,
                $kind,
                $homeTeamName,
                $opponentTeamName,
            ));
        }
    }

    /**
     * @param  list<int>  $delegatedHomeCoachIds
     */
    private function kindFor(User $recipient, LiveGame $liveGame, array $delegatedHomeCoachIds): string
    {
        if ((int) $recipient->team_id === (int) $liveGame->opponent_team_id) {
            return LiveGameInviteNotification::KIND_OPPONENT_SETUP;
        }

        if (in_array((int) $recipient->id, $delegatedHomeCoachIds, true)) {
            return LiveGameInviteNotification::KIND_HOME_ASSIGNED;
        }

        return LiveGameInviteNotification::KIND_HOME_TEAM;
    }
}
