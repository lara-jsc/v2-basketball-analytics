<?php

namespace App\Services\LiveGame;

/**
 * Single source of truth for which live-game event types are legal against which
 * clock state. Mirrored on the client in
 * resources/js/Components/features/live-game/event-catalog.ts — change both together.
 */
class LiveGameEventRules
{
    public const CLOCK_RUNNING = 'running';

    public const CLOCK_STOPPED = 'stopped';

    public const CLOCK_ANY = 'any';

    /** FIBA: a player is disqualified on their 5th personal foul. Set to 6 for NBA rules. */
    public const MAX_PERSONAL_FOULS = 5;

    /** Event types still recordable once the period clock reaches 0:00. */
    public const EXEMPT_AT_PERIOD_END = ['correction'];

    /** @var array<string, string> */
    private const CLOCK_REQUIREMENTS = [
        'shot_made' => self::CLOCK_RUNNING,
        'shot_missed' => self::CLOCK_RUNNING,
        'rebound' => self::CLOCK_RUNNING,
        'assist' => self::CLOCK_RUNNING,
        'turnover' => self::CLOCK_RUNNING,
        'opponent_score' => self::CLOCK_RUNNING,
        'foul' => self::CLOCK_ANY,
        'free_throw_made' => self::CLOCK_STOPPED,
        'free_throw_missed' => self::CLOCK_STOPPED,
        'timeout' => self::CLOCK_STOPPED,
        'substitution' => self::CLOCK_STOPPED,
        'correction' => self::CLOCK_ANY,
    ];

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::CLOCK_REQUIREMENTS);
    }

    public static function clockRequirement(string $type): string
    {
        return self::CLOCK_REQUIREMENTS[$type] ?? self::CLOCK_ANY;
    }

    public static function isAllowedWhileClockRunning(string $type): bool
    {
        return self::clockRequirement($type) !== self::CLOCK_STOPPED;
    }

    public static function isAllowedWhileClockStopped(string $type): bool
    {
        return self::clockRequirement($type) !== self::CLOCK_RUNNING;
    }

    public static function isAllowedAtPeriodEnd(string $type): bool
    {
        return in_array($type, self::EXEMPT_AT_PERIOD_END, true);
    }

    /** A whistle stops the clock, so recording a foul stops it too. */
    public static function stopsClock(string $type): bool
    {
        return $type === 'foul';
    }
}
