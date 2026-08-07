<?php

namespace App\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameAlert;
use App\Models\LiveGameEvent;
use App\Models\Player;
use Illuminate\Support\Collection;

class LiveGameAlertService
{
    /** @param Collection<int, LiveGameEvent> $effectiveEvents */
    public function sync(LiveGame $game, Collection $effectiveEvents): void
    {
        $currentPeriod = $game->current_period;
        $homeTeamId = (int) $game->home_team_id;
        $opponentTeamId = (int) $game->opponent_team_id;

        $madeShots = [];
        $personalFouls = [];
        $playerTeamIds = $this->playerTeamIds($effectiveEvents);
        $missStreaks = app(LiveGameMissStreakCalculator::class)->compute(
            $effectiveEvents->filter(fn (LiveGameEvent $event): bool => $event->team_scope === 'own'),
        );

        // Every accumulator is keyed by team: a single scalar would mix both benches, which is
        // what made opponent_run and team_drought meaningless in a dual-team game.
        $runs = [$homeTeamId => 0, $opponentTeamId => 0];
        $emptyPossessions = [$homeTeamId => 0, $opponentTeamId => 0];

        foreach ($effectiveEvents as $event) {
            if ($event->type === 'opponent_score') {
                $this->addRun($runs, $opponentTeamId, $homeTeamId, $this->payloadInt($event, 'points'));

                continue;
            }

            if ($event->team_scope !== 'own' || $event->player_id === null) {
                continue;
            }

            $teamId = $playerTeamIds[(int) $event->player_id] ?? null;
            if ($teamId === null) {
                continue;
            }

            $otherTeamId = $teamId === $homeTeamId ? $opponentTeamId : $homeTeamId;

            if ($event->type === 'shot_made' || $event->type === 'free_throw_made') {
                $this->addRun($runs, $teamId, $otherTeamId, $this->scoredPoints($event));
                $emptyPossessions[$teamId] = 0;
            }

            if ($event->type === 'shot_made') {
                if ($event->period === $currentPeriod) {
                    $madeShots[$event->player_id] = ($madeShots[$event->player_id] ?? 0) + 1;
                }

                continue;
            }

            if ($event->type === 'shot_missed') {
                $emptyPossessions[$teamId]++;

                continue;
            }

            if ($event->type === 'foul') {
                // Only personal fouls lead to disqualification, so only personal fouls belong in
                // a foul-trouble count. Counting technicals here reported "6 fouls" for a player
                // the recorder had already capped at 5 personal.
                if ($this->foulKind($event) === 'personal') {
                    $personalFouls[$event->player_id] = ($personalFouls[$event->player_id] ?? 0) + 1;
                }

                continue;
            }

            if (in_array($event->type, ['turnover', 'free_throw_missed'], true)) {
                $emptyPossessions[$teamId]++;
            }
        }

        $playerIds = array_values(array_unique(array_merge(
            array_keys($madeShots),
            array_keys($missStreaks),
            array_keys($personalFouls),
        )));
        $players = Player::query()->whereIn('id', $playerIds)->get()->keyBy('id');
        $alerts = [];

        foreach ($madeShots as $playerId => $count) {
            if ($count >= 3) {
                $alerts[] = $this->playerAlert(
                    'hot_player',
                    $playerId,
                    $playerTeamIds[$playerId] ?? null,
                    'info',
                    $currentPeriod,
                    $game->clock_seconds_remaining,
                    sprintf('%s has made %d shots in Q%d.', $this->playerName($players, $playerId), $count, $currentPeriod),
                    ['made_shots' => $count, 'period' => $currentPeriod],
                );
            }
        }

        $foulThreshold = $currentPeriod < 4 ? 3 : 4;
        foreach ($personalFouls as $playerId => $count) {
            if ($count < $foulThreshold) {
                continue;
            }

            $disqualified = $count >= LiveGameEventRules::MAX_PERSONAL_FOULS;

            $alerts[] = $this->playerAlert(
                'foul_trouble',
                $playerId,
                $playerTeamIds[$playerId] ?? null,
                'warning',
                $currentPeriod,
                $game->clock_seconds_remaining,
                $disqualified
                    ? sprintf('%s is disqualified — %d personal fouls.', $this->playerName($players, $playerId), $count)
                    : sprintf('%s has %d personal fouls.', $this->playerName($players, $playerId), $count),
                ['personal_fouls' => $count, 'disqualified' => $disqualified],
            );
        }

        foreach ($missStreaks as $playerId => $count) {
            if ($count >= 3) {
                $alerts[] = $this->playerAlert(
                    'cold_player',
                    $playerId,
                    $playerTeamIds[$playerId] ?? null,
                    'warning',
                    $currentPeriod,
                    $game->clock_seconds_remaining,
                    sprintf('%s has missed %d shots in a row.', $this->playerName($players, $playerId), $count),
                    ['miss_streak' => $count],
                );
            }
        }

        foreach ([$homeTeamId, $opponentTeamId] as $teamId) {
            $otherTeamId = $teamId === $homeTeamId ? $opponentTeamId : $homeTeamId;
            $timeoutReasons = [];

            // A run *against* this side is what should prompt this side's bench.
            if ($runs[$otherTeamId] >= 8) {
                $alerts[] = $this->teamAlert(
                    'opponent_run',
                    $teamId,
                    'warning',
                    $currentPeriod,
                    $game->clock_seconds_remaining,
                    sprintf('Opponent has scored %d unanswered points.', $runs[$otherTeamId]),
                    ['points' => $runs[$otherTeamId]],
                );
                $timeoutReasons[] = 'opponent_run';
            }

            if ($emptyPossessions[$teamId] >= 4) {
                $alerts[] = $this->teamAlert(
                    'team_drought',
                    $teamId,
                    'warning',
                    $currentPeriod,
                    $game->clock_seconds_remaining,
                    sprintf('Team has %d empty possessions.', $emptyPossessions[$teamId]),
                    ['empty_possessions' => $emptyPossessions[$teamId]],
                );
                $timeoutReasons[] = 'team_drought';
            }

            if ($timeoutReasons !== []) {
                $alerts[] = $this->teamAlert(
                    'timeout_prompt',
                    $teamId,
                    'warning',
                    $currentPeriod,
                    $game->clock_seconds_remaining,
                    count($timeoutReasons) === 2
                        ? 'Opponent run and team drought are active.'
                        : ($timeoutReasons[0] === 'opponent_run' ? 'Opponent run is active.' : 'Team drought is active.'),
                    ['reasons' => $timeoutReasons],
                );
            }
        }

        $substitutionReasons = [];
        foreach ($personalFouls as $playerId => $count) {
            if ($count >= $foulThreshold) {
                $substitutionReasons[$playerId] = 'foul_trouble';
            }
        }
        foreach ($missStreaks as $playerId => $count) {
            if ($count >= 3 && ! isset($substitutionReasons[$playerId])) {
                $substitutionReasons[$playerId] = 'cold_player';
            }
        }
        foreach ($substitutionReasons as $playerId => $reason) {
            // Phrased as the action to take — repeating the foul_trouble diagnosis verbatim
            // produced two rows with identical text.
            $alerts[] = $this->playerAlert(
                'substitution_prompt',
                $playerId,
                $playerTeamIds[$playerId] ?? null,
                'warning',
                $currentPeriod,
                $game->clock_seconds_remaining,
                $reason === 'foul_trouble'
                    ? sprintf('Sub out %s — %d personal fouls.', $this->playerName($players, $playerId), $personalFouls[$playerId])
                    : sprintf('Sub out %s — %d straight misses.', $this->playerName($players, $playerId), $missStreaks[$playerId]),
                ['reason' => $reason],
            );
        }

        $now = now();
        $activeAlerts = LiveGameAlert::query()
            ->where('live_game_id', $game->id)
            ->whereNull('resolved_at')
            ->orderBy('id')
            ->get();
        $activeAlertsByKey = [];
        $duplicateAlertIds = [];
        foreach ($activeAlerts as $activeAlert) {
            $key = $this->alertKey($activeAlert->type, $activeAlert->player_id, $activeAlert->team_id);

            if (isset($activeAlertsByKey[$key])) {
                $duplicateAlertIds[] = $activeAlert->id;

                continue;
            }

            $activeAlertsByKey[$key] = $activeAlert;
        }

        if ($duplicateAlertIds !== []) {
            LiveGameAlert::query()
                ->whereKey($duplicateAlertIds)
                ->update(['resolved_at' => $now, 'updated_at' => $now]);
        }

        $alertsByKey = [];
        foreach ($alerts as $alert) {
            $alertsByKey[$this->alertKey($alert['type'], $alert['player_id'], $alert['team_id'])] = $alert;
        }

        foreach ($alertsByKey as $key => $alert) {
            $activeAlert = $activeAlertsByKey[$key] ?? null;
            if ($activeAlert instanceof LiveGameAlert) {
                // Keep period and clock_seconds_remaining at their original values: they record
                // when the alert fired, not when it was last recomputed.
                $activeAlert->fill([
                    'message' => $alert['message'],
                    'severity' => $alert['severity'],
                    'context' => $alert['context'],
                ]);

                if ($activeAlert->isDirty()) {
                    $activeAlert->save();
                }

                unset($activeAlertsByKey[$key]);

                continue;
            }

            LiveGameAlert::query()->create([
                'live_game_id' => $game->id,
                ...$alert,
                'triggered_at' => $now,
                'resolved_at' => null,
            ]);
        }

        if ($activeAlertsByKey !== []) {
            LiveGameAlert::query()
                ->whereKey(array_map(fn (LiveGameAlert $alert): int => $alert->id, $activeAlertsByKey))
                ->update(['resolved_at' => $now, 'updated_at' => $now]);
        }
    }

    /**
     * A run is unanswered points, so scoring resets the other side's run.
     *
     * @param  array<int, int>  $runs
     */
    private function addRun(array &$runs, int $scoringTeamId, int $otherTeamId, int $points): void
    {
        if ($points > 0) {
            $runs[$scoringTeamId] = ($runs[$scoringTeamId] ?? 0) + $points;
        }

        $runs[$otherTeamId] = 0;
    }

    /**
     * @param  Collection<int, LiveGameEvent>  $events
     * @return array<int, int>
     */
    private function playerTeamIds(Collection $events): array
    {
        $playerIds = $events
            ->pluck('player_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($playerIds === []) {
            return [];
        }

        return Player::query()
            ->whereIn('id', $playerIds)
            ->pluck('team_id', 'id')
            ->map(fn (mixed $teamId): int => (int) $teamId)
            ->all();
    }

    private function scoredPoints(LiveGameEvent $event): int
    {
        return $event->type === 'free_throw_made' ? 1 : $this->payloadInt($event, 'points');
    }

    private function foulKind(LiveGameEvent $event): string
    {
        $kind = $event->payload['kind'] ?? 'personal';

        return is_string($kind) ? $kind : 'personal';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function playerAlert(string $type, int $playerId, ?int $teamId, string $severity, int $period, int $clockSecondsRemaining, string $message, array $context): array
    {
        return [
            'type' => $type,
            'player_id' => $playerId,
            'team_id' => $teamId,
            'severity' => $severity,
            'period' => $period,
            'clock_seconds_remaining' => $clockSecondsRemaining,
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function teamAlert(string $type, int $teamId, string $severity, int $period, int $clockSecondsRemaining, string $message, array $context): array
    {
        return [
            'type' => $type,
            'player_id' => null,
            'team_id' => $teamId,
            'severity' => $severity,
            'period' => $period,
            'clock_seconds_remaining' => $clockSecondsRemaining,
            'message' => $message,
            'context' => $context,
        ];
    }

    /** @param Collection<int, Player> $players */
    private function playerName(Collection $players, int $playerId): string
    {
        $player = $players->get($playerId);
        if (! $player instanceof Player) {
            return "Player #{$playerId}";
        }

        $name = trim("{$player->first_name} {$player->last_name}");

        return $name !== '' ? $name : "Player #{$playerId}";
    }

    private function payloadInt(LiveGameEvent $event, string $key): int
    {
        return (int) ($event->payload[$key] ?? 0);
    }

    /** Team-level alerts key on their side, or the two benches' prompts would collide. */
    private function alertKey(string $type, ?int $playerId, ?int $teamId): string
    {
        if ($playerId !== null) {
            return $type.':'.$playerId;
        }

        return $type.':team:'.($teamId ?? 'game');
    }
}
