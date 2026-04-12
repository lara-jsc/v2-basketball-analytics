<?php

use App\Services\PlayerStatisticsService;

/*
|--------------------------------------------------------------------------
| Pure-math unit tests for PlayerStatisticsService
|--------------------------------------------------------------------------
|
| No database, no HTTP, no Laravel container. These tests verify the four
| computation methods produce mathematically correct results for known inputs.
|
| Plus/minus input format: each game record is an associative array whose
| keys match PlayerHistory column names. The service sums the pre-stored
| plus_minus value — it does not re-derive it from scoring columns.
|
*/

beforeEach(function (): void {
    $this->service = new PlayerStatisticsService();
});

// ── computePlusMinus ──────────────────────────────────────────────────────────

it('computes plus/minus for a single positive game', function (): void {
    // team_pts (58) − opp_pts (50) = +8, stored as plus_minus on the row
    $histories = [
        ['plus_minus' => 8],
    ];

    expect($this->service->computePlusMinus($histories))->toBe(8);
});

it('sums plus/minus across multiple games', function (): void {
    // +8 + (−3) + (+7) = +12
    $histories = [
        ['plus_minus' =>  8],
        ['plus_minus' => -3],
        ['plus_minus' =>  7],
    ];

    expect($this->service->computePlusMinus($histories))->toBe(12);
});

it('returns a negative total when all games are negative', function (): void {
    // −20 + (−15) = −35
    $histories = [
        ['plus_minus' => -20],
        ['plus_minus' => -15],
    ];

    expect($this->service->computePlusMinus($histories))->toBe(-35);
});

it('returns zero when every game is perfectly even', function (): void {
    $histories = [
        ['plus_minus' => 0],
        ['plus_minus' => 0],
    ];

    expect($this->service->computePlusMinus($histories))->toBe(0);
});

it('returns zero for an empty history array', function (): void {
    expect($this->service->computePlusMinus([]))->toBe(0);
});

// ── computeEfficiency ─────────────────────────────────────────────────────────

it('computes EFF for a single game', function (): void {
    /*
     * Positive: pts(24) + reb(6) + ast(4) + stl(2) + blk(1) = 37
     * Negative: missedFG(18−9=9) + missedFT(2−0=2) + to(3)  = 14
     * EFF = 37 − 14 = 23.0
     */
    $histories = [
        [
            'points'                 => 24,
            'rebounds'               => 6,
            'assists'                => 4,
            'steals'                 => 2,
            'blocks'                 => 1,
            'field_goals_made'       => 9,
            'field_goals_attempted'  => 18,
            'free_throws_made'       => 0,
            'free_throws_attempted'  => 2,
            'turnovers'              => 3,
        ],
    ];

    expect($this->service->computeEfficiency($histories))->toBe(23.0);
});

it('sums EFF across multiple games', function (): void {
    /*
     * Game 1: (24+6+4+2+1) − (9+2+3) = 37 − 14 = 23
     * Game 2: (18+6+3+1+1) − (6+2+2) = 29 − 10 = 19
     * Total EFF = 42.0
     */
    $game1 = [
        'points'                => 24, 'rebounds' => 6, 'assists' => 4,
        'steals'                => 2,  'blocks'   => 1,
        'field_goals_made'      => 9,  'field_goals_attempted' => 18,
        'free_throws_made'      => 0,  'free_throws_attempted' => 2,
        'turnovers'             => 3,
    ];

    $game2 = [
        'points'                => 18, 'rebounds' => 6, 'assists' => 3,
        'steals'                => 1,  'blocks'   => 1,
        'field_goals_made'      => 8,  'field_goals_attempted' => 14,
        'free_throws_made'      => 0,  'free_throws_attempted' => 2,
        'turnovers'             => 2,
    ];

    expect($this->service->computeEfficiency([$game1, $game2]))->toBe(42.0);
});

// ── computeEfgPercent ─────────────────────────────────────────────────────────

it('computes eFG% across multiple games', function (): void {
    /*
     * Game 1: fgm=9,  3pm=2, fga=17
     * Game 2: fgm=7,  3pm=1, fga=16
     * Totals: fgm=16, 3pm=3, fga=33
     * eFG% = (16 + 0.5×3) / 33 = 17.5 / 33 ≈ 0.5303
     */
    $game1 = ['field_goals_made' => 9,  'three_pointers_made' => 2, 'field_goals_attempted' => 17];
    $game2 = ['field_goals_made' => 7,  'three_pointers_made' => 1, 'field_goals_attempted' => 16];

    expect($this->service->computeEfgPercent([$game1, $game2]))->toBe(0.5303);
});

// ── computeTrueShooting ───────────────────────────────────────────────────────

it('computes TS% across multiple games', function (): void {
    /*
     * Game 1: pts=24, fga=18, fta=6
     * Game 2: pts=18, fga=15, fta=4
     * Totals: pts=42, fga=33, fta=10
     * TS% = 42 / (2 × (33 + 0.44×10)) = 42 / 74.8 ≈ 0.5615
     */
    $game1 = ['points' => 24, 'field_goals_attempted' => 18, 'free_throws_attempted' => 6];
    $game2 = ['points' => 18, 'field_goals_attempted' => 15, 'free_throws_attempted' => 4];

    expect($this->service->computeTrueShooting([$game1, $game2]))->toBe(0.5615);
});

// ── All four metrics on a shared dataset ─────────────────────────────────────

it('computes all four metrics correctly on the same two-game dataset', function (): void {
    /*
     * Game 1: pts=24, reb=6, ast=4, stl=2, blk=1
     *         fgm=9,  fga=18, 3pm=2, ftm=2, fta=4, to=3, plus_minus=+8
     *
     * Game 2: pts=18, reb=6, ast=3, stl=1, blk=1
     *         fgm=8,  fga=15, 3pm=1, ftm=3, fta=6, to=2, plus_minus=−3
     *
     * Expected:
     *   computePlusMinus  → +8 + (−3)              = 5
     *   computeEfficiency → (37−14) + (29−12)       = 23 + 17 = 40.0
     *                        (37 = 24+6+4+2+1, neg_1 = (18−9)+(4−2)+3 = 14)
     *                        (29 = 18+6+3+1+1, neg_2 = (15−8)+(6−3)+2 = 12)
     *   computeEfgPercent → (17 + 0.5×3) / 33      = 18.5/33 ≈ 0.5606
     *   computeTrueShooting → 42 / (2×(33+0.44×10)) = 42/74.8 ≈ 0.5615
     */
    $dataset = [
        [
            'points'                 => 24, 'rebounds' => 6, 'assists' => 4,
            'steals'                 => 2,  'blocks'   => 1,
            'field_goals_made'       => 9,  'field_goals_attempted'   => 18,
            'three_pointers_made'    => 2,
            'free_throws_made'       => 2,  'free_throws_attempted'   => 4,
            'turnovers'              => 3,  'plus_minus'              => 8,
        ],
        [
            'points'                 => 18, 'rebounds' => 6, 'assists' => 3,
            'steals'                 => 1,  'blocks'   => 1,
            'field_goals_made'       => 8,  'field_goals_attempted'   => 15,
            'three_pointers_made'    => 1,
            'free_throws_made'       => 3,  'free_throws_attempted'   => 6,
            'turnovers'              => 2,  'plus_minus'              => -3,
        ],
    ];

    expect($this->service->computePlusMinus($dataset))->toBe(5);
    expect($this->service->computeEfficiency($dataset))->toBe(40.0);
    expect($this->service->computeEfgPercent($dataset))->toBe(0.5606);
    expect($this->service->computeTrueShooting($dataset))->toBe(0.5615);
});
