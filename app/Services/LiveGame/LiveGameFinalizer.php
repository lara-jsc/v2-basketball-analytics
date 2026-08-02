<?php

namespace App\Services\LiveGame;

use App\Actions\UpsertPlayerHistoryAction;
use App\Jobs\RebuildPlayerStats;
use App\Models\LiveGame;
use App\Models\LiveGamePlayerStat;

class LiveGameFinalizer
{
    public function __construct(
        private readonly UpsertPlayerHistoryAction $upsertPlayerHistory,
        private readonly LiveGameProjectionService $projectionService,
    ) {}

    public function finalize(LiveGame $game): void
    {
        $this->projectionService->rebuild($game);

        $gameDate = $game->game_date?->toDateString() ?? now()->toDateString();

        LiveGamePlayerStat::query()
            ->where('live_game_id', $game->id)
            ->get()
            ->filter(fn (LiveGamePlayerStat $stat): bool => $this->participated($stat))
            ->each(function (LiveGamePlayerStat $stat) use ($game, $gameDate): void {
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
                    'notes' => "Finalized from live game #{$game->id}",
                ]);

                RebuildPlayerStats::dispatch($stat->player_id);
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
