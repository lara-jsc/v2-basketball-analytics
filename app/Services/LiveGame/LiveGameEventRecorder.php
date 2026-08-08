<?php

namespace App\Services\LiveGame;

use App\Enums\ShotZone;
use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\LiveGamePlayerDelegation;
use App\Models\LiveGamePlayerStat;
use App\Models\Player;
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

            $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];

            // team_scope 'own' is relative to whoever recorded it, and a timeout carries no
            // player_id, so the client cannot resolve which team called it. Stamp it here.
            if ($input['type'] === 'timeout') {
                $payload['team_id'] = $game->sideFor($user) === LiveGame::SIDE_OPPONENT
                    ? (int) $game->opponent_team_id
                    : (int) $game->home_team_id;
            }

            $event = LiveGameEvent::query()->create([
                'live_game_id' => $game->id,
                'sequence' => (int) $game->events()->max('sequence') + 1,
                'type' => $input['type'],
                'team_scope' => $input['team_scope'],
                'player_id' => $input['player_id'] ?? null,
                'period' => $input['period'] ?? $game->current_period,
                'clock_seconds_remaining' => $input['clock_seconds_remaining'] ?? $this->clockService->effectiveSecondsRemaining($game),
                'occurred_at' => $input['occurred_at'] ?? now(),
                'payload' => $payload,
                'voids_event_id' => $input['voids_event_id'] ?? null,
                'recorded_by_user_id' => $user->id,
            ]);

            if (LiveGameEventRules::stopsClock($input['type']) && $game->clock_running) {
                $this->clockService->stopFor($game);
            }

            if ($game->status === LiveGame::STATUS_FINISHED && $input['type'] === 'correction') {
                $this->finalizer->finalize($game);
            } else {
                $this->projectionService->rebuild($game);
            }

            $snapshot = $this->stateBuilder->build($game);
            // Expose the created event's id so the frontend can open the zone overlay
            // immediately without guessing by max sequence.
            $snapshot['last_recorded_event_id'] = $event->id;

            return $snapshot;
        });

        event(new LiveGameStateUpdated($game->id, $snapshot));

        return $snapshot;
    }

    /**
     * Write-once attach of a shot location. The shot itself is already persisted;
     * this only fills payload.zone when it is absent. Never overwrites, never
     * deletes — corrections still go through voids_event_id.
     *
     * @return array<string, mixed>
     */
    public function attachZone(LiveGame $game, User $user, LiveGameEvent $event, ShotZone $zone): array
    {
        $snapshot = DB::transaction(function () use ($game, $event, $zone): array {
            $game = LiveGame::query()->lockForUpdate()->findOrFail($game->id);
            $event = LiveGameEvent::query()->lockForUpdate()->findOrFail($event->id);

            $payload = is_array($event->payload) ? $event->payload : [];

            if (($payload['zone'] ?? null) !== null) {
                throw ValidationException::withMessages(['zone' => 'This shot already has a location.']);
            }

            $payload['zone'] = $zone->value;
            $event->payload = $payload;
            $event->save();

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
        $controlledPlayerIds = [];

        if ($side !== null) {
            $teamId = $side === LiveGame::SIDE_OPPONENT ? (int) $game->opponent_team_id : (int) $game->home_team_id;
            $mainCoachUserId = $side === LiveGame::SIDE_OPPONENT
                ? $game->opponent_main_coach_user_id
                : $game->home_main_coach_user_id;

            $allActiveRosterPlayerIds = Player::query()
                ->where('team_id', $teamId)
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            if ($mainCoachUserId === null) {
                // Defensive fallback: if main-coach isn't established yet, don't block recordings.
                $controlledPlayerIds = $allActiveRosterPlayerIds;
            } else {
                $delegations = LiveGamePlayerDelegation::query()
                    ->where('live_game_id', $game->id)
                    ->whereIn('player_id', $allActiveRosterPlayerIds)
                    ->get(['coach_user_id', 'player_id']);

                if ((int) $mainCoachUserId === (int) $user->id) {
                    $delegatedAwayPlayerIds = $delegations
                        ->reject(fn ($row) => (int) $row->coach_user_id === (int) $mainCoachUserId)
                        ->pluck('player_id')
                        ->map(fn (mixed $id): int => (int) $id)
                        ->unique()
                        ->values()
                        ->all();

                    $controlledPlayerIds = array_values(array_diff($allActiveRosterPlayerIds, $delegatedAwayPlayerIds));
                } else {
                    $controlledPlayerIds = $delegations
                        ->where('coach_user_id', $user->id)
                        ->pluck('player_id')
                        ->map(fn (mixed $id): int => (int) $id)
                        ->values()
                        ->all();
                }
            }
        }

        if ($game->status === LiveGame::STATUS_SETUP) {
            $errors['game'][] = 'Events cannot be recorded until the game is live.';
        }

        if ($game->status === LiveGame::STATUS_FINISHED && $type !== 'correction') {
            $errors['game'][] = 'Only correction events can be recorded after the game is finished.';
        }

        if ($game->status === LiveGame::STATUS_LIVE && is_string($type)) {
            $periodExpired = $this->clockService->effectiveSecondsRemaining($game) === 0;

            if ($periodExpired && ! LiveGameEventRules::isAllowedAtPeriodEnd($type)) {
                $errors['game'][] = "Q{$game->current_period} has ended. Advance the period before recording.";
            } elseif ($game->clock_running && ! LiveGameEventRules::isAllowedWhileClockRunning($type)) {
                $errors['game'][] = 'This is recorded with the clock stopped. Stop the clock first.';
            } elseif (! $game->clock_running && ! LiveGameEventRules::isAllowedWhileClockStopped($type)) {
                $errors['game'][] = 'This is recorded with the clock running. Start the clock first.';
            }
        }

        if ($type === 'foul' && $side !== null) {
            $foulPayload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
            $foulKind = $foulPayload['kind'] ?? 'personal';
            $fouledPlayerId = (int) ($input['player_id'] ?? 0);

            if ($foulKind === 'personal' && $fouledPlayerId > 0) {
                $personalFouls = (int) LiveGamePlayerStat::query()
                    ->where('live_game_id', $game->id)
                    ->where('player_id', $fouledPlayerId)
                    ->value('personal_fouls');

                if ($personalFouls >= LiveGameEventRules::MAX_PERSONAL_FOULS) {
                    $errors['player_id'][] = sprintf(
                        'This player already has %d personal fouls and is disqualified.',
                        LiveGameEventRules::MAX_PERSONAL_FOULS,
                    );
                }
            }
        }

        if ($type === 'correction') {
            $voidsEventId = $input['voids_event_id'] ?? null;

            $alreadyVoided = $voidsEventId !== null && $game->events()
                ->where('type', 'correction')
                ->where('voids_event_id', $voidsEventId)
                ->exists();

            if ($alreadyVoided) {
                $errors['voids_event_id'][] = 'That event has already been voided.';
            }
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

            if ($playerOutId && ! in_array((int) $playerOutId, $controlledPlayerIds, true)) {
                $errors['payload.player_out_id'][] = 'You can only substitute players assigned to you.';
            }

            if ($playerInId && in_array((int) $playerInId, $activePlayerIds, true)) {
                $errors['payload.player_in_id'][] = 'The incoming player must be inactive.';
            }

            if ($playerInId && ! in_array((int) $playerInId, $controlledPlayerIds, true)) {
                $errors['payload.player_in_id'][] = 'You can only substitute players assigned to you.';
            }
        }

        $ownPlayerEventTypes = [
            'shot_made', 'shot_missed', 'free_throw_made', 'free_throw_missed', 'rebound', 'assist', 'foul', 'turnover',
        ];
        $shotTypes = ['shot_made', 'shot_missed'];
        if (($input['team_scope'] ?? null) === 'own' && in_array($type, $ownPlayerEventTypes, true)) {
            if ($side === null) {
                $errors['game'][] = 'Only team coaches can record player events for this live game.';
            } else {
                $playerId = (int) ($input['player_id'] ?? 0);
                $playerSide = $game->sideForPlayer($playerId);

                if ($playerSide === $side) {
                    // Own-team player — standard active + control checks.
                    $activePlayerIds = $game->activePlayerIdsForSide($side);

                    if (! in_array($playerId, $activePlayerIds, true)) {
                        $errors['player_id'][] = 'The player must be active to record this event.';
                    }

                    if (! in_array($playerId, $controlledPlayerIds, true)) {
                        $errors['player_id'][] = 'You can only record events for players assigned to you.';
                    }
                } elseif (in_array($type, $shotTypes, true) && $playerSide !== null) {
                    // Opponent player — shots only. Verify active on the opponent side and that the
                    // recorder controls this player (head for undelegated, assistant via delegation).
                    $activeOpponentIds = $game->activePlayerIdsForSide($playerSide);

                    if (! in_array($playerId, $activeOpponentIds, true)) {
                        $errors['player_id'][] = 'The player must be active to record this event.';
                    }

                    if (! $this->controlsOpponentPlayer($game, $user, $playerId)) {
                        $errors['player_id'][] = 'You can only record events for players assigned to you.';
                    }
                } else {
                    // Non-shot event on an opponent player — always refused.
                    $errors['player_id'][] = 'You can only record events for your own team.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Whether the given user controls an opponent player for shot-recording purposes.
     *
     * Head coach controls all opponent players NOT exclusively delegated to an assistant.
     * Assistants control only players with an explicit LiveGamePlayerDelegation row.
     */
    private function controlsOpponentPlayer(LiveGame $game, User $user, int $playerId): bool
    {
        $userSide = $game->sideFor($user);
        if ($userSide === null) {
            return false;
        }

        $mainCoachId = $userSide === LiveGame::SIDE_HOME
            ? $game->home_main_coach_user_id
            : $game->opponent_main_coach_user_id;

        if ($mainCoachId !== null && (int) $mainCoachId === (int) $user->id) {
            // Head coach controls undelegated opponent players.
            return ! LiveGamePlayerDelegation::query()
                ->where('live_game_id', $game->id)
                ->where('player_id', $playerId)
                ->where('coach_user_id', '!=', $user->id)
                ->exists();
        }

        // Assistants need an explicit delegation row.
        return LiveGamePlayerDelegation::query()
            ->where('live_game_id', $game->id)
            ->where('player_id', $playerId)
            ->where('coach_user_id', $user->id)
            ->exists();
    }
}
