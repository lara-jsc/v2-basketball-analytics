<?php

use App\Enums\ShotZone;

it('knows what each zone is worth', function () {
    expect(ShotZone::Paint->points())->toBe(2)
        ->and(ShotZone::MidRange->points())->toBe(2)
        ->and(ShotZone::CornerThreeLeft->points())->toBe(3)
        ->and(ShotZone::CornerThreeRight->points())->toBe(3)
        ->and(ShotZone::AboveBreakThree->points())->toBe(3);
});

it('lists only the zones legal for a shot value', function () {
    expect(array_map(fn (ShotZone $z) => $z->value, ShotZone::forPoints(2)))
        ->toEqualCanonicalizing(['paint', 'mid_range'])
        ->and(array_map(fn (ShotZone $z) => $z->value, ShotZone::forPoints(3)))
        ->toEqualCanonicalizing(['corner_3_left', 'corner_3_right', 'above_break_3']);
});
