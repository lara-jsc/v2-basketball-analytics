<?php

namespace App\Services;

use App\Enums\ShotZone;
use App\Repositories\ShotZoneRepository;

class ShotZoneService
{
    /** Attempts required in a zone before it is allowed any color. */
    public const MIN_ATTEMPTS = 5;

    public function __construct(private readonly ShotZoneRepository $repository) {}

    /** @return array<string, mixed> */
    public function profileFor(int $playerId): array
    {
        $live = $this->repository->locatedShotsFor($playerId);
        $baseline = $this->repository->profileShotsFor($playerId); // Task 9; empty map until then
        $zones = [];
        $located = 0;
        $liveLocated = 0;

        foreach (ShotZone::cases() as $zone) {
            $key = $zone->value;
            $made = (int) ($baseline[$key]['made'] ?? 0) + (int) ($live[$key]['made'] ?? 0);
            $attempted = (int) ($baseline[$key]['attempted'] ?? 0) + (int) ($live[$key]['attempted'] ?? 0);
            $located += $attempted;
            $liveLocated += (int) ($live[$key]['attempted'] ?? 0);

            $percentage = $attempted > 0 ? $made / $attempted : 0.0;

            $zones[$key] = [
                'label' => $zone->label(),
                'made' => $made,
                'attempted' => $attempted,
                'percentage' => round($percentage * 100, 1),
                // Points per shot makes a 47% corner three (1.42) legible as better
                // than a 65% layup (1.30). Raw FG% would rank them the other way.
                'points_per_shot' => round($percentage * $zone->points(), 2),
                'has_enough_data' => $attempted >= self::MIN_ATTEMPTS,
            ];
        }

        $liveTotal = $this->repository->totalShotsFor($playerId);

        return [
            'zones' => $zones,
            'located_shots' => $located,
            'live_located_shots' => $liveLocated,
            'live_total_shots' => $liveTotal,
            // Legacy key for early UI consumers — same as live_total_shots for now.
            'total_shots' => $liveTotal,
            'profile_vs_history_ok' => $this->repository->profileReconcilesWithHistory($playerId),
        ];
    }
}
