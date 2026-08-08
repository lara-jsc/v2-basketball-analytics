<?php

namespace App\Repositories;

use App\Models\PlayerHistory;
use Illuminate\Database\Eloquent\Collection;

class PlayerHistoryRepository
{
    /**
     * All history rows for a player, newest game first.
     * Optionally filtered by date range and/or team IDs.
     *
     * @param  array{from?: string, to?: string, playing_team_id?: int, opponent_team_id?: int}  $filters
     * @return Collection<int, PlayerHistory>
     */
    public function forPlayer(int $playerId, array $filters = []): Collection
    {
        $query = PlayerHistory::where('player_id', $playerId)
            ->with(['playingTeam:id,code,name', 'opponentTeam:id,code,name'])
            ->orderByDesc('game_date');

        if (isset($filters['from'])) {
            $query->whereDate('game_date', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->whereDate('game_date', '<=', $filters['to']);
        }

        if (isset($filters['playing_team_id'])) {
            $query->where('playing_team_id', $filters['playing_team_id']);
        }

        if (isset($filters['opponent_team_id'])) {
            $query->where('opponent_team_id', $filters['opponent_team_id']);
        }

        return $query->get();
    }

    /**
     * Find a single history row by PK. Throws ModelNotFoundException if absent.
     */
    public function findOrFail(int $id): PlayerHistory
    {
        return PlayerHistory::with([
            'player:id,first_name,last_name',
            'playingTeam:id,code,name',
            'opponentTeam:id,code,name',
        ])->findOrFail($id);
    }

    /**
     * Insert or update a history row matched on the unique key
     * (player_id, game_date, opponent_team_id).
     *
     * @param  array<string, mixed>  $data
     */
    public function upsert(array $data): PlayerHistory
    {
        return PlayerHistory::updateOrCreate(
            [
                'player_id' => $data['player_id'],
                'game_date' => $data['game_date'],
                'opponent_team_id' => $data['opponent_team_id'],
            ],
            $data,
        );
    }

    /**
     * Hard-delete a history row.
     */
    public function delete(PlayerHistory $history): void
    {
        $history->delete();
    }

    /**
     * All raw history rows for a player — no eager loads.
     * Used by the aggregator which only needs column values.
     *
     * @return Collection<int, PlayerHistory>
     */
    public function rawForPlayer(int $playerId): Collection
    {
        return PlayerHistory::where('player_id', $playerId)->get();
    }
}
