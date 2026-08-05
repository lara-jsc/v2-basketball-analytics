<?php

namespace App\Services\LiveGame;

/**
 * The result of gating a bench against tonight's live data *and* against what this
 * particular coach is allowed to change.
 *
 * The on-court five is shared between a bench's coaches, but each may only substitute
 * their own players. So a player the coach does not control is never a candidate:
 * on court they are *fixed* (they hold a place in the five and cannot be moved), and on
 * the bench they are simply irrelevant.
 */
class LiveLineupEligibility
{
    /**
     * @param  list<int>  $rankablePlayerIds  Controlled and eligible — sent to the ranker.
     * @param  list<int>  $demotedPlayerIds  Controlled, but only used to backfill a short lineup.
     * @param  list<int>  $fixedPlayerIds  On court and not this coach's to move.
     * @param  array<int, string>  $reasons  player_id => reason code, for every non-clean player.
     */
    public function __construct(
        public readonly array $rankablePlayerIds,
        public readonly array $demotedPlayerIds,
        public readonly array $fixedPlayerIds,
        public readonly array $reasons,
    ) {}

    /** Everyone the ranker may consider, best-case first. */
    public function eligiblePlayerIds(): array
    {
        return array_values(array_merge($this->rankablePlayerIds, $this->demotedPlayerIds));
    }

    /**
     * How many of the five the coach may actually fill.
     *
     * The fixed players already occupy the rest, so asking the ranker for more than this
     * would propose changes the coach cannot make.
     */
    public function slotCount(): int
    {
        return max(0, LiveGameEventRules::LINEUP_SIZE - count($this->fixedPlayerIds));
    }

    public function reasonFor(int $playerId): ?string
    {
        return $this->reasons[$playerId] ?? null;
    }

    public function isFixed(int $playerId): bool
    {
        return in_array($playerId, $this->fixedPlayerIds, true);
    }
}
