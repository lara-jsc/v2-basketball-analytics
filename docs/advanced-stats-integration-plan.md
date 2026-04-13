# Advanced Stats Integration Plan: EFF, eFG%, and TS%

## Overview

This document outlines the implementation plan for integrating three standard basketball statistical formulas — **Efficiency (EFF)**, **Effective Field Goal Percentage (eFG%)**, and **True Shooting Percentage (TS%)** — into the existing HoopSense+ analytics system.

---

## Formula Definitions

### 1. Efficiency (EFF)

```
EFF = Pts + Reb + Ast + Stl + Blk − (FGA − FGM) − (FTA − FTM) − TO
```

Where `(FGA − FGM)` = Missed Field Goals and `(FTA − FTM)` = Missed Free Throws.

**What it measures:** An all-in-one player efficiency metric that rewards positive contributions (scoring, rebounding, playmaking, defense) and penalizes inefficiency (missed shots, turnovers). Negative EFF is valid and indicates an inefficient or foul-prone player.

---

### 2. Effective Field Goal Percentage (eFG%)

```
eFG% = (FGM + 0.5 × 3PM) / FGA
```

**What it measures:** An adjusted field goal percentage that accounts for the added value of three-pointers. A player shooting 33.3% on all threes is equally efficient as one shooting 50% on all twos — eFG% makes that equivalence visible.

**Guard:** Return `null` if `FGA == 0`.

---

### 3. True Shooting Percentage (TS%)

```
TS% = Pts / (2 × (FGA + 0.44 × FTA))
```

The `0.44` coefficient on FTA accounts for the fact that not every foul drawn results in two free throws (and-ones, technical free throws, three-point fouls).

**What it measures:** The most complete shooting efficiency metric — combines field goals, three-pointers, and free throws into a single efficiency number.

**Guard:** Return `null` if `(2 × (FGA + 0.44 × FTA)) == 0`.

---

### Quick Comparison

| Metric | Accounts for 3PT value | Accounts for FTs | Penalizes misses |
|--------|------------------------|------------------|-----------------|
| FG%    | No                     | No               | Implicitly      |
| eFG%   | Yes                    | No               | Implicitly      |
| TS%    | Yes                    | Yes              | Implicitly      |
| EFF    | Yes (via Pts)          | Yes (via Pts)    | Explicitly      |

---

## What Already Exists

All inputs these formulas require are already aggregated in `PlayerStatsAggregator::compute()`:

| Input | Source in Aggregator |
|-------|----------------------|
| `pts`, `reb`, `ast`, `stl`, `blk`, `to_per_game` | Already computed as per-game averages |
| `fgm`, `fga` | Already parsed from `fg` (made-attempted string e.g. `"8-15"`) |
| `ftm`, `fta` | Already parsed from `ft` |
| `3pm`, `3pa` | Already parsed from `three_pt` |

The system also already has `sc_eff` (scoring efficiency = pts/fga) and `sh_eff` (shooting efficiency) in `player_stats`. The three new formulas extend this existing pattern.

---

## Where Each Formula Lives

These formulas are **pure arithmetic** derived from already-aggregated stats. No probabilistic model or optimization is involved. They belong in the **PHP service layer**, not the Python engine.

> Python is reserved for BPM, win probability, lineup optimization — models with weights and logistic functions. Adding these to Python would add unnecessary subprocess round-trips with zero analytical benefit.

---

## Step-by-Step Implementation

### Step 1 — Database Migration

**Create:** `database/migrations/YYYY_MM_DD_add_advanced_stats_to_player_stats.php`

```php
Schema::table('player_stats', function (Blueprint $table) {
    $table->decimal('eff', 6, 2)->nullable()->after('sh_eff');
    $table->decimal('efg_pct', 5, 2)->nullable()->after('eff');
    $table->decimal('ts_pct', 5, 2)->nullable()->after('efg_pct');
});
```

All three columns are **nullable** — existing rows won't have values until `RebuildPlayerStats` re-runs for each player. This matches the established `plus_minus` nullable pattern.

---

### Step 2 — PHP Service Layer

**File:** `app/Services/PlayerStatsAggregator.php` — `compute()` method

Add the three computations after the existing `sh_eff` / `sc_eff` calculations:

```php
// --- Missed shots (per game averages) ---
$missedFg = $fgaAvg - $fgmAvg;
$missedFt = $ftaAvg - $ftmAvg;

// EFF
$eff = $pts + $reb + $ast + $stl + $blk - $missedFg - $missedFt - $toPg;

// eFG%
$efgPct = $fgaAvg > 0
    ? min(($fgmAvg + 0.5 * $threePmAvg) / $fgaAvg, 1.0)
    : null;

// TS%
$tsDenominator = 2 * ($fgaAvg + 0.44 * $ftaAvg);
$tsPct = $tsDenominator > 0
    ? min($pts / $tsDenominator, 1.0)
    : null;
```

Include in the returned array:
```php
'eff'     => round($eff, 2),
'efg_pct' => $efgPct !== null ? round($efgPct, 4) : null,
'ts_pct'  => $tsPct  !== null ? round($tsPct, 4)  : null,
```

---

### Step 3 — No New Jobs Needed

The existing `RebuildPlayerStats` job already:
1. Re-aggregates all history rows via `PlayerStatsAggregator::compute()`
2. Upserts the full `player_stats` row
3. Then dispatches `ComputePlayerPlusMinus`

Once Step 2 is complete, all three new fields are automatically populated whenever `RebuildPlayerStats` fires — CSV import, player history create/update/delete, and manual player edits are all covered.

---

### Step 4 — Model Update

**File:** `app/Models/PlayerStat.php`

Add to `$fillable`:
```php
'eff', 'efg_pct', 'ts_pct',
```

Add to `$casts`:
```php
'eff'     => 'float',
'efg_pct' => 'float',
'ts_pct'  => 'float',
```

---

### Step 5 — TypeScript Types

**File:** `resources/js/types/index.ts` — `PlayerStat` interface

```ts
eff:     number | null;
efg_pct: number | null;
ts_pct:  number | null;
```

Follow the existing `plus_minus: number | null` pattern — always nullable until computed.

---

### Step 6 — Frontend Display

#### 6a. Player Stats Table (Teams & Players page)

Add three columns after `SH-EFF`:

| Column Header | Field | Format |
|---|---|---|
| `EFF` | `eff` | One decimal — display `—` if null |
| `eFG%` | `efg_pct` | Multiply by 100, one decimal, append `%` — display `—` if null |
| `TS%` | `ts_pct` | Multiply by 100, one decimal, append `%` — display `—` if null |

> Never display `0` for a null stat. Use `—` — consistent with the established `plus_minus` null convention.

#### 6b. Player Matchup Table

**File:** `resources/js/Components/features/comparison/PlayerMatchupTable.tsx`

Add three rows to the existing stat comparison table following the same amber-highlight-on-stronger-value pattern already used for all other rows.

#### 6c. Player Matchup Python Engine

**File:** `analytics/win_probability/model.py` — `compute_player_matchup()`

Add the three new fields to the comparable stats list:

```python
COMPARABLE_STATS = [
    # existing stats ...
    "eff", "efg_pct", "ts_pct",  # ADD
]
```

Since these are stored values passed in the payload, no formula re-implementation is needed in Python — the engine compares them the same way as every other stat.

---

### Step 7 — Backfill Existing Data

After deploying the migration, re-trigger `RebuildPlayerStats` for all players that have existing history rows. This can be run via `php artisan tinker` or a one-time artisan command:

```php
Player::whereHas('histories')->each(function (Player $player) {
    RebuildPlayerStats::dispatch($player->id);
});
```

This regenerates aggregated stats (now including EFF, eFG%, TS%) and automatically re-triggers BPM computation for every player.

---

## Edge Cases

| Case | Location | Handling |
|---|---|---|
| `fga_avg == 0` | `PlayerStatsAggregator` | `efg_pct = null`, `ts_pct = null` |
| `(2 × (fga + 0.44 × fta)) == 0` | `PlayerStatsAggregator` | `ts_pct = null` |
| `eff` is negative | `PlayerStatsAggregator` | Allow — negative EFF is statistically valid |
| `efg_pct > 1.0` | `PlayerStatsAggregator` | Clamp to `1.0` — guards malformed history data |
| `ts_pct > 1.0` | `PlayerStatsAggregator` | Clamp to `1.0` — same reason |
| Player has no history rows | `RebuildPlayerStats` | No-op — `player_stats` row retains previous values |
| Null display on frontend | All stat tables | Display `—`, never `0` or blank |

---

## Validation Strategy

### Unit Test — `PlayerStatsAggregator::compute()`

Use known inputs to assert expected outputs:

```
Inputs (single game):
  pts=20, reb=5, ast=4, stl=1, blk=1
  fg="8-15", ft="4-6", three_pt="2-5", to=3

Expected:
  EFF   = 20 + 5 + 4 + 1 + 1 − (15−8) − (6−4) − 3
        = 31 − 7 − 2 − 3 = 19

  eFG%  = (8 + 0.5 × 2) / 15
        = 9 / 15 = 0.6000 (60.0%)

  TS%   = 20 / (2 × (15 + 0.44 × 6))
        = 20 / (2 × 17.64)
        = 20 / 35.28 ≈ 0.5669 (56.7%)
```

### Cross-Validation

Cross-check computed values against [Basketball-Reference](https://www.basketball-reference.com) for known players with public stat lines. This validates that per-game average inputs produce sensible outputs at the aggregated level.

---

## Summary: What Changes Where

```
database/migrations/
  └── YYYY_MM_DD_add_advanced_stats_to_player_stats.php   ADD 3 nullable columns

app/Models/
  └── PlayerStat.php                                       ADD to $fillable and $casts

app/Services/
  └── PlayerStatsAggregator.php                            ADD 3 formula computations in compute()

analytics/
  └── win_probability/model.py                             ADD eff, efg_pct, ts_pct to COMPARABLE_STATS

resources/js/
  ├── types/index.ts                                       ADD 3 nullable fields to PlayerStat interface
  └── Components/features/
      ├── comparison/PlayerMatchupTable.tsx                ADD 3 stat rows with amber highlight
      └── players/ (stats table component)                ADD 3 columns with null guard display
```

**Unchanged:** Jobs, Repositories, Controllers, Python BPM/Lineup engines, caching layer — the existing data flow handles propagation automatically.
