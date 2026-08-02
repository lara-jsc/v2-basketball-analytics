<?php

namespace App\Http\Controllers;

use App\Models\LiveGame;
use App\Models\Player;
use App\Models\User;
use App\Services\LiveGame\LiveGameEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LiveGameEventController extends Controller
{
    public function store(Request $request, LiveGame $liveGame, LiveGameEventRecorder $recorder): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $this->validated($request, $liveGame, $user);

        return response()->json($recorder->record($liveGame, $user, $validated));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, LiveGame $game, User $user): array
    {
        $types = [
            'shot_made', 'shot_missed', 'free_throw_made', 'free_throw_missed', 'rebound', 'assist',
            'foul', 'turnover', 'opponent_score', 'substitution', 'timeout', 'correction',
        ];

        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', Rule::in($types)],
            'team_scope' => ['required', 'string', Rule::in(['own', 'opponent', 'game'])],
            'player_id' => ['nullable', 'integer', Rule::exists('players', 'id')],
            'period' => ['nullable', 'integer', 'between:1,4'],
            'clock_seconds_remaining' => ['nullable', 'integer', 'min:0', "max:{$game->period_length_seconds}"],
            'occurred_at' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
            'payload.points' => ['nullable', 'integer'],
            'payload.kind' => ['nullable', 'string'],
            'payload.player_out_id' => ['nullable', 'integer', Rule::exists('players', 'id')],
            'payload.player_in_id' => ['nullable', 'integer', Rule::exists('players', 'id')],
            'voids_event_id' => ['nullable', 'integer'],
        ]);

        $validator->after(function ($validator) use ($game, $user): void {
            $input = $validator->getData();
            $type = $input['type'] ?? null;
            $scope = $input['team_scope'] ?? null;
            $payload = $input['payload'] ?? [];
            $ownPlayerTypes = [
                'shot_made', 'shot_missed', 'free_throw_made', 'free_throw_missed', 'rebound', 'assist', 'foul', 'turnover',
            ];
            $side = $game->sideFor($user);

            if (in_array($type, $ownPlayerTypes, true) || $type === 'substitution') {
                if ($side === null) {
                    $validator->errors()->add('game', 'Only team coaches can record player events for this live game.');
                }
            }

            if (in_array($type, $ownPlayerTypes, true)) {
                $this->requireScope($validator, $scope, 'own');
                $this->requirePlayer($validator, $input);
            }

            if (in_array($type, ['shot_made', 'shot_missed'], true) && ! in_array($payload['points'] ?? null, [2, 3], true)) {
                $validator->errors()->add('payload.points', 'Shot points must be 2 or 3.');
            }

            if ($type === 'rebound' && ! in_array($payload['kind'] ?? null, ['offensive', 'defensive'], true)) {
                $validator->errors()->add('payload.kind', 'Rebound kind must be offensive or defensive.');
            }

            if ($type === 'foul' && isset($payload['kind']) && ! in_array($payload['kind'], ['personal', 'technical', 'flagrant'], true)) {
                $validator->errors()->add('payload.kind', 'Foul kind must be personal, technical, or flagrant.');
            }

            if ($type === 'opponent_score') {
                $this->requireScope($validator, $scope, 'opponent');
                if (isset($input['player_id'])) {
                    $validator->errors()->add('player_id', 'Opponent score events cannot have a player.');
                }
                if (! in_array($payload['points'] ?? null, [1, 2, 3], true)) {
                    $validator->errors()->add('payload.points', 'Opponent score points must be 1, 2, or 3.');
                }
            }

            if ($type === 'substitution') {
                $this->requireScope($validator, $scope, 'game');
                foreach (['player_out_id', 'player_in_id'] as $field) {
                    if (! isset($payload[$field])) {
                        $validator->errors()->add("payload.{$field}", 'This field is required.');
                    }
                }
                if (($payload['player_out_id'] ?? null) === ($payload['player_in_id'] ?? null)) {
                    $validator->errors()->add('payload.player_in_id', 'The incoming player must differ from the outgoing player.');
                }
            }

            if (in_array($type, ['timeout', 'correction'], true)) {
                $this->requireScope($validator, $scope, 'game');
            }

            if ($type === 'correction') {
                $voidedEventId = $input['voids_event_id'] ?? null;
                if (! $voidedEventId) {
                    $validator->errors()->add('voids_event_id', 'A correction must reference the event it voids.');
                } elseif (! $game->events()->whereKey($voidedEventId)->exists()) {
                    $validator->errors()->add('voids_event_id', 'The voided event must belong to this live game.');
                }
            }

            $allowedTeamId = $user->team_id;
            foreach (array_filter([
                $input['player_id'] ?? null,
                $payload['player_out_id'] ?? null,
                $payload['player_in_id'] ?? null,
            ]) as $playerId) {
                $player = Player::query()->find($playerId);
                if ($player === null) {
                    continue;
                }

                $onGameRoster = in_array((int) $player->team_id, [(int) $game->home_team_id, (int) $game->opponent_team_id], true);
                if (! $onGameRoster) {
                    $validator->errors()->add('player_id', 'Players must belong to a team in this live game.');

                    continue;
                }

                if (in_array($type, $ownPlayerTypes, true) || $type === 'substitution') {
                    if ($allowedTeamId === null || (int) $player->team_id !== (int) $allowedTeamId) {
                        $validator->errors()->add('player_id', 'You can only record events for your own team.');
                    }
                }
            }
        });

        return $validator->validate();
    }

    private function requireScope($validator, mixed $scope, string $expectedScope): void
    {
        if ($scope !== $expectedScope) {
            $validator->errors()->add('team_scope', "The team scope must be {$expectedScope}.");
        }
    }

    /** @param array<string, mixed> $input */
    private function requirePlayer($validator, array $input): void
    {
        if (! isset($input['player_id'])) {
            $validator->errors()->add('player_id', 'A player is required for this event.');
        }
    }
}
