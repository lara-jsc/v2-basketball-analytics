<?php

use App\Models\LiveGame;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('live-game.{liveGameId}', function (User $user, int $liveGameId): bool {
    if (! $user->hasVerifiedEmail()) {
        return false;
    }

    $liveGame = LiveGame::query()->find($liveGameId);

    return $liveGame !== null && $liveGame->isParticipant($user);
});
