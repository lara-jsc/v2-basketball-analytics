<?php

use App\Repositories\ShotZoneRepository;
use App\Services\ShotZoneService;

/**
 * Build a stub ShotZoneRepository backed by pre-seeded zone data.
 *
 * @param  array<string, array{made: int, attempted: int}>  $zones
 */
function fakeRepoReturning(array $zones, int $totalShots = 0): ShotZoneRepository
{
    return new class($zones, $totalShots) extends ShotZoneRepository
    {
        public function __construct(
            private readonly array $fakeZones,
            private readonly int $fakeTotalShots,
        ) {
            // No parent constructor needed — repository has no dependencies.
        }

        public function locatedShotsFor(int $playerId): array
        {
            return $this->fakeZones;
        }

        public function totalShotsFor(int $playerId): int
        {
            return $this->fakeTotalShots;
        }

        public function profileShotsFor(int $playerId): array
        {
            return [];
        }

        public function profileReconcilesWithHistory(int $playerId): bool
        {
            return true;
        }
    };
}

it('computes points per shot per zone', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'paint' => ['made' => 31, 'attempted' => 48],
        'corner_3_left' => ['made' => 9, 'attempted' => 19],
    ], totalShots: 67)))->profileFor(1);

    // 31/48 * 2 = 1.2917
    expect($profile['zones']['paint']['points_per_shot'])->toBe(1.29)
        // 9/19 * 3 = 1.4211 — a 47% corner three beats a 65% layup
        ->and($profile['zones']['corner_3_left']['points_per_shot'])->toBe(1.42);
});

it('withholds color below the five-attempt threshold', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'corner_3_right' => ['made' => 2, 'attempted' => 4],
    ], totalShots: 4)))->profileFor(1);

    expect($profile['zones']['corner_3_right']['has_enough_data'])->toBeFalse()
        ->and($profile['zones']['corner_3_right']['attempted'])->toBe(4);
});

it('colors a zone at exactly five attempts', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'mid_range' => ['made' => 1, 'attempted' => 5],
    ], totalShots: 5)))->profileFor(1);

    expect($profile['zones']['mid_range']['has_enough_data'])->toBeTrue();
});

it('always returns all five zones, including untouched ones', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([], totalShots: 0)))->profileFor(1);

    expect(array_keys($profile['zones']))
        ->toEqualCanonicalizing(['paint', 'mid_range', 'corner_3_left', 'corner_3_right', 'above_break_3'])
        ->and($profile['zones']['paint']['attempted'])->toBe(0)
        ->and($profile['zones']['paint']['has_enough_data'])->toBeFalse();
});

it('reports coverage so skipped locations are visible', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'paint' => ['made' => 20, 'attempted' => 40],
    ], totalShots: 61)))->profileFor(1);

    expect($profile['located_shots'])->toBe(40)
        ->and($profile['total_shots'])->toBe(61);
});

it('names the most-attempted zone as the top zone', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'paint' => ['made' => 6, 'attempted' => 12],
        'above_break_3' => ['made' => 14, 'attempted' => 28],
    ], totalShots: 40)))->profileFor(1);

    expect($profile['top_zone']['key'])->toBe('above_break_3')
        ->and($profile['top_zone']['label'])->toBe('Above the break 3')
        // 28 of 40 located attempts
        ->and($profile['top_zone']['attempt_share'])->toBe(70)
        ->and($profile['top_zone']['percentage'])->toBe(50.0)
        ->and($profile['top_zone']['has_enough_data'])->toBeTrue();
});

it('breaks a tie in favour of the earlier zone', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'paint' => ['made' => 5, 'attempted' => 10],
        'mid_range' => ['made' => 4, 'attempted' => 10],
    ], totalShots: 20)))->profileFor(1);

    expect($profile['top_zone']['key'])->toBe('paint');
});

it('flags a thin top zone rather than hiding it', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'corner_3_right' => ['made' => 2, 'attempted' => 3],
    ], totalShots: 3)))->profileFor(1);

    expect($profile['top_zone']['key'])->toBe('corner_3_right')
        ->and($profile['top_zone']['attempt_share'])->toBe(100)
        ->and($profile['top_zone']['has_enough_data'])->toBeFalse();
});

it('has no top zone before any shot is located', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([], totalShots: 9)))->profileFor(1);

    expect($profile['top_zone'])->toBeNull();
});

it('never divides by zero', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'paint' => ['made' => 0, 'attempted' => 0],
    ], totalShots: 0)))->profileFor(1);

    expect($profile['zones']['paint']['percentage'])->toBe(0.0)
        ->and($profile['zones']['paint']['points_per_shot'])->toBe(0.0);
});
