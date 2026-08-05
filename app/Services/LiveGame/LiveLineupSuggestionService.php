<?php

namespace App\Services\LiveGame;

use App\Jobs\RecommendLiveLineup;
use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use Illuminate\Support\Facades\Cache;

/**
 * Caches the two ranked lineups for one bench, following LineupService's
 * dispatch-and-poll shape.
 *
 * The key carries the game's latest event sequence, so a recorded event silently
 * orphans the old entry instead of needing an invalidation hook. Nothing in the
 * recording path has to know this service exists.
 */
class LiveLineupSuggestionService
{
    private const TTL_MINUTES = 15;

    /**
     * Cached suggestion for the bench, or null after dispatching the job.
     *
     * @return array<string, mixed>|null
     */
    public function getOrDispatch(LiveGame $game, int $teamId): ?array
    {
        $sequence = $this->currentSequence($game);

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($this->cacheKey($game->id, $teamId, $sequence));

        if ($cached !== null) {
            return $cached;
        }

        RecommendLiveLineup::dispatch($game->id, $teamId, $sequence);

        return null;
    }

    /**
     * Store the ranked lineups. Called by the job on completion.
     *
     * The job passes back the sequence it was dispatched for rather than re-reading it.
     * If an event landed while the job ran, the next poll computes a newer key, misses,
     * and re-dispatches — which is the correct outcome, since the suggestion should
     * reflect the latest events.
     *
     * @param  array<string, mixed>  $result
     */
    public function store(int $liveGameId, int $teamId, int $sequence, array $result): void
    {
        Cache::put(
            $this->cacheKey($liveGameId, $teamId, $sequence),
            $result,
            now()->addMinutes(self::TTL_MINUTES),
        );
    }

    public function currentSequence(LiveGame $game): int
    {
        return (int) LiveGameEvent::query()
            ->where('live_game_id', $game->id)
            ->max('sequence');
    }

    private function cacheKey(int $liveGameId, int $teamId, int $sequence): string
    {
        return "live_lineup.{$liveGameId}.{$teamId}.seq{$sequence}";
    }
}
