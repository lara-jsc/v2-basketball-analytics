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
        $madeShots = [];
        $missStreaks = [];
        $fouls = [];
        $opponentRun = 0;
        $emptyPossessions = 0;

        foreach ($effectiveEvents as $event) {
            if ($event->type === 'opponent_score') {
                $opponentRun += $this->payloadInt($event, 'points');

                continue;
            }

            if ($event->team_scope !== 'own') {
                continue;
            }

            if ($event->type === 'shot_made' || $event->type === 'free_throw_made') {
                $opponentRun = 0;
                $emptyPossessions = 0;
            }

            if ($event->player_id === null) {
                continue;
            }

            if ($event->type === 'shot_made') {
                if ($event->period === $currentPeriod) {
                    $madeShots[$event->player_id] = ($madeShots[$event->player_id] ?? 0) + 1;
                }

                $missStreaks[$event->player_id] = 0;

                continue;
            }

            if ($event->type === 'shot_missed') {
                $missStreaks[$event->player_id] = ($missStreaks[$event->player_id] ?? 0) + 1;
                $emptyPossessions++;

                continue;
            }

            if ($event->type === 'foul') {
                $fouls[$event->player_id] = ($fouls[$event->player_id] ?? 0) + 1;

                continue;
            }

            if (in_array($event->type, ['turnover', 'free_throw_missed'], true)) {
                $emptyPossessions++;
            }
        }

        $playerIds = array_values(array_unique(array_merge(
            array_keys($madeShots),
            array_keys($missStreaks),
            array_keys($fouls),
        )));
        $players = Player::query()->whereIn('id', $playerIds)->get()->keyBy('id');
        $alerts = [];

        foreach ($madeShots as $playerId => $count) {
            if ($count >= 3) {
                $alerts[] = $this->playerAlert(
                    'hot_player',
                    $playerId,
                    'info',
                    $currentPeriod,
                    $game->clock_seconds_remaining,
                    sprintf('%s has made %d shots in Q%d.', $this->playerName($players, $playerId), $count, $currentPeriod),
                    ['made_shots' => $count, 'period' => $currentPeriod],
                );
            }
        }

        $foulThreshold = $currentPeriod < 4 ? 3 : 4;
        foreach ($fouls as $playerId => $count) {
            if ($count >= $foulThreshold) {
                $alerts[] = $this->playerAlert(
                    'foul_trouble',
                    $playerId,
                    'warning',
                    $currentPeriod,
                    $game->clock_seconds_remaining,
                    sprintf('%s has %d fouls.', $this->playerName($players, $playerId), $count),
                    ['fouls' => $count],
                );
            }
        }

        foreach ($missStreaks as $playerId => $count) {
            if ($count >= 3) {
                $alerts[] = $this->playerAlert(
                    'cold_player',
                    $playerId,
                    'warning',
                    $currentPeriod,
                    $game->clock_seconds_remaining,
                    sprintf('%s has missed %d shots in a row.', $this->playerName($players, $playerId), $count),
                    ['miss_streak' => $count],
                );
            }
        }

        $timeoutReasons = [];
        if ($opponentRun >= 8) {
            $alerts[] = $this->gameAlert(
                'opponent_run',
                'warning',
                $currentPeriod,
                $game->clock_seconds_remaining,
                sprintf('Opponent has scored %d unanswered points.', $opponentRun),
                ['points' => $opponentRun],
            );
            $timeoutReasons[] = 'opponent_run';
        }

        if ($emptyPossessions >= 4) {
            $alerts[] = $this->gameAlert(
                'team_drought',
                'warning',
                $currentPeriod,
                $game->clock_seconds_remaining,
                sprintf('Team has %d empty possessions.', $emptyPossessions),
                ['empty_possessions' => $emptyPossessions],
            );
            $timeoutReasons[] = 'team_drought';
        }

        if ($timeoutReasons !== []) {
            $alerts[] = $this->gameAlert(
                'timeout_prompt',
                'warning',
                $currentPeriod,
                $game->clock_seconds_remaining,
                count($timeoutReasons) === 2
                    ? 'Opponent run and team drought are active.'
                    : ($timeoutReasons[0] === 'opponent_run' ? 'Opponent run is active.' : 'Team drought is active.'),
                ['reasons' => $timeoutReasons],
            );
        }

        $substitutionReasons = [];
        foreach ($fouls as $playerId => $count) {
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
            $alerts[] = $this->playerAlert(
                'substitution_prompt',
                $playerId,
                'warning',
                $currentPeriod,
                $game->clock_seconds_remaining,
                $reason === 'foul_trouble'
                    ? sprintf('%s has %d fouls.', $this->playerName($players, $playerId), $fouls[$playerId])
                    : sprintf('%s has missed %d shots in a row.', $this->playerName($players, $playerId), $missStreaks[$playerId]),
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
            $key = $this->alertKey($activeAlert->type, $activeAlert->player_id);

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
            $alertsByKey[$this->alertKey($alert['type'], $alert['player_id'])] = $alert;
        }

        foreach ($alertsByKey as $key => $alert) {
            $activeAlert = $activeAlertsByKey[$key] ?? null;
            if ($activeAlert instanceof LiveGameAlert) {
                $activeAlert->fill($alert);
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

    /** @return array<string, mixed> */
    private function playerAlert(string $type, int $playerId, string $severity, int $period, int $clockSecondsRemaining, string $message, array $context): array
    {
        return [
            'type' => $type,
            'player_id' => $playerId,
            'severity' => $severity,
            'period' => $period,
            'clock_seconds_remaining' => $clockSecondsRemaining,
            'message' => $message,
            'context' => $context,
        ];
    }

    /** @return array<string, mixed> */
    private function gameAlert(string $type, string $severity, int $period, int $clockSecondsRemaining, string $message, array $context): array
    {
        return [
            'type' => $type,
            'player_id' => null,
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

    private function alertKey(string $type, ?int $playerId): string
    {
        return $type.':'.($playerId ?? 'game');
    }
}
