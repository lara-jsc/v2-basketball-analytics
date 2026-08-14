<?php

namespace App\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\User;
use App\Notifications\LiveGameInviteNotification;
use Illuminate\Notifications\DatabaseNotification;

class LiveGameInviteReader
{
    /** @return array<string, mixed>|null */
    public function activeBannerFor(User $user): ?array
    {
        /** @var DatabaseNotification|null $notification */
        $notification = $user->unreadNotifications()
            ->where('type', LiveGameInviteNotification::class)
            ->latest()
            ->get()
            ->first(function (DatabaseNotification $notification): bool {
                $liveGameId = (int) ($notification->data['live_game_id'] ?? 0);

                return $liveGameId > 0
                    && LiveGame::query()
                        ->whereKey($liveGameId)
                        ->where('status', LiveGame::STATUS_SETUP)
                        ->exists();
            });

        if ($notification === null) {
            return null;
        }

        $data = $notification->data;

        return [
            'id' => $notification->id,
            'live_game_id' => (int) $data['live_game_id'],
            'kind' => (string) $data['kind'],
            'title' => (string) $data['title'],
            'body' => (string) $data['body'],
            'home_team_name' => (string) $data['home_team_name'],
            'opponent_team_name' => (string) $data['opponent_team_name'],
        ];
    }

    public function dismiss(User $user, string $notificationId): void
    {
        $notification = $user->notifications()->whereKey($notificationId)->first();
        $notification?->markAsRead();
    }

    public function markReadForLiveGame(User $user, LiveGame $liveGame): void
    {
        $user->unreadNotifications()
            ->where('type', LiveGameInviteNotification::class)
            ->get()
            ->filter(fn (DatabaseNotification $notification): bool => (int) ($notification->data['live_game_id'] ?? 0) === (int) $liveGame->id)
            ->each(fn (DatabaseNotification $notification) => $notification->markAsRead());
    }
}
