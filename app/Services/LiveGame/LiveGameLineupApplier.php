<?php

namespace App\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiveGameLineupApplier
{
    public function __construct(
        private readonly LiveGameEventRecorder $recorder,
        private readonly LiveGameClockService $clockService,
        private readonly LiveGameStateBuilder $stateBuilder,
    ) {}

    /**
     * Swap the on-court five for the chosen five as a batch of substitutions.
     *
     * @param  list<int>  $playerIds  The five players who should end up on the floor.
     * @return array<string, mixed> The resulting snapshot, plus what was applied.
     */
    public function apply(LiveGame $game, User $user, LiveGameControl $control, array $playerIds): array
    {
        $side = $control->side;

        if ($side === null) {
            throw ValidationException::withMessages([
                'game' => 'Only team coaches can apply a lineup.',
            ]);
        }

        $active = $game->activePlayerIdsForSide($side);
        $chosen = array_values(array_unique(array_map('intval', $playerIds)));

        $outs = array_values(array_diff($active, $chosen));
        $ins = array_values(array_diff($chosen, $active));

        $this->assertApplicable($game, $control, $chosen, $outs, $ins);

        if ($outs === []) {
            return [
                'applied' => [],
                'snapshot' => $this->stateBuilder->build($game),
            ];
        }

        // One transaction so a rejected pair cannot leave a half-changed lineup on the
        // floor. The recorder rebuilds the projection and broadcasts per call, so an
        // N-for-N swap does N rebuilds — wasteful, but it keeps every existing
        // substitution validation in the path instead of duplicating it here.
        $result = DB::transaction(function () use ($game, $user, $outs, $ins): array {
            $applied = [];
            $snapshot = [];

            foreach ($outs as $index => $playerOutId) {
                $playerInId = $ins[$index];

                $snapshot = $this->recorder->record($game, $user, [
                    'type' => 'substitution',
                    'team_scope' => 'game',
                    'payload' => [
                        'player_out_id' => $playerOutId,
                        'player_in_id' => $playerInId,
                    ],
                ]);

                $applied[] = ['player_out_id' => $playerOutId, 'player_in_id' => $playerInId];
            }

            return ['applied' => $applied, 'snapshot' => $snapshot];
        });

        return $result;
    }

    /**
     * Everything the recorder would reject, checked up front.
     *
     * The recorder validates each pair as it goes, but by then the earlier pairs are
     * already written. Failing before the first write means the coach gets one clear
     * message instead of a partially-swapped lineup and a rollback.
     *
     * @param  list<int>  $chosen
     * @param  list<int>  $outs
     * @param  list<int>  $ins
     */
    private function assertApplicable(LiveGame $game, LiveGameControl $control, array $chosen, array $outs, array $ins): void
    {
        $errors = [];

        if ($game->status !== LiveGame::STATUS_LIVE) {
            $errors['game'][] = 'Lineups can only be applied while the game is live.';
        }

        if ($game->clock_running) {
            $errors['game'][] = 'Substitutions are recorded with the clock stopped. Stop the clock first.';
        }

        if ($game->status === LiveGame::STATUS_LIVE && $this->clockService->effectiveSecondsRemaining($game) === 0) {
            $errors['game'][] = "Q{$game->current_period} has ended. Advance the period before substituting.";
        }

        if (count($chosen) !== 5) {
            $errors['player_ids'][] = 'A lineup must be exactly five distinct players.';
        }

        if (count($outs) !== count($ins)) {
            $errors['player_ids'][] = 'The lineup change is unbalanced. Refresh and try again.';
        }

        $rosterPlayerIds = Player::query()
            ->where('team_id', $control->teamId)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        foreach ($chosen as $playerId) {
            if (! in_array($playerId, $rosterPlayerIds, true)) {
                $errors['player_ids'][] = 'Every player must be on your active roster.';
                break;
            }
        }

        // Locked players are shown in the suggestion on purpose, so a coach can see what
        // the system wants. Applying them is what they cannot do.
        foreach (array_merge($outs, $ins) as $playerId) {
            if (! in_array($playerId, $control->controlledPlayerIds, true)) {
                $errors['player_ids'][] = 'This change includes players assigned to another coach.';
                break;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
