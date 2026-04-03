<?php

namespace App\Services;

use App\Jobs\ComputeWinProbability;
use Illuminate\Support\Facades\Cache;

/**
 * Manages win probability results for a team matchup.
 *
 * Cache strategy: version-based keys.
 *   Key: "win_prob.{minId}.{maxId}.v{vA}.{vB}"
 *   Version increments on each CSV upload → old keys orphaned, expire via TTL.
 */
class WinProbabilityService
{
    private const TTL_HOURS = 24;

    /**
     * Return the cached win probability result, or dispatch the job and return null.
     *
     * @return array<string, float>|null
     */
    public function getOrDispatch(int $teamAId, int $teamBId): ?array
    {
        $key = $this->cacheKey($teamAId, $teamBId);

        /** @var array<string, float>|null $cached */
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        ComputeWinProbability::dispatch($teamAId, $teamBId);

        return null;
    }

    /**
     * Store the computed result. Called by the Job on completion.
     *
     * @param  array<string, float>  $result
     */
    public function store(int $teamAId, int $teamBId, array $result): void
    {
        Cache::put($this->cacheKey($teamAId, $teamBId), $result, now()->addHours(self::TTL_HOURS));
    }

    /** Increment the team's cache version, orphaning all existing matchup caches. */
    public function invalidateForTeam(int $teamId): void
    {
        Cache::increment("team.{$teamId}.cache_version");
    }

    private function cacheKey(int $teamAId, int $teamBId): string
    {
        // Sort IDs so A vs B == B vs A
        [$lo, $hi] = $teamAId < $teamBId ? [$teamAId, $teamBId] : [$teamBId, $teamAId];
        $vA = (int) Cache::get("team.{$teamAId}.cache_version", 0);
        $vB = (int) Cache::get("team.{$teamBId}.cache_version", 0);

        return "win_prob.{$lo}.{$hi}.v{$vA}.{$vB}";
    }
}
