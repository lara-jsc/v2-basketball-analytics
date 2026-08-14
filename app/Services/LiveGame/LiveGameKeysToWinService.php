<?php

namespace App\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\Player;
use App\Models\PlayerStat;
use Illuminate\Support\Collection;

class LiveGameKeysToWinService
{
    public const MAX_KEYS = 2;

    public const ON_COURT_CLOSE_RATIO = 0.10;

    public const HOT_MAKES_THRESHOLD = 3;

    /** @var array<string, string> */
    private const DEFENSE_KEYS = [
        'scorer' => 'Deny the ball; force contested looks',
        'playmaker' => 'Pressure the ball; deny easy entries',
        'boarder' => 'Box out; crash the glass',
        'rim_protector' => 'Attack early; finish through contact',
        'disruptor' => 'Protect the ball; avoid telegraphed passes',
    ];

    /**
     * @param  Collection<int, LiveGameEvent>  $effectiveEvents
     * @return array{home: array{team_id: int, keys: list<array<string, mixed>>}, opponent: array{team_id: int, keys: list<array<string, mixed>>}}
     */
    public function compute(LiveGame $game, Collection $effectiveEvents): array
    {
        $homeTeamId = (int) $game->home_team_id;
        $opponentTeamId = (int) $game->opponent_team_id;

        return [
            'home' => [
                'team_id' => $homeTeamId,
                'keys' => $this->keysForSide(
                    defendingTeamId: $homeTeamId,
                    threatTeamId: $opponentTeamId,
                    threatOnCourtIds: $this->playerIds($game->opponent_active_player_ids),
                    defendingOnCourtIds: $this->playerIds($game->active_player_ids),
                    currentPeriod: (int) $game->current_period,
                    effectiveEvents: $effectiveEvents,
                ),
            ],
            'opponent' => [
                'team_id' => $opponentTeamId,
                'keys' => $this->keysForSide(
                    defendingTeamId: $opponentTeamId,
                    threatTeamId: $homeTeamId,
                    threatOnCourtIds: $this->playerIds($game->active_player_ids),
                    defendingOnCourtIds: $this->playerIds($game->opponent_active_player_ids),
                    currentPeriod: (int) $game->current_period,
                    effectiveEvents: $effectiveEvents,
                ),
            ],
        ];
    }

    /**
     * @param  list<int>  $threatOnCourtIds
     * @param  list<int>  $defendingOnCourtIds
     * @param  Collection<int, LiveGameEvent>  $effectiveEvents
     * @return list<array<string, mixed>>
     */
    private function keysForSide(
        int $defendingTeamId,
        int $threatTeamId,
        array $threatOnCourtIds,
        array $defendingOnCourtIds,
        int $currentPeriod,
        Collection $effectiveEvents,
    ): array {
        $threatPlayers = Player::query()
            ->where('team_id', $threatTeamId)
            ->where('is_active', true)
            ->get();
        $defendingPlayers = Player::query()
            ->where('team_id', $defendingTeamId)
            ->where('is_active', true)
            ->get();

        $statsByPlayerId = PlayerStat::query()
            ->whereIn('player_id', $threatPlayers->pluck('id')->merge($defendingPlayers->pluck('id'))->all())
            ->get()
            ->keyBy('player_id');

        $threatPlayers = $threatPlayers
            ->filter(fn (Player $player): bool => $statsByPlayerId->has($player->id))
            ->values();
        $defendingPlayers = $defendingPlayers
            ->filter(fn (Player $player): bool => $statsByPlayerId->has($player->id))
            ->values();

        if ($threatPlayers->isEmpty()) {
            return [];
        }

        $plusMinus = $threatPlayers->map(
            fn (Player $p): float => (float) ($statsByPlayerId->get($p->id)->plus_minus ?? 0),
        )->all();
        $pts = $threatPlayers->map(
            fn (Player $p): float => (float) $statsByPlayerId->get($p->id)->pts,
        )->all();
        $secondary = $threatPlayers->map(function (Player $p) use ($statsByPlayerId): float {
            $stat = $statsByPlayerId->get($p->id);

            return (float) $stat->reb + (float) $stat->ast;
        })->all();

        $ranked = $threatPlayers->map(function (Player $player) use ($plusMinus, $pts, $secondary, $threatOnCourtIds, $statsByPlayerId): array {
            $stat = $statsByPlayerId->get($player->id);

            $score = 0.45 * $this->normalize($plusMinus, (float) ($stat->plus_minus ?? 0))
                + 0.35 * $this->normalize($pts, (float) $stat->pts)
                + 0.20 * $this->normalize($secondary, (float) $stat->reb + (float) $stat->ast);

            return [
                'player' => $player,
                'threat_score' => round($score, 4),
                'on_court' => in_array((int) $player->id, $threatOnCourtIds, true),
            ];
        })->sort(function (array $a, array $b): int {
            $leader = max($a['threat_score'], $b['threat_score']);
            $close = $leader > 0
                && abs($a['threat_score'] - $b['threat_score']) / $leader <= self::ON_COURT_CLOSE_RATIO;

            if ($close && $a['on_court'] !== $b['on_court']) {
                return ((int) $b['on_court']) <=> ((int) $a['on_court']);
            }

            $scoreCmp = $b['threat_score'] <=> $a['threat_score'];
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }

            return ((int) $b['on_court']) <=> ((int) $a['on_court']);
        })->values();

        $selected = $ranked->take(self::MAX_KEYS)->values()->all();
        $ownMedians = $this->medians($defendingPlayers, $statsByPlayerId);
        $madeShots = $this->periodMadeShots($effectiveEvents, $currentPeriod);
        $missStreaks = app(LiveGameMissStreakCalculator::class)->compute(
            $effectiveEvents->filter(fn (LiveGameEvent $event): bool => $event->team_scope === 'own'),
        );
        $personalFouls = $this->personalFouls($effectiveEvents);
        $foulThreshold = $currentPeriod < 4 ? 3 : 4;
        $dqIds = $this->disqualifiedPlayerIds($personalFouls);

        $keys = [];
        foreach ($selected as $row) {
            /** @var Player $threat */
            $threat = $row['player'];
            $threatStat = $statsByPlayerId->get($threat->id);
            $tag = $this->strengthTag($threatStat, $ownMedians);
            $counter = $this->pickCounter($defendingPlayers, $statsByPlayerId, $threat, $tag, $dqIds);
            $liveStatus = $this->liveStatus(
                (int) $threat->id,
                $madeShots,
                $missStreaks,
                $personalFouls,
                $foulThreshold,
            );
            $counterId = $counter?->id;
            $counterOnCourt = $counterId !== null && in_array((int) $counterId, $defendingOnCourtIds, true);

            $keys[] = [
                'opponent_player_id' => (int) $threat->id,
                'strength_tag' => $tag,
                'threat_score' => $row['threat_score'],
                'live_status' => $liveStatus,
                'defense_key' => self::DEFENSE_KEYS[$tag],
                'counter_player_id' => $counterId !== null ? (int) $counterId : null,
                'context' => [
                    'pts' => (float) $threatStat->pts,
                    'plus_minus' => $threatStat->plus_minus,
                    'reb' => (float) $threatStat->reb,
                    'ast' => (float) $threatStat->ast,
                    'counter_on_court' => $counterOnCourt,
                    'period_makes' => $madeShots[(int) $threat->id] ?? 0,
                ],
            ];
        }

        return $keys;
    }

    /**
     * @param  Collection<int, Player>  $defendingPlayers
     * @param  Collection<int, PlayerStat>  $statsByPlayerId
     * @return array<string, float>
     */
    private function medians(Collection $defendingPlayers, Collection $statsByPlayerId): array
    {
        $keys = ['pts', 'efg_pct', 'ast', 'reb', 'blk', 'stl'];
        $medians = [];
        foreach ($keys as $key) {
            $values = $defendingPlayers
                ->map(fn (Player $p): float => (float) ($statsByPlayerId->get($p->id)->{$key} ?? 0))
                ->sort()
                ->values();
            $medians[$key] = $this->median($values->all());
        }

        return $medians;
    }

    /** @param list<float> $values */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        $count = count($values);
        $mid = intdiv($count, 2);
        if ($count % 2 === 1) {
            return $values[$mid];
        }

        return ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /** @param array<string, float> $ownMedians */
    private function strengthTag(PlayerStat $stat, array $ownMedians): string
    {
        $edges = [
            'scorer' => max(
                (float) $stat->pts - ($ownMedians['pts'] ?? 0),
                (float) ($stat->efg_pct ?? 0) - ($ownMedians['efg_pct'] ?? 0),
            ),
            'playmaker' => (float) $stat->ast - ($ownMedians['ast'] ?? 0),
            'boarder' => (float) $stat->reb - ($ownMedians['reb'] ?? 0),
            'rim_protector' => (float) $stat->blk - ($ownMedians['blk'] ?? 0),
            'disruptor' => (float) $stat->stl - ($ownMedians['stl'] ?? 0),
        ];

        arsort($edges);

        return (string) array_key_first($edges);
    }

    /**
     * @param  Collection<int, Player>  $defendingPlayers
     * @param  Collection<int, PlayerStat>  $statsByPlayerId
     * @param  array<int, true>  $dqIds
     */
    private function pickCounter(
        Collection $defendingPlayers,
        Collection $statsByPlayerId,
        Player $threat,
        string $tag,
        array $dqIds,
    ): ?Player {
        $eligible = $defendingPlayers
            ->reject(fn (Player $player): bool => isset($dqIds[(int) $player->id]))
            ->values();

        if ($eligible->isEmpty()) {
            return null;
        }

        $sameRole = $eligible->filter(
            fn (Player $player): bool => $player->role !== null
                && $threat->role !== null
                && $player->role === $threat->role,
        );

        $pool = $sameRole->isNotEmpty() ? $sameRole : $eligible;

        return $pool
            ->sortByDesc(fn (Player $player): float => $this->defenseScore($statsByPlayerId->get($player->id), $tag))
            ->first();
    }

    private function defenseScore(PlayerStat $stat, string $tag): float
    {
        $stl = (float) $stat->stl;
        $blk = (float) $stat->blk;
        $dr = (float) $stat->dr;
        $reb = (float) $stat->reb;
        $pf = (float) $stat->pf;

        $base = match ($tag) {
            'scorer' => $stl * 0.4 + $dr * 0.4 + $blk * 0.2,
            'playmaker' => $stl * 0.5 + $dr * 0.3 + $blk * 0.2,
            'boarder' => $reb * 0.5 + $dr * 0.3 + $blk * 0.2,
            'rim_protector' => $blk * 0.35 + $dr * 0.35 + $stl * 0.3,
            'disruptor' => $stl * 0.55 + $dr * 0.25 + $blk * 0.2,
            default => $stl + $dr + $blk,
        };

        return $base - ($pf * 0.1);
    }

    /**
     * @param  array<int, int>  $madeShots
     * @param  array<int, int>  $missStreaks
     * @param  array<int, int>  $personalFouls
     */
    private function liveStatus(
        int $playerId,
        array $madeShots,
        array $missStreaks,
        array $personalFouls,
        int $foulThreshold,
    ): string {
        if (($madeShots[$playerId] ?? 0) >= self::HOT_MAKES_THRESHOLD) {
            return 'confirmed';
        }

        $cold = ($missStreaks[$playerId] ?? 0) >= LiveGameMissStreakCalculator::DEMOTION_THRESHOLD;
        $foulTrouble = ($personalFouls[$playerId] ?? 0) >= $foulThreshold;

        if ($cold || $foulTrouble) {
            return 'fading';
        }

        return 'season';
    }

    /**
     * @param  Collection<int, LiveGameEvent>  $events
     * @return array<int, int>
     */
    private function periodMadeShots(Collection $events, int $currentPeriod): array
    {
        $made = [];
        foreach ($events as $event) {
            if ($event->type !== 'shot_made' || $event->player_id === null || $event->period !== $currentPeriod) {
                continue;
            }
            $playerId = (int) $event->player_id;
            $made[$playerId] = ($made[$playerId] ?? 0) + 1;
        }

        return $made;
    }

    /**
     * @param  Collection<int, LiveGameEvent>  $events
     * @return array<int, int>
     */
    private function personalFouls(Collection $events): array
    {
        $fouls = [];
        foreach ($events as $event) {
            if ($event->type !== 'foul' || $event->player_id === null) {
                continue;
            }
            $kind = $event->payload['kind'] ?? 'personal';
            if ($kind !== 'personal') {
                continue;
            }
            $playerId = (int) $event->player_id;
            $fouls[$playerId] = ($fouls[$playerId] ?? 0) + 1;
        }

        return $fouls;
    }

    /**
     * @param  array<int, int>  $personalFouls
     * @return array<int, true>
     */
    private function disqualifiedPlayerIds(array $personalFouls): array
    {
        $dq = [];
        foreach ($personalFouls as $playerId => $count) {
            if ($count >= LiveGameEventRules::MAX_PERSONAL_FOULS) {
                $dq[(int) $playerId] = true;
            }
        }

        return $dq;
    }

    /** @param list<float> $values */
    private function normalize(array $values, float $value): float
    {
        if ($values === []) {
            return 0.0;
        }
        $min = min($values);
        $max = max($values);
        if ($max <= $min) {
            return 0.5;
        }

        return ($value - $min) / ($max - $min);
    }

    /** @return list<int> */
    private function playerIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_map(fn (mixed $id): int => (int) $id, $ids));
    }
}
