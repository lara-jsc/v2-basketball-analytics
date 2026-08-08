<?php

namespace App\Enums;

/**
 * Shot locations captured during live games.
 * Mirror of SHOT_ZONES in resources/js/Components/features/live-game/event-catalog.ts.
 * Change both together.
 */
enum ShotZone: string
{
    case Paint = 'paint';
    case MidRange = 'mid_range';
    case CornerThreeLeft = 'corner_3_left';
    case CornerThreeRight = 'corner_3_right';
    case AboveBreakThree = 'above_break_3';

    public function points(): int
    {
        return match ($this) {
            self::Paint, self::MidRange => 2,
            self::CornerThreeLeft, self::CornerThreeRight, self::AboveBreakThree => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Paint => 'Paint',
            self::MidRange => 'Mid-range',
            self::CornerThreeLeft => 'Left corner 3',
            self::CornerThreeRight => 'Right corner 3',
            self::AboveBreakThree => 'Above the break 3',
        };
    }

    /** @return list<self> */
    public static function forPoints(int $points): array
    {
        return array_values(array_filter(self::cases(), fn (self $zone): bool => $zone->points() === $points));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $zone): string => $zone->value, self::cases());
    }
}
