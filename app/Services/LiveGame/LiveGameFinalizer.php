<?php

namespace App\Services\LiveGame;

use App\Actions\UpsertPlayerHistoryAction;
use App\Jobs\RebuildPlayerStats;
use App\Models\LiveGame;
use App\Models\LiveGamePlayerStat;
use App\Models\Player;
use App\Models\PlayerHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiveGameFinalizer
{
    private const LIVE_FINALIZED_NOTES_PATTERN = '/^Finalized from live game #\d+$/';

    public function __construct(
        private readonly UpsertPlayerHistoryAction $upsertPlayerHistory,
        private readonly LiveGameProjectionService $projectionService,
    ) {}

    public function finalize(LiveGame $game): void
    {
        DB::transaction(function () use ($game): void {
            $this->projectionService->rebuild($game);
            $game->refresh();

            $gameDateSource = $game->started_at ?? $game->game_date ?? $game->created_at;
            if ($game->game_date === null || $game->game_date->toDateString() !== $gameDateSource->toDateString()) {
                $game->forceFill(['game_date' => $gameDateSource->toDateString()])->save();
            }

            $gameDate = $game->game_date->toDateString();
            $notes = "Finalized from live game #{$game->id}";
            $participatingStats = LiveGamePlayerStat::query()
                ->where('live_game_id', $game->id)
                ->with('player:id,team_id')
                ->get()
                ->filter(fn (LiveGamePlayerStat $stat): bool => $this->participated($stat));

            $playerIds = $participatingStats->pluck('player_id')->all();
            $playerTeamIds = collect(
                Player::query()
                    ->whereIn('id', $playerIds)
                    ->pluck('team_id', 'id')
                    ->all()
            )->map(fn (mixed $teamId): int => (int) $teamId)->all();

            foreach ([[$game->home_team_id, $game->opponent_team_id], [$game->opponent_team_id, $game->home_team_id]] as [$playingTeamId, $opponentTeamId]) {
                $teamStats = $participatingStats->filter(
                    fn (LiveGamePlayerStat $stat): bool => ($playerTeamIds[$stat->player_id] ?? null) === (int) $playingTeamId,
                );
                $participatingPlayerIds = $teamStats->pluck('player_id')->all();

                $matchupHistories = PlayerHistory::query()
                    ->where('playing_team_id', $playingTeamId)
                    ->where('opponent_team_id', $opponentTeamId)
                    ->whereDate('game_date', $gameDate)
                    ->get();

                $conflictingHistory = $matchupHistories
                    ->whereIn('player_id', $participatingPlayerIds)
                    ->contains(fn (PlayerHistory $history): bool => ! $this->isLiveFinalizedNotes($history->notes));

                if ($conflictingHistory) {
                    throw ValidationException::withMessages([
                        'game' => 'Finalization would overwrite an existing manual or imported player history entry.',
                    ]);
                }

                $replaceableHistories = $matchupHistories->filter(
                    fn (PlayerHistory $history): bool => $this->isLiveFinalizedNotes($history->notes),
                );
                $previouslyFinalizedPlayerIds = $replaceableHistories->pluck('player_id')->all();

                if ($replaceableHistories->isNotEmpty()) {
                    PlayerHistory::query()
                        ->whereIn('id', $replaceableHistories->pluck('id')->all())
                        ->delete();
                }

                $teamStats->each(function (LiveGamePlayerStat $stat) use ($playingTeamId, $opponentTeamId, $gameDate, $notes): void {
                    $this->upsertPlayerHistory->execute($stat->player_id, [
                        'playing_team_id' => $playingTeamId,
                        'opponent_team_id' => $opponentTeamId,
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
            }
        });
    }

    private function isLiveFinalizedNotes(?string $notes): bool
    {
        return $notes !== null && preg_match(self::LIVE_FINALIZED_NOTES_PATTERN, $notes) === 1;
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
