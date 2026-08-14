<?php

namespace App\Services\LiveGame;

/**
 * What one user is allowed to do with one live game's roster.
 */
class LiveGameControl
{
    /**
     * @param  'home'|'opponent'|null  $side  Null when the viewer belongs to neither bench.
     * @param  list<int>  $controlledPlayerIds  Players this user may record for and substitute.
     */
    public function __construct(
        public readonly ?string $side,
        public readonly ?int $teamId,
        public readonly array $controlledPlayerIds,
        public readonly bool $isMainCoach,
    ) {}

    public function isSpectator(): bool
    {
        return $this->side === null;
    }
}
