<?php

namespace App\Repositories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AccountRepository
{
    /**
     * All accounts with their team and staffing relations, admins first.
     *
     * @return Collection<int, User>
     */
    public function allWithStaffing(): Collection
    {
        return User::query()
            ->with([
                'team:id,name',
                'mainCoachedTeams:id,main_coach_user_id',
                'assistantCoachedTeams:id',
            ])
            ->orderBy('role')
            ->orderBy('name')
            ->get();
    }

    public function loadStaffing(User $user): User
    {
        return $user->load([
            'team:id,name',
            'mainCoachedTeams:id,main_coach_user_id',
            'assistantCoachedTeams:id',
        ]);
    }

    /**
     * Teams for the account form, with the current main coach name.
     *
     * @return Collection<int, Team>
     */
    public function teamsForSelect(): Collection
    {
        return Team::query()
            ->with('mainCoach:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'main_coach_user_id']);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        $user = new User;
        $user->forceFill($attributes)->save();

        return $user;
    }

    /**
     * Remove the user from every main-coach and assistant-coach slot.
     */
    public function clearStaffing(User $user): void
    {
        Team::query()
            ->where('main_coach_user_id', $user->id)
            ->update(['main_coach_user_id' => null]);

        $user->assistantCoachedTeams()->detach();
    }
}
