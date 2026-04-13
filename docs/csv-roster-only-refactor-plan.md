# CSV Roster-Only Refactor Plan

## Problem

The system currently has two separate write paths into `player_stats`:

1. **CSV import** — writes pre-aggregated season averages directly into `player_stats`
2. **Game history** — writes raw per-game data into `player_histories`, which triggers a rebuild of `player_stats` via `RebuildPlayerStats`

This creates an inconsistency: if a player has CSV-imported stats and a coach later adds a game history entry, the `RebuildPlayerStats` job overwrites the CSV stats with data derived only from that one manual game — ignoring the CSV entirely.

---

## Goal

Make `player_histories` the **single source of truth** for all player statistics.

- CSV upload creates player records only (name, jersey, role, height, weight)
- Stats are populated exclusively through game history entries
- `player_stats` is always a computed summary of `player_histories` — never written to directly

---

## What Changes

### What stays the same — do not touch

- `player_histories` table and all history CRUD
- `player_stats` table
- `RebuildPlayerStats` job
- `PlayerStatsAggregator`
- `ComputePlayerPlusMinus` job
- `PlayerHistoryImportJob` (the Excel game-log import)
- All comparison and analytics features

---

## Step-by-Step Changes

### Step 1 — Strip stats out of the CSV template

**File:** `app/Services/CsvTemplateService.php`

Reduce `HEADERS` from 37 columns to 7:

```
first_name, last_name, jersey_number, role, height_feet, weight_kg, is_active
```

Remove everything from `pc` onward. This becomes the new upload contract.

---

### Step 2 — Remove the stat-writing half of the CSV import service

**File:** `app/Services/CsvImportService.php`

Remove:
- `STAT_COLUMN_MAP` constant
- `castStat()` method
- `upsertStat()` call inside `importRow()`
- `ComputePlayerPlusMinus::dispatch()` call

Keep:
- `updateOrCreateByJersey()` — creates and updates player records
- Header validation
- Row parsing for the 7 remaining player fields

`importRow()` shrinks from ~50 lines to ~15.

---

### Step 3 — Remove dead repository methods

**File:** `app/Repositories/PlayerRepository.php`

Remove `upsertStat()` and `createStat()`. Both were only called by `CsvImportService`.

`RebuildPlayerStats` writes to `PlayerStat` directly via `PlayerStat::updateOrCreate()` — it never used these repository methods, so nothing downstream breaks.

---

### Step 4 — Handle the `sd` (Spatial Data) field

`sd` currently comes from the CSV. After removing it from the template it will never be populated.

**Decision:** Leave the column in `player_stats` as nullable. Remove it from the CSV template and from the field legend on the frontend. No migration needed. It stays null until a future phase introduces a real source for it.

---

### Step 5 — Update frontend copy

**File:** `resources/js/Components/features/csv/CsvUploadForm.tsx`

Update the description from:
> "Upload a roster file to import player stats in bulk."

To:
> "Upload a roster file to create your player list. Add game history per player to populate stats."

This sets the right expectation before a coach hits a blank stats table.

---

### Step 6 — Remove SD from the field legend

**File:** `resources/js/Pages/Teams/Show.tsx`

Remove `{ abbr: 'SD', desc: 'Spatial data' }` from the `FIELD_LEGEND` array. It will never be populated under this design.

---

### Step 7 — Verify the template download

**File:** `resources/js/Components/features/csv/TemplateDownloadButton.tsx`

The template download reads from `CsvTemplateService::HEADERS`. Once Step 1 is done, the downloaded file will automatically reflect the new 7-column format. Check that any button label or tooltip copy does not reference stats.

---

## Execution Order

| # | File | Change |
|---|------|--------|
| 1 | `app/Services/CsvTemplateService.php` | Shrink HEADERS to 7 columns |
| 2 | `app/Services/CsvImportService.php` | Remove stat map, stat write, BPM dispatch |
| 3 | `app/Repositories/PlayerRepository.php` | Remove `upsertStat()` and `createStat()` |
| 4 | `resources/js/Components/features/csv/CsvUploadForm.tsx` | Update description copy |
| 5 | `resources/js/Pages/Teams/Show.tsx` | Remove SD from field legend |
| 6 | `resources/js/Components/features/csv/TemplateDownloadButton.tsx` | Verify no stat references in copy |

---

## Smoke Test Checklist

After all changes:

- [ ] Download the CSV template — confirm it has exactly 7 columns, no stat columns
- [ ] Upload a valid 7-column CSV — confirm players are created with blank stats (`—` in the table)
- [ ] Upload a CSV with the old 37-column format — confirm it is rejected with a clear error
- [ ] Add game history entries for a player — confirm `player_stats` auto-populates after the queue processes
- [ ] Add a second history entry — confirm stats recalculate correctly
- [ ] Delete a history entry — confirm stats rebuild again
- [ ] Use the Excel history bulk import — confirm it still works and stats rebuild
- [ ] Verify the Team Comparison and Player Matchup pages still load correctly for players with history-derived stats

---

## Risk: Existing CSV-Imported Stats Will Be Wiped

If there is any existing data in `player_stats` that was imported via the old CSV flow (with no corresponding `player_histories` rows), those stats will be **overwritten with nulls** the next time `RebuildPlayerStats` runs for that player — because it finds zero history rows and calls `zeroed()`.

**Before starting:** If the database has real data, export it first. There is no automatic migration path from old CSV stats to history rows — the data would need to be re-entered via game history.

If the environment is clean (fresh seed or empty database), this is a non-issue.

---

## How Stats Flow After This Change

```
Coach uploads roster CSV
        │
        ▼
  player created (name, jersey, role, height, weight)
  player_stats = empty (all —)
        │
        ▼
Coach uploads game history (Excel) or adds games manually
        │
        ▼
  player_histories rows saved
        │
        ▼
  RebuildPlayerStats job dispatched
        │
        ▼
  PlayerStatsAggregator computes averages, percentages, ratios
        │
        ▼
  player_stats row updated
        │
        ▼
  ComputePlayerPlusMinus job dispatched → Python BPM engine
        │
        ▼
  player_stats.plus_minus updated
        │
        ▼
  All features (Comparison, Lineup, Matchup) read from player_stats
```
