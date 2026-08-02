<?php

namespace App\Services\LiveGame;

use App\Actions\UpsertPlayerHistoryAction;
use App\Jobs\RebuildPlayerStats;
use App\Models\LiveGame;
use App\Models\LiveGamePlayerStat;
use App\Models\PlayerHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiveGameFinalizer
{
    public function __construct(
        private readonly UpsertPlayerHistoryAction $upsertPlayerHistory,
        private readonly LiveGameProjectionService $projectionService,
    ) {}

    public function finalize(LiveGame $game): void
    {
        DB::transaction(function () use ($game): void {
            $this->projectionService->rebuild($game);
            $game->refresh();

            if ($game->game_date === null) {
                $game->forceFill(['game_date' => $game->started_at ?? $game->created_at])->save();
            }

            $gameDate = $game->game_date->toDateString();
            $notes = "Finalized from live game #{$game->id}";
            $participatingStats = LiveGamePlayerStat::query()
                ->where('live_game_id', $game->id)
                ->get()
                ->filter(fn (LiveGamePlayerStat $stat): bool => $this->participated($stat));
            $participatingPlayerIds = $participatingStats->pluck('player_id')->all();

            $conflictingHistory = PlayerHistory::query()
                ->whereIn('player_id', $participatingPlayerIds)
                ->where('playing_team_id', $game->home_team_id)
                ->where('opponent_team_id', $game->opponent_team_id)
                ->whereDate('game_date', $gameDate)
                ->where(fn ($query) => $query->whereNull('notes')->orWhere('notes', '!=', $notes))
                ->exists();

            if ($conflictingHistory) {
                throw ValidationException::withMessages([
                    'game' => 'Finalization would overwrite an existing manual player history entry.',
                ]);
            }

            $previouslyFinalizedPlayerIds = PlayerHistory::query()
                ->where('playing_team_id', $game->home_team_id)
                ->where('opponent_team_id', $game->opponent_team_id)
                ->whereDate('game_date', $gameDate)
                ->where('notes', $notes)
                ->pluck('player_id')
                ->all();

            PlayerHistory::query()
                ->whereIn('player_id', $previouslyFinalizedPlayerIds)
                ->where('playing_team_id', $game->home_team_id)
                ->where('opponent_team_id', $game->opponent_team_id)
                ->whereDate('game_date', $gameDate)
                ->where('notes', $notes)
                ->delete();

            $participatingStats
                ->each(function (LiveGamePlayerStat $stat) use ($game, $gameDate, $notes): void {
                    $this->upsertPlayerHistory->execute($stat->player_id, [
                        'playing_team_id' => $game->home_team_id,
                        'opponent_team_id' => $game->opponent_team_id,
                        'game_date' => $gameDate,
                        'position_played' => null,
                        'minutes_played' => round($stat->minutes_seconds / 60, 2),
                        'points' => $stat->points,
                        'field_goals_made' => $stat->field_goals_made,
                        'field_goals_attempted' => $stat->field_goals_attempted,
                        'three_pointers_made' => $stat->three_pointers_made,
                        'three_pointers_attempted' => $stat->three_pointers_attempted,
                        'free_throws_made' => $stat->free_throws_made,
                        'free_throws_attempted' => $stat->free_throws_attempted,
                        'offensive_rebounds' => $stat->offensive_rebounds,
                        'defensive_rebounds' => $stat->defensive_rebounds,
                        'rebounds' => $stat->rebounds,
                        'assists' => $stat->assists,
                        'steals' => $stat->steals,
                        'blocks' => $stat->blocks,
                        'turnovers' => $stat->turnovers,
                        'personal_fouls' => $stat->personal_fouls,
                        'flagrant_fouls' => $stat->flagrant_fouls,
                        'technical_fouls' => $stat->technical_fouls,
                        'ejections' => 0,
                        'disqualifications' => 0,
                        'is_started' => $stat->is_starter,
                        'notes' => $notes,
                    ]);

                    RebuildPlayerStats::dispatch($stat->player_id)->afterCommit();
                });

            foreach (array_diff($previouslyFinalizedPlayerIds, $participatingPlayerIds) as $playerId) {
                RebuildPlayerStats::dispatch($playerId)->afterCommit();
            }
        });
    }

    private function participated(LiveGamePlayerStat $stat): bool
    {
        if ($stat->is_starter || $stat->minutes_seconds > 0) {
            return true;
        }

        foreach ([
            'points', 'field_goals_made', 'field_goals_attempted', 'three_pointers_made', 'three_pointers_attempted',
            'free_throws_made', 'free_throws_attempted', 'offensive_rebounds', 'defensive_rebounds', 'rebounds',
            'assists', 'steals', 'blocks', 'turnovers', 'personal_fouls', 'flagrant_fouls', 'technical_fouls',
        ] as $column) {
            if ($stat->{$column} !== 0) {
                return true;
            }
        }

        return false;
    }
}
