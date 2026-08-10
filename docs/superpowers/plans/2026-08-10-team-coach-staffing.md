# Team Coach Staffing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add persistent team-level coach staffing so each team can assign one main coach and multiple assistant coaches, surface that staffing on the team detail page, and use it only as a live-game default.

**Architecture:** Store staffing on the team domain itself: `teams.main_coach_user_id` for the single main coach and a dedicated `team_assistant_coaches` pivot for assistants. Expose staffing through `TeamController@show`, update it through a dedicated staffing request path, render it in a focused team-page sheet, and let `LiveGameController@create` prefill the existing per-game assistant flow when the team staffing provides an unambiguous default.

**Tech Stack:** Laravel 13, Inertia 2, React 18 + TypeScript, existing team/live-game controllers and sheets, Pest/PHPUnit feature coverage.

## Global Constraints

- Staffing is managed only from existing `users` already assigned to the team.
- This feature does not create, invite, edit, or remove user accounts.
- One team has at most one main coach.
- One team can have zero or more assistant coaches.
- A user cannot be both main coach and assistant for the same team.
- Only verified users on that same team are eligible selections.
- Staffing data is visible and editable from the team detail page.
- Live-game flows consume staffing as a default only.
- Existing live-game rules that treat verified team users as coaches remain unchanged in v1.
- Keep staffing writes out of the generic `TeamRepository::update` path.
- Keep the existing `Edit Team` sheet scoped to team metadata only.

---

### Task 1: Add team staffing persistence

**Files:**
- Create: `database/migrations/2026_08_10_000001_add_main_coach_user_id_to_teams_table.php`
- Create: `database/migrations/2026_08_10_000002_create_team_assistant_coaches_table.php`
- Modify: `app/Models/Team.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Team/TeamCoachStaffingSchemaTest.php`

**Interfaces:**
- Produces: nullable column `teams.main_coach_user_id`
- Produces: pivot table `team_assistant_coaches(id, team_id, user_id, created_at, updated_at)`
- Produces: `Team::mainCoach()`, `Team::assistantCoaches()`
- Produces: optional inverse helpers on `User` for test setup and query clarity

- [ ] **Step 1: Write the failing schema test**

Create `tests/Feature/Team/TeamCoachStaffingSchemaTest.php` with assertions that:
- `teams` has a nullable `main_coach_user_id` foreign key to `users`
- `team_assistant_coaches` exists
- `team_assistant_coaches` has a unique constraint on `team_id` + `user_id`

Use Laravel schema inspection helpers already used in the repo’s schema tests.

- [ ] **Step 2: Run the schema test to verify it fails**

Run: `php artisan test --filter=TeamCoachStaffingSchemaTest`
Expected: FAIL because the new column/table do not exist yet.

- [ ] **Step 3: Add the migrations**

In `2026_08_10_000001_add_main_coach_user_id_to_teams_table.php`, add:

```php
$table->foreignId('main_coach_user_id')
    ->nullable()
    ->after('logo_path')
    ->constrained('users')
    ->nullOnDelete();
```

In `2026_08_10_000002_create_team_assistant_coaches_table.php`, add:

```php
$table->id();
$table->foreignId('team_id')->constrained()->cascadeOnDelete();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->timestamps();

$table->unique(['team_id', 'user_id']);
```

- [ ] **Step 4: Add model relationships**

In `app/Models/Team.php`, add:

```php
public function mainCoach(): BelongsTo
{
    return $this->belongsTo(User::class, 'main_coach_user_id');
}

public function assistantCoaches(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'team_assistant_coaches')
        ->withTimestamps()
        ->orderBy('name');
}
```

In `app/Models/User.php`, add only the inverse helpers needed by tests or controller queries, for example:

```php
public function mainCoachedTeams(): HasMany
public function assistantCoachedTeams(): BelongsToMany
```

- [ ] **Step 5: Run the schema test to verify it passes**

Run: `php artisan test --filter=TeamCoachStaffingSchemaTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Team.php app/Models/User.php database/migrations/2026_08_10_000001_add_main_coach_user_id_to_teams_table.php database/migrations/2026_08_10_000002_create_team_assistant_coaches_table.php tests/Feature/Team/TeamCoachStaffingSchemaTest.php
git commit -m "feat(team): add persistent coach staffing schema"
```

### Task 2: Add staffing read/write backend flow

**Files:**
- Create: `app/Http/Requests/UpdateTeamStaffingRequest.php`
- Create: `app/Services/TeamStaffingService.php`
- Modify: `app/Http/Controllers/TeamController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Team/TeamCoachStaffingUpdateTest.php`

**Interfaces:**
- Produces: `PUT /teams/{team}/staffing` named `teams.staffing.update`
- Produces: request contract:
  - `main_coach_user_id: number | null`
  - `assistant_coach_user_ids: number[]`
- Produces: atomic staffing replacement behavior

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/Team/TeamCoachStaffingUpdateTest.php` covering:
- saves a main coach
- saves multiple assistants
- replaces previous assistants on update
- rejects a main coach from another team
- rejects assistants from another team
- rejects unverified users
- rejects duplicate assistant ids
- rejects main/assistant overlap
- accepts clearing all assignments

Build the fixtures with real `Team` and `User` rows; keep validation assertions on response errors rather than database exceptions.

- [ ] **Step 2: Run the staffing update tests to verify they fail**

Run: `php artisan test --filter=TeamCoachStaffingUpdateTest`
Expected: FAIL because the route, request, and persistence path do not exist yet.

- [ ] **Step 3: Add the dedicated request**

Create `app/Http/Requests/UpdateTeamStaffingRequest.php` with rules equivalent to:

```php
'main_coach_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
'assistant_coach_user_ids' => ['array'],
'assistant_coach_user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
```

Add after-validation checks that ensure:
- every selected user belongs to the route team
- every selected user has `email_verified_at !== null`
- `main_coach_user_id` is not included in `assistant_coach_user_ids`

Normalize absent assistant input to an empty array.

- [ ] **Step 4: Add the staffing service**

Create `app/Services/TeamStaffingService.php` with a single public method:

```php
public function update(Team $team, ?int $mainCoachUserId, array $assistantCoachUserIds): void
```

Inside a transaction:
- update `$team->main_coach_user_id`
- sync the `assistantCoaches()` relation with the provided ids

Do not route this through `TeamRepository::update`.

- [ ] **Step 5: Add the controller action and route**

In `app/Http/Controllers/TeamController.php`, add:

```php
public function updateStaffing(UpdateTeamStaffingRequest $request, Team $team, TeamStaffingService $teamStaffingService): RedirectResponse
```

Behavior:
- validate the request
- call the staffing service with normalized ids
- redirect back to `teams.show`
- flash `Coach staffing updated.`

In `routes/web.php`, add:

```php
Route::put('/teams/{team}/staffing', [TeamController::class, 'updateStaffing'])->name('teams.staffing.update');
```

- [ ] **Step 6: Run the staffing update tests to verify they pass**

Run: `php artisan test --filter=TeamCoachStaffingUpdateTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/TeamController.php app/Http/Requests/UpdateTeamStaffingRequest.php app/Services/TeamStaffingService.php routes/web.php tests/Feature/Team/TeamCoachStaffingUpdateTest.php
git commit -m "feat(team): add coach staffing update flow"
```

### Task 3: Expose staffing on the team show payload

**Files:**
- Modify: `app/Http/Controllers/TeamController.php`
- Test: `tests/Feature/Team/TeamShowStaffingPayloadTest.php`

**Interfaces:**
- Produces Inertia payload keys:
  - `coachOptions`
  - `mainCoach`
  - `assistantCoaches`
- Consumes `Team::mainCoach()` and `Team::assistantCoaches()`

- [ ] **Step 1: Write the failing payload test**

Create `tests/Feature/Team/TeamShowStaffingPayloadTest.php` asserting that `teams.show` returns:
- `coachOptions` containing only verified users from the viewed team
- `mainCoach` for the current staffing assignment
- `assistantCoaches` for the current staffing assignment

Also assert that unverified same-team users and verified users from other teams are excluded from `coachOptions`.

- [ ] **Step 2: Run the payload test to verify it fails**

Run: `php artisan test --filter=TeamShowStaffingPayloadTest`
Expected: FAIL because `TeamController@show` does not yet provide staffing props.

- [ ] **Step 3: Extend `TeamController@show`**

Load the team staffing relations and compute verified same-team coach options:

```php
$team->load(['mainCoach:id,name,email', 'assistantCoaches:id,name,email']);

$coachOptions = User::query()
    ->where('team_id', $team->id)
    ->whereNotNull('email_verified_at')
    ->orderBy('name')
    ->get(['id', 'name', 'email']);
```

Return them from `Inertia::render('Teams/Show', [...])` as:

```php
'coachOptions' => $coachOptions,
'mainCoach' => $team->mainCoach,
'assistantCoaches' => $team->assistantCoaches,
```

- [ ] **Step 4: Run the payload test to verify it passes**

Run: `php artisan test --filter=TeamShowStaffingPayloadTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/TeamController.php tests/Feature/Team/TeamShowStaffingPayloadTest.php
git commit -m "feat(team): expose coach staffing on team show"
```

### Task 4: Add team-page staffing UI and typing

**Files:**
- Create: `resources/js/Components/features/teams/TeamCoachStaffingCard.tsx`
- Create: `resources/js/Components/features/teams/TeamCoachStaffingSheet.tsx`
- Modify: `resources/js/Pages/Teams/Show.tsx`
- Modify: `resources/js/types/index.ts`
- Test: targeted frontend type check or build command already used by the repo

**Interfaces:**
- Produces: `CoachOption` TypeScript type
- Produces: `TeamCoachSummary` TypeScript type
- Produces: team show page props for `coachOptions`, `mainCoach`, `assistantCoaches`
- Consumes route `teams.staffing.update`

- [ ] **Step 1: Add the new page-level types**

In `resources/js/types/index.ts`, add:

```ts
export interface CoachOption {
  id: number;
  name: string;
  email: string;
}

export interface TeamCoachSummary {
  id: number;
  name: string;
  email: string;
}
```

Keep these scoped to the team page payload instead of inflating the global `Team` type with staffing-only fields.

- [ ] **Step 2: Add the staffing sheet**

Create `resources/js/Components/features/teams/TeamCoachStaffingSheet.tsx` that:
- uses existing sheet/modal patterns from the repo
- renders a main-coach single select
- renders assistant checkboxes or a multi-select list
- submits to `route('teams.staffing.update', team.id)` with:
  - `main_coach_user_id`
  - `assistant_coach_user_ids`
- shows inline field errors from Inertia form state
- preserves page context on cancel/save

Disable the selected main coach inside the assistant selector where practical, but keep the server as the source of truth.

- [ ] **Step 3: Add the staffing summary card**

Create `resources/js/Components/features/teams/TeamCoachStaffingCard.tsx` that shows:
- current main coach block
- assistant coaches block
- eligible coach count
- empty state when there are no verified team users
- empty state when assistants are unassigned
- `Manage staffing` action that opens the sheet

Match the existing `Teams/Show` dashboard-card styling rather than introducing a new visual system.

- [ ] **Step 4: Wire the team show page**

Update `resources/js/Pages/Teams/Show.tsx` to:
- accept `coachOptions`, `mainCoach`, `assistantCoaches`
- render the new `Coach Staffing` card above the players section
- keep the existing `Edit Team` and `Add Player` flows unchanged

- [ ] **Step 5: Run frontend verification**

Run the repo’s TypeScript/build verification command for the touched frontend files.
Expected: PASS with no type errors from the new page props or sheet state.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/features/teams/TeamCoachStaffingCard.tsx resources/js/Components/features/teams/TeamCoachStaffingSheet.tsx resources/js/Pages/Teams/Show.tsx resources/js/types/index.ts
git commit -m "feat(team): add coach staffing management UI"
```

### Task 5: Prefill live-game assistant defaults from team staffing

**Files:**
- Modify: `app/Http/Controllers/LiveGameController.php`
- Modify: `resources/js/Pages/LiveGames/Create.tsx`
- Test: `tests/Feature/LiveGame/LiveGameCreateStaffingDefaultsTest.php`

**Interfaces:**
- Consumes `Team::assistantCoaches()` staffing data
- Produces existing `assistantCoachOptions` payload plus optional default selection data
- Preserves current per-game delegation write logic

- [ ] **Step 1: Write the failing live-game default test**

Create `tests/Feature/LiveGame/LiveGameCreateStaffingDefaultsTest.php` covering:
- when the user’s team has exactly one assistant coach assignment, `live-games.create` returns that coach as the default selection
- when the team has multiple assistant coaches, `live-games.create` still returns all options but leaves the default unset
- when the team has no assigned assistants, the default remains unset

- [ ] **Step 2: Run the live-game default test to verify it fails**

Run: `php artisan test --filter=LiveGameCreateStaffingDefaultsTest`
Expected: FAIL because `LiveGameController@create` does not yet use team staffing.

- [ ] **Step 3: Extend the controller payload**

In `app/Http/Controllers/LiveGameController.php`, replace the current generic same-team verified-user query with team staffing-aware behavior:
- keep the coach options list editable and based on verified same-team users
- load the user’s team staffing
- compute `defaultAssistantCoachUserId` as the single assigned assistant’s id only when exactly one assistant exists

Return:

```php
'assistantCoachOptions' => $assistantCoachOptions,
'defaultAssistantCoachUserId' => $defaultAssistantCoachUserId,
```

- [ ] **Step 4: Prefill the create page without locking it**

Update `resources/js/Pages/LiveGames/Create.tsx` so the existing assistant-coach selection UI:
- initializes from `defaultAssistantCoachUserId` when present
- remains editable
- does not change the existing submit contract or validation behavior

- [ ] **Step 5: Run the live-game default test and frontend verification**

Run:
- `php artisan test --filter=LiveGameCreateStaffingDefaultsTest`
- the repo’s frontend type/build verification command

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/LiveGameController.php resources/js/Pages/LiveGames/Create.tsx tests/Feature/LiveGame/LiveGameCreateStaffingDefaultsTest.php
git commit -m "feat(live-game): prefill assistant coach from team staffing"
```

### Task 6: Run full regression checks for staffing scope

**Files:**
- Modify: tests only if regressions found

**Interfaces:**
- Verifies the combined contract across team staffing and live-game setup

- [ ] **Step 1: Run the focused backend suite**

Run:

```bash
php artisan test --filter=TeamCoachStaffingSchemaTest
php artisan test --filter=TeamCoachStaffingUpdateTest
php artisan test --filter=TeamShowStaffingPayloadTest
php artisan test --filter=LiveGameCreateStaffingDefaultsTest
```

Expected: all PASS.

- [ ] **Step 2: Run adjacent live-game coverage**

Run the existing live-game create/delegation tests most likely to regress after assistant default changes.

Suggested command:

```bash
php artisan test --filter=LiveGameDelegationTest
```

Expected: PASS, proving the per-game override flow is still editable.

- [ ] **Step 3: Run frontend verification**

Run the repo’s frontend type/build verification command.
Expected: PASS.

- [ ] **Step 4: Review the product requirements against the diff**

Confirm the implementation still satisfies:
- staffing does not alter authorization
- staffing does not create a separate coaches page
- staffing is not merged into the generic team edit sheet
- assistants may be assigned even when no main coach is assigned

- [ ] **Step 5: Commit any follow-up fixes**

Only if needed after regression verification:

```bash
git add <follow-up files>
git commit -m "fix(team): address coach staffing regressions"
```

## Self-Review

- Spec coverage: this plan covers persistence, backend validation/write path, team show payload, team-page staffing UI, live-game default prefilling, and regression checks.
- Placeholder scan: all tasks name concrete files, routes, payload keys, and test targets.
- Type consistency: backend payload keys and frontend prop names are aligned on `coachOptions`, `mainCoach`, `assistantCoaches`, and `defaultAssistantCoachUserId`.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-10-team-coach-staffing.md`.

Two execution options:

1. Subagent-Driven (recommended) - dispatch a fresh subagent per task with review between tasks.
2. Inline Execution - execute tasks in this session with manual checkpoints.
