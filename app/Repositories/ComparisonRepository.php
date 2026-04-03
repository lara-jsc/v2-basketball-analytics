<?php

namespace App\Repositories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Collection;

class ComparisonRepository
{
    /**
     * All is_active players for a team with their latest stat row eager-loaded.
     * Only active players are eligible for lineup and comparison.
     *
     * @return Collection<int, Player>
     */
    public function activPlayersWithStats(int $teamId): Collection
    {
        return Player::where('team_id', $teamId)
            ->where('is_active', true)
            ->with(['stats' => fn ($q) => $q->latest()->limit(1)])
            ->orderBy('jersey_number')
            ->get();
    }
}
