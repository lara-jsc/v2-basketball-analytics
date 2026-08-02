<?php

namespace App\Services\LiveGame;

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiveGameClockService
{
    public function __construct(
        private readonly LiveGameProjectionService $projectionService,
        private readonly LiveGameStateBuilder $stateBuilder,
    ) {}

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function handle(LiveGame $game, User $user, array $input): array
    {
        if (! $game->isCreator($user)) {
            throw ValidationException::withMessages([
                'game' => 'Only the game creator can control the clock.',
            ]);
        }

        $snapshot = DB::transaction(function () use ($game, $input): array {
            $game = LiveGame::query()->lockForUpdate()->findOrFail($game->id);

            if ($game->status === LiveGame::STATUS_FINISHED) {
                throw ValidationException::withMessages([
                    'game' => 'Clock actions are not available after the game is finished.',
                ]);
            }

            match ($input['action'] ?? null) {
                'start' => $this->start($game),
                'stop' => $this->stop($game),
                'set_period' => $this->setPeriod($game, $input),
                'reset_period' => $this->resetPeriod($game),
                default => throw ValidationException::withMessages(['action' => 'The clock action is invalid.']),
            };

            $this->projectionService->rebuild($game);

            return $this->stateBuilder->build($game);
        });

        event(new LiveGameStateUpdated($game->id, $snapshot));

        return $snapshot;
    }

    public function effectiveSecondsRemaining(LiveGame $game): int
    {
        if (! $game->clock_running || $game->clock_started_at === null) {
            return $game->clock_seconds_remaining;
        }

        return max(0, $game->clock_seconds_remaining - $game->clock_started_at->diffInSeconds(now()));
    }

    private function start(LiveGame $game): void
    {
        if ($game->clock_running) {
            $this->refreshElapsedClock($game);

            return;
        }

        $remaining = $this->refreshElapsedClock($game);

        if ($remaining === 0) {
            return;
        }

        $game->forceFill([
            'clock_running' => true,
            'clock_started_at' => now(),
            'status' => $game->status === LiveGame::STATUS_SETUP ? LiveGame::STATUS_LIVE : $game->status,
            'started_at' => $game->started_at ?? now(),
            'game_date' => $game->started_at === null ? now()->toDateString() : $game->game_date,
        ])->save();
    }

    private function stop(LiveGame $game): void
    {
        $remaining = $this->refreshElapsedClock($game);

        $game->forceFill([
            'clock_seconds_remaining' => $remaining,
            'clock_running' => false,
            'clock_started_at' => null,
        ])->save();
    }

    /** @param array<string, mixed> $input */
    private function setPeriod(LiveGame $game, array $input): void
    {
        $period = (int) ($input['period'] ?? 0);

        if ($period < 1 || $period > 4 || $period < $game->current_period) {
            throw ValidationException::withMessages([
                'period' => 'The period must be between 1 and 4 and cannot move backward.',
            ]);
        }

        $game->forceFill([
            'current_period' => $period,
            'clock_seconds_remaining' => isset($input['clock_seconds_remaining'])
                ? (int) $input['clock_seconds_remaining']
                : $game->period_length_seconds,
            'clock_running' => false,
            'clock_started_at' => null,
        ])->save();
    }

    private function resetPeriod(LiveGame $game): void
    {
        $game->forceFill([
            'clock_seconds_remaining' => $game->period_length_seconds,
            'clock_running' => false,
            'clock_started_at' => null,
        ])->save();
    }

    private function refreshElapsedClock(LiveGame $game): int
    {
        $remaining = $this->effectiveSecondsRemaining($game);

        if ($game->clock_running && $remaining === 0) {
            $game->forceFill([
                'clock_seconds_remaining' => 0,
                'clock_running' => false,
                'clock_started_at' => null,
            ])->save();
        }

        return $remaining;
    }
}
