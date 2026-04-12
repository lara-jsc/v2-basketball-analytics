<?php

use App\Models\PlayerHistory;

/*
|--------------------------------------------------------------------------
| Unit tests for PlayerHistory model computed accessors
|--------------------------------------------------------------------------
|
| Tests the three advanced-stat accessors and the formatted plus/minus
| accessor. Instances are created with `new PlayerHistory([...])` — the
| Laravel app is bootstrapped (uses Tests\TestCase) so Eloquent can resolve
| attribute casts, but no database reads or writes occur.
|
| Reference game used in tests 1–3:
|
|   pts=20, reb=8, ast=5, stl=2, blk=1
|   fgm=8,  fga=14, 3pm=2,  ftm=4, fta=5,  to=2
|
|   EFF     = (20+8+5+2+1) − ((14−8)+(5−4)+2)  = 36 − 9   = 27.0
|   eFG%    = (8 + 0.5×2) / 14                  = 9/14     ≈ 0.6429
|   TS%     = 20 / (2×(14 + 0.44×5))            = 20/32.4  ≈ 0.6173
|
*/

uses(\Tests\TestCase::class);

// ── Tests 1–3: EFF accessor ───────────────────────────────────────────────────

it('efficiency accessor computes correct EFF for a known game', function (): void {
    /*
     * Positive: pts(20) + reb(8) + ast(5) + stl(2) + blk(1) = 36
     * Negative: missedFG(14−8=6) + missedFT(5−4=1) + to(2)  =  9
     * EFF = 36 − 9 = 27.0
     */
    $history = new PlayerHistory([
        'points'                => 20,
        'rebounds'              => 8,
        'assists'               => 5,
        'steals'                => 2,
        'blocks'                => 1,
        'field_goals_made'      => 8,
        'field_goals_attempted' => 14,
        'free_throws_made'      => 4,
        'free_throws_attempted' => 5,
        'turnovers'             => 2,
    ]);

    expect($history->efficiency)->toBe(27.0);
});

it('efficiency accessor returns 0.0 for an all-zero game', function (): void {
    // No positive contributions, no negative contributions → EFF = 0.0
    $history = new PlayerHistory([
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
    ]);

    expect($history->efficiency)->toBe(0.0);
});

it('efficiency accessor returns a negative value when misses and turnovers dominate', function (): void {
    /*
     * Positive: pts(0) + reb(0) + ast(0) + stl(0) + blk(0) = 0
     * Negative: missedFG(5−0=5) + missedFT(3−0=3) + to(4)  = 12
     * EFF = 0 − 12 = −12.0
     */
    $history = new PlayerHistory([
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
    ]);

    expect($history->efficiency)->toBe(-12.0);
});

// ── Tests 4–6: eFG% accessor ──────────────────────────────────────────────────

it('efg_percent accessor computes correct eFG% for a known game', function (): void {
    /*
     * eFG% = (FGM + 0.5 × 3PM) / FGA
     *       = (8   + 0.5 × 2)  / 14
     *       = 9 / 14
     *       ≈ 0.6429
     */
    $history = new PlayerHistory([
        'field_goals_made'      => 8,
        'three_pointers_made'   => 2,
        'field_goals_attempted' => 14,
    ]);

    expect($history->efg_percent)->toBeGreaterThan(0.6428);
    expect($history->efg_percent)->toBeLessThan(0.6430);
});

it('efg_percent accessor returns 0.0 when FGA is zero', function (): void {
    $history = new PlayerHistory([
        'field_goals_made'      => 0,
        'three_pointers_made'   => 0,
        'field_goals_attempted' => 0,
    ]);

    expect($history->efg_percent)->toBe(0.0);
});

it('efg_percent accessor equals plain FG% when no three-pointers are made', function (): void {
    /*
     * When 3PM = 0: eFG% = (FGM + 0) / FGA = FGM / FGA = FG%
     * fgm=10, 3pm=0, fga=20 → eFG% = 10/20 = 0.5
     */
    $history = new PlayerHistory([
        'field_goals_made'      => 10,
        'three_pointers_made'   => 0,
        'field_goals_attempted' => 20,
    ]);

    expect($history->efg_percent)->toBe(0.5);
});

// ── Tests 7–9: TS% accessor ───────────────────────────────────────────────────

it('ts_percent accessor computes correct TS% for a known game', function (): void {
    /*
     * TS% = Pts / (2 × (FGA + 0.44 × FTA))
     *      = 20  / (2 × (14  + 0.44 × 5))
     *      = 20  / (2 × 16.2)
     *      = 20  / 32.4
     *      ≈ 0.6173
     */
    $history = new PlayerHistory([
        'points'                => 20,
        'field_goals_attempted' => 14,
        'free_throws_attempted' => 5,
    ]);

    expect($history->ts_percent)->toBeGreaterThan(0.6172);
    expect($history->ts_percent)->toBeLessThan(0.6174);
});

it('ts_percent accessor returns 0.0 when both FGA and FTA are zero', function (): void {
    $history = new PlayerHistory([
        'points'                => 0,
        'field_goals_attempted' => 0,
        'free_throws_attempted' => 0,
    ]);

    expect($history->ts_percent)->toBe(0.0);
});

it('ts_percent accessor handles a free-throw-only game (FGA=0)', function (): void {
    /*
     * TS% = Pts / (2 × (FGA + 0.44 × FTA))
     *      = 8   / (2 × (0   + 0.44 × 10))
     *      = 8   / (2 × 4.4)
     *      = 8   / 8.8
     *      ≈ 0.9091
     */
    $history = new PlayerHistory([
        'points'                => 8,
        'field_goals_attempted' => 0,
        'free_throws_attempted' => 10,
    ]);

    expect($history->ts_percent)->toBeGreaterThan(0.9090);
    expect($history->ts_percent)->toBeLessThan(0.9092);
});

// ── Tests 10–13: formatted_plus_minus accessor ────────────────────────────────

it('formatted_plus_minus accessor prefixes positive values with a plus sign', function (): void {
    $history = new PlayerHistory(['plus_minus' => 14]);
    expect($history->formatted_plus_minus)->toBe('+14');
});

it('formatted_plus_minus accessor returns the raw string for negative values', function (): void {
    $history = new PlayerHistory(['plus_minus' => -3]);
    expect($history->formatted_plus_minus)->toBe('-3');
});

it('formatted_plus_minus accessor returns "0" for an even game', function (): void {
    $history = new PlayerHistory(['plus_minus' => 0]);
    expect($history->formatted_plus_minus)->toBe('0');
});

it('formatted_plus_minus accessor treats null plus_minus as zero', function (): void {
    $history = new PlayerHistory(['plus_minus' => null]);
    expect($history->formatted_plus_minus)->toBe('0');
});
