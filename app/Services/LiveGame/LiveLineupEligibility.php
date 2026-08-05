<?php

namespace App\Services\LiveGame;

/**
 * The result of gating a roster against tonight's live data.
 *
 * Ranking never sees the excluded players at all. Demoted players are held back as a
 * backfill pool so a coach is only offered a player in foul trouble when there are not
 * five healthier bodies available.
 */
class LiveLineupEligibility
{
    /**
     * @param  list<int>  $rankablePlayerIds  Sent to the ranker.
     * @param  list<int>  $demotedPlayerIds  Eligible, but only used to backfill a short lineup.
     * @param  list<int>  $lockedPlayerIds  Rankable, but this coach cannot substitute them.
     * @param  array<int, string>  $reasons  player_id => reason code, for every non-clean player.
     */
    public function __construct(
        public readonly array $rankablePlayerIds,
        public readonly array $demotedPlayerIds,
        public readonly array $lockedPlayerIds,
        public readonly array $reasons,
    ) {}

    /** Everyone the ranker may consider, best-case first. */
    public function eligiblePlayerIds(): array
    {
        return array_values(array_merge($this->rankablePlayerIds, $this->demotedPlayerIds));
    }

    public function reasonFor(int $playerId): ?string
    {
        return $this->reasons[$playerId] ?? null;
    }

    public function isLocked(int $playerId): bool
    {
        return in_array($playerId, $this->lockedPlayerIds, true);
    }
}
