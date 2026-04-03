<?php

namespace App\Services;

use App\Jobs\RecommendLineup;
use Illuminate\Support\Facades\Cache;

/**
 * Manages lineup recommendation results for a home vs opponent matchup.
 *
 * Cache key is NOT symmetric — home team matters for lineup selection.
 * Invalidated via team cache version (same mechanism as WinProbabilityService).
 */
class LineupService
{
    private const TTL_HOURS = 24;

    /**
     * Return the cached lineup, or dispatch the job and return null.
     *
     * @return array<string, mixed>|null
     */
    public function getOrDispatch(int $homeTeamId, int $opponentTeamId): ?array
    {
        $key = $this->cacheKey($homeTeamId, $opponentTeamId);

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        RecommendLineup::dispatch($homeTeamId, $opponentTeamId);

        return null;
    }

    /**
     * Store the computed lineup. Called by the Job on completion.
     *
     * @param  array<string, mixed>  $result
     */
    public function store(int $homeTeamId, int $opponentTeamId, array $result): void
    {
        Cache::put($this->cacheKey($homeTeamId, $opponentTeamId), $result, now()->addHours(self::TTL_HOURS));
    }

    private function cacheKey(int $homeTeamId, int $opponentTeamId): string
    {
        $vHome = (int) Cache::get("team.{$homeTeamId}.cache_version", 0);
        $vOpp  = (int) Cache::get("team.{$opponentTeamId}.cache_version", 0);

        return "lineup.{$homeTeamId}.{$opponentTeamId}.v{$vHome}.{$vOpp}";
    }
}
