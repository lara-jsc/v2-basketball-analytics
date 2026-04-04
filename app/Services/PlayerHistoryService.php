<?php

namespace App\Services;

use App\Jobs\RebuildPlayerStats;
use App\Models\Player;
use App\Models\PlayerHistory;
use App\Repositories\PlayerHistoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PlayerHistoryService
{
    public function __construct(
        private readonly PlayerHistoryRepository $repository,
        private readonly WinProbabilityService $winProbabilityService,
    ) {}

    /**
     * Return all history rows for a player, with optional filters.
     *
     * @param  array{from?: string, to?: string, playing_team_id?: int, opponent_team_id?: int}  $filters
     * @return Collection<int, PlayerHistory>
     */
    public function listForPlayer(Player $player, array $filters = []): Collection
    {
        return $this->repository->forPlayer($player->id, $filters);
    }

    /**
     * Create a new game history entry and trigger stats rebuild for the player.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(Player $player, array $data): PlayerHistory
    {
        $history = DB::transaction(function () use ($player, $data) {
            return $this->repository->upsert([
                ...$data,
                'player_id' => $player->id,
            ]);
        });

        RebuildPlayerStats::dispatch($player->id);
        $this->winProbabilityService->invalidateForTeam($player->team_id);

        return $history;
    }

    /**
     * Update an existing game history entry and trigger stats rebuild.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PlayerHistory $history, array $data): PlayerHistory
    {
        $teamId = $history->player->team_id;

        DB::transaction(function () use ($history, $data) {
            $history->update($data);
        });

        RebuildPlayerStats::dispatch($history->player_id);
        $this->winProbabilityService->invalidateForTeam($teamId);

        return $history->fresh();
    }

    /**
     * Hard-delete a game history entry and trigger stats rebuild.
     */
    public function destroy(PlayerHistory $history): void
    {
        $playerId = $history->player_id;
        $teamId   = $history->player->team_id;

        DB::transaction(function () use ($history) {
            $this->repository->delete($history);
        });

        RebuildPlayerStats::dispatch($playerId);
        $this->winProbabilityService->invalidateForTeam($teamId);
    }
}
