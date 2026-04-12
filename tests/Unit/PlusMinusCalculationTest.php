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

// ── Tests 11–13: computePlusMinus — additional edge cases ────────────────────

it('treats null plus_minus values as 0 when summing', function (): void {
    // Only game 2 and game 4 have real values: +5 + (−2) = +3
    expect(
        $this->service->computePlusMinus([
            ['plus_minus' => null],
            ['plus_minus' =>  5],
            ['plus_minus' => null],
            ['plus_minus' => -2],
        ])
    )->toBe(3);
});

it('handles a single-game plus/minus of zero', function (): void {
    // team=50, opp=50 → 50 − 50 = 0
    expect(
        $this->service->computePlusMinus([['plus_minus' => 0]])
    )->toBe(0);
});

it('accumulates plus/minus correctly across a five-game stretch', function (): void {
    // +10 + (−4) + +6 + (−8) + +3 = +7
    expect(
        $this->service->computePlusMinus([
            ['plus_minus' =>  10],
            ['plus_minus' =>  -4],
            ['plus_minus' =>   6],
            ['plus_minus' =>  -8],
            ['plus_minus' =>   3],
        ])
    )->toBe(7);
});

// ── Tests 14–16: computeEfficiency — additional edge cases ───────────────────

it('computeEfficiency returns 0.0 for an empty history array', function (): void {
    expect($this->service->computeEfficiency([]))->toBe(0.0);
});

it('computeEfficiency returns a negative value when misses and turnovers dominate', function (): void {
    /*
     * Positive: pts(0) + reb(0) + ast(0) + stl(0) + blk(0) = 0
     * Negative: missedFG(5−0=5) + missedFT(3−0=3) + to(4)  = 12
     * EFF = 0 − 12 = −12.0
     */
    expect(
        $this->service->computeEfficiency([[
            'points'                => 0,
            'rebounds'              => 0,
            'assists'               => 0,
            'steals'                => 0,
            'blocks'                => 0,
            'field_goals_made'      => 0,
            'field_goals_attempted' => 5,
            'free_throws_made'      => 0,
            'free_throws_attempted' => 3,
            'turnovers'             => 4,
        ]])
    )->toBe(-12.0);
});

it('computeEfficiency returns 0.0 for an all-zero stat line', function (): void {
    expect(
        $this->service->computeEfficiency([[
            'points'                => 0,
            'rebounds'              => 0,
            'assists'               => 0,
            'steals'                => 0,
            'blocks'                => 0,
            'field_goals_made'      => 0,
            'field_goals_attempted' => 0,
            'free_throws_made'      => 0,
            'free_throws_attempted' => 0,
            'turnovers'             => 0,
        ]])
    )->toBe(0.0);
});

// ── Tests 17–18: computeEfgPercent — additional edge cases ───────────────────

it('computeEfgPercent equals plain FG% when no three-pointers are made', function (): void {
    /*
     * When 3PM = 0: eFG% = (FGM + 0) / FGA = FGM / FGA
     * fgm=10, 3pm=0, fga=20 → eFG% = 10/20 = 0.5
     */
    expect(
        $this->service->computeEfgPercent([[
            'field_goals_made'      => 10,
            'three_pointers_made'   => 0,
            'field_goals_attempted' => 20,
        ]])
    )->toBe(0.5);
});

it('computeEfgPercent gives a bonus over plain FG% when three-pointers are made', function (): void {
    /*
     * All makes are 3-pointers: fgm=5, 3pm=5, fga=10
     * eFG% = (5 + 0.5×5) / 10 = 7.5 / 10 = 0.75
     * Plain FG% would be 5/10 = 0.50 — the 0.25 gap confirms the 3PM bonus.
     */
    expect(
        $this->service->computeEfgPercent([[
            'field_goals_made'      => 5,
            'three_pointers_made'   => 5,
            'field_goals_attempted' => 10,
        ]])
    )->toBe(0.75);
});

// ── Tests 19–20: computeTrueShooting — additional edge cases ─────────────────

it('computeTrueShooting handles a free-throw-only game (FGA=0, FTA>0)', function (): void {
    /*
     * TS% = Pts / (2 × (FGA + 0.44 × FTA))
     *      = 8   / (2 × (0   + 0.44 × 10))
     *      = 8   / 8.8
     *      ≈ 0.9091
     */
    $result = $this->service->computeTrueShooting([[
        'points'                => 8,
        'field_goals_attempted' => 0,
        'free_throws_attempted' => 10,
    ]]);

    expect($result)->toBeGreaterThan(0.9090);
    expect($result)->toBeLessThan(0.9092);
});

it('computeTrueShooting returns an exact value for a single well-defined game', function (): void {
    /*
     * pts=28, fga=20, fta=6
     * denom = 2 × (20 + 0.44×6) = 2 × 22.64 = 45.28
     * TS%   = 28 / 45.28 ≈ 0.6183
     */
    $result = $this->service->computeTrueShooting([[
        'points'                => 28,
        'field_goals_attempted' => 20,
        'free_throws_attempted' => 6,
    ]]);

    expect($result)->toBeGreaterThan(0.6182);
    expect($result)->toBeLessThan(0.6185);
});
