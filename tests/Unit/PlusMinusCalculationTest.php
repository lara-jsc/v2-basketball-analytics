<?php

use App\Services\PlayerStatisticsService;

/*
|--------------------------------------------------------------------------
| Unit tests for PlayerStatisticsService — pure math, no I/O
|--------------------------------------------------------------------------
|
| No database, no HTTP, no Laravel container.
|
| Input-format note for plus/minus tests:
|   The blueprint uses conceptual [team=58, opp=50] notation.
|   PlayerStatisticsService::computePlusMinus() reads the 'plus_minus' key
|   directly (the service stores per-game values, not separate team/opp cols).
|   Translation: team(58) − opp(50) = 8  →  ['plus_minus' => 8]
|
| Canonical two-game dataset used in tests 7–10:
|
|   Game 1: pts=24, reb=6, ast=4, stl=2, blk=1
|           fgm=9,  fga=18, 3pm=2,  ftm=4, fta=6,  to=3
|           free_throws_missed = fta(6)−ftm(4) = 2
|           plus_minus = 58−50 = +8
|
|   Game 2: pts=18, reb=4, ast=7, stl=1, blk=0
|           fgm=7,  fga=15, 3pm=1,  ftm=3, fta=4,  to=2
|           free_throws_missed = fta(4)−ftm(3) = 1
|           plus_minus = 45−48 = −3
|
*/

beforeEach(function (): void {
    $this->service = new PlayerStatisticsService();
});

// ── Tests 1–5: computePlusMinus ───────────────────────────────────────────────

it('computes plus/minus for a single positive game', function (): void {
    // team=58, opp=50  →  58 − 50 = +8
    expect(
        $this->service->computePlusMinus([['plus_minus' => 8]])
    )->toBe(8);
});

it('sums plus/minus across three mixed-sign games', function (): void {
    // (58−50) + (45−48) + (62−55) = +8 + (−3) + (+7) = +12
    expect(
        $this->service->computePlusMinus([
            ['plus_minus' =>  8],
            ['plus_minus' => -3],
            ['plus_minus' =>  7],
        ])
    )->toBe(12);
});

it('returns a negative total when all games are losses', function (): void {
    // (40−60) + (30−45) = −20 + (−15) = −35
    expect(
        $this->service->computePlusMinus([
            ['plus_minus' => -20],
            ['plus_minus' => -15],
        ])
    )->toBe(-35);
});

it('returns zero when all games are perfectly even', function (): void {
    // (50−50) + (40−40) = 0 + 0 = 0
    expect(
        $this->service->computePlusMinus([
            ['plus_minus' => 0],
            ['plus_minus' => 0],
        ])
    )->toBe(0);
});

it('returns zero for an empty history array', function (): void {
    expect(
        $this->service->computePlusMinus([])
    )->toBe(0);
});

// ── Tests 6–7: computeEfficiency ─────────────────────────────────────────────

it('computes EFF for a single game', function (): void {
    /*
     * Positive: pts(24) + reb(6) + ast(4) + stl(2) + blk(1)    = 37
     * Negative: missedFG(18−9=9) + missedFT(6−4=2) + to(3)     = 14
     * EFF = 37 − 14 = 23.0
     */
    expect(
        $this->service->computeEfficiency([[
            'points'                => 24,
            'rebounds'              => 6,
            'assists'               => 4,
            'steals'                => 2,
            'blocks'                => 1,
            'field_goals_made'      => 9,
            'field_goals_attempted' => 18,
            'free_throws_made'      => 4,  // missed = fta(6) − ftm(4) = 2
            'free_throws_attempted' => 6,
            'turnovers'             => 3,
        ]])
    )->toBe(23.0);
});

it('sums EFF across two games', function (): void {
    /*
     * Game 1: (24+6+4+2+1) − ((18−9)+(6−4)+3) = 37 − 14 = 23
     * Game 2: (18+4+7+1+0) − ((15−7)+(4−3)+2) = 30 − 11 = 19
     * Total EFF = 23 + 19 = 42.0
     */
    $game1 = [
        'points'                => 24, 'rebounds' => 6, 'assists' => 4,
        'steals'                => 2,  'blocks'   => 1,
        'field_goals_made'      => 9,  'field_goals_attempted' => 18,
        'free_throws_made'      => 4,  'free_throws_attempted' => 6,  // missed=2
        'turnovers'             => 3,
    ];

    $game2 = [
        'points'                => 18, 'rebounds' => 4, 'assists' => 7,
        'steals'                => 1,  'blocks'   => 0,
        'field_goals_made'      => 7,  'field_goals_attempted' => 15,
        'free_throws_made'      => 3,  'free_throws_attempted' => 4,  // missed=1
        'turnovers'             => 2,
    ];

    expect(
        $this->service->computeEfficiency([$game1, $game2])
    )->toBe(42.0);
});

// ── Test 8: computeEfgPercent ─────────────────────────────────────────────────

it('computes eFG% across two games and returns 0.0 when FGA is zero', function (): void {
    /*
     * Game 1: fgm=9,  3pm=2, fga=18
     * Game 2: fgm=7,  3pm=1, fga=15
     * Totals: fgm=16, 3pm=3, fga=33
     * eFG% = (16 + 0.5×3) / 33 = 17.5 / 33 ≈ 0.5303
     */
    $game1 = ['field_goals_made' => 9, 'three_pointers_made' => 2, 'field_goals_attempted' => 18];
    $game2 = ['field_goals_made' => 7, 'three_pointers_made' => 1, 'field_goals_attempted' => 15];

    $result = $this->service->computeEfgPercent([$game1, $game2]);

    expect($result)->toBeGreaterThan(0.530);
    expect($result)->toBeLessThan(0.531);

    // Divide-by-zero edge case: FGA = 0 must return 0.0, not a division error
    expect(
        $this->service->computeEfgPercent([[
            'field_goals_attempted' => 0,
            'field_goals_made'      => 0,
            'three_pointers_made'   => 0,
        ]])
    )->toBe(0.0);
});

// ── Test 9: computeTrueShooting ───────────────────────────────────────────────

it('computes TS% across two games and returns 0.0 when denominator is zero', function (): void {
    /*
     * Game 1: pts=24, fga=18, fta=6
     * Game 2: pts=18, fga=15, fta=4
     * Totals: pts=42, fga=33, fta=10
     * TS% = 42 / (2 × (33 + 0.44×10)) = 42 / 74.8 ≈ 0.5615
     */
    $game1 = ['points' => 24, 'field_goals_attempted' => 18, 'free_throws_attempted' => 6];
    $game2 = ['points' => 18, 'field_goals_attempted' => 15, 'free_throws_attempted' => 4];

    $result = $this->service->computeTrueShooting([$game1, $game2]);

    expect($result)->toBeGreaterThan(0.561);
    expect($result)->toBeLessThan(0.562);

    // Divide-by-zero edge case: FGA=0 and FTA=0 must return 0.0, not a division error
    expect(
        $this->service->computeTrueShooting([[
            'points'                => 0,
            'field_goals_attempted' => 0,
            'free_throws_attempted' => 0,
        ]])
    )->toBe(0.0);
});

// ── Test 10: All four statistics on the shared canonical dataset ──────────────

it('computes all four statistics correctly on the same two-game dataset', function (): void {
    /*
     * Game 1: pts=24, reb=6, ast=4, stl=2, blk=1
     *         fgm=9,  fga=18, 3pm=2,  ftm=4, fta=6,  to=3
     *         plus_minus = +8  (team=58, opp=50: 58−50 = +8)
     *
     * Game 2: pts=18, reb=4, ast=7, stl=1, blk=0
     *         fgm=7,  fga=15, 3pm=1,  ftm=3, fta=4,  to=2
     *         plus_minus = −3  (team=45, opp=48: 45−48 = −3)
     *
     * plus_minus  = +8 + (−3)                         =   5
     * efficiency  = (37−14) + (30−11)                 = 42.0
     * eFG%        = (16 + 0.5×3) / 33 = 17.5/33       > 0.530
     * TS%         = 42 / (2×(33 + 0.44×10)) = 42/74.8 > 0.561
     */
    $dataset = [
        [
            'points'                => 24, 'rebounds' => 6, 'assists' => 4,
            'steals'                => 2,  'blocks'   => 1,
            'field_goals_made'      => 9,  'field_goals_attempted' => 18,
            'three_pointers_made'   => 2,
            'free_throws_made'      => 4,  'free_throws_attempted' => 6,
            'turnovers'             => 3,
            'plus_minus'            => 8,
        ],
        [
            'points'                => 18, 'rebounds' => 4, 'assists' => 7,
            'steals'                => 1,  'blocks'   => 0,
            'field_goals_made'      => 7,  'field_goals_attempted' => 15,
            'three_pointers_made'   => 1,
            'free_throws_made'      => 3,  'free_throws_attempted' => 4,
            'turnovers'             => 2,
            'plus_minus'            => -3,
        ],
    ];

    // +8 + (−3) = +5
    expect($this->service->computePlusMinus($dataset))->toBe(5);

    // (37−14) + (30−11) = 23 + 19 = 42.0
    expect($this->service->computeEfficiency($dataset))->toBe(42.0);

    // (16 + 0.5×3) / 33 = 17.5/33 ≈ 0.5303
    expect($this->service->computeEfgPercent($dataset))->toBeGreaterThan(0.530);

    // 42 / (2×(33 + 0.44×10)) = 42/74.8 ≈ 0.5615
    expect($this->service->computeTrueShooting($dataset))->toBeGreaterThan(0.561);
});
