<?php

namespace App\Jobs;

use App\Models\LiveGame;
use App\Models\Player;
use App\Models\User;
use App\Repositories\ComparisonRepository;
use App\Services\LiveGame\LiveGameControlResolver;
use App\Services\LiveGame\LiveLineupEligibility;
use App\Services\LiveGame\LiveLineupEligibilityFilter;
use App\Services\LiveGame\LiveLineupPayloadBuilder;
use App\Services\LiveGame\LiveLineupSuggestionService;
use App\Services\PythonEngineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Ranks the slots one coach can fill, twice, with the same Python `lineup` command: once
 * on season averages, once on tonight's per-36 box score.
 *
 * Two calls rather than one blended call is the whole design. The columns disagree
 * often, and that disagreement is the information the coach actually wants — collapsing
 * it into a single number would require an exchange rate between a 20-game rating and an
 * 8-minute sample that nothing in the data justifies.
 *
 * The payload is scoped to the players this coach controls, so the job is per-coach, not
 * per-bench. It has to be: `analytics/lineup_optimizer/ranker.py` returns only its top 5,
 * so a bench-wide ranking cannot be sliced per coach afterwards — the coach's best
 * available player might rank sixth and never appear at all.
 */
class RecommendLiveLineup implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $liveGameId,
        public readonly int $teamId,
        public readonly int $coachUserId,
        public readonly int $sequence,
    ) {}

    public function handle(
        ComparisonRepository $comparisonRepository,
        LiveGameControlResolver $controlResolver,
        LiveLineupEligibilityFilter $eligibilityFilter,
        LiveLineupPayloadBuilder $payloadBuilder,
        PythonEngineService $engine,
        LiveLineupSuggestionService $suggestions,
    ): void {
        $game = LiveGame::query()->find($this->liveGameId);
        $coach = User::query()->find($this->coachUserId);

        if (! $game instanceof LiveGame || ! $coach instanceof User) {
            return;
        }

        $roster = $comparisonRepository->activPlayersWithStats($this->teamId);

        // Reuse the resolver rather than re-deriving delegation arithmetic — the controller
        // must reach the identical answer or the coach gets offered a change they cannot make.
        $control = $controlResolver->resolve($game, $coach, $roster);

        if ($control->side === null) {
            return;
        }

        $eligibility = $eligibilityFilter->filter($game, $control->side, $roster, $control->controlledPlayerIds);
        $slotCount = $eligibility->slotCount();

        if ($slotCount === 0 || $eligibility->eligiblePlayerIds() === []) {
            $suggestions->store($this->liveGameId, $this->teamId, $this->coachUserId, $this->sequence, [
                'season' => $this->emptyColumn(),
                'tonight' => $this->emptyColumn(),
            ]);

            return;
        }

        $opponentTeamId = (int) $game->home_team_id === $this->teamId
            ? (int) $game->opponent_team_id
            : (int) $game->home_team_id;

        $opponents = $comparisonRepository->activPlayersWithStats($opponentTeamId);
        $rankable = $this->only($roster, $eligibility->rankablePlayerIds);

        try {
            $season = $engine->call('lineup', [
                'home_team_players' => $payloadBuilder->season($rankable),
                'opponent_team_players' => $payloadBuilder->season($opponents),
            ]);

            $tonight = $engine->call('lineup', [
                'home_team_players' => $payloadBuilder->tonight($game, $rankable),
                'opponent_team_players' => $payloadBuilder->tonight($game, $opponents),
            ]);
        } catch (RuntimeException $e) {
            // Same posture as RecommendLineup: leave the cache empty rather than break the
            // console. The next poll re-dispatches.
            Log::error('RecommendLiveLineup: engine error', [
                'live_game' => $this->liveGameId,
                'team' => $this->teamId,
                'coach' => $this->coachUserId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $suggestions->store($this->liveGameId, $this->teamId, $this->coachUserId, $this->sequence, [
            'season' => $this->shaped($season, $eligibility, $roster, $slotCount),
            'tonight' => $this->shaped($tonight, $eligibility, $roster, $slotCount),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('RecommendLiveLineup job failed', [
            'live_game' => $this->liveGameId,
            'team' => $this->teamId,
            'coach' => $this->coachUserId,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Trim the ranker's five down to the coach's open slots, backfilling from the demoted
     * pool if the ranked candidates don't reach that far.
     *
     * A player in foul trouble is only ever offered when there aren't enough healthier
     * bodies — which is what "demoted to last" should mean in practice. They carry a null
     * score because they were never ranked, only drafted to fill a hole.
     *
     * @param  array<string, mixed>  $result
     * @param  Collection<int, Player>  $roster
     * @return array<string, mixed>
     */
    private function shaped(array $result, LiveLineupEligibility $eligibility, Collection $roster, int $slotCount): array
    {
        /** @var list<array<string, mixed>> $lineup */
        $lineup = array_slice($result['recommended_lineup'] ?? [], 0, $slotCount);

        $chosenIds = array_map(
            static fn (array $row): int => (int) ($row['player_id'] ?? 0),
            $lineup,
        );

        foreach ($this->only($roster, $eligibility->demotedPlayerIds) as $player) {
            if (count($lineup) >= $slotCount) {
                break;
            }

            if (in_array((int) $player->id, $chosenIds, true)) {
                continue;
            }

            $lineup[] = [
                'player_id' => (int) $player->id,
                'name' => "{$player->first_name} {$player->last_name}",
                'plus_minus_score' => null,
            ];
        }

        return [
            'recommended_lineup' => $lineup,
            'confidence' => $result['confidence'] ?? 0.0,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyColumn(): array
    {
        return ['recommended_lineup' => [], 'confidence' => 0.0];
    }

    /**
     * @param  Collection<int, Player>  $roster
     * @param  list<int>  $playerIds
     * @return Collection<int, Player>
     */
    private function only(Collection $roster, array $playerIds): Collection
    {
        return $roster->filter(
            fn (Player $player): bool => in_array((int) $player->id, $playerIds, true),
        )->values();
    }
}
