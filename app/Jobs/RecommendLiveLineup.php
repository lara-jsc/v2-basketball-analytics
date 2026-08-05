<?php

namespace App\Jobs;

use App\Models\LiveGame;
use App\Models\Player;
use App\Repositories\ComparisonRepository;
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
 * Ranks one bench twice with the same Python `lineup` command: once on season averages,
 * once on tonight's per-36 box score.
 *
 * Two calls rather than one blended call is the whole design. The columns disagree
 * often, and that disagreement is the information the coach actually wants — collapsing
 * it into a single number would require an exchange rate between a 20-game rating and an
 * 8-minute sample that nothing in the data justifies.
 */
class RecommendLiveLineup implements ShouldQueue
{
    use Queueable;

    private const LINEUP_SIZE = 5;

    public function __construct(
        public readonly int $liveGameId,
        public readonly int $teamId,
        public readonly int $sequence,
    ) {}

    public function handle(
        ComparisonRepository $comparisonRepository,
        LiveLineupEligibilityFilter $eligibilityFilter,
        LiveLineupPayloadBuilder $payloadBuilder,
        PythonEngineService $engine,
        LiveLineupSuggestionService $suggestions,
    ): void {
        $game = LiveGame::query()->find($this->liveGameId);

        if (! $game instanceof LiveGame) {
            return;
        }

        $opponentTeamId = (int) $game->home_team_id === $this->teamId
            ? (int) $game->opponent_team_id
            : (int) $game->home_team_id;

        $roster = $comparisonRepository->activPlayersWithStats($this->teamId);
        $opponents = $comparisonRepository->activPlayersWithStats($opponentTeamId);

        // Locking is presentational and per-user, so ranking treats the whole roster as
        // controlled. The controller re-derives the real locks for the viewer.
        $eligibility = $eligibilityFilter->filter(
            $game,
            $roster,
            $roster->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
        );

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
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $suggestions->store($this->liveGameId, $this->teamId, $this->sequence, [
            'season' => $this->backfilled($season, $eligibility, $roster),
            'tonight' => $this->backfilled($tonight, $eligibility, $roster),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('RecommendLiveLineup job failed', [
            'live_game' => $this->liveGameId,
            'team' => $this->teamId,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Top up a short lineup from the demoted pool.
     *
     * A player in foul trouble is only ever offered when there aren't five healthier
     * bodies — which is exactly what "demoted to last" should mean in practice. They
     * carry a null score because they were never ranked, only drafted to fill a hole.
     *
     * @param  array<string, mixed>  $result
     * @param  Collection<int, Player>  $roster
     * @return array<string, mixed>
     */
    private function backfilled(array $result, LiveLineupEligibility $eligibility, Collection $roster): array
    {
        /** @var list<array<string, mixed>> $lineup */
        $lineup = $result['recommended_lineup'] ?? [];
        $shortfall = self::LINEUP_SIZE - count($lineup);

        if ($shortfall <= 0 || $eligibility->demotedPlayerIds === []) {
            return [
                'recommended_lineup' => $lineup,
                'confidence' => $result['confidence'] ?? 0.0,
            ];
        }

        $chosenIds = array_map(
            static fn (array $row): int => (int) ($row['player_id'] ?? 0),
            $lineup,
        );

        foreach ($this->only($roster, $eligibility->demotedPlayerIds) as $player) {
            if ($shortfall <= 0) {
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
            $shortfall--;
        }

        return [
            'recommended_lineup' => $lineup,
            'confidence' => $result['confidence'] ?? 0.0,
        ];
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
