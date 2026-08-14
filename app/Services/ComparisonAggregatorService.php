<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds aggregate team stats and team plus-minus from stored player data.
 * Pure PHP — no Python calls. All values come from player_stats rows.
 *
 * Team Plus-Minus spec:
 *   Minutes-weighted average of is_active=true players' stored plus_minus.
 *   Returns null if no players have a computed plus_minus yet.
 */
class ComparisonAggregatorService
{
    /**
     * Compute aggregate stats for a team's active players.
     * Returns averages suitable for the win_probability Python contract.
     *
     * @param  Collection<int, Player>  $players
     * @return array<string, float>
     */
    public function aggregateStats(Collection $players): array
    {
        $stats = $players
            ->map(fn (Player $p) => $p->stats->first())
            ->filter();

        $count = $stats->count();

        if ($count === 0) {
            return $this->emptyAggregates();
        }

        return [
            'avg_pts' => round($stats->avg('pts') ?? 0, 2),
            'avg_reb' => round($stats->avg('reb') ?? 0, 2),
            'avg_ast' => round($stats->avg('ast') ?? 0, 2),
            'avg_fg_pct' => round($stats->avg('fg_pct') ?? 0, 2),
            'avg_blk' => round($stats->avg('blk') ?? 0, 2),
            'avg_stl' => round($stats->avg('stl') ?? 0, 2),
            'avg_to_per_game' => round($stats->avg('to_per_game') ?? 0, 2),
        ];
    }

    /**
     * Compute team plus-minus: minutes-weighted average of active players' plus_minus.
     * Returns null when no player has a computed plus_minus yet.
     *
     * @param  Collection<int, Player>  $players
     */
    public function teamPlusMinus(Collection $players): ?float
    {
        $eligible = $players
            ->map(fn (Player $p) => $p->stats->first())
            ->filter(fn ($s) => $s !== null && $s->plus_minus !== null && $s->min > 0);

        if ($eligible->isEmpty()) {
            return null;
        }

        $totalMinutes = $eligible->sum('min');
        $weightedSum = $eligible->sum(fn ($s) => $s->plus_minus * $s->min);

        return round($weightedSum / $totalMinutes, 2);
    }

    /**
     * Serialize active players into the payload format expected by the Python engine.
     *
     * @param  Collection<int, Player>  $players
     * @return list<array<string, mixed>>
     */
    public function toEnginePayload(Collection $players): array
    {
        return $players->map(function (Player $p): array {
            $s = $p->stats->first();

            return [
                'player_id' => $p->id,
                'name' => "{$p->first_name} {$p->last_name}",
                'pts' => $s?->pts ?? 0,
                'reb' => $s?->reb ?? 0,
                'ast' => $s?->ast ?? 0,
                'blk' => $s?->blk ?? 0,
                'stl' => $s?->stl ?? 0,
                'fg_pct' => $s?->fg_pct ?? 0,
                'to_per_game' => $s?->to_per_game ?? 0,
                'min' => $s?->min ?? 0,
                'plus_minus' => $s?->plus_minus ?? 0,
            ];
        })->values()->all();
    }

    /** @return array<string, float> */
    private function emptyAggregates(): array
    {
        return [
            'avg_pts' => 0.0,
            'avg_reb' => 0.0,
            'avg_ast' => 0.0,
            'avg_fg_pct' => 0.0,
            'avg_blk' => 0.0,
            'avg_stl' => 0.0,
            'avg_to_per_game' => 0.0,
        ];
    }
}
