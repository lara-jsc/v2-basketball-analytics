# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

HoopSense+ — a pre-game basketball decision support system (thesis project). Coaches import rosters and per-game player histories, then get win probability, lineup recommendations, and player-vs-player matchup analysis derived from plus-minus analytics.

Authoritative specs live outside this file — read them before non-trivial work:
- `.claude/Project-Specification.md` — full scope, DB schema, Python contracts, UI/design tokens, gotchas table
- `SYSTEM_GUIDE.md` — end-to-end functional walkthrough
- `docs/` — active refactor/feature plans (`csv-roster-only-refactor-plan.md`, `advanced-stats-integration-plan.md`, `player-histories-feature-spec.md`)
- `computation.md` — stat formula derivations

## Commands

```bash
composer dev                      # full stack: artisan serve + queue:listen + pail + vite (use this)
composer setup                    # one-shot bootstrap: install, key, migrate, npm build

php artisan test                  # all PHP tests (Pest, SQLite :memory:)
php artisan test --filter=LineupServiceTest
php artisan test tests/Unit/Services/WinProbabilityServiceTest.php
vendor/bin/pint                   # PHP formatting (PSR-12, no pint.json — Laravel preset)

pytest tests/python               # Python analytics tests
pytest tests/python/test_bpm_calculator.py::test_name

npm run typecheck                 # tsc --noEmit
npm run build

php artisan migrate:fresh --seed  # reseed demo data
```

Local dev runs SQLite (`.env`), production targets MySQL. `composer dev` must be running (or a queue worker) or **nothing computes** — all analytics happen in queued jobs.

## Architecture

Single deployment: Laravel 13 + Inertia 2 + React 18/TS. Python is a stateless subprocess, not a service.

### Layering (strictly enforced — see Project-Specification "Code Style")

```
Controller (Inertia::render / redirect only)
  → FormRequest (all validation)
  → Service (all business logic)
  → Repository (all DB queries)
  → Model (Eloquent, zero logic)
Jobs — every CSV parse and every Python call
Actions — single-responsibility writes (e.g. UpsertPlayerHistoryAction)
```

Controllers never touch Eloquent queries or `PythonEngineService` directly.

### The stats pipeline — this is the core of the system

`player_histories` is the **single source of truth**. Nothing writes to `player_stats` directly.

```
CSV/XLSX upload → ProcessCsvImport (roster only: name, jersey, role, height, weight)
Game history write (store/update/destroy or PlayerHistoryImportJob)
        ↓
RebuildPlayerStats(playerId)
        ↓  PlayerHistoryRepository::rawForPlayer → PlayerStatsAggregator::compute
        ↓  PlayerStat::updateOrCreate (one row per player, plus_minus reset to null)
        ↓
ComputePlayerPlusMinus(playerStat->id)   ← dispatched with the *stat row id*, not player id
        ↓  Python "bpm" command → writes player_stats.plus_minus
```

Consequences to respect when changing anything here:
- Roster CSV import must not write stat columns. That was deliberately removed (see `docs/csv-roster-only-refactor-plan.md`).
- Any new write path into `player_histories` must dispatch `RebuildPlayerStats`. That job also invalidates the team's win-probability/lineup cache (`WinProbabilityService::invalidateForTeam`).
- Team-level plus-minus is derived at read time (minutes-weighted average of active players) in the Service layer — never stored.

### Python engine bridge

`App\Services\PythonEngineService` → `Process::input(json)->run([python_bin, analytics/engine.py])`. One JSON object in on stdin, one out on stdout. Commands: `bpm`, `lineup`, `win_probability`, `player_matchup`, dispatched from `ComputePlayerPlusMinus`, `RecommendLineup`, `ComputeWinProbability`, `ComputePlayerMatchup`.

Rules: Python never touches the DB, never runs as a web server, returns JSON-serializable dicts only, and is only ever invoked from a Job. Adding a command means updating `SUPPORTED_COMMANDS` in `analytics/engine.py` plus the module under `analytics/{plus_minus,lineup_optimizer,win_probability}/`. Config lives in `config/analytics.php` (`PYTHON_BIN`, `PYTHON_ENGINE_PATH`).

Note `.env.example` still lists a stale `PYTHON_ANALYTICS_URL` — there is no HTTP analytics service.

### Async results in the UI

Expensive results (win probability, lineups) use a dispatch-and-poll shape: `WinProbabilityService::getOrDispatch()` returns cached data or `null` while dispatching the job. Version-based cache keys (`win_prob.{minId}.{maxId}.v{vA}.{vB}`) are bumped on import rather than deleted. Every page that shows a computed value must render a pending state.

### Frontend

`resources/js/Pages/*` map 1:1 to routes in `routes/web.php`; data arrives only via Inertia props — there is no REST API. New code is `.tsx` (older Breeze scaffolding is still `.jsx`; convert when touching it). Component layers: `Components/ui/` is shadcn base and **must never be modified** — extend via `className` or add variants in `Components/features/<domain>/`.

## Gotchas

- `or` and `to` are MySQL reserved words → DB columns are `offensive_rebounds` and `to_per_game`, displayed as `OR` / `TO`.
- `plus_minus` is nullable until the BPM job finishes. UI renders `—`, never `0` or blank.
- `is_active = false` players are excluded from lineup recommendations and comparison dropdowns.
- Import headers are case-sensitive and order-sensitive (`PlayerHistoryImportJob::HEADERS`); invalid rows are skipped and logged, the import continues.
- Player history import auto-fills `playing_team_id` from the player's current team — the template only asks for `opponent_team_id`.
- Profile pictures and team logos live in Laravel storage and are served via signed URLs.
- Tablet (768–1024px) is the primary breakpoint; design there first.
- Real-time possession tracking is explicitly out of scope — do not scaffold for it. Team ownership is limited to staffing: one team per coach (`users.team_id`); self-signup main coaches create or claim a team; self-signup assistants join via `team_join_requests`, approved by that team's main coach or an admin (`TeamPolicy::manageJoinRequests`).

## Testing

Pest (`tests/Pest.php`): `Feature` gets `RefreshDatabase` on SQLite `:memory:`, `Unit` does not. Queue is `sync` under test, so dispatched jobs run inline unless faked. Python tests are pytest with `tests/python/conftest.py` putting the repo root on `sys.path`.
