<?php

namespace App\Services\LiveGame;

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiveGameEventRecorder
{
    public function __construct(
        private readonly LiveGameProjectionService $projectionService,
        private readonly LiveGameFinalizer $finalizer,
        private readonly LiveGameStateBuilder $stateBuilder,
        private readonly LiveGameClockService $clockService,
    ) {}

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function record(LiveGame $game, User $user, array $input): array
    {
        $snapshot = DB::transaction(function () use ($game, $user, $input): array {
            $game = LiveGame::query()->lockForUpdate()->findOrFail($game->id);

            $this->validateRecording($game, $user, $input);

            LiveGameEvent::query()->create([
                'live_game_id' => $game->id,
                'sequence' => (int) $game->events()->max('sequence') + 1,
                'type' => $input['type'],
                'team_scope' => $input['team_scope'],
                'player_id' => $input['player_id'] ?? null,
                'period' => $input['period'] ?? $game->current_period,
                'clock_seconds_remaining' => $input['clock_seconds_remaining'] ?? $this->clockService->effectiveSecondsRemaining($game),
                'occurred_at' => $input['occurred_at'] ?? now(),
                'payload' => $input['payload'] ?? [],
                'voids_event_id' => $input['voids_event_id'] ?? null,
                'recorded_by_user_id' => $user->id,
            ]);

            if ($game->status === LiveGame::STATUS_FINISHED && $input['type'] === 'correction') {
                $this->finalizer->finalize($game);
            } else {
                $this->projectionService->rebuild($game);
            }

            return $this->stateBuilder->build($game);
        });

        event(new LiveGameStateUpdated($game->id, $snapshot));

        return $snapshot;
    }

    /** @param array<string, mixed> $input */
    private function validateRecording(LiveGame $game, User $user, array $input): void
    {
        $errors = [];
        $type = $input['type'] ?? null;
        $side = $game->sideFor($user);

        if ($game->status === LiveGame::STATUS_SETUP) {
            $errors['game'][] = 'Events cannot be recorded until the game is live.';
        }

        if ($game->status === LiveGame::STATUS_FINISHED && $type !== 'correction') {
            $errors['game'][] = 'Only correction events can be recorded after the game is finished.';
        }

        if ($type === 'substitution') {
            $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
            $playerOutId = $payload['player_out_id'] ?? null;
            $playerInId = $payload['player_in_id'] ?? null;

            if ($side === null) {
                $errors['game'][] = 'Only team coaches can record substitutions for this live game.';
            }

            if (! $playerOutId) {
                $errors['payload.player_out_id'][] = 'The outgoing player is required.';
            }

            if (! $playerInId) {
                $errors['payload.player_in_id'][] = 'The incoming player is required.';
            }

            if ($playerOutId && $playerInId && (int) $playerOutId === (int) $playerInId) {
                $errors['payload.player_in_id'][] = 'The incoming player must differ from the outgoing player.';
            }

            $activePlayerIds = $side !== null ? $game->activePlayerIdsForSide($side) : [];

            if ($playerOutId && ! in_array((int) $playerOutId, $activePlayerIds, true)) {
                $errors['payload.player_out_id'][] = 'The outgoing player must be active.';
            }

            if ($playerInId && in_array((int) $playerInId, $activePlayerIds, true)) {
                $errors['payload.player_in_id'][] = 'The incoming player must be inactive.';
            }
        }

        $ownPlayerEventTypes = [
            'shot_made', 'shot_missed', 'free_throw_made', 'free_throw_missed', 'rebound', 'assist', 'foul', 'turnover',
        ];
        if (($input['team_scope'] ?? null) === 'own' && in_array($type, $ownPlayerEventTypes, true)) {
            if ($side === null) {
                $errors['game'][] = 'Only team coaches can record player events for this live game.';
            } else {
                $activePlayerIds = $game->activePlayerIdsForSide($side);

                if (! in_array((int) ($input['player_id'] ?? 0), $activePlayerIds, true)) {
                    $errors['player_id'][] = 'The player must be active to record this event.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
