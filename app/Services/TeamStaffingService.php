<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

class TeamStaffingService
{
    /** @param  list<int>  $assistantCoachUserIds */
    public function update(Team $team, ?int $mainCoachUserId, array $assistantCoachUserIds): void
    {
        DB::transaction(function () use ($team, $mainCoachUserId, $assistantCoachUserIds): void {
            $team->forceFill([
                'main_coach_user_id' => $mainCoachUserId,
            ])->save();

            $team->assistantCoaches()->sync($assistantCoachUserIds);
        });
    }
}
