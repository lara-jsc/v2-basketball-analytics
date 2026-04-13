<?php

namespace App\Repositories;

use App\Models\Player;
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
     * Update or create a player matched by team_id + jersey_number.
     * Used by CSV import to prevent duplicates on re-upload.
     *
     * @param  array<string, mixed>  $playerData
     */
    public function updateOrCreateByJersey(int $teamId, int $jerseyNumber, array $playerData): Player
    {
        return Player::updateOrCreate(
            ['team_id' => $teamId, 'jersey_number' => $jerseyNumber],
            $playerData,
        );
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

}
