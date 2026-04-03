<?php

namespace App\Repositories;

use App\Models\Player;
use App\Models\PlayerStat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PlayerRepository
{
    /**
     * All players for a team, each with their latest PlayerStat eager-loaded.
     * Ordered by jersey number.
     *
     * @return Collection<int, Player>
     */
    public function forTeamWithLatestStats(int $teamId): Collection
    {
        return Player::where('team_id', $teamId)
            ->with(['stats' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->orderBy('jersey_number')
            ->get();
    }

    /**
     * Create a new player record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Player
    {
        return Player::create($data);
    }

    /**
     * Create a PlayerStat record for a player.
     *
     * @param  array<string, mixed>  $data
     */
    public function createStat(array $data): PlayerStat
    {
        return PlayerStat::create($data);
    }
}
