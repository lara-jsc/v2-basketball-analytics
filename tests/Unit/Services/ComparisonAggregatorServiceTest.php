<?php

use App\Models\Player;
use App\Models\PlayerStat;
use App\Services\ComparisonAggregatorService;
use Illuminate\Database\Eloquent\Collection;

describe('ComparisonAggregatorService', function () {

    beforeEach(function () {
        $this->service = new ComparisonAggregatorService();
    });

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build a mock Player with a fake PlayerStat.
     *
     * @param  array<string, mixed>  $statOverrides
     */
    function makePlayerWithStat(array $statOverrides = []): Player
    {
        $stat = new PlayerStat(array_merge([
            'pts'         => 10.0,
            'reb'         => 5.0,
            'ast'         => 3.0,
            'fg_pct'      => 0.50,
            'blk'         => 1.0,
            'stl'         => 1.0,
            'to_per_game' => 2.0,
            'min'         => 30.0,
            'plus_minus'  => 5.0,
        ], $statOverrides));

        // Force a non-empty stats relation
        $player = new Player(['first_name' => 'Test', 'last_name' => 'Player']);
        $player->id = 1;
        $player->setRelation('stats', collect([$stat]));

        return $player;
    }

    // ------------------------------------------------------------------
    // aggregateStats
    // ------------------------------------------------------------------
    describe('aggregateStats', function () {
        it('returns all zeros when collection is empty', function () {
            $result = $this->service->aggregateStats(new Collection());

            expect($result)->toMatchArray([
                'avg_pts'         => 0.0,
                'avg_reb'         => 0.0,
                'avg_ast'         => 0.0,
                'avg_fg_pct'      => 0.0,
                'avg_blk'         => 0.0,
                'avg_stl'         => 0.0,
                'avg_to_per_game' => 0.0,
            ]);
        });

        it('returns all zeros when players have no stats relation', function () {
            $player = new Player();
            $player->setRelation('stats', collect());

            $result = $this->service->aggregateStats(new Collection([$player]));

            expect($result['avg_pts'])->toBe(0.0);
        });

        it('averages correctly across multiple players', function () {
            $p1 = makePlayerWithStat(['pts' => 20.0, 'reb' => 8.0]);
            $p2 = makePlayerWithStat(['pts' => 10.0, 'reb' => 4.0]);

            $result = $this->service->aggregateStats(new Collection([$p1, $p2]));

            expect($result['avg_pts'])->toBe(15.0);
            expect($result['avg_reb'])->toBe(6.0);
        });

        it('rounds values to 2 decimal places', function () {
            $p1 = makePlayerWithStat(['pts' => 10.0]);
            $p2 = makePlayerWithStat(['pts' => 20.0]);
            $p3 = makePlayerWithStat(['pts' => 30.0]);

            $result = $this->service->aggregateStats(new Collection([$p1, $p2, $p3]));

            expect($result['avg_pts'])->toBe(20.0);
        });
    });

    // ------------------------------------------------------------------
    // teamPlusMinus
    // ------------------------------------------------------------------
    describe('teamPlusMinus', function () {
        it('returns null for an empty collection', function () {
            expect($this->service->teamPlusMinus(new Collection()))->toBeNull();
        });

        it('returns null when all players have null plus_minus', function () {
            $player = makePlayerWithStat(['plus_minus' => null, 'min' => 30.0]);
            expect($this->service->teamPlusMinus(new Collection([$player])))->toBeNull();
        });

        it('returns null when all players have zero minutes', function () {
            $player = makePlayerWithStat(['plus_minus' => 5.0, 'min' => 0.0]);
            expect($this->service->teamPlusMinus(new Collection([$player])))->toBeNull();
        });

        it('computes the minutes-weighted average', function () {
            // Player A: 30 min, +10 => contribution 300
            // Player B: 10 min, -10 => contribution -100
            // Total minutes: 40, weighted sum: 200
            // Expected: 200 / 40 = 5.0
            $p1 = makePlayerWithStat(['plus_minus' => 10.0, 'min' => 30.0]);
            $p2 = makePlayerWithStat(['plus_minus' => -10.0, 'min' => 10.0]);

            $result = $this->service->teamPlusMinus(new Collection([$p1, $p2]));

            expect($result)->toBe(5.0);
        });

        it('ignores players without plus_minus when computing the average', function () {
            $withPm    = makePlayerWithStat(['plus_minus' => 8.0, 'min' => 30.0]);
            $withoutPm = makePlayerWithStat(['plus_minus' => null, 'min' => 30.0]);

            $result = $this->service->teamPlusMinus(new Collection([$withPm, $withoutPm]));

            expect($result)->toBe(8.0);
        });
    });

    // ------------------------------------------------------------------
    // toEnginePayload
    // ------------------------------------------------------------------
    describe('toEnginePayload', function () {
        it('serializes player data in the engine contract format', function () {
            $player              = makePlayerWithStat(['pts' => 22.4, 'plus_minus' => 7.4]);
            $player->first_name  = 'John';
            $player->last_name   = 'Doe';

            $payload = $this->service->toEnginePayload(new Collection([$player]));

            expect($payload)->toHaveCount(1);
            expect($payload[0])->toMatchArray([
                'player_id'  => 1,
                'name'       => 'John Doe',
                'pts'        => 22.4,
                'plus_minus' => 7.4,
            ]);
        });

        it('returns an empty array for an empty collection', function () {
            expect($this->service->toEnginePayload(new Collection()))->toBe([]);
        });

        it('uses 0 for null stat values', function () {
            $player = new Player(['first_name' => 'Empty', 'last_name' => 'Player']);
            $player->id = 99;
            $player->setRelation('stats', collect()); // no stats

            $payload = $this->service->toEnginePayload(new Collection([$player]));

            expect($payload[0]['pts'])->toBe(0);
            expect($payload[0]['plus_minus'])->toBe(0);
        });
    });
});
