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
}
