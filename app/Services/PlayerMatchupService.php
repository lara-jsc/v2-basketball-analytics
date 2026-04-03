<?php

namespace App\Services;

use App\Jobs\ComputePlayerMatchup;
use Illuminate\Support\Facades\Cache;

/**
 * Manages player-vs-player matchup edge score results.
 * Symmetric: playerA vs playerB == playerB vs playerA.
 */
class PlayerMatchupService
{
    private const TTL_HOURS = 24;

    /**
     * Return cached matchup result, or dispatch job and return null.
     *
     * @return array<string, mixed>|null
     */
    public function getOrDispatch(int $playerAId, int $playerBId): ?array
    {
        $key = $this->cacheKey($playerAId, $playerBId);

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        ComputePlayerMatchup::dispatch($playerAId, $playerBId);

        return null;
    }

    /**
     * Store the computed matchup. Called by the Job on completion.
     *
     * @param  array<string, mixed>  $result
     */
    public function store(int $playerAId, int $playerBId, array $result): void
    {
        Cache::put($this->cacheKey($playerAId, $playerBId), $result, now()->addHours(self::TTL_HOURS));
    }

    private function cacheKey(int $playerAId, int $playerBId): string
    {
        [$lo, $hi] = $playerAId < $playerBId ? [$playerAId, $playerBId] : [$playerBId, $playerAId];

        return "matchup.{$lo}.{$hi}";
    }
}
