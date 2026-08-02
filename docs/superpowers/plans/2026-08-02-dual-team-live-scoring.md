# Dual-Team Live Scoring MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let one home-team coach and one opponent-team coach record real player events on the same live game and see scores, lineups, and timeline sync in real time (1+1 prove-out of a future 3+3 courtside model).

**Architecture:** Add `users.team_id` so coaches are permanently tied to one team. Split lineups into home + opponent starting/active JSON on `live_games`. Authorize event writes from the recorder’s team only; project both scores from player shot/FT events (hide coach-facing opponent `+N`). Clock/start/finish stay creator-only. Keep `LiveGameStateUpdated` + Echo as the sync path; tighten private channel auth to participants.

**Tech Stack:** Laravel 13, PHP 8.3+, MySQL (SQLite in tests), Inertia React/TypeScript, Laravel Reverb, Echo, Pest, Vite.

## Global Constraints

- Coach accounts permanently tied via `users.team_id` (one team per user).
- Full scoreboard visible to everyone; record **own team only**.
- “Designated players” is soft convention for MVP (no hard locks / claim UI).
- Only the **game creator** controls clock / start / finish.
- Duplicate taps accepted; fix via existing void/correction.
- Build **1+1 MVP** first (not 3+3 assignment/presence UI).
- Both sides use **real player scoring events**; hide opponent `+N` buttons.
- Starting five for **both** home and opponent at create.
- Keep existing home fields `starting_player_ids` / `active_player_ids` as home; add `opponent_starting_player_ids` / `opponent_active_player_ids`.
- Layering: Controller → validation → Service → Model. No Eloquent queries in controllers beyond what existing LiveGame controllers already do; prefer extending current LiveGame services.
- Do not modify `Components/ui/` shadcn bases.
- Controllers never call Python; live game does not use the Python engine.
- Partial work may already exist in the branch — verify each task’s tests before rewriting files.

## Out of scope

- Hard player assignment across 3 coaches per team
- Presence indicators / “who recorded this” polish
- Multi-team users / pivot tables
- Dedicated clock-master role separate from creator
- LAN/Reverb ops beyond a short manual checklist

---

## File Structure

| Area | Responsibility |
|------|----------------|
| `database/migrations/*_add_team_id_to_users_table.php` | Nullable `users.team_id` FK → `teams` |
| `database/migrations/*_add_opponent_lineup_to_live_games_table.php` | Opponent starting/active JSON columns |
| `app/Models/User.php` | `team_id`, `team()` BelongsTo |
| `app/Models/LiveGame.php` | Opponent lineup casts; `isCreator`, `isParticipant`, `sideFor`, `activePlayerIdsForSide`, `startingPlayerIdsForSide` |
| `database/factories/UserFactory.php` | `team_id` null + `forTeam()` |
| `database/factories/LiveGameFactory.php` | Opponent lineup defaults |
| `database/seeders/DemoUserSeeder.php` | `warriors@email.com` / `lakers@email.com` coaches (after teams seeded) |
| `database/seeders/DatabaseSeeder.php` | `TeamPlayersSeeder` then `DemoUserSeeder` |
| `app/Http/Controllers/LiveGameController.php` | Dual starting fives on store; show props (`homePlayers`, `opponentPlayers`, `viewerSide`, `isCreator`); creator-only finish |
| `app/Http/Controllers/LiveGameEventController.php` | Own-team player validation |
| `app/Services/LiveGame/LiveGameEventRecorder.php` | Active-list validation per side |
| `app/Services/LiveGame/LiveGameProjectionService.php` | Dual actives; opponent player events → `opponent_score` |
| `app/Services/LiveGame/LiveGameStateBuilder.php` | Snapshot includes `opponent_active_player_ids` |
| `app/Services/LiveGame/LiveGameClockService.php` | Creator-only gate |
| `app/Services/LiveGame/LiveGameFinalizer.php` | Histories for both teams |
| `routes/channels.php` | Participant-only `live-game.{id}` |
| `resources/js/Pages/LiveGames/Create.tsx` | Two starting-five pickers |
| `resources/js/Pages/LiveGames/Show.tsx` | Side-scoped pad/subs; creator-only controls |
| `resources/js/Components/features/live-game/EventPad.tsx` | Remove opponent `+N` |
| `resources/js/Components/features/live-game/GameScoreboard.tsx` | `canControlClock` |
| `resources/js/types/index.ts` | Opponent lineup + snapshot fields |
| `docs/superpowers/plans/…` checklist note or `docs/live-game-1plus1-sync-checklist.md` | Manual 1+1 sync steps |
| Tests under `tests/Feature/LiveGame` + `tests/Unit/Services/LiveGame` + `tests/Feature/UserTeamMembershipTest.php` | TDD coverage |

### Key interfaces (lock these names)

```php
// LiveGame
public function isCreator(User $user): bool;
public function isParticipant(User $user): bool;
/** @return 'home'|'opponent'|null */
public function sideFor(User $user): ?string;
/** @return list<int> */
public function startingPlayerIdsForSide(string $side): array;
/** @return list<int> */
public function activePlayerIdsForSide(string $side): array;

// Snapshot extras (LiveGameStateBuilder::build)
'active_player_ids' => list<int>,           // home
'opponent_active_player_ids' => list<int>,

// Show Inertia props
'homePlayers', 'opponentPlayers', 'players' /* legacy home alias */,
'viewerSide' => 'home'|'opponent'|null,
'isCreator' => bool,
```

---

### Task 1: `users.team_id` + coach seed

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_team_id_to_users_table.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `database/seeders/DemoUserSeeder.php`, `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/UserTeamMembershipTest.php`

**Interfaces:**
- Consumes: existing `teams` table / `TeamPlayersSeeder` codes `GSW`, `LAK`
- Produces: `User::$team_id`, `User::team()`, `UserFactory::forTeam(Team)`, seeded coaches

- [ ] **Step 1: Write the failing test**

```php
public function test_a_user_can_belong_to_a_team(): void
{
    $team = Team::factory()->create();
    $user = User::factory()->forTeam($team)->create();
    $this->assertSame($team->id, $user->fresh()->team_id);
    $this->assertTrue($user->team->is($team));
}

public function test_demo_seeder_creates_team_coaches(): void
{
    $this->seed(TeamPlayersSeeder::class);
    $this->seed(DemoUserSeeder::class);
    // assert warriors@email.com → GSW, lakers@email.com → LAK, test@email.com team_id null
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UserTeamMembershipTest`  
Expected: FAIL (missing column / factory method / coaches)

- [ ] **Step 3: Write minimal implementation**

Migration: nullable `team_id` FK `constrained('teams')->nullOnDelete()`.  
User fillable + cast + `team()`. Factory `team_id => null` + `forTeam()`.  
Reorder seeder: teams first, then DemoUserSeeder creating coaches by team code.

- [ ] **Step 4: Run tests and make sure they pass**

Run: `php artisan test --filter=UserTeamMembershipTest`  
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*team_id* app/Models/User.php database/factories/UserFactory.php database/seeders/ tests/Feature/UserTeamMembershipTest.php
git commit -m "$(cat <<'EOF'
Add users.team_id and seed Warriors/Lakers coach accounts.

EOF
)"
```

---

### Task 2: Opponent lineup columns + create API/UI

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_opponent_lineup_to_live_games_table.php`
- Modify: `app/Models/LiveGame.php`, `database/factories/LiveGameFactory.php`, `app/Http/Controllers/LiveGameController.php` (`store`), `resources/js/Pages/LiveGames/Create.tsx`, `resources/js/types/index.ts`, `tests/Feature/LiveGame/LiveGameSchemaTest.php`, `tests/Feature/LiveGame/LiveGameControllerTest.php`

**Interfaces:**
- Consumes: Task 1 users (optional for create — any verified user can create)
- Produces: `opponent_starting_player_ids`, `opponent_active_player_ids` on create (mirrored)

- [ ] **Step 1: Extend failing create test**

```php
$opponentPlayers = Player::factory()->count(5)->for($opponent)->create(['is_active' => true]);
$post = [
    // ...existing...
    'starting_player_ids' => $homePlayers->modelKeys(),
    'opponent_starting_player_ids' => $opponentPlayers->modelKeys(),
];
// assert both starting + active arrays persisted
```

- [ ] **Step 2: Run test — expect FAIL** (missing validation/column)

Run: `php artisan test --filter=test_a_verified_user_can_create_a_live_game`

- [ ] **Step 3: Implement migration, model casts/fillable, store validation, Create.tsx second picker**

Validate opponent five: size 5, distinct, active, `team_id = opponent_team_id`.  
Schema test: add the two new columns to the expected list.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=LiveGameControllerTest`  
Run: `php artisan test --filter=LiveGameSchemaTest`

- [ ] **Step 5: Commit**

```bash
git commit -m "$(cat <<'EOF'
Require starting fives for both teams when creating a live game.

EOF
)"
```

---

### Task 3: Show props + dual lineup UI (read path)

**Files:**
- Modify: `app/Http/Controllers/LiveGameController.php` (`show`), `app/Services/LiveGame/LiveGameStateBuilder.php`, `resources/js/Pages/LiveGames/Show.tsx`, `resources/js/Components/features/live-game/GameScoreboard.tsx`, types
- Test: extend `LiveGameControllerTest` show assertions

**Interfaces:**
- Consumes: `LiveGame::sideFor`, `isCreator`; snapshot `opponent_active_player_ids`
- Produces: Inertia props listed above; UI uses own-side roster for pad/subs

- [ ] **Step 1: Failing show assertion**

```php
->assertInertia(fn ($page) => $page
    ->has('homePlayers')
    ->has('opponentPlayers')
    ->has('viewerSide')
    ->has('isCreator')
    ->has('snapshot.opponent_active_player_ids')
);
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement show props + Show.tsx side scoping + `canControlClock={isCreator}`**

Non-creator: hide Start / Finish / clock buttons.  
Viewer with `viewerSide === null`: scoreboard + timeline only; no event pad writes.

- [ ] **Step 4: Pass controller tests**

- [ ] **Step 5: Commit**

```bash
git commit -m "$(cat <<'EOF'
Expose dual-team show props and side-scoped live console UI.

EOF
)"
```

---

### Task 4: Authorize record own team only

**Files:**
- Modify: `app/Http/Controllers/LiveGameEventController.php`, `app/Services/LiveGame/LiveGameEventRecorder.php`
- Test: `tests/Feature/LiveGame/LiveGameEventRecordingTest.php`, `tests/Unit/Services/LiveGame/LiveGameEventRecorderTest.php`

**Interfaces:**
- Consumes: `LiveGame::sideFor`, `activePlayerIdsForSide`
- Produces: validation errors when recording other team’s players

- [ ] **Step 1: Write failing feature tests**

```php
// home coach records home player → 200
// home coach records opponent player → 422 player_id
// user with null team_id recording own-scope player event → 422 game
```

Update unit helper `gameWithStarter` to `User::factory()->forTeam($game->homeTeam)`.

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement**

Controller: players must be on game roster AND match `$user->team_id` for own-player / sub events.  
Recorder: active check uses side-specific active list.

- [ ] **Step 4: Pass event tests**

Run: `php artisan test --filter=LiveGameEvent`

- [ ] **Step 5: Commit**

```bash
git commit -m "$(cat <<'EOF'
Restrict live-game event recording to the coach's own team.

EOF
)"
```

---

### Task 5: Projection — both sides score from player events

**Files:**
- Modify: `app/Services/LiveGame/LiveGameProjectionService.php`, `EventPad.tsx` (hide +N if not already)
- Test: `tests/Unit/Services/LiveGame/LiveGameProjectionServiceTest.php`

**Interfaces:**
- Consumes: both starting lists; `Player.team_id`
- Produces: `home_score` / `opponent_score` from player events; both active arrays persisted

- [ ] **Step 1: Failing unit test**

```php
// home starter shot_made 2 → home_score 2
// opponent starter shot_made 3 → opponent_score 3
// active_player_ids and opponent_active_player_ids remain split after rebuild
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement dual rebuild**

Open stints for both starting fives.  
On `team_scope === 'own'` events, branch score/plus-minus by player’s team.  
Keep projecting legacy `opponent_score` events for old rows.  
Subs apply to the side of `player_out`.

- [ ] **Step 4: Pass projection + alert tests**

Run: `php artisan test --filter=LiveGameProjection`  
Run: `php artisan test --filter=LiveGameAlert`

- [ ] **Step 5: Commit**

```bash
git commit -m "$(cat <<'EOF'
Project both team scores from player events and track dual actives.

EOF
)"
```

---

### Task 6: Creator-only clock

**Files:**
- Modify: `app/Services/LiveGame/LiveGameClockService.php`, `LiveGameController::start`/`finish` (finish already gated), UI already uses `isCreator`
- Test: `tests/Feature/LiveGame/LiveGameClockControllerTest.php`, `tests/Unit/Services/LiveGame/LiveGameClockServiceTest.php`, controller lifecycle test

**Interfaces:**
- Consumes: `LiveGame::isCreator`
- Produces: ValidationException `game` when non-creator posts clock/start/finish

- [ ] **Step 1: Failing tests**

```php
// creator (created_by_user_id) clock start → 200
// other verified user clock start → 422 game
```

Update unit `handle()` helper to use `$game->creator` (or set `created_by_user_id` to the acting user).

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Gate `LiveGameClockService::handle` and finish (and start route which delegates to clock)**

- [ ] **Step 4: Pass clock + lifecycle tests**

- [ ] **Step 5: Commit**

```bash
git commit -m "$(cat <<'EOF'
Allow only the live-game creator to control the clock and finish.

EOF
)"
```

---

### Task 7: Finalizer both teams

**Files:**
- Modify: `app/Services/LiveGame/LiveGameFinalizer.php`
- Test: `tests/Feature/LiveGame/LiveGameFinalizationTest.php`

**Interfaces:**
- Consumes: `LiveGamePlayerStat` + `Player.team_id`
- Produces: `player_histories` for home players (`playing=home, opponent=opp`) and opponent players (`playing=opp, opponent=home`)

- [ ] **Step 1: Failing test with both-team starters + events**

Assert histories for one home and one opponent participant with swapped team IDs.

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Loop finalizer over both sides** (conflict checks per side)

Ensure finish tests act as the **creator**.

- [ ] **Step 4: Pass finalization tests**

- [ ] **Step 5: Commit**

```bash
git commit -m "$(cat <<'EOF'
Finalize live-game player histories for both teams.

EOF
)"
```

---

### Task 8: Channel auth + 1+1 sync checklist

**Files:**
- Modify: `routes/channels.php`, `tests/Feature/LiveGame/LiveGameBroadcastingTest.php`
- Create: `docs/live-game-1plus1-sync-checklist.md`

**Interfaces:**
- Consumes: `LiveGame::isParticipant`
- Produces: channel auth true only for creator or coaches whose `team_id` is home/opponent

- [ ] **Step 1: Update broadcasting tests**

```php
// creator → OK
// home coach → OK
// verified stranger (other team / null team, not creator) → Forbidden
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement channel callback + checklist doc**

Checklist contents:
1. `composer dev` + `php artisan reverb:start`
2. Device A: `warriors@email.com` / `password123` → create or open game (creator) → Start → record 2PT
3. Device B: `lakers@email.com` / `password123` → same `/live-games/{id}` → see home score update → record 3PT
4. Device A sees opponent score update without refresh

- [ ] **Step 4: Pass broadcasting tests**

- [ ] **Step 5: Commit**

```bash
git commit -m "$(cat <<'EOF'
Limit live-game broadcast auth to participants and document 1+1 sync.

EOF
)"
```

---

### Task 9: Regression pass

**Files:** any remaining breakage (factories, EventPad types, schema assertions)

- [ ] **Step 1: Run full LiveGame filter**

Run: `php artisan test --filter=LiveGame`  
Also: `php artisan test --filter=UserTeamMembershipTest`

- [ ] **Step 2: Fix failures** (especially tests still assuming any user can clock/record, or missing opponent lineup on create)

- [ ] **Step 3: Format PHP**

Run: `vendor/bin/pint --dirty`

- [ ] **Step 4: Optional typecheck**

Run: `npm run typecheck`

- [ ] **Step 5: Commit any leftover fixes**

```bash
git commit -m "$(cat <<'EOF'
Fix LiveGame regression fallout for dual-team scoring.

EOF
)"
```

---

## Manual success criteria

- Seeded coaches: `warriors@email.com` → GSW, `lakers@email.com` → LAK (`password123`)
- Create game with two starting fives
- Each coach only events their roster; both scores move from player events
- Non-creator cannot control clock
- Second device updates via Echo within ~1s on local Reverb
- Finish writes `player_histories` for both teams

## Execution note

If the working tree already contains migrations/services/UI for this MVP, **do not wipe them**. For each task: run the task’s tests first; implement only what’s missing; then commit. Prefer completing unfinished authorization/tests over re-scaffolding.
