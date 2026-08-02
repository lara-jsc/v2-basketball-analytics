<?php

namespace App\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\LiveGameLineupStint;
use App\Models\LiveGamePlayerStat;

class LiveGameProjectionService
{
    public function __construct(
        private readonly LiveGameAlertService $alertService,
    ) {}

    public function rebuild(LiveGame $game): void
    {
        $game->refresh();
        $events = $game->events()->orderBy('sequence')->get();
        $voidedEventIds = $events
            ->where('type', 'correction')
            ->pluck('voids_event_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $effectiveEvents = $events
            ->reject(fn (LiveGameEvent $event): bool => in_array($event->id, $voidedEventIds, true) || $event->type === 'correction')
            ->values();

        $startingPlayerIds = $this->playerIds($game->starting_player_ids);
        $activePlayerIds = $startingPlayerIds;
        $game->playerStats()->delete();
        $game->lineupStints()->delete();

        /** @var array<int, LiveGameLineupStint> $activeStints */
        $activeStints = [];
        foreach ($startingPlayerIds as $playerId) {
            $this->statFor($game, $playerId, true, true);
            $activeStints[$playerId] = $this->openStint($game, $playerId, 0, 0, $game->started_at ?? $game->created_at);
        }

        $homeScore = 0;
        $opponentScore = 0;

        foreach ($effectiveEvents as $event) {
            if ($event->type === 'substitution') {
                $activePlayerIds = $this->applySubstitution(
                    $game,
                    $event,
                    $startingPlayerIds,
                    $activePlayerIds,
                    $activeStints,
                    $homeScore,
                    $opponentScore,
                );

                continue;
            }

            if ($event->type === 'opponent_score') {
                $points = $this->payloadInt($event, 'points');
                $opponentScore += $points;
                $this->applyPlusMinus($game, $activePlayerIds, $activeStints, -$points);

                continue;
            }

            if ($event->team_scope !== 'own' || $event->player_id === null) {
                continue;
            }

            $stat = $this->statFor(
                $game,
                $event->player_id,
                in_array($event->player_id, $startingPlayerIds, true),
                in_array($event->player_id, $activePlayerIds, true),
            );

            $points = $this->applyOwnPlayerEvent($stat, $event);
            $homeScore += $points;
            $this->applyPlusMinus($game, $activePlayerIds, $activeStints, $points);
        }

        $currentElapsedSeconds = $this->gameElapsedSeconds(
            $game,
            $game->current_period,
            $this->effectiveSecondsRemaining($game),
        );
        foreach ($activeStints as $playerId => $stint) {
            $this->setStintDuration($game, $playerId, $stint, $currentElapsedSeconds);
        }

        LiveGamePlayerStat::query()
            ->where('live_game_id', $game->id)
            ->update(['is_active' => false]);

        if ($activePlayerIds !== []) {
            LiveGamePlayerStat::query()
                ->where('live_game_id', $game->id)
                ->whereIn('player_id', $activePlayerIds)
                ->update(['is_active' => true]);
        }

        $game->forceFill([
            'home_score' => $homeScore,
            'opponent_score' => $opponentScore,
            'active_player_ids' => array_values($activePlayerIds),
        ])->save();

        $this->alertService->sync($game, $effectiveEvents);
    }

    /** @param list<int> $startingPlayerIds @param list<int> $activePlayerIds @param array<int, LiveGameLineupStint> $activeStints @return list<int> */
    private function applySubstitution(LiveGame $game, LiveGameEvent $event, array $startingPlayerIds, array $activePlayerIds, array &$activeStints, int $homeScore, int $opponentScore): array
    {
        $playerOutId = $this->payloadInt($event, 'player_out_id');
        $playerInId = $this->payloadInt($event, 'player_in_id');
        $elapsedSeconds = $this->eventElapsedSeconds($game, $event);

        if (isset($activeStints[$playerOutId])) {
            $this->closeStint($game, $playerOutId, $activeStints[$playerOutId], $event, $elapsedSeconds, $homeScore, $opponentScore);
            unset($activeStints[$playerOutId]);
        }

        $activePlayerIds = array_values(array_filter(
            $activePlayerIds,
            fn (int $playerId): bool => $playerId !== $playerOutId,
        ));

        if (! in_array($playerInId, $activePlayerIds, true)) {
            $activePlayerIds[] = $playerInId;
            $activeStints[$playerInId] = $this->openStint(
                $game,
                $playerInId,
                $event->period,
                $event->clock_seconds_remaining,
                $event->occurred_at,
                $homeScore,
                $opponentScore,
            );
        }

        $this->statFor($game, $playerOutId, in_array($playerOutId, $startingPlayerIds, true), false);
        $this->statFor($game, $playerInId, in_array($playerInId, $startingPlayerIds, true), true);

        return $activePlayerIds;
    }

    /** @param list<int> $activePlayerIds @param array<int, LiveGameLineupStint> $activeStints */
    private function applyPlusMinus(LiveGame $game, array $activePlayerIds, array $activeStints, int $points): void
    {
        if ($points === 0) {
            return;
        }

        foreach ($activePlayerIds as $playerId) {
            LiveGamePlayerStat::query()
                ->where('live_game_id', $game->id)
                ->where('player_id', $playerId)
                ->increment('plus_minus', $points);

            if (isset($activeStints[$playerId])) {
                $activeStints[$playerId]->increment('plus_minus', $points);
            }
        }
    }

    private function openStint(LiveGame $game, int $playerId, int $period, int $clockSecondsRemaining, mixed $startedAt, int $homeScore = 0, int $opponentScore = 0): LiveGameLineupStint
    {
        return LiveGameLineupStint::query()->create([
            'live_game_id' => $game->id,
            'player_id' => $playerId,
            'start_period' => $period === 0 ? 1 : $period,
            'start_clock_seconds_remaining' => $period === 0 ? $game->period_length_seconds : $clockSecondsRemaining,
            'started_at' => $startedAt,
            'start_score_for' => $homeScore,
            'start_score_against' => $opponentScore,
            'duration_seconds' => 0,
            'plus_minus' => 0,
        ]);
    }

    private function closeStint(LiveGame $game, int $playerId, LiveGameLineupStint $stint, LiveGameEvent $event, int $elapsedSeconds, int $homeScore, int $opponentScore): void
    {
        $duration = $this->stintDuration($game, $stint, $elapsedSeconds);
        $stint->update([
            'end_period' => $event->period,
            'end_clock_seconds_remaining' => $event->clock_seconds_remaining,
            'ended_at' => $event->occurred_at,
            'end_score_for' => $homeScore,
            'end_score_against' => $opponentScore,
            'duration_seconds' => $duration,
        ]);
        $this->addMinutes($game, $playerId, $duration);
    }

    private function setStintDuration(LiveGame $game, int $playerId, LiveGameLineupStint $stint, int $elapsedSeconds): void
    {
        $duration = $this->stintDuration($game, $stint, $elapsedSeconds);
        $stint->update(['duration_seconds' => $duration]);
        $this->addMinutes($game, $playerId, $duration);
    }

    private function addMinutes(LiveGame $game, int $playerId, int $seconds): void
    {
        if ($seconds > 0) {
            LiveGamePlayerStat::query()
                ->where('live_game_id', $game->id)
                ->where('player_id', $playerId)
                ->increment('minutes_seconds', $seconds);
        }
    }

    private function stintDuration(LiveGame $game, LiveGameLineupStint $stint, int $endElapsedSeconds): int
    {
        return max(0, $endElapsedSeconds - $this->gameElapsedSeconds(
            $game,
            $stint->start_period,
            $stint->start_clock_seconds_remaining,
        ));
    }

    private function eventElapsedSeconds(LiveGame $game, LiveGameEvent $event): int
    {
        return $this->gameElapsedSeconds($game, $event->period, $event->clock_seconds_remaining);
    }

    private function gameElapsedSeconds(LiveGame $game, int $period, int $clockSecondsRemaining): int
    {
        return max(0, ($period - 1) * $game->period_length_seconds + ($game->period_length_seconds - $clockSecondsRemaining));
    }

    private function effectiveSecondsRemaining(LiveGame $game): int
    {
        if (! $game->clock_running || $game->clock_started_at === null) {
            return $game->clock_seconds_remaining;
        }

        return max(0, $game->clock_seconds_remaining - $game->clock_started_at->diffInSeconds(now()));
    }

    private function applyOwnPlayerEvent(LiveGamePlayerStat $stat, LiveGameEvent $event): int
    {
        $increments = [];
        $score = 0;

        switch ($event->type) {
            case 'shot_made':
                $points = $this->payloadInt($event, 'points');
                $score = $points;
                $increments = [
                    'points' => $points,
                    'field_goals_made' => 1,
                    'field_goals_attempted' => 1,
                ];
                if ($points === 3) {
                    $increments['three_pointers_made'] = 1;
                    $increments['three_pointers_attempted'] = 1;
                }
                break;

            case 'shot_missed':
                $increments = ['field_goals_attempted' => 1];
                if ($this->payloadInt($event, 'points') === 3) {
                    $increments['three_pointers_attempted'] = 1;
                }
                break;

            case 'free_throw_made':
                $score = 1;
                $increments = [
                    'points' => 1,
                    'free_throws_made' => 1,
                    'free_throws_attempted' => 1,
                ];
                break;

            case 'free_throw_missed':
                $increments = ['free_throws_attempted' => 1];
                break;

            case 'rebound':
                $increments = ['rebounds' => 1];
                $increments[$this->payloadString($event, 'kind') === 'offensive' ? 'offensive_rebounds' : 'defensive_rebounds'] = 1;
                break;

            case 'assist':
                $increments = ['assists' => 1];
                break;

            case 'foul':
                $kind = $this->payloadString($event, 'kind', 'personal');
                $increments = [match ($kind) {
                    'technical' => 'technical_fouls',
                    'flagrant' => 'flagrant_fouls',
                    default => 'personal_fouls',
                } => 1];
                break;

            case 'turnover':
                $increments = ['turnovers' => 1];
                break;
        }

        foreach ($increments as $column => $amount) {
            $stat->increment($column, $amount);
        }

        return $score;
    }

    private function statFor(LiveGame $game, int $playerId, bool $isStarter, bool $isActive): LiveGamePlayerStat
    {
        return LiveGamePlayerStat::query()->firstOrCreate(
            ['live_game_id' => $game->id, 'player_id' => $playerId],
            ['is_starter' => $isStarter, 'is_active' => $isActive],
        );
    }

    /** @param array<int, mixed>|null $playerIds @return list<int> */
    private function playerIds(?array $playerIds): array
    {
        return array_values(array_unique(array_map('intval', $playerIds ?? [])));
    }

    private function payloadInt(LiveGameEvent $event, string $key): int
    {
        return (int) ($event->payload[$key] ?? 0);
    }

    private function payloadString(LiveGameEvent $event, string $key, string $default = ''): string
    {
        return (string) ($event->payload[$key] ?? $default);
    }
}
