<?php

namespace App\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\LiveGamePlayerStat;

class LiveGameProjectionService
{
    public function rebuild(LiveGame $game): void
    {
        $events = $game->events()->orderBy('sequence')->get();
        $voidedEventIds = $events
            ->where('type', 'correction')
            ->pluck('voids_event_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $startingPlayerIds = $this->playerIds($game->starting_player_ids);
        $activePlayerIds = $startingPlayerIds;
        $game->playerStats()->delete();

        foreach ($startingPlayerIds as $playerId) {
            $this->statFor($game, $playerId, true, true);
        }

        $homeScore = 0;
        $opponentScore = 0;

        foreach ($events as $event) {
            if (in_array($event->id, $voidedEventIds, true) || $event->type === 'correction') {
                continue;
            }

            if ($event->type === 'substitution') {
                $activePlayerIds = $this->applySubstitution($game, $event, $startingPlayerIds, $activePlayerIds);

                continue;
            }

            if ($event->type === 'opponent_score') {
                $opponentScore += $this->payloadInt($event, 'points');

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

            $homeScore += $this->applyOwnPlayerEvent($stat, $event);
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
    }

    /** @param list<int> $startingPlayerIds @param list<int> $activePlayerIds @return list<int> */
    private function applySubstitution(LiveGame $game, LiveGameEvent $event, array $startingPlayerIds, array $activePlayerIds): array
    {
        $playerOutId = $this->payloadInt($event, 'player_out_id');
        $playerInId = $this->payloadInt($event, 'player_in_id');

        $activePlayerIds = array_values(array_filter(
            $activePlayerIds,
            fn (int $playerId): bool => $playerId !== $playerOutId,
        ));

        if (! in_array($playerInId, $activePlayerIds, true)) {
            $activePlayerIds[] = $playerInId;
        }

        $this->statFor($game, $playerOutId, in_array($playerOutId, $startingPlayerIds, true), false);
        $this->statFor($game, $playerInId, in_array($playerInId, $startingPlayerIds, true), true);

        return $activePlayerIds;
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
