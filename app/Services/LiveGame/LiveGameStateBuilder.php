<?php

namespace App\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameAlert;
use App\Models\LiveGameEvent;
use App\Models\LiveGamePlayerStat;

class LiveGameStateBuilder
{
    /** @return array<string, mixed> */
    public function build(LiveGame $game): array
    {
        $game->refresh();

        return [
            'liveGame' => [
                'id' => $game->id,
                'home_team_id' => $game->home_team_id,
                'opponent_team_id' => $game->opponent_team_id,
                'status' => $game->status,
                'game_date' => $game->game_date?->toDateString(),
                'period_length_seconds' => $game->period_length_seconds,
                'current_period' => $game->current_period,
            ],
            'score' => [
                'home' => $game->home_score,
                'opponent' => $game->opponent_score,
            ],
            'clock' => [
                'period' => $game->current_period,
                'period_length_seconds' => $game->period_length_seconds,
                'seconds_remaining' => $this->effectiveSecondsRemaining($game),
                'running' => $game->clock_running,
                'server_now' => now()->toISOString(),
            ],
            'active_player_ids' => $game->active_player_ids ?? [],
            'opponent_active_player_ids' => $game->opponent_active_player_ids ?? [],
            'stats' => LiveGamePlayerStat::query()
                ->where('live_game_id', $game->id)
                ->orderBy('player_id')
                ->get()
                ->map(fn (LiveGamePlayerStat $stat): array => $this->stat($stat))
                ->all(),
            'events' => LiveGameEvent::query()
                ->where('live_game_id', $game->id)
                ->orderBy('sequence')
                ->get()
                ->map(fn (LiveGameEvent $event): array => $this->event($event))
                ->all(),
            'alerts' => LiveGameAlert::query()
                ->where('live_game_id', $game->id)
                ->whereNull('resolved_at')
                ->orderByDesc('triggered_at')
                ->get()
                ->map(fn (LiveGameAlert $alert): array => $this->alert($alert))
                ->all(),
        ];
    }

    /** @return array<string, int|bool> */
    private function stat(LiveGamePlayerStat $stat): array
    {
        return [
            'player_id' => $stat->player_id,
            'is_starter' => $stat->is_starter,
            'is_active' => $stat->is_active,
            'minutes_seconds' => $stat->minutes_seconds,
            'plus_minus' => $stat->plus_minus,
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
        ];
    }

    private function effectiveSecondsRemaining(LiveGame $game): int
    {
        if (! $game->clock_running || $game->clock_started_at === null) {
            return $game->clock_seconds_remaining;
        }

        return max(0, $game->clock_seconds_remaining - $game->clock_started_at->diffInSeconds(now()));
    }

    /** @return array<string, mixed> */
    private function event(LiveGameEvent $event): array
    {
        return [
            'id' => $event->id,
            'sequence' => $event->sequence,
            'type' => $event->type,
            'team_scope' => $event->team_scope,
            'player_id' => $event->player_id,
            'period' => $event->period,
            'clock_seconds_remaining' => $event->clock_seconds_remaining,
            'occurred_at' => $event->occurred_at->toISOString(),
            'payload' => $event->payload ?? [],
            'voids_event_id' => $event->voids_event_id,
            'recorded_by_user_id' => $event->recorded_by_user_id,
        ];
    }

    /** @return array<string, mixed> */
    private function alert(LiveGameAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'player_id' => $alert->player_id,
            'type' => $alert->type,
            'severity' => $alert->severity,
            'period' => $alert->period,
            'clock_seconds_remaining' => $alert->clock_seconds_remaining,
            'message' => $alert->message,
            'context' => $alert->context ?? [],
            'triggered_at' => $alert->triggered_at?->toISOString(),
            'resolved_at' => $alert->resolved_at?->toISOString(),
        ];
    }
}
