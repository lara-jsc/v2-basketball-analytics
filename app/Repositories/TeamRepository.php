<?php

namespace App\Repositories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

class TeamRepository
{
    /**
     * All teams ordered by name.
     *
     * @return Collection<int, Team>
     */
    public function all(): Collection
    {
        return Team::orderBy('name')->get();
    }

    /**
     * Find a team by PK. Throws ModelNotFoundException if not found.
     */
    public function findOrFail(int $id): Team
    {
        return Team::findOrFail($id);
    }

    /**
     * Create a new team.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Team
    {
        return Team::create($data);
    }

    /**
     * Update a team's attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Team $team, array $data): void
    {
        $team->update($data);
    }

    /**
     * Active teams a self-signup main coach may claim (no main coach yet).
     *
     * @return Collection<int, Team>
     */
    public function claimableForSignup(): Collection
    {
        return Team::query()
            ->where('is_active', true)
            ->whereNull('main_coach_user_id')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /**
     * Teams a self-signup assistant may request to join (has a main coach).
     *
     * @return Collection<int, Team>
     */
    public function joinableForSignup(): Collection
    {
        return Team::query()
            ->whereNotNull('main_coach_user_id')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /**
     * Lock the team row for a staffing-slot check inside a transaction.
     */
    public function lockForUpdate(int $id): Team
    {
        return Team::query()->lockForUpdate()->findOrFail($id);
    }
}
