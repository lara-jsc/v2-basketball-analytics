<?php

namespace App\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGamePlayerStat;
use App\Models\Player;
use App\Services\ComparisonAggregatorService;
use Illuminate\Support\Collection;

/**
 * Builds the two per-player payloads the ranker consumes.
 *
 * Both use the identical key shape, because both are fed to the same Python `lineup`
 * command with the same weights. The only difference is the sample: season averages for
 * one, tonight's box score for the other. That is the whole point — one documented
 * ranking function, two bodies of evidence, no blend constant to defend.
 */
class LiveLineupPayloadBuilder
{
    /**
     * Below this, per-36 extrapolation is nonsense: four points in three minutes projects
     * to forty-eight. Players under the floor are omitted rather than ranked on noise,
     * which is why the tonight column is legitimately sparse early in a game.
     */
    public const MIN_LIVE_SECONDS = 240;

    public function __construct(
        private readonly ComparisonAggregatorService $aggregator,
    ) {}

    /**
     * Season averages, straight from the stored player_stats row.
     *
     * @param  Collection<int, Player>  $players  With the `stats` relation loaded.
     * @return list<array<string, mixed>>
     */
    public function season(Collection $players): array
    {
        return $this->aggregator->toEnginePayload($players);
    }

    /**
     * Tonight's box score, normalised to per-36-minute rates so a starter's totals do not
     * simply outrank a reserve's for having played longer.
     *
     * @param  Collection<int, Player>  $players
     * @return list<array<string, mixed>>
     */
    public function tonight(LiveGame $game, Collection $players): array
    {
        $stats = $this->liveStatsByPlayer($game, $players);
        $rows = [];

        foreach ($players as $player) {
            $stat = $stats[(int) $player->id] ?? null;

            if (! $stat instanceof LiveGamePlayerStat || $stat->minutes_seconds < self::MIN_LIVE_SECONDS) {
                continue;
            }

            $minutes = $stat->minutes_seconds / 60;
            $per36 = 36 / $minutes;

            $rows[] = [
                'player_id' => $player->id,
                'name' => "{$player->first_name} {$player->last_name}",
                'pts' => $this->rate($stat->points, $per36),
                'reb' => $this->rate($stat->rebounds, $per36),
                'ast' => $this->rate($stat->assists, $per36),
                'blk' => $this->rate($stat->blocks, $per36),
                'stl' => $this->rate($stat->steals, $per36),
                // Already a ratio, so scaling it would be wrong. Matches player_stats,
                // where fg_pct is stored as a 0–1 fraction.
                'fg_pct' => $stat->field_goals_attempted > 0
                    ? round($stat->field_goals_made / $stat->field_goals_attempted, 4)
                    : 0.0,
                'to_per_game' => $this->rate($stat->turnovers, $per36),
                'min' => round($minutes, 2),
                'plus_minus' => $stat->plus_minus ?? 0,
            ];
        }

        return $rows;
    }

    private function rate(int $total, float $per36): float
    {
        return round($total * $per36, 2);
    }

    /**
     * @param  Collection<int, Player>  $players
     * @return array<int, LiveGamePlayerStat>
     */
    private function liveStatsByPlayer(LiveGame $game, Collection $players): array
    {
        $playerIds = $players->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($playerIds === []) {
            return [];
        }

        return LiveGamePlayerStat::query()
            ->where('live_game_id', $game->id)
            ->whereIn('player_id', $playerIds)
            ->get()
            ->keyBy(fn (LiveGamePlayerStat $stat): int => (int) $stat->player_id)
            ->all();
    }
}
