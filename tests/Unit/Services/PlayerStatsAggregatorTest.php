<?php

use App\Models\PlayerHistory;
use App\Services\PlayerStatsAggregator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Build a PlayerHistory model with the given attributes (no DB).
 *
 * @param  array<string, mixed>  $attrs
 */
function makeHistory(array $attrs): PlayerHistory
{
    return new PlayerHistory(array_merge([
        'player_id' => 1,
        'playing_team_id' => 1,
        'opponent_team_id' => 2,
        'game_date' => '2024-01-01',
        'position_played' => 'PG',
        'minutes_played' => 30,
        'points' => 0,
        'field_goals_made' => 0,
        'field_goals_attempted' => 0,
        'three_pointers_made' => 0,
        'three_pointers_attempted' => 0,
        'free_throws_made' => 0,
        'free_throws_attempted' => 0,
        'rebounds' => 0,
        'offensive_rebounds' => 0,
        'defensive_rebounds' => 0,
        'assists' => 0,
        'steals' => 0,
        'blocks' => 0,
        'turnovers' => 0,
        'personal_fouls' => 0,
        'flagrant_fouls' => 0,
        'technical_fouls' => 0,
        'ejections' => 0,
        'disqualifications' => 0,
        'is_started' => false,
    ], $attrs));
}

describe('PlayerStatsAggregator', function () {

    beforeEach(function () {
        $this->agg = new PlayerStatsAggregator;
    });

    // ------------------------------------------------------------------
    // Empty collection
    // ------------------------------------------------------------------
    describe('empty collection', function () {
        it('returns zeroed stats when no history rows exist', function () {
            $result = $this->agg->compute(1, new Collection);

            expect($result['gp'])->toBe(0);
            expect($result['gs'])->toBe(0);
            expect($result['pts'])->toBeNull();
            expect($result['fg'])->toBe('0-0');
            expect($result['plus_minus'])->toBeNull();
        });
    });

    // ------------------------------------------------------------------
    // Games played / games started
    // ------------------------------------------------------------------
    describe('gp and gs counts', function () {
        it('counts all rows as games played', function () {
            $histories = new Collection([
                makeHistory([]),
                makeHistory([]),
                makeHistory([]),
            ]);

            expect($this->agg->compute(1, $histories)['gp'])->toBe(3);
        });

        it('counts only is_started=true rows as games started', function () {
            $histories = new Collection([
                makeHistory(['is_started' => true]),
                makeHistory(['is_started' => false]),
                makeHistory(['is_started' => true]),
            ]);

            expect($this->agg->compute(1, $histories)['gs'])->toBe(2);
        });
    });

    // ------------------------------------------------------------------
    // Per-game averages
    // ------------------------------------------------------------------
    describe('per-game averages', function () {
        it('calculates pts, reb, ast averages correctly', function () {
            $histories = new Collection([
                makeHistory(['points' => 20, 'rebounds' => 10, 'assists' => 5]),
                makeHistory(['points' => 10, 'rebounds' => 6,  'assists' => 3]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['pts'])->toBe(15.0);
            expect($result['reb'])->toBe(8.0);
            expect($result['ast'])->toBe(4.0);
        });

        it('rounds averages to 2 decimal places', function () {
            $histories = new Collection([
                makeHistory(['points' => 10]),
                makeHistory(['points' => 20]),
                makeHistory(['points' => 11]),
            ]);

            $result = $this->agg->compute(1, $histories);

            // 41 / 3 = 13.666...  → rounds to 13.67
            expect($result['pts'])->toBe(13.67);
        });

        it('accumulates cumulative counts (flags, techs, ejections, dqs)', function () {
            $histories = new Collection([
                makeHistory(['flagrant_fouls' => 1, 'technical_fouls' => 2, 'ejections' => 0, 'disqualifications' => 0]),
                makeHistory(['flagrant_fouls' => 0, 'technical_fouls' => 1, 'ejections' => 1, 'disqualifications' => 0]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['flag'])->toBe(1);
            expect($result['tech'])->toBe(3);
            expect($result['eject'])->toBe(1);
            expect($result['dq'])->toBe(0);
        });
    });

    // ------------------------------------------------------------------
    // Shooting percentages
    // ------------------------------------------------------------------
    describe('shooting percentages', function () {
        it('computes fg_pct from total made / total attempted', function () {
            // Game 1: 4/8, Game 2: 6/10 → totals 10/18 = 0.5556
            $histories = new Collection([
                makeHistory(['field_goals_made' => 4, 'field_goals_attempted' => 8]),
                makeHistory(['field_goals_made' => 6, 'field_goals_attempted' => 10]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['fg_pct'])->toBe(0.5556);
            expect($result['fg'])->toBe('10-18');
        });

        it('computes ft_pct from total free throw totals', function () {
            $histories = new Collection([
                makeHistory(['free_throws_made' => 6, 'free_throws_attempted' => 8]),
                makeHistory(['free_throws_made' => 2, 'free_throws_attempted' => 4]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['ft_pct'])->toBe(0.6667);
            expect($result['ft'])->toBe('8-12');
        });

        it('computes three_p_pct correctly', function () {
            $histories = new Collection([
                makeHistory(['three_pointers_made' => 3, 'three_pointers_attempted' => 6]),
                makeHistory(['three_pointers_made' => 1, 'three_pointers_attempted' => 4]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['three_p_pct'])->toBe(0.4);
            expect($result['three_pt'])->toBe('4-10');
        });

        it('returns null percentages when no attempts recorded', function () {
            $result = $this->agg->compute(1, new Collection([makeHistory([])]));

            expect($result['fg_pct'])->toBeNull();
            expect($result['ft_pct'])->toBeNull();
            expect($result['three_p_pct'])->toBeNull();
        });
    });

    // ------------------------------------------------------------------
    // Ratio stats
    // ------------------------------------------------------------------
    describe('ratio stats', function () {
        it('computes ast_to (assists to turnover ratio)', function () {
            // avg ast = (8+4)/2=6, avg tov = (2+2)/2=2 → 6/2=3.0
            $histories = new Collection([
                makeHistory(['assists' => 8, 'turnovers' => 2]),
                makeHistory(['assists' => 4, 'turnovers' => 2]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['ast_to'])->toBe(3.0);
        });

        it('returns null ast_to when turnovers are zero', function () {
            $histories = new Collection([
                makeHistory(['assists' => 5, 'turnovers' => 0]),
            ]);

            expect($this->agg->compute(1, $histories)['ast_to'])->toBeNull();
        });

        it('computes stl_to (steals to turnover ratio)', function () {
            $histories = new Collection([
                makeHistory(['steals' => 4, 'turnovers' => 2]),
                makeHistory(['steals' => 2, 'turnovers' => 2]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['stl_to'])->toBe(1.5);
        });
    });

    // ------------------------------------------------------------------
    // Advanced stats: EFF, eFG%, TS%
    // ------------------------------------------------------------------
    describe('advanced stats', function () {
        it('computes EFF correctly', function () {
            // EFF = pts + reb + ast + stl + blk − missedFG − missedFT − TO
            // Single game: pts=20, reb=8, ast=5, stl=2, blk=1
            // fgm=8, fga=15 → missedFG=7; ftm=4, fta=5 → missedFT=1; TO=3
            // EFF = 20+8+5+2+1 - 7 - 1 - 3 = 25
            $histories = new Collection([
                makeHistory([
                    'points' => 20,
                    'rebounds' => 8,
                    'assists' => 5,
                    'steals' => 2,
                    'blocks' => 1,
                    'field_goals_made' => 8,
                    'field_goals_attempted' => 15,
                    'free_throws_made' => 4,
                    'free_throws_attempted' => 5,
                    'turnovers' => 3,
                ]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['eff'])->toBe(25.0);
        });

        it('computes eFG% correctly — (FGM + 0.5×3PM) / FGA', function () {
            // FGM=8, 3PM=3, FGA=15 → (8 + 1.5) / 15 = 0.6333
            $histories = new Collection([
                makeHistory([
                    'field_goals_made' => 8,
                    'field_goals_attempted' => 15,
                    'three_pointers_made' => 3,
                    'three_pointers_attempted' => 6,
                ]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['efg_pct'])->toBe(0.6333);
        });

        it('computes TS% correctly — PTS / (2 × (FGA + 0.44×FTA))', function () {
            // pts=20, fga=15, fta=5
            // denominator = 2 × (15 + 0.44×5) = 2 × 17.2 = 34.4
            // TS% = 20 / 34.4 = 0.5814
            $histories = new Collection([
                makeHistory([
                    'points' => 20,
                    'field_goals_attempted' => 15,
                    'free_throws_attempted' => 5,
                ]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['ts_pct'])->toBe(0.5814);
        });

        it('returns null eFG% and TS% when FGA is zero', function () {
            $result = $this->agg->compute(1, new Collection([makeHistory([])]));

            expect($result['efg_pct'])->toBeNull();
            expect($result['ts_pct'])->toBeNull();
        });
    });

    // ------------------------------------------------------------------
    // Scoring efficiency and shooting efficiency
    // ------------------------------------------------------------------
    describe('sc_eff and sh_eff', function () {
        it('computes sc_eff as pts per avg FG attempt', function () {
            // pts_avg=20, fga_total=15 (1 game) → sc_eff = 20/15 = 1.33
            $histories = new Collection([
                makeHistory([
                    'points' => 20,
                    'field_goals_attempted' => 15,
                ]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['sc_eff'])->toBe(1.33);
        });

        it('computes sh_eff: (FGM + 0.5×3PM + 0.44×FTM - FGA) / FGA', function () {
            // FGM=8, 3PM=3, FTM=4, FGA=15
            // (8 + 1.5 + 1.76 - 15) / 15 = -3.74/15 = -0.2493
            $histories = new Collection([
                makeHistory([
                    'field_goals_made' => 8,
                    'field_goals_attempted' => 15,
                    'three_pointers_made' => 3,
                    'free_throws_made' => 4,
                ]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['sh_eff'])->toBe(-0.2493);
        });
    });

    // ------------------------------------------------------------------
    // Double-doubles / Triple-doubles
    // ------------------------------------------------------------------
    describe('double-doubles and triple-doubles', function () {
        it('counts a game with 2 stats >= 10 as a double-double', function () {
            $histories = new Collection([
                makeHistory(['points' => 20, 'rebounds' => 10, 'assists' => 5]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['dd2'])->toBe(1);
            expect($result['td3'])->toBe(0);
        });

        it('counts a game with 3 stats >= 10 as both a triple-double and a double-double', function () {
            $histories = new Collection([
                makeHistory(['points' => 20, 'rebounds' => 10, 'assists' => 10]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['dd2'])->toBe(1);
            expect($result['td3'])->toBe(1);
        });

        it('does not count a game with only 1 stat >= 10', function () {
            $histories = new Collection([
                makeHistory(['points' => 30, 'rebounds' => 5, 'assists' => 4]),
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['dd2'])->toBe(0);
            expect($result['td3'])->toBe(0);
        });

        it('accumulates dd2 and td3 across multiple games', function () {
            $histories = new Collection([
                makeHistory(['points' => 20, 'rebounds' => 10, 'assists' => 4]),   // dd2
                makeHistory(['points' => 10, 'rebounds' => 10, 'assists' => 10]),  // td3 + dd2
                makeHistory(['points' => 5,  'rebounds' => 5,  'assists' => 4]),   // none
            ]);

            $result = $this->agg->compute(1, $histories);

            expect($result['dd2'])->toBe(2);
            expect($result['td3'])->toBe(1);
        });
    });

    // ------------------------------------------------------------------
    // Mode position
    // ------------------------------------------------------------------
    describe('mode position (pc)', function () {
        it('picks the most frequently played position', function () {
            $histories = new Collection([
                makeHistory(['position_played' => 'PG']),
                makeHistory(['position_played' => 'PG']),
                makeHistory(['position_played' => 'SG']),
            ]);

            expect($this->agg->compute(1, $histories)['pc'])->toBe('PG');
        });

        it('returns null when all position values are null', function () {
            $histories = new Collection([
                makeHistory(['position_played' => null]),
                makeHistory(['position_played' => null]),
            ]);

            expect($this->agg->compute(1, $histories)['pc'])->toBeNull();
        });
    });

    // ------------------------------------------------------------------
    // plus_minus is always null from aggregator
    // ------------------------------------------------------------------
    it('always returns null for plus_minus — BPM Job handles this separately', function () {
        $histories = new Collection([
            makeHistory(['points' => 30]),
        ]);

        expect($this->agg->compute(1, $histories)['plus_minus'])->toBeNull();
    });
});
