<?php

namespace App\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGamePlayerStat;
use App\Models\Player;
use Illuminate\Support\Collection;

/**
 * Decides who is *allowed* on the floor right now. It never decides who is *best* —
 * that stays with the ranker, fed season averages.
 *
 * The split matters: an 8-minute on/off margin and a 20-game rating are not the same
 * unit, so blending them would need an exchange rate nothing in the data supports.
 * Live data therefore only ever constrains legality.
 */
class LiveLineupEligibilityFilter
{
    public const REASON_DISQUALIFIED = 'disqualified';

    public const REASON_FOUL_TROUBLE = 'foul_trouble';

    public const REASON_INACTIVE = 'inactive';

    public const REASON_ASSIGNED_TO_ASSISTANT = 'assigned_to_assistant';

    /**
     * One foul short of disqualification. Deliberately not the alert service's
     * period-aware threshold: that one decides whether to *warn* a coach, this one
     * decides whether to keep offering the player, and offering a 4-foul player as a
     * first-choice recommendation is wrong in every period.
     */
    public const FOUL_TROUBLE_THRESHOLD = LiveGameEventRules::MAX_PERSONAL_FOULS - 1;

    /**
     * @param  Collection<int, Player>  $roster
     * @param  list<int>  $controlledPlayerIds  Players this coach may substitute.
     */
    public function filter(LiveGame $game, Collection $roster, array $controlledPlayerIds): LiveLineupEligibility
    {
        $personalFouls = $this->personalFoulsByPlayer($game, $roster);

        $rankable = [];
        $demoted = [];
        $locked = [];
        $reasons = [];

        foreach ($roster as $player) {
            $playerId = (int) $player->id;
            $fouls = $personalFouls[$playerId] ?? 0;

            // Disqualification is a rule of the game, so it outranks every other reason —
            // including inactivity, which is only a roster preference.
            if ($fouls >= LiveGameEventRules::MAX_PERSONAL_FOULS) {
                $reasons[$playerId] = self::REASON_DISQUALIFIED;

                continue;
            }

            if ($player->is_active === false) {
                $reasons[$playerId] = self::REASON_INACTIVE;

                continue;
            }

            if ($fouls >= self::FOUL_TROUBLE_THRESHOLD) {
                $demoted[] = $playerId;
                $reasons[$playerId] = self::REASON_FOUL_TROUBLE;
            } else {
                $rankable[] = $playerId;
            }

            // Locked players are still ranked: the coach should see that the system wants
            // them, then be told plainly why they cannot make the change themselves.
            if (! in_array($playerId, $controlledPlayerIds, true)) {
                $locked[] = $playerId;
                $reasons[$playerId] ??= self::REASON_ASSIGNED_TO_ASSISTANT;
            }
        }

        return new LiveLineupEligibility($rankable, $demoted, $locked, $reasons);
    }

    /**
     * @param  Collection<int, Player>  $roster
     * @return array<int, int>
     */
    private function personalFoulsByPlayer(LiveGame $game, Collection $roster): array
    {
        $playerIds = $roster->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($playerIds === []) {
            return [];
        }

        return LiveGamePlayerStat::query()
            ->where('live_game_id', $game->id)
            ->whereIn('player_id', $playerIds)
            ->pluck('personal_fouls', 'player_id')
            ->map(fn (mixed $fouls): int => (int) $fouls)
            ->all();
    }
}
