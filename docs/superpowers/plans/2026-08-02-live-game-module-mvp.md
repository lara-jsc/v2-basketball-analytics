# Live Game Module MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Laravel/Inertia Live Game Module for multi-coach real-time basketball event recording, synced scoreboard/clock, live stats, rule-based DSS alerts, and post-game stat finalization.

**Architecture:** Laravel owns the event stream, projections, synced game clock, and broadcasts. Events are append-only; every write updates live projections synchronously, then broadcasts a full game-state snapshot over Laravel Reverb. React/Inertia renders setup and live console screens.

**Tech Stack:** Laravel 13, PHP 8.3+, MySQL, Inertia React, TypeScript, Laravel Reverb, Laravel Echo, pusher-js, Pest/PHPUnit, Vite.

## Global Constraints

- Target existing Laravel/Inertia repo, not Rails.
- MVP auth is minimal: any verified user can create, join, and record live games.
- Production DB target is MySQL.
- Game lifecycle is `setup`, `live`, `finished`.
- Basketball format is four quarters with configurable period length per game.
- Clock is server-authoritative and synchronized.
- Own team is tracked at player level; opponent is tracked at aggregate level.
- Events are never edited or deleted; corrections create linked void/reversal events.
- Broadcast full server snapshots, not client-applied event deltas.
- Live DSS is deterministic rule-based alerts only.
- Finished games auto-finalize into existing player history/stat rebuild flows.

---

## File Structure

- Create `app/Models/LiveGame.php`, `LiveGameEvent.php`, `LiveGamePlayerStat.php`, `LiveGameLineupStint.php`, `LiveGameAlert.php`.
- Create migrations for live games, events, player stats, stints, alerts.
- Create `app/Services/LiveGame/` services:
  `LiveGameStateBuilder`, `LiveGameEventRecorder`, `LiveGameProjectionService`, `LiveGameClockService`, `LiveGameAlertService`, `LiveGameFinalizer`.
- Create `app/Events/LiveGameStateUpdated.php`.
- Create `app/Http/Controllers/LiveGameController.php`, `LiveGameEventController.php`, `LiveGameClockController.php`.
- Create request classes for create/start/event/clock/correction actions.
- Modify `routes/web.php`, `routes/channels.php`, `config/broadcasting.php`, `.env.example`, `docker/supervisord.conf`, `package.json`, `resources/js/bootstrap.js`.
- Create React pages/components under `resources/js/Pages/LiveGames` and `resources/js/Components/features/live-game`.
- Add tests under `tests/Feature/LiveGame` and `tests/Unit/Services/LiveGame`.

---

### Task 1: Real-Time Foundation

**Files:**
- Modify: `composer.json`
- Modify: `package.json`
- Modify: `.env.example`
- Create/modify: `config/broadcasting.php`
- Create: `routes/channels.php`
- Modify: `docker/supervisord.conf`
- Modify: `resources/js/bootstrap.js`

**Interfaces:**
- Produces private broadcast channel: `live-game.{liveGameId}`
- Produces frontend Echo client available as `window.Echo`

- [ ] Install backend/frontend dependencies:
  `composer require laravel/reverb`
  `npm install laravel-echo pusher-js`

- [ ] Install Reverb config:
  `php artisan reverb:install`

- [ ] Add `.env.example` values:
  `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID=local`, `REVERB_APP_KEY=local`, `REVERB_APP_SECRET=local`, `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`.

- [ ] Add private channel auth in `routes/channels.php`:
  authenticated users may join `live-game.{liveGameId}` if the live game exists.

- [ ] Configure `resources/js/bootstrap.js` to initialize Laravel Echo with Reverb env values.

- [ ] Add Supervisor process:
  `php artisan reverb:start --host=0.0.0.0 --port=8080`.

- [ ] Test:
  add a feature test proving unauthenticated users cannot authorize the channel and authenticated users can.

- [ ] Run:
  `php artisan test tests/Feature/LiveGame/LiveGameBroadcastingTest.php`.

---

### Task 2: Live Game Persistence

**Files:**
- Create migrations for `live_games`, `live_game_events`, `live_game_player_stats`, `live_game_lineup_stints`, `live_game_alerts`.
- Create matching Eloquent models.
- Modify `app/Models/Team.php`, `app/Models/Player.php`, `app/Models/User.php` with relationships.

**Interfaces:**
- `LiveGame` states: `setup`, `live`, `finished`
- Event fields include: `live_game_id`, `sequence`, `type`, `team_scope`, `player_id`, `period`, `clock_seconds_remaining`, `occurred_at`, `payload`, `voids_event_id`, `recorded_by_user_id`

- [ ] Write migration tests for required columns, indexes, and foreign keys.

- [ ] Implement migrations with unique `(live_game_id, sequence)` and indexes on `live_game_id`, `type`, `player_id`, `voids_event_id`.

- [ ] Add model factories for live games and events.

- [ ] Run:
  `php artisan test tests/Feature/LiveGame/LiveGameSchemaTest.php`.

---

### Task 3: Event Recording And Projection

**Files:**
- Create `app/Services/LiveGame/LiveGameEventRecorder.php`
- Create `app/Services/LiveGame/LiveGameProjectionService.php`
- Create `app/Services/LiveGame/LiveGameStateBuilder.php`
- Create `app/Events/LiveGameStateUpdated.php`
- Create `app/Http/Controllers/LiveGameEventController.php`

**Interfaces:**
- `LiveGameEventRecorder::record(LiveGame $game, User $user, array $input): array`
- Returns full state snapshot from `LiveGameStateBuilder::build(LiveGame $game): array`

- [ ] Write failing tests for made/missed shots, free throws, rebounds, assists, fouls, turnovers, opponent aggregate scoring, substitutions, and timeout events.

- [ ] Implement event recording inside a DB transaction:
  assign next sequence, persist event, update projection, build snapshot, broadcast after commit.

- [ ] Implement correction events:
  `voids_event_id` points at original event; projection ignores voided events and applies rebuilt state.

- [ ] Run:
  `php artisan test tests/Unit/Services/LiveGame/LiveGameProjectionServiceTest.php`
  and
  `php artisan test tests/Feature/LiveGame/LiveGameEventRecordingTest.php`.

---

### Task 4: Synced Clock And Lineup Stints

**Files:**
- Create `app/Services/LiveGame/LiveGameClockService.php`
- Create `app/Http/Controllers/LiveGameClockController.php`
- Extend live-game migrations/model fields for clock state.
- Extend `LiveGameProjectionService` for minutes and plus-minus.

**Interfaces:**
- Clock actions: `start`, `stop`, `set_period`, `reset_period`
- Snapshot includes `clock: { period, period_length_seconds, seconds_remaining, running, server_now }`

- [ ] Write failing tests for clock start/stop elapsed time, period transition, substitution stint closing/opening, minutes calculation, and plus-minus attribution.

- [ ] Implement server-authoritative elapsed-time calculation from persisted `clock_started_at`.

- [ ] Record substitution events and maintain lineup stints.

- [ ] Derive player minutes and plus-minus from closed and active stints.

- [ ] Run:
  `php artisan test tests/Unit/Services/LiveGame/LiveGameClockServiceTest.php`.

---

### Task 5: Setup, Live Console, And Reconnect UX

**Files:**
- Create `resources/js/Pages/LiveGames/Index.tsx`
- Create `resources/js/Pages/LiveGames/Create.tsx`
- Create `resources/js/Pages/LiveGames/Show.tsx`
- Create components under `resources/js/Components/features/live-game`
- Modify `routes/web.php`
- Modify `resources/js/Layouts/AuthenticatedLayout.tsx`
- Modify `resources/js/types/index.ts`

**Interfaces:**
- Inertia page props include `liveGame`, `snapshot`, `teams`, `players`.
- Client subscribes to `live-game.{id}` and replaces local state with each snapshot.

- [ ] Add routes for index/create/store/show/start/finish/event/clock/correction.

- [ ] Build setup page:
  select home team, opponent team, configurable quarter length, suggested starting five, manual lineup override.

- [ ] Build live console:
  scoreboard, synced clock controls, five active players, event buttons, bench/sub drawer, timeline with void action, and alerts panel.

- [ ] Implement reconnect:
  initial HTTP snapshot load, then Echo subscription, then replace state on broadcasts.

- [ ] Run:
  `npm run typecheck`
  and
  `npm run build`.

---

### Task 6: Rule-Based DSS Alerts

**Files:**
- Create `app/Services/LiveGame/LiveGameAlertService.php`
- Extend `LiveGameProjectionService`
- Add alert UI component in `resources/js/Components/features/live-game/LiveGameAlertsPanel.tsx`

**Interfaces:**
- Alerts include: `hot_player`, `cold_player`, `foul_trouble`, `opponent_run`, `team_drought`, `timeout_prompt`, `substitution_prompt`.

- [ ] Write failing tests for each alert rule using controlled event streams.

- [ ] Implement deterministic thresholds:
  hot player = 3 made shots in current quarter;
  cold player = 3 missed shots without make;
  foul trouble = 3 fouls before Q4 or 4+ any time;
  opponent run = 8 unanswered points;
  drought = 4 own empty possessions;
  timeout prompt = opponent run or drought;
  substitution prompt = foul trouble or cold player.

- [ ] Persist active alerts and clear resolved alerts during projection.

- [ ] Run:
  `php artisan test tests/Unit/Services/LiveGame/LiveGameAlertServiceTest.php`.

---

### Task 7: Finish And Finalize

**Files:**
- Create `app/Services/LiveGame/LiveGameFinalizer.php`
- Modify/use `app/Actions/UpsertPlayerHistoryAction.php`
- Dispatch existing `RebuildPlayerStats` job after finalization.
- Add finish action to `LiveGameController`.

**Interfaces:**
- `LiveGameFinalizer::finalize(LiveGame $game): void`
- Writes one `player_histories` row per participating own-team player.

- [ ] Write failing feature test:
  finished live game creates player history rows and dispatches stat rebuild.

- [ ] Implement finalizer from live projections into existing player history fields.

- [ ] Mark game `finished` and prevent new non-correction gameplay events after finish.

- [ ] Run:
  `php artisan test tests/Feature/LiveGame/LiveGameFinalizationTest.php`.

---

### Task 8: Full Verification

**Files:**
- No new files unless tests reveal gaps.

**Interfaces:**
- Whole module works from setup to finalization.

- [ ] Run PHP tests:
  `php artisan test`.

- [ ] Run frontend checks:
  `npm run typecheck`
  and
  `npm run build`.

- [ ] Manually verify:
  create game, confirm lineup, start clock, record events from two browser sessions, see both sessions update, void an event, substitute players, trigger alert, finish game, confirm player history rows exist.

- [ ] Commit in small checkpoints:
  `feat: add live game realtime foundation`
  `feat: add live game event projections`
  `feat: add live game console`
  `feat: finalize live games into history`

## Assumptions

- This plan should be saved as `docs/superpowers/plans/2026-08-02-live-game-module-mvp.md` once file mutation is allowed.
- Execution should use `superpowers:subagent-driven-development` if multi-agent support is available; otherwise use `superpowers:executing-plans`.
- Full role permissions, offline sync, ML recommendations, and detailed opponent player tracking are out of MVP.
