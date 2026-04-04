# Player Histories Feature Spec

## Overview

`player_histories` is a per-game raw stat log for every player. Each row represents a single game a player participated in. `player_stats` is then **derived** — averaged and computed — from this history table, rather than uploaded directly via CSV.

This enables:
- Historical tracking of individual game performances
- Accurate aggregate statistics (averages computed from real game data)
- Cross-team tracking (a player may have played for different teams across games)
- Richer Python analytics input — per-game data instead of pre-averaged totals

---

## Relationship to Existing Tables

```
players ──< player_histories >── teams (playing_team_id)
                       │
                       └──────── teams (opponent_team_id)

player_histories ──► player_stats (derived/aggregated via Service layer)
```

- `player_histories` stores **raw per-game counting stats**
- `player_stats` stores **computed averages** — always recalculated from `player_histories` when new game data is added
- `plus_minus` on `player_stats` is still Python-computed (dispatched after aggregation)

---

## Database Schema — `player_histories`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `player_id` | bigint FK → `players.id` | The player this record belongs to |
| `playing_team_id` | bigint FK → `teams.id` | Team the player suited up for in this game |
| `opponent_team_id` | bigint FK → `teams.id` | Team they played against |
| `game_date` | date | Date the game was played (required) |
| `position_played` | varchar(50) nullable | Position on court for this game (e.g. `PG`, `SF`) |
| `minutes_played` | decimal(5,2) nullable | Raw minutes played this game |
| `points` | tinyint unsigned nullable | Raw points scored |
| `field_goals_made` | tinyint unsigned nullable | FG made |
| `field_goals_attempted` | tinyint unsigned nullable | FG attempted |
| `three_pointers_made` | tinyint unsigned nullable | 3PT made |
| `three_pointers_attempted` | tinyint unsigned nullable | 3PT attempted |
| `free_throws_made` | tinyint unsigned nullable | FT made |
| `free_throws_attempted` | tinyint unsigned nullable | FT attempted |
| `offensive_rebounds` | tinyint unsigned nullable | Offensive rebounds (avoids `OR` reserved word) |
| `defensive_rebounds` | tinyint unsigned nullable | Defensive rebounds |
| `rebounds` | tinyint unsigned nullable | Total rebounds |
| `assists` | tinyint unsigned nullable | Assists |
| `steals` | tinyint unsigned nullable | Steals |
| `blocks` | tinyint unsigned nullable | Blocks |
| `turnovers` | tinyint unsigned nullable | Turnovers (avoids `TO` reserved word) |
| `personal_fouls` | tinyint unsigned nullable | Personal fouls |
| `flagrant_fouls` | tinyint unsigned nullable | Flagrant fouls |
| `technical_fouls` | tinyint unsigned nullable | Technical fouls |
| `ejections` | tinyint unsigned nullable | Ejections |
| `disqualifications` | tinyint unsigned nullable | Disqualifications |
| `is_started` | boolean | Whether the player started this game. Default: false |
| `notes` | text nullable | Optional coaching notes for this game entry |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### Columns NOT stored (derived at read time or by Python)

| Stat | Derived From | Where Computed |
|------|-------------|----------------|
| `fg_pct` | `field_goals_made / field_goals_attempted` | Service layer |
| `three_p_pct` | `three_pointers_made / three_pointers_attempted` | Service layer |
| `ft_pct` | `free_throws_made / free_throws_attempted` | Service layer |
| `ast_to` | `assists / turnovers` | Service layer |
| `stl_to` | `steals / turnovers` | Service layer |
| `sc_eff` | `points / field_goals_attempted` | Service layer |
| `sh_eff` | `(fg_made + 0.5 * three_made + 0.44 * ft_made - fg_attempted) / fg_attempted` | Service layer |
| `dd2` | `two stats ≥ 10 in same game` | Service layer |
| `td3` | `three stats ≥ 10 in same game` | Service layer |
| `plus_minus` | Python BPM model | `ComputePlayerPlusMinus` Job |

---

## How `player_stats` Is Rebuilt

After any write to `player_histories` (create, update, delete via web or import), Laravel dispatches a `RebuildPlayerStats` Job for the affected player:

1. Pull all `player_histories` rows for the player
2. Compute averages across all games (total ÷ games played count)
3. Compute derived ratios (fg_pct, ast_to, etc.) from averages
4. Upsert the single `player_stats` row for this player
5. Dispatch `ComputePlayerPlusMinus` Job to update `plus_minus`

> This means `player_stats` is always a **materialized aggregate** — never edited directly after this feature is live.

---

## CSV Template — Player History Import

### Download Template

Route: `GET /player-histories/template/download`  
Controller: `PlayerHistoryController@downloadTemplate`  
Returns a `.csv` file with the exact header row below and one example data row.

### CSV Header (case-sensitive, exact match required)

```
player_id,game_date,playing_team_id,opponent_team_id,position_played,minutes_played,points,field_goals_made,field_goals_attempted,three_pointers_made,three_pointers_attempted,free_throws_made,free_throws_attempted,offensive_rebounds,defensive_rebounds,rebounds,assists,steals,blocks,turnovers,personal_fouls,flagrant_fouls,technical_fouls,ejections,disqualifications,is_started,notes
```

> `player_id`, `playing_team_id`, and `opponent_team_id` use **numeric IDs** in the CSV — the UI template download should pre-fill a reference sheet or sidebar with available player and team IDs to help the user fill in correctly.

### Import Validation Rules

| Rule | Behavior on Failure |
|------|---------------------|
| Exact column header match | Reject entire file — return error, no partial import |
| `player_id` must exist in `players` | Skip row, log to `error_log` |
| `playing_team_id` must exist in `teams` | Skip row, log to `error_log` |
| `opponent_team_id` must exist in `teams` | Skip row, log to `error_log` |
| `game_date` must be valid date (`YYYY-MM-DD`) | Skip row, log to `error_log` |
| `playing_team_id ≠ opponent_team_id` | Skip row — a team cannot play itself |
| Numeric stat fields: non-negative integers | Skip row, log to `error_log` |
| `is_started`: accepts `1`, `0`, `true`, `false` | Normalize to boolean |
| Duplicate: same `player_id` + `game_date` + `opponent_team_id` | Update existing row (upsert) |

### Import Flow

1. User uploads CSV via the **Player Histories** tab on the Teams & Players page (or a dedicated section)
2. `PlayerHistoryImportRequest` validates file type and headers
3. `PlayerHistoryImportJob` (queued) processes rows — upsert per `(player_id, game_date, opponent_team_id)`
4. After all rows processed, dispatch `RebuildPlayerStats` Job per unique `player_id` found in the import
5. Update `csv_imports` record with status and `rows_imported` count

---

## Web CRUD — Player History Management

### View: Player History Table

- Accessible from the player detail page (or a dedicated route: `/players/{player}/histories`)
- Table columns: `game_date`, `playing_team`, `opponent_team`, `position_played`, `MIN`, `PTS`, `REB`, `AST`, `STL`, `BLK`, `TO`, `FG`, `3PT`, `FT`, `+/-` (computed after job)
- Sort by `game_date` descending by default
- Filters: date range, opponent team, playing team
- Empty state: "No game history yet — import a CSV or add a game manually"

### Create — Manual Game Entry

- Form fields match all `player_histories` columns
- `playing_team_id` and `opponent_team_id` use team-selector dropdowns (show team name + code)
- `game_date` uses a date picker
- Submit dispatches `RebuildPlayerStats` Job after save
- Accessible via **"Add Game"** button on the player history view

### Update — Edit Game Entry

- Same form as Create, pre-populated
- Inline edit row or modal/drawer — match the pattern used elsewhere in the app
- Submit dispatches `RebuildPlayerStats` Job after save

### Delete — Remove Game Entry

- Confirm dialog before delete: "Removing this game will recalculate this player's stats."
- Hard delete (game data is additive — no soft delete needed)
- Dispatch `RebuildPlayerStats` Job after delete

---

## Routes

```php
// Player History routes (resource-style)
Route::get('/players/{player}/histories',          [PlayerHistoryController::class, 'index'])->name('player-histories.index');
Route::get('/players/{player}/histories/create',   [PlayerHistoryController::class, 'create'])->name('player-histories.create');
Route::post('/players/{player}/histories',         [PlayerHistoryController::class, 'store'])->name('player-histories.store');
Route::get('/player-histories/{history}/edit',     [PlayerHistoryController::class, 'edit'])->name('player-histories.edit');
Route::put('/player-histories/{history}',          [PlayerHistoryController::class, 'update'])->name('player-histories.update');
Route::delete('/player-histories/{history}',       [PlayerHistoryController::class, 'destroy'])->name('player-histories.destroy');

// CSV Import & Template
Route::get('/player-histories/template/download',  [PlayerHistoryController::class, 'downloadTemplate'])->name('player-histories.template');
Route::post('/player-histories/import',            [PlayerHistoryController::class, 'import'])->name('player-histories.import');
```

---

## Laravel Architecture

### Files to Create

```
app/
├── Http/
│   ├── Controllers/
│   │   └── PlayerHistoryController.php
│   └── Requests/
│       ├── StorePlayerHistoryRequest.php
│       ├── UpdatePlayerHistoryRequest.php
│       └── PlayerHistoryImportRequest.php
├── Models/
│   └── PlayerHistory.php
├── Services/
│   ├── PlayerHistoryService.php     # CRUD logic + triggers RebuildPlayerStats
│   └── PlayerStatsAggregator.php    # Computes averages from player_histories
├── Repositories/
│   └── PlayerHistoryRepository.php
├── Jobs/
│   ├── PlayerHistoryImportJob.php
│   └── RebuildPlayerStats.php       # Aggregates histories → upserts player_stats
└── Actions/
    └── UpsertPlayerHistoryAction.php

database/migrations/
└── xxxx_create_player_histories_table.php

resources/js/
├── Pages/
│   └── Players/
│       └── Histories/
│           ├── Index.tsx            # History table view
│           ├── Create.tsx           # Manual add form
│           └── Edit.tsx             # Edit form
├── Components/features/players/
│   ├── PlayerHistoryTable.tsx
│   ├── PlayerHistoryForm.tsx
│   └── PlayerHistoryImport.tsx      # CSV upload + template download button
└── types/
    └── PlayerHistory.types.ts
```

### `PlayerHistory` Model Relationships

```php
// PlayerHistory.php
public function player(): BelongsTo          // → Player
public function playingTeam(): BelongsTo     // → Team (foreign key: playing_team_id)
public function opponentTeam(): BelongsTo    // → Team (foreign key: opponent_team_id)
```

### `Player` Model — Add Relationship

```php
// Player.php
public function histories(): HasMany         // → PlayerHistory
public function stats(): HasOne              // → PlayerStat (existing)
```

---

## `RebuildPlayerStats` Job Logic

```
Input: player_id

1. Fetch all player_histories where player_id = $playerId
2. If no rows: zero-out player_stats (or delete) — do not leave stale averages
3. Compute:
   - gp          = count(rows)
   - gs          = count(rows where is_started = true)
   - min         = avg(minutes_played)
   - pts         = avg(points)
   - reb         = avg(rebounds)
   - dr          = avg(defensive_rebounds)
   - offensive_rebounds = avg(offensive_rebounds)
   - ast         = avg(assists)
   - stl         = avg(steals)
   - blk         = avg(blocks)
   - to_per_game = avg(turnovers)
   - pf          = avg(personal_fouls)
   - flag        = sum(flagrant_fouls)   ← cumulative, not per-game average
   - tech        = sum(technical_fouls)  ← cumulative
   - eject       = sum(ejections)        ← cumulative
   - dq          = sum(disqualifications) ← cumulative
   - fg          = "{sum(fg_made)}-{sum(fg_attempted)}"
   - ft          = "{sum(ft_made)}-{sum(ft_attempted)}"
   - three_pt    = "{sum(3pm)}-{sum(3pa)}"
   - fg_pct      = sum(fg_made) / sum(fg_attempted)  (null-safe)
   - ft_pct      = sum(ft_made) / sum(ft_attempted)  (null-safe)
   - three_p_pct = sum(3pm) / sum(3pa)               (null-safe)
   - ast_to      = avg(ast) / avg(to_per_game)        (null-safe)
   - stl_to      = avg(stl) / avg(to_per_game)        (null-safe)
   - sc_eff      = avg(pts) / (sum(fg_attempted) / gp) (null-safe)
   - dd2         = count(rows where two stats ≥ 10)
   - td3         = count(rows where three stats ≥ 10)
   - pc          = mode(position_played)  ← most frequent position
   - plus_minus  = null  ← cleared, re-dispatches ComputePlayerPlusMinus Job

4. Upsert player_stats where player_id = $playerId
5. Dispatch ComputePlayerPlusMinus Job for this player
```

---

## UI Notes (Arena Theme)

Follows Phase 5 standards from CLAUDE.md:

- **Template Download button:** amber (`accent #F9A01B`) outlined button, icon: download arrow, label: "Download Template"
- **Import CSV button:** amber filled CTA, label: "Import History"
- **Add Game button:** primary crimson (`#98002E`), placed top-right of the history table header
- **History table:** same dark striped row style as player stats table; `game_date` column first, sorted descending
- **Opponent column:** show team code + name (e.g., `LAL — Los Angeles Lakers`)
- **Empty state:** "No game history recorded yet" with sub-label and Import + Add Game CTAs
- **Loading skeleton:** 5 placeholder rows while data loads
- **Error state:** inline error banner with retry button

---

## Gotchas & Warnings

| # | Warning |
|---|---------|
| 1 | `turnovers` in `player_histories` maps to `TO` in CSV display — do not use `to` as a column name (MySQL reserved word) |
| 2 | `offensive_rebounds` in DB maps to `OR` in CSV display — do not use `or` as a column name |
| 3 | `playing_team_id ≠ opponent_team_id` must be enforced at both FormRequest and DB constraint level |
| 4 | Duplicate game detection: upsert on `(player_id, game_date, opponent_team_id)` — a player can only have one log per opponent per date |
| 5 | `RebuildPlayerStats` must always dispatch `ComputePlayerPlusMinus` after upsert — `plus_minus` on `player_stats` is cleared and re-computed every time |
| 6 | `player_stats` should no longer be editable directly once `player_histories` is live — the aggregator owns that data |
| 7 | `pc` (position on court) stored in `player_stats` becomes the statistical mode of `position_played` across all history rows |
| 8 | The CSV import uses `player_id` as a numeric FK — the template download should include a reference tab or sidebar listing all player IDs and names |

