<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Admins, or the team's own main coach, decide assistant join requests.
     */
    public function manageJoinRequests(User $user, Team $team): bool
    {
        return $user->isAdmin() || $team->main_coach_user_id === $user->id;
    }
}
