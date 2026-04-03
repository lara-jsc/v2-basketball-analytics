<?php

namespace App\Repositories;

use App\Models\Player;
use App\Models\PlayerStat;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * Find a player by PK. Throws ModelNotFoundException if not found.
     */
    public function findOrFail(int $id): Player
    {
        return Player::findOrFail($id);
    }

    /**
     * Update a player's attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Player $player, array $data): void
    {
        $player->update($data);
    }

    /**
     * Update the player's most recent stat row, or create one if none exists.
     *
     * @param  array<string, mixed>  $statsData
     */
    public function upsertStat(Player $player, array $statsData): PlayerStat
    {
        $existing = $player->stats()->latest()->first();

        if ($existing) {
            $existing->update($statsData);

            return $existing;
        }

        return $this->createStat([...$statsData, 'player_id' => $player->id]);
    }
}
