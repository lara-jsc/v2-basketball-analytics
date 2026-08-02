<?php

namespace App\Services\LiveGame;

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LiveGameEventRecorder
{
    public function __construct(
        private readonly LiveGameProjectionService $projectionService,
        private readonly LiveGameStateBuilder $stateBuilder,
    ) {}

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function record(LiveGame $game, User $user, array $input): array
    {
        $snapshot = DB::transaction(function () use ($game, $user, $input): array {
            $game = LiveGame::query()->lockForUpdate()->findOrFail($game->id);

            LiveGameEvent::query()->create([
                'live_game_id' => $game->id,
                'sequence' => (int) $game->events()->max('sequence') + 1,
                'type' => $input['type'],
                'team_scope' => $input['team_scope'],
                'player_id' => $input['player_id'] ?? null,
                'period' => $input['period'] ?? $game->current_period,
                'clock_seconds_remaining' => $input['clock_seconds_remaining'] ?? $game->clock_seconds_remaining,
                'occurred_at' => $input['occurred_at'] ?? now(),
                'payload' => $input['payload'] ?? [],
                'voids_event_id' => $input['voids_event_id'] ?? null,
                'recorded_by_user_id' => $user->id,
            ]);

            $this->projectionService->rebuild($game);

            return $this->stateBuilder->build($game);
        });

        event(new LiveGameStateUpdated($game->id, $snapshot));

        return $snapshot;
    }
}
