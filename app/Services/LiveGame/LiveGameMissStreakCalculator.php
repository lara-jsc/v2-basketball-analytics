<?php

namespace App\Services\LiveGame;

use App\Models\LiveGameEvent;
use Illuminate\Support\Collection;

class LiveGameMissStreakCalculator
{
    public const DEMOTION_THRESHOLD = 3;

    /**
     * @param  Collection<int, LiveGameEvent>  $events  Effective (non-voided) own-player events.
     * @return array<int, int> player_id => current miss streak
     */
    public function compute(Collection $events): array
    {
        $streaks = [];

        foreach ($events as $event) {
            if ($event->team_scope !== 'own' || $event->player_id === null) {
                continue;
            }

            $playerId = (int) $event->player_id;

            if ($event->type === 'shot_made') {
                $streaks[$playerId] = 0;

                continue;
            }

            if ($event->type === 'shot_missed') {
                $streaks[$playerId] = ($streaks[$playerId] ?? 0) + 1;
            }
        }

        return $streaks;
    }
}
