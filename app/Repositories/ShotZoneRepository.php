<?php

namespace App\Repositories;

use App\Models\LiveGameEvent;
use App\Models\PlayerHistory;
use App\Models\PlayerShotZoneProfile;
use Illuminate\Support\Facades\DB;

class ShotZoneRepository
{
    /**
     * Located field goal attempts for a player, grouped by zone, across every live game.
     *
     * Live locations live in the event log. Season baselines live in
     * player_shot_zone_profiles (Task 9). player_histories stays box-score only
     * but must reconcile with the profile's 2PT/3PT splits when both exist.
     *
     * @return array<string, array{made: int, attempted: int}>
     */
    public function locatedShotsFor(int $playerId): array
    {
        return $this->shotQuery($playerId)
            ->whereNotNull(DB::raw("json_extract(payload, '$.zone')"))
            ->get(['type', 'payload'])
            ->groupBy(fn (LiveGameEvent $event): string => (string) ($event->payload['zone'] ?? ''))
            ->map(fn ($events): array => [
                'made' => $events->where('type', 'shot_made')->count(),
                'attempted' => $events->count(),
            ])
            ->all();
    }

    /** Every field goal attempt, located or not. Free throws excluded. */
    public function totalShotsFor(int $playerId): int
    {
        return $this->shotQuery($playerId)->count();
    }

    /**
     * Season baseline zone profile for a player from player_shot_zone_profiles.
     *
     * @return array<string, array{made: int, attempted: int}>
     */
    public function profileShotsFor(int $playerId): array
    {
        $row = PlayerShotZoneProfile::where('player_id', $playerId)->first();

        if ($row === null) {
            return [];
        }

        return [
            'paint' => ['made' => (int) $row->paint_made,            'attempted' => (int) $row->paint_attempted],
            'mid_range' => ['made' => (int) $row->mid_range_made,        'attempted' => (int) $row->mid_range_attempted],
            'corner_3_left' => ['made' => (int) $row->corner_3_left_made,    'attempted' => (int) $row->corner_3_left_attempted],
            'corner_3_right' => ['made' => (int) $row->corner_3_right_made,   'attempted' => (int) $row->corner_3_right_attempted],
            'above_break_3' => ['made' => (int) $row->above_break_3_made,    'attempted' => (int) $row->above_break_3_attempted],
        ];
    }

    /**
     * Whether the player's imported zone profile is consistent with their
     * aggregated box-score histories. True when no profile exists.
     */
    public function profileReconcilesWithHistory(int $playerId): bool
    {
        $profile = PlayerShotZoneProfile::where('player_id', $playerId)->first();

        if ($profile === null) {
            return true;
        }

        $history = PlayerHistory::where('player_id', $playerId)
            ->whereNotNull('field_goals_attempted')
            ->whereNotNull('three_pointers_attempted')
            ->selectRaw('
                SUM(field_goals_made) as fgm,
                SUM(field_goals_attempted) as fga,
                SUM(three_pointers_made) as tpm,
                SUM(three_pointers_attempted) as tpa
            ')
            ->first();

        if ($history === null || $history->fga === null) {
            return true;
        }

        $histFga = (int) $history->fga;
        $histTpa = (int) $history->tpa;
        $hist2pa = $histFga - $histTpa;

        $profile2pa = (int) $profile->paint_attempted + (int) $profile->mid_range_attempted;
        $profile3pa = (int) $profile->corner_3_left_attempted
            + (int) $profile->corner_3_right_attempted
            + (int) $profile->above_break_3_attempted;

        return $profile2pa === $hist2pa && $profile3pa === $histTpa;
    }

    private function shotQuery(int $playerId)
    {
        return LiveGameEvent::query()
            ->where('player_id', $playerId)
            ->whereIn('type', ['shot_made', 'shot_missed'])
            // A voided shot never happened, so it must not reach the court.
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('live_game_events as corrections')
                    ->whereColumn('corrections.voids_event_id', 'live_game_events.id')
                    ->where('corrections.type', 'correction');
            });
    }
}
