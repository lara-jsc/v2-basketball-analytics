<?php

namespace App\Policies;

use App\Models\LiveGame;
use App\Models\User;

class LiveGamePolicy
{
    /**
     * Anyone may view a live game; only participants may change it.
     * Mirrors the channel authorization in routes/channels.php.
     */
    public function record(User $user, LiveGame $liveGame): bool
    {
        return $liveGame->isParticipant($user);
    }
}
