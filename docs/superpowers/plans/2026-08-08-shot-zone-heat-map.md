# Shot Zone Heat Map — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Decisions log (2026-08-08 session):** who records zones, opponent-side head coaches, separate season shot-profile CSV with history reconcile, SVG court (no Jumpman raster), overlay as non-blocking sheet. Mirror of working notes: `.cursor/plans/shot_zone_input_flow_34c56f20.plan.md`.

**Goal:** Capture *where* each shot was taken during a live game, seed pre-live season heat via a separate shot-profile CSV, and render each player's merged season shot profile as a five-zone half-court heat map on the player matchup page.

**Architecture:** Live zones ride in `live_game_events.payload` via a skippable post-shot `PATCH` (write-once). Pre-live baselines live in a new `player_shot_zone_profiles` table fed by a **separate** season CSV (not player-history). Matchup heat = **profile + live located shots**, colored by **points per shot**. History CSV / `player_histories` stay box-score only but **must reconcile** with the profile’s 2PT/3PT splits when both exist. Court UI is **SVG only** — do not ship `public/images/half-court.png` (Jumpman) or the undersized webp as the interactive surface.

**Tech Stack:** Laravel 13, Inertia 2, React 18 + TypeScript, Pest, SQLite (dev/test) / MySQL (prod). **No Python** — this is counting, not modelling; `analytics/engine.py` is untouched.

## Global Constraints

- Layering is strictly enforced: Controller → FormRequest → Service → Repository → Model. Controllers never touch Eloquent or `PythonEngineService`.
- `resources/js/Components/ui/` is shadcn base and **must never be modified**. Extend via `className` or add variants under `Components/features/<domain>/`.
- No REST API. All page data arrives as Inertia props.
- New frontend files are `.tsx`.
- Design at the 768–1024px tablet breakpoint first.
- `resources/js/Components/features/live-game/event-catalog.ts` is a hand-maintained mirror of `App\Services\LiveGame\LiveGameEventRules`. Anything added to one is added to the other in the same commit.
- Zone slugs, used verbatim everywhere (DB payload, TS, PHP): `paint`, `mid_range`, `corner_3_left`, `corner_3_right`, `above_break_3`.
- Zone point values: `paint` → 2, `mid_range` → 2, `corner_3_left` → 3, `corner_3_right` → 3, `above_break_3` → 3.
- Minimum attempts before a zone is colored: **5**. Below that the zone renders neutral gray with its raw count only.
- Free throws never carry a zone and are excluded from every court calculation.
- Run `vendor/bin/pint` before each PHP commit.

## Implementation order — Decision locked

**Do live capture first, then the season shot-profile CSV.** Do not flip it.

| Phase | Tasks | Why first / second |
|---|---|---|
| **1. Live path** | 1 → 2 → 5 → 6 → 3 → 4 → 7 → 8 | Vocabulary + PATCH + SVG overlay are the courtside loop. Opponent/head control (3–4) unblocks full live logging. Aggregation + matchup (7–8) can ship with `profileShotsFor` returning empty zeros so heat works from live located shots alone. |
| **2. Pre-live baseline** | 9 | Profile CSV is additive: same `ShotZoneService` merge, new table/import/UI. Needs Task 7’s merge shape and Task 8’s court to be real before import is worth verifying on matchup. |

Why not CSV first:

- Matchup heat without live is only a static import demo; the product risk is the overlay + opponent shot gates under game pressure.
- Reconcile rules in Task 9 need stable history + merge behavior from Task 7.
- Shipping profile before the overlay invites “we have heat maps” without the capture path that keeps them honest after tip-off.

Stub rule while Phase 1 ships: `ShotZoneRepository::profileShotsFor` / `profileReconcilesWithHistory` exist but return empty / `true` until Task 9 fills them in.

## Locked product decisions

### Who inputs the zone

**Symmetric on both benches:** any coach who records a field-goal for a player they control gets the zone overlay. Not assistants-only.

| Who | Can record shot? | Gets zone overlay? |
|---|---|---|
| Head coach on own-team players they control | Yes | Yes |
| Assistant on delegated own-team players | Yes | Yes |
| Head coach on opponent players they control | Yes (shots only) | Yes |
| Assistant on delegated opponent players | Yes (shots only) | Yes |
| Coach who did not record that event | — | No (`recorded_by_user_id` only) |
| Non-shot events on opponent players | No | — |

Head coach opponent control: unassigned opponent players fall to the head (same idea as own roster leftovers). Assistants may take exclusive opponent assignments. Do **not** fake head as an assistant in `LiveGameDelegationWriter` (it still rejects `assistant === mainCoach`).

### Season shot-profile CSV (separate from history)

- **Chosen:** one row per player, five zones × made/attempted → `player_shot_zone_profiles` (upsert).
- **Rejected:** zone columns on `PlayerHistoryImportJob::HEADERS`; live-only empty courts until live data.
- Roster CSV stays roster-only. History CSV unchanged.
- Do **not** fake `live_game_events` from the import.

**Reconcile against history when history exists** (hard row reject on mismatch):

| Check | Rule |
|---|---|
| Per zone | `made <= attempted` |
| 2PT zones | `paint_* + mid_range_*` attempts/makes == season `(FGA−3PA)` / `(FGM−3PM)` |
| 3PT zones | three 3PT zones’ attempts/makes == season `3PA` / `3PM` |

Season FG totals = summed `player_histories`. No history yet → accept profile; surface mismatch warning when history arrives / on next profile import. Live Skip does not rewrite the imported baseline; live coverage stays a separate footer metric.

### Court asset

- Implement Task 5 SVG `HalfCourt`. Quarantine Jumpman PNG from production UI paths. WebP is too small (283×197) for tablet tap targets.

### Overlay UX (critique)

- Prefer pad-anchored **non-blocking sheet**, not a full-screen confirm Dialog.
- Context chip: `{Player} · 2PT/3PT make|miss`.
- PATCH fail: keep sheet open with Retry + Skip (do not flash-close).
- Expand corner-3 hit targets (or mirror zone buttons under the court).

## Key discovery — read before starting

`app/Services/LiveGame/LiveGameProjectionService.php:100-124` resolves a shot's side from **the player's `team_id`**, not from who recorded it:

```php
$isHomePlayer = $teamId === (int) $game->home_team_id;
...
if ($isHomePlayer) { $homeScore += $points; ... } else { $opponentScore += $points; ... }
```

So an event with `team_scope: 'own'` carrying an **opponent** player already scores to `opponent_score`, builds that player's `LiveGamePlayerStat`, and applies plus/minus in the correct direction. Opponent logging needs **no projection changes**. Gates to widen:

1. `app/Http/Controllers/LiveGameEventController.php:142-146` — rejects any player whose `team_id` differs from `$user->team_id`.
2. `app/Services/LiveGame/LiveGameEventRecorder.php:222-237` — derives `$side` from `LiveGame::sideFor($user)` (which is purely `$user->team_id`), so an opponent player is never in `activePlayerIdsForSide($side)`.

Authorization for opponent FGs: **controlling coach** — head coach for opponent players not exclusively delegated away, or an assistant with a `LiveGamePlayerDelegation` for that player. `live_game_player_delegations` has no team constraint, so opponent players are already storable for assistants.

## File Structure

**Create**
- `app/Enums/ShotZone.php` — zone slugs, point values, which zones are legal for a given shot value.
- `app/Http/Requests/AttachShotZoneRequest.php` — validation for the zone PATCH.
- `app/Models/PlayerShotZoneProfile.php` + migration `player_shot_zone_profiles`.
- `app/Repositories/ShotZoneRepository.php` — live located shots + profile load.
- `app/Services/ShotZoneService.php` — merge profile + live → display model (pct, PPS, `has_enough_data`, live coverage).
- `app/Jobs/ShotZoneProfileImportJob.php` (+ FormRequest / Service / Repository pieces as needed).
- `resources/js/Components/features/analytics/HalfCourt.tsx` — one SVG, two modes (interactive / display).
- `resources/js/Components/features/live-game/ShotZoneOverlay.tsx` — skippable post-shot sheet.
- `resources/js/Components/features/comparison/ShotZoneCourt.tsx` — display court + coverage footer.
- Tests: `tests/Unit/Services/ShotZoneServiceTest.php`, `tests/Feature/LiveGame/AttachShotZoneTest.php`, `tests/Feature/LiveGame/OpponentShotRecordingTest.php`, `tests/Feature/ShotZoneMatchupTest.php`, `tests/Feature/ShotZoneProfileImportTest.php`.

**Modify**
- `app/Http/Controllers/LiveGameEventController.php` — add `attachZone()`; relax the own-team gate for shots only (head + delegated assistants).
- `app/Services/LiveGame/LiveGameEventRecorder.php` — add `attachZone()`; player-side resolution for opponent shots under control.
- `app/Services/LiveGame/LiveGameControlResolver.php` — opponent players controllable by head by default.
- `app/Services/LiveGame/LiveGameDelegationWriter.php` — permit opponent-roster delegations to assistants.
- `routes/web.php` — zone PATCH + shot-profile import routes.
- `resources/js/Components/features/live-game/event-catalog.ts` — `SHOT_ZONES`.
- `resources/js/Components/features/live-game/EventPad.tsx` — open the overlay after a successful shot.
- `resources/js/Components/features/live-game/AssignAssistantPanel.tsx` — opponent-roster section (shots only).
- Player/team import UI — “Import shot zone profile” + template download (separate from history).
- The comparison show controller + `resources/js/Pages/Comparison/Show.tsx` — pass and render zone profiles.

---

### Task 1: Shared zone vocabulary

**Files:**
- Create: `app/Enums/ShotZone.php`
- Modify: `resources/js/Components/features/live-game/event-catalog.ts`
- Test: `tests/Unit/Services/ShotZoneEnumTest.php`

**Interfaces:**
- Produces: `ShotZone::points(): int`, `ShotZone::forPoints(int $points): array<ShotZone>`, `ShotZone::tryFrom(string)`. TS: `SHOT_ZONES: ShotZoneMeta[]`, `zonesForPoints(points: 2 | 3): ShotZoneMeta[]`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/Services/ShotZoneEnumTest.php
use App\Enums\ShotZone;

it('knows what each zone is worth', function () {
    expect(ShotZone::Paint->points())->toBe(2)
        ->and(ShotZone::MidRange->points())->toBe(2)
        ->and(ShotZone::CornerThreeLeft->points())->toBe(3)
        ->and(ShotZone::CornerThreeRight->points())->toBe(3)
        ->and(ShotZone::AboveBreakThree->points())->toBe(3);
});

it('lists only the zones legal for a shot value', function () {
    expect(array_map(fn (ShotZone $z) => $z->value, ShotZone::forPoints(2)))
        ->toEqualCanonicalizing(['paint', 'mid_range'])
        ->and(array_map(fn (ShotZone $z) => $z->value, ShotZone::forPoints(3)))
        ->toEqualCanonicalizing(['corner_3_left', 'corner_3_right', 'above_break_3']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/ShotZoneEnumTest.php`
Expected: FAIL — `Class "App\Enums\ShotZone" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Enums;

/**
 * Shot locations captured during live games.
 * Mirror of SHOT_ZONES in resources/js/Components/features/live-game/event-catalog.ts.
 * Change both together.
 */
enum ShotZone: string
{
    case Paint = 'paint';
    case MidRange = 'mid_range';
    case CornerThreeLeft = 'corner_3_left';
    case CornerThreeRight = 'corner_3_right';
    case AboveBreakThree = 'above_break_3';

    public function points(): int
    {
        return match ($this) {
            self::Paint, self::MidRange => 2,
            self::CornerThreeLeft, self::CornerThreeRight, self::AboveBreakThree => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Paint => 'Paint',
            self::MidRange => 'Mid-range',
            self::CornerThreeLeft => 'Left corner 3',
            self::CornerThreeRight => 'Right corner 3',
            self::AboveBreakThree => 'Above the break 3',
        };
    }

    /** @return list<self> */
    public static function forPoints(int $points): array
    {
        return array_values(array_filter(self::cases(), fn (self $zone): bool => $zone->points() === $points));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $zone): string => $zone->value, self::cases());
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/ShotZoneEnumTest.php`
Expected: PASS

- [ ] **Step 5: Add the TypeScript mirror**

Append to `resources/js/Components/features/live-game/event-catalog.ts`:

```ts
/**
 * Mirror of App\Enums\ShotZone. Change both together.
 * Slugs are written verbatim into live_game_events.payload.zone.
 */
export type ShotZoneKey =
    | 'paint'
    | 'mid_range'
    | 'corner_3_left'
    | 'corner_3_right'
    | 'above_break_3';

export interface ShotZoneMeta {
    key: ShotZoneKey;
    label: string;
    points: 2 | 3;
}

export const SHOT_ZONES: ShotZoneMeta[] = [
    { key: 'paint', label: 'Paint', points: 2 },
    { key: 'mid_range', label: 'Mid-range', points: 2 },
    { key: 'corner_3_left', label: 'Left corner 3', points: 3 },
    { key: 'corner_3_right', label: 'Right corner 3', points: 3 },
    { key: 'above_break_3', label: 'Above the break 3', points: 3 },
];

/** A zone is only tappable when its value matches the shot that was just recorded. */
export function zonesForPoints(points: 2 | 3): ShotZoneMeta[] {
    return SHOT_ZONES.filter((zone) => zone.points === points);
}

/** Attempts required in a zone before it earns any color. Below this it stays gray. */
export const MIN_ZONE_ATTEMPTS = 5;
```

- [ ] **Step 6: Typecheck and commit**

```bash
npm run typecheck && vendor/bin/pint
git add app/Enums/ShotZone.php tests/Unit/Services/ShotZoneEnumTest.php resources/js/Components/features/live-game/event-catalog.ts
git commit -m "feat(shot-zones): add shared five-zone vocabulary"
```

---

### Task 2: Write-once zone attach endpoint

The shot saves on the first tap, so the zone arrives in a second request. The event log is not freely mutable — corrections go through `voids_event_id` — so this is a deliberately narrow, write-once endpoint, not a general event update.

**Files:**
- Create: `app/Http/Requests/AttachShotZoneRequest.php`
- Modify: `app/Http/Controllers/LiveGameEventController.php`, `app/Services/LiveGame/LiveGameEventRecorder.php`, `routes/web.php`
- Test: `tests/Feature/LiveGame/AttachShotZoneTest.php`

**Interfaces:**
- Consumes: `App\Enums\ShotZone` (Task 1).
- Produces: `LiveGameEventRecorder::attachZone(LiveGame $game, User $user, LiveGameEvent $event, ShotZone $zone): array` returning the same state snapshot shape as `record()`. Route name `live-games.events.zone`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/LiveGame/AttachShotZoneTest.php
use App\Models\LiveGameEvent;

// Reuse whatever live-game fixture helper the existing tests in tests/Feature/LiveGame use.
// If none exists, build the game with factories exactly as the neighbouring tests do.

it('attaches a zone to a shot event', function () {
    ['game' => $game, 'coach' => $coach, 'event' => $event] = liveGameWithRecordedShot(points: 3);

    $this->actingAs($coach)
        ->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'corner_3_left'])
        ->assertOk();

    expect(LiveGameEvent::find($event->id)->payload['zone'])->toBe('corner_3_left');
});

it('refuses to overwrite a zone that is already set', function () {
    ['game' => $game, 'coach' => $coach, 'event' => $event] = liveGameWithRecordedShot(points: 3);

    $this->actingAs($coach)->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'corner_3_left'])->assertOk();

    $this->actingAs($coach)
        ->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'above_break_3'])
        ->assertStatus(422);

    expect(LiveGameEvent::find($event->id)->payload['zone'])->toBe('corner_3_left');
});

it('refuses a zone whose value contradicts the shot', function () {
    ['game' => $game, 'coach' => $coach, 'event' => $event] = liveGameWithRecordedShot(points: 2);

    $this->actingAs($coach)
        ->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'corner_3_left'])
        ->assertStatus(422);
});

it('refuses a zone on a non-shot event', function () {
    ['game' => $game, 'coach' => $coach, 'event' => $event] = liveGameWithRecordedTurnover();

    $this->actingAs($coach)
        ->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'paint'])
        ->assertStatus(422);
});

it('refuses a zone on a voided event', function () {
    ['game' => $game, 'coach' => $coach, 'event' => $event] = liveGameWithVoidedShot(points: 2);

    $this->actingAs($coach)
        ->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'paint'])
        ->assertStatus(422);
});

it('refuses a zone from a coach who did not record the shot', function () {
    ['game' => $game, 'event' => $event, 'otherCoach' => $other] = liveGameWithRecordedShot(points: 2);

    $this->actingAs($other)
        ->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'paint'])
        ->assertStatus(403);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/LiveGame/AttachShotZoneTest.php`
Expected: FAIL — route `live-games.events.zone` not defined.

- [ ] **Step 3: Write the FormRequest**

```php
<?php

namespace App\Http\Requests;

use App\Enums\ShotZone;
use App\Models\LiveGameEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachShotZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var LiveGameEvent $event */
        $event = $this->route('event');

        return $this->user() !== null
            && (int) $event->recorded_by_user_id === (int) $this->user()->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'zone' => ['required', 'string', Rule::in(ShotZone::values())],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var LiveGameEvent $event */
            $event = $this->route('event');
            $zone = ShotZone::tryFrom((string) $this->input('zone'));

            if ((int) $event->live_game_id !== (int) $this->route('liveGame')->id) {
                $validator->errors()->add('zone', 'That event does not belong to this live game.');

                return;
            }

            if (! in_array($event->type, ['shot_made', 'shot_missed'], true)) {
                $validator->errors()->add('zone', 'Only field goal attempts carry a location.');

                return;
            }

            if (($event->payload['zone'] ?? null) !== null) {
                $validator->errors()->add('zone', 'This shot already has a location.');
            }

            if ($zone !== null && $zone->points() !== (int) ($event->payload['points'] ?? 0)) {
                $validator->errors()->add('zone', 'That location does not match the value of this shot.');
            }

            $isVoided = $this->route('liveGame')->events()
                ->where('type', 'correction')
                ->where('voids_event_id', $event->id)
                ->exists();

            if ($isVoided) {
                $validator->errors()->add('zone', 'This shot has been voided.');
            }
        });
    }
}
```

- [ ] **Step 4: Add the recorder method**

Append to `app/Services/LiveGame/LiveGameEventRecorder.php`. It mirrors `record()`: transaction, rebuild projection, broadcast.

```php
    /**
     * Write-once attach of a shot location. The shot itself is already persisted;
     * this only fills payload.zone when it is absent. Never overwrites, never
     * deletes — corrections still go through voids_event_id.
     *
     * @return array<string, mixed>
     */
    public function attachZone(LiveGame $game, User $user, LiveGameEvent $event, ShotZone $zone): array
    {
        $snapshot = DB::transaction(function () use ($game, $event, $zone): array {
            $game = LiveGame::query()->lockForUpdate()->findOrFail($game->id);
            $event = LiveGameEvent::query()->lockForUpdate()->findOrFail($event->id);

            $payload = is_array($event->payload) ? $event->payload : [];

            if (($payload['zone'] ?? null) !== null) {
                throw ValidationException::withMessages(['zone' => 'This shot already has a location.']);
            }

            $payload['zone'] = $zone->value;
            $event->payload = $payload;
            $event->save();

            return $this->stateBuilder->build($game);
        });

        event(new LiveGameStateUpdated($game->id, $snapshot));

        return $snapshot;
    }
```

Add `use App\Enums\ShotZone;` to the imports.

> Note: the projection is intentionally **not** rebuilt — a zone changes no stat, only how the shot is described. Skipping the rebuild keeps the extra tap cheap during a game.

- [ ] **Step 5: Add controller method and route**

In `app/Http/Controllers/LiveGameEventController.php`:

```php
    public function attachZone(
        AttachShotZoneRequest $request,
        LiveGame $liveGame,
        LiveGameEvent $event,
        LiveGameEventRecorder $recorder,
    ): JsonResponse {
        return response()->json($recorder->attachZone(
            $liveGame,
            $request->user(),
            $event,
            ShotZone::from($request->validated()['zone']),
        ));
    }
```

Imports: `App\Enums\ShotZone`, `App\Http\Requests\AttachShotZoneRequest`, `App\Models\LiveGameEvent`.

In `routes/web.php`, directly after the existing events route at line 42:

```php
    Route::patch('/live-games/{liveGame}/events/{event}/zone', [LiveGameEventController::class, 'attachZone'])
        ->name('live-games.events.zone');
```

Match the middleware of the neighbouring `live-games.events.store` route on line 42-44 exactly.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/LiveGame/AttachShotZoneTest.php`
Expected: PASS (6 tests)

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint
git add app/Http/Requests/AttachShotZoneRequest.php app/Http/Controllers/LiveGameEventController.php app/Services/LiveGame/LiveGameEventRecorder.php routes/web.php tests/Feature/LiveGame/AttachShotZoneTest.php
git commit -m "feat(shot-zones): write-once zone attach endpoint"
```

---

### Task 3: Cross-team shot recording (head + delegated assistants)

**Files:**
- Modify: `app/Http/Controllers/LiveGameEventController.php:142-146`, `app/Services/LiveGame/LiveGameEventRecorder.php:219-237`, `app/Services/LiveGame/LiveGameControlResolver.php`, `app/Models/LiveGame.php` (`sideForPlayer`)
- Test: `tests/Feature/LiveGame/OpponentShotRecordingTest.php`

**Interfaces:**
- Produces: nothing new — this widens two existing gates. `shot_made` / `shot_missed` become recordable for a player on **either** team when the recorder **controls** that player: head coach for opponent players not exclusively delegated away, or an assistant with a `LiveGamePlayerDelegation`. Every other event type keeps the strict own-team rule.

Also cover: head coach records undelegated opponent FG; head refused on opponent foul; assistant without control refused.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/LiveGame/OpponentShotRecordingTest.php
use App\Models\LiveGamePlayerDelegation;

it('lets the head coach record an opponent shot for an undelegated opponent player', function () {
    ['game' => $game, 'headCoach' => $head, 'opponentPlayer' => $player] = liveGameWithOpponentRoster();

    $this->actingAs($head)
        ->postJson(route('live-games.events.store', $game), [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 2],
        ])
        ->assertOk();

    expect($game->fresh()->opponent_score)->toBe(2);
});

it('lets a delegated assistant record an opponent shot', function () {
    ['game' => $game, 'assistant' => $assistant, 'opponentPlayer' => $player] = liveGameWithOpponentDelegation();

    $this->actingAs($assistant)
        ->postJson(route('live-games.events.store', $game), [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 2],
        ])
        ->assertOk();

    expect($game->fresh()->opponent_score)->toBe(2)
        ->and($game->fresh()->home_score)->toBe(0);
});

it('refuses an opponent shot without a delegation', function () {
    ['game' => $game, 'assistant' => $assistant, 'opponentPlayer' => $player] = liveGameWithOpponentDelegation();
    LiveGamePlayerDelegation::query()->where('player_id', $player->id)->delete();

    $this->actingAs($assistant)
        ->postJson(route('live-games.events.store', $game), [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 2],
        ])
        ->assertStatus(422);
});

it('still refuses a foul on an opponent player even with a delegation', function () {
    ['game' => $game, 'assistant' => $assistant, 'opponentPlayer' => $player] = liveGameWithOpponentDelegation();

    $this->actingAs($assistant)
        ->postJson(route('live-games.events.store', $game), [
            'type' => 'foul',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['kind' => 'personal'],
        ])
        ->assertStatus(422);
});

it('credits plus-minus to the correct side for a delegated opponent shot', function () {
    ['game' => $game, 'assistant' => $assistant, 'opponentPlayer' => $player, 'homePlayer' => $homePlayer]
        = liveGameWithOpponentDelegation();

    $this->actingAs($assistant)->postJson(route('live-games.events.store', $game), [
        'type' => 'shot_made',
        'team_scope' => 'own',
        'player_id' => $player->id,
        'payload' => ['points' => 3],
    ])->assertOk();

    expect(livePlusMinus($game, $homePlayer))->toBe(-3)
        ->and(livePlusMinus($game, $player))->toBe(3);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/LiveGame/OpponentShotRecordingTest.php`
Expected: FAIL — 422 "You can only record events for your own team."

- [ ] **Step 3: Relax the controller gate**

Replace the own-team-only check so opponent players are allowed **only** for `shot_made` / `shot_missed` when the recorder **controls** that player (via `LiveGameControlResolver` extended for opponent roster — head gets undelegated leftovers; assistants need a delegation). Do not require a delegation row for the head. Fouls/subs/turnovers stay own-team only.

Also relax the `$side === null` guard so a controlled opponent shot is not blocked when `sideFor($user)` is home-only — gate on “controlled opponent shot”, not “has delegation” alone.

- [ ] **Step 4: Relax the recorder gate**

In `app/Services/LiveGame/LiveGameEventRecorder.php`, for shots on a controlled player, resolve side with `$game->sideForPlayer($playerId)` (works for home and opponent). For non-shots, keep existing own-side + `$controlledPlayerIds` behavior.

Add to `app/Models/LiveGame.php`, beside `sideFor()` at line 117:

```php
    /** @return self::SIDE_HOME|self::SIDE_OPPONENT|null */
    public function sideForPlayer(int $playerId): ?string
    {
        $teamId = (int) (Player::query()->whereKey($playerId)->value('team_id') ?? 0);

        if ($teamId === (int) $this->home_team_id) {
            return self::SIDE_HOME;
        }

        if ($teamId === (int) $this->opponent_team_id) {
            return self::SIDE_OPPONENT;
        }

        return null;
    }
```

Extend `LiveGameControlResolver` so opponent-roster control mirrors own-roster leftovers for the head coach, with assistants claiming exclusive opponent players via delegation. Shot-only enforcement stays in the event gates (control alone must not unlock fouls).

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/LiveGame/OpponentShotRecordingTest.php`
Expected: PASS (including head-coach undelegated opponent shot)

- [ ] **Step 6: Run the whole live-game suite for regressions**

Run: `php artisan test tests/Feature/LiveGame tests/Unit/Services/LiveGame`
Expected: PASS. This widened a shared gate — a green run here is the gate for the commit.

- [ ] **Step 7: Verify the finalizer**

Read `app/Services/LiveGame/LiveGameFinalizer.php:56-113`. Confirm that finalizing a game where an opponent player has a stat row writes that player's `player_histories` row with `playing_team_id` = the **opponent** team and `opponent_team_id` = the home team — not reversed. If the existing per-team grouping already handles it, note that in the commit message. If it does not, fix it here and add a test to `tests/Feature/LiveGame` asserting both directions.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint
git add app/Http/Controllers/LiveGameEventController.php app/Services/LiveGame/LiveGameEventRecorder.php app/Models/LiveGame.php tests/Feature/LiveGame/OpponentShotRecordingTest.php
git commit -m "feat(shot-zones): allow head and assistants to log opponent shots"
```

When implementing the recorder/controller gates in this task, authorize opponent FGs via **control**, not “has a delegation row” alone — head must work without a self-delegation. Delegation rows remain the exclusive-claim mechanism for assistants.

---

### Task 4: Opponent-roster delegation (assistants) + head default control

**Files:**
- Modify: `app/Services/LiveGame/LiveGameDelegationWriter.php`, `app/Services/LiveGame/LiveGameControlResolver.php`, `resources/js/Components/features/live-game/AssignAssistantPanel.tsx`
- Test: extend `tests/Feature/LiveGame/OpponentShotRecordingTest.php`

**Interfaces:**
- Consumes: `LiveGamePlayerDelegation` (unchanged schema — `lgpd_player_u` already guarantees one coach per player per game, which is what prevents two people double-logging the same shot).
- Produces: opponent-roster players become assignable to **assistants** through the existing assistant flow; head coach controls undelegated opponent players for **shots only** without being written as an assistant.

- [ ] **Step 1: Write the failing test**

```php
it('assigns an opponent player to an assistant', function () {
    ['game' => $game, 'headCoach' => $head, 'assistant' => $assistant, 'opponentPlayer' => $player]
        = liveGameWithAssistant();

    $this->actingAs($head)
        ->postJson(route('live-games.delegations.store', $game), [
            'coach_user_id' => $assistant->id,
            'player_ids' => [$player->id],
        ])
        ->assertOk();

    expect(LiveGamePlayerDelegation::query()
        ->where('live_game_id', $game->id)
        ->where('player_id', $player->id)
        ->value('coach_user_id'))->toBe($assistant->id);
});

it('refuses to assign a player from a team not in this game', function () {
    ['game' => $game, 'headCoach' => $head, 'assistant' => $assistant] = liveGameWithAssistant();
    $stranger = \App\Models\Player::factory()->create();

    $this->actingAs($head)
        ->postJson(route('live-games.delegations.store', $game), [
            'coach_user_id' => $assistant->id,
            'player_ids' => [$stranger->id],
        ])
        ->assertStatus(422);
});
```

> Use the actual delegation route name from `routes/web.php` — read it before writing the test rather than assuming `live-games.delegations.store`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/LiveGame/OpponentShotRecordingTest.php --filter=opponent`
Expected: FAIL — delegation writer rejects the off-team player.

- [ ] **Step 3: Widen the writer**

In `app/Services/LiveGame/LiveGameDelegationWriter.php`, replace the own-team roster check with a **both-rosters** check: a player is delegatable when their `team_id` is either `$game->home_team_id` or `$game->opponent_team_id`. Keep every other guard (game status, one-coach-per-player, assistant must be a participant) exactly as it is.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/LiveGame/OpponentShotRecordingTest.php`
Expected: PASS

- [ ] **Step 5: Add the opponent section to the panel**

In `resources/js/Components/features/live-game/AssignAssistantPanel.tsx`, add a second roster group below the existing own-team list, headed `Opponent — shots only`, listing opponent active players with the same row component and the same assign/unassign call. Add a one-line hint under the heading: `Assistants assigned here can log shots only — not fouls or substitutions. Unassigned opponent players stay with the head coach for shots.`

Reuse the existing row markup; do not fork it. Ensure head-coach EventPad can select undelegated opponent players for shot buttons (control resolver), without requiring a delegation to self.

- [ ] **Step 6: Typecheck and commit**

```bash
npm run typecheck && vendor/bin/pint
git add app/Services/LiveGame/LiveGameDelegationWriter.php app/Services/LiveGame/LiveGameControlResolver.php resources/js/Components/features/live-game/AssignAssistantPanel.tsx tests/Feature/LiveGame/OpponentShotRecordingTest.php
git commit -m "feat(shot-zones): opponent shot control for head and assistants"
```

---

### Task 5: Shared half-court SVG

**Files:**
- Create: `resources/js/Components/features/analytics/HalfCourt.tsx`

**Do not** use `public/images/half-court.png` (Jumpman / mislabeled JPEG) or `half-court.webp` (283×197) as the interactive or heat substrate. SVG paths only; rasters are reference at most.

**Interfaces:**
- Consumes: `ShotZoneKey`, `SHOT_ZONES` from `event-catalog.ts` (Task 1).
- Produces:

```ts
export interface HalfCourtZoneState {
    fill: string;          // resolved color, or the neutral gray
    disabled?: boolean;    // dimmed and not clickable
    label?: React.ReactNode; // centered inside the zone
}

export interface HalfCourtProps {
    zones: Partial<Record<ShotZoneKey, HalfCourtZoneState>>;
    onZoneClick?: (zone: ShotZoneKey) => void;  // omit for display mode
    className?: string;
}
```

- [ ] **Step 1: Build the SVG**

One `<svg viewBox="0 0 500 470">` (half court, hoop at the bottom, matching the mockup orientation), with five `<path>` regions keyed by `ShotZoneKey`:

- `paint` — the key rectangle
- `mid_range` — inside the arc, outside the key
- `corner_3_left` / `corner_3_right` — the two baseline strips outside the arc
- `above_break_3` — everything else beyond the arc

Rules:
- Regions carry `pointer-events: none` when `onZoneClick` is undefined (display mode) or the zone is `disabled`.
- Interactive regions get `role="button"`, `tabIndex={0}`, an `aria-label` of the zone's `label`, and keyboard `Enter`/`Space` handling — this is a tap target, and it must be reachable without a pointer.
- Zone labels render as `<text>` centered in each region, using `pointer-events: none` so they never swallow a tap.
- Court lines use `currentColor` at low opacity so the component inherits light/dark theming from its container rather than hard-coding colors.
- No fill colors are chosen here. This component only paints what `zones[key].fill` gives it. Color policy lives in Task 7.

- [ ] **Step 2: Verify it renders in both modes**

Temporarily mount it in `resources/js/Pages/Comparison/Show.tsx` with a hard-coded `zones` map, run `composer dev`, and confirm at 768px and 1024px that all five regions are visible, non-overlapping, and hit-testable. Remove the temporary mount before committing.

- [ ] **Step 3: Typecheck and commit**

```bash
npm run typecheck
git add resources/js/Components/features/analytics/HalfCourt.tsx
git commit -m "feat(shot-zones): shared half-court SVG component"
```

---

### Task 6: The skippable overlay in the event pad

**Files:**
- Create: `resources/js/Components/features/live-game/ShotZoneOverlay.tsx`
- Modify: `resources/js/Components/features/live-game/EventPad.tsx`

**Interfaces:**
- Consumes: `HalfCourt` (Task 5), `zonesForPoints` (Task 1), route `live-games.events.zone` (Task 2).
- Produces: `<ShotZoneOverlay eventId={number} points={2 | 3} onDone={() => void} />`

- [ ] **Step 1: Build the overlay**

A **pad-anchored dismissible sheet** (not a blocking confirm Dialog like `VoidEventConfirmModal`). Courtside coaches must keep recording; prefer sheet/drawer patterns over full-screen traps.

Contents: the `HalfCourt` in interactive mode with `zonesForPoints(points)` enabled and the other zones `disabled`; expanded hit targets on thin corner zones (or secondary zone buttons under the court); a prominent thumb-zone **Skip** button; heading `Where was the shot taken?` plus a context chip `{Player} · {2PT|3PT} {make|miss}`.

- [ ] **Step 2: Wire it into the pad**

In `EventPad.tsx`, after a **successful** shot POST, read the new event's id from the response and open the overlay with the shot's point value. Requirements:

- The shot is already saved. The overlay is never on the critical path — dismissing it, pressing Escape, tapping outside, or abandoning a failed PATCH all leave the shot intact.
- If the PATCH fails, keep the sheet open with **Retry** + **Skip** (brief error text). Do **not** flash-close and do **not** block further recording after Skip.
- Opens for **every** controlling coach who just recorded the FG (head or assistant, own or opponent) — not assistants-only.
- No new buttons on the pad. `PAD_EVENTS` is unchanged.

The store response is the state snapshot from `stateBuilder->build()`. If it does not already carry the created event's id, extend `LiveGameEventRecorder::record()` to return it alongside the snapshot rather than having the client guess by max sequence.

- [ ] **Step 3: Verify by hand**

Run `composer dev`. Start a live game, record a 3PT make, confirm the overlay opens with only the three 3-point zones tappable and the two 2-point zones dimmed. Tap one; confirm the timeline entry now shows the location. Record a second shot and press **Skip**; confirm the shot is still in the timeline with no location.

- [ ] **Step 4: Typecheck and commit**

```bash
npm run typecheck
git add resources/js/Components/features/live-game/ShotZoneOverlay.tsx resources/js/Components/features/live-game/EventPad.tsx
git commit -m "feat(shot-zones): skippable court overlay after each shot"
```

---

### Task 7: Season aggregation — repository and service

This is where the honesty rules are enforced. Everything in Task 8 is presentation only. **Merge rule:** per zone, `made/attempted = imported profile + live located events`. Task 9 adds the profile table/import; until then stub `profileForPlayer` as empty zeros so unit tests can fake both sources.

**Files:**
- Create: `app/Repositories/ShotZoneRepository.php`, `app/Services/ShotZoneService.php`
- Test: `tests/Unit/Services/ShotZoneServiceTest.php`

**Interfaces:**
- Produces: `ShotZoneService::profileFor(int $playerId): array` shaped as

```php
[
    'zones' => [
        'paint' => ['made' => 31, 'attempted' => 48, 'percentage' => 64.6, 'points_per_shot' => 1.29, 'has_enough_data' => true],
        // ... one entry per ShotZone case, always all five, zeroes included
    ],
    'located_shots' => 54,           // profile attempts + live located (for heat)
    'live_located_shots' => 40,      // live events with zone only (Skip honesty)
    'live_total_shots' => 61,        // live FG attempts, located or not. Excludes FT.
    'profile_vs_history_ok' => true, // false when history exists and splits disagree
]
```

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/Services/ShotZoneServiceTest.php
use App\Services\ShotZoneService;

it('computes points per shot per zone', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'paint' => ['made' => 31, 'attempted' => 48],
        'corner_3_left' => ['made' => 9, 'attempted' => 19],
    ], totalShots: 67)))->profileFor(1);

    // 31/48 * 2 = 1.2917
    expect($profile['zones']['paint']['points_per_shot'])->toBe(1.29)
        // 9/19 * 3 = 1.4211 — a 47% corner three beats a 65% layup
        ->and($profile['zones']['corner_3_left']['points_per_shot'])->toBe(1.42);
});

it('withholds color below the five-attempt threshold', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'corner_3_right' => ['made' => 2, 'attempted' => 4],
    ], totalShots: 4)))->profileFor(1);

    expect($profile['zones']['corner_3_right']['has_enough_data'])->toBeFalse()
        ->and($profile['zones']['corner_3_right']['attempted'])->toBe(4);
});

it('colors a zone at exactly five attempts', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'mid_range' => ['made' => 1, 'attempted' => 5],
    ], totalShots: 5)))->profileFor(1);

    expect($profile['zones']['mid_range']['has_enough_data'])->toBeTrue();
});

it('always returns all five zones, including untouched ones', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([], totalShots: 0)))->profileFor(1);

    expect(array_keys($profile['zones']))
        ->toEqualCanonicalizing(['paint', 'mid_range', 'corner_3_left', 'corner_3_right', 'above_break_3'])
        ->and($profile['zones']['paint']['attempted'])->toBe(0)
        ->and($profile['zones']['paint']['has_enough_data'])->toBeFalse();
});

it('reports coverage so skipped locations are visible', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'paint' => ['made' => 20, 'attempted' => 40],
    ], totalShots: 61)))->profileFor(1);

    expect($profile['located_shots'])->toBe(40)
        ->and($profile['total_shots'])->toBe(61);
});

it('never divides by zero', function () {
    $profile = (new ShotZoneService(fakeRepoReturning([
        'paint' => ['made' => 0, 'attempted' => 0],
    ], totalShots: 0)))->profileFor(1);

    expect($profile['zones']['paint']['percentage'])->toBe(0.0)
        ->and($profile['zones']['paint']['points_per_shot'])->toBe(0.0);
});
```

Define `fakeRepoReturning()` at the top of the test file as a small anonymous-class stub of `ShotZoneRepository` — this is `tests/Unit`, so there is no database (`tests/Pest.php` gives `RefreshDatabase` to `Feature` only).

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Services/ShotZoneServiceTest.php`
Expected: FAIL — `Class "App\Services\ShotZoneService" not found`

- [ ] **Step 3: Write the repository**

```php
<?php

namespace App\Repositories;

use App\Models\LiveGameEvent;
use Illuminate\Support\Facades\DB;

class ShotZoneRepository
{
    /**
     * Located field goal attempts for a player, grouped by zone, across every live game.
     *
     * Live locations live in the event log. Season baselines live in
     * player_shot_zone_profiles (Task 9). player_histories stays box-score only
     * but must reconcile with the profile's 2PT/3PT splits when both exist.
     *
     * @return array<string, array{made: int, attempted: int}>
     */
    public function locatedShotsFor(int $playerId): array
    {
        return $this->shotQuery($playerId)
            ->whereNotNull(DB::raw("json_extract(payload, '$.zone')"))
            ->get(['type', 'payload'])
            ->groupBy(fn (LiveGameEvent $event): string => (string) ($event->payload['zone'] ?? ''))
            ->map(fn ($events): array => [
                'made' => $events->where('type', 'shot_made')->count(),
                'attempted' => $events->count(),
            ])
            ->all();
    }

    /** Every field goal attempt, located or not. Free throws excluded. */
    public function totalShotsFor(int $playerId): int
    {
        return $this->shotQuery($playerId)->count();
    }

    private function shotQuery(int $playerId)
    {
        return LiveGameEvent::query()
            ->where('player_id', $playerId)
            ->whereIn('type', ['shot_made', 'shot_missed'])
            // A voided shot never happened, so it must not reach the court.
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('live_game_events as corrections')
                    ->whereColumn('corrections.voids_event_id', 'live_game_events.id')
                    ->where('corrections.type', 'correction');
            });
    }
}
```

> `json_extract` works on both SQLite (dev/test) and MySQL (prod). Verify the corrections subquery against the void behavior asserted in the existing live-game tests before relying on it.

- [ ] **Step 4: Write the service**

```php
<?php

namespace App\Services;

use App\Enums\ShotZone;
use App\Repositories\ShotZoneRepository;

class ShotZoneService
{
    /** Attempts required in a zone before it is allowed any color. */
    public const MIN_ATTEMPTS = 5;

    public function __construct(private readonly ShotZoneRepository $repository) {}

    /** @return array<string, mixed> */
    public function profileFor(int $playerId): array
    {
        $live = $this->repository->locatedShotsFor($playerId);
        $baseline = $this->repository->profileShotsFor($playerId); // Task 9; empty map until then
        $zones = [];
        $located = 0;
        $liveLocated = 0;

        foreach (ShotZone::cases() as $zone) {
            $key = $zone->value;
            $made = (int) ($baseline[$key]['made'] ?? 0) + (int) ($live[$key]['made'] ?? 0);
            $attempted = (int) ($baseline[$key]['attempted'] ?? 0) + (int) ($live[$key]['attempted'] ?? 0);
            $located += $attempted;
            $liveLocated += (int) ($live[$key]['attempted'] ?? 0);

            $percentage = $attempted > 0 ? $made / $attempted : 0.0;

            $zones[$key] = [
                'label' => $zone->label(),
                'made' => $made,
                'attempted' => $attempted,
                'percentage' => round($percentage * 100, 1),
                // Points per shot makes a 47% corner three (1.42) legible as better
                // than a 65% layup (1.30). Raw FG% would rank them the other way.
                'points_per_shot' => round($percentage * $zone->points(), 2),
                'has_enough_data' => $attempted >= self::MIN_ATTEMPTS,
            ];
        }

        $liveTotal = $this->repository->totalShotsFor($playerId);

        return [
            'zones' => $zones,
            'located_shots' => $located,
            'live_located_shots' => $liveLocated,
            'live_total_shots' => $liveTotal,
            // Keep legacy key for any early UI: live FG attempts only.
            'total_shots' => $liveTotal,
            'profile_vs_history_ok' => $this->repository->profileReconcilesWithHistory($playerId),
        ];
    }
}
```

Add unit cases that merge baseline + live, and that `has_enough_data` uses the merged attempt count.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Services/ShotZoneServiceTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint
git add app/Repositories/ShotZoneRepository.php app/Services/ShotZoneService.php tests/Unit/Services/ShotZoneServiceTest.php
git commit -m "feat(shot-zones): season zone aggregation with PPS and sample gating"
```

---

### Task 8: The court on the matchup page

**Files:**
- Create: `resources/js/Components/features/comparison/ShotZoneCourt.tsx`
- Modify: the comparison show controller (find it via `routes/web.php`), `resources/js/Pages/Comparison/Show.tsx`
- Test: `tests/Feature/ShotZoneMatchupTest.php`

**Interfaces:**
- Consumes: `ShotZoneService::profileFor()` (Task 7), `HalfCourt` (Task 5), `MIN_ZONE_ATTEMPTS` (Task 1).
- Produces: Inertia props `playerAShotZones` and `playerBShotZones`, each the `profileFor()` shape.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/ShotZoneMatchupTest.php

it('passes a zone profile for both players', function () {
    ['playerA' => $a, 'playerB' => $b, 'user' => $user] = comparablePlayers();

    $this->actingAs($user)
        ->get(route('comparison.show', ['playerA' => $a->id, 'playerB' => $b->id]))
        ->assertInertia(fn ($page) => $page
            ->has('playerAShotZones.zones', 5)
            ->has('playerBShotZones.zones', 5)
            ->where('playerAShotZones.total_shots', 0));
});
```

> Read the actual comparison route name and parameter names from `routes/web.php` before writing this — do not assume `comparison.show`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ShotZoneMatchupTest.php`
Expected: FAIL — prop `playerAShotZones` missing.

- [ ] **Step 3: Add the props**

Inject `ShotZoneService` into the comparison show controller method and add both profiles to the `Inertia::render` payload. The controller calls the service and nothing else — no Eloquent, per the layering rule.

- [ ] **Step 4: Build the display court**

`ShotZoneCourt.tsx` renders `HalfCourt` in display mode plus a footer. Color policy:

- A zone with `has_enough_data === false` gets the neutral gray fill, and its label shows **only** the raw count (`2/4`) — no percentage, no PPS, no color.
- A zone with enough data is filled on a cold→hot scale driven by `points_per_shot`, anchored to a **fixed** domain of `0.7 → 1.4` PPS so the same color means the same thing on both players' courts. Do not normalize per-player — a relative scale would make every player look like they have a hot zone.
- Every colored zone labels as three lines: `9/19`, `47%`, `1.42 PPS`.
- Footer: heat uses merged `located_shots`. Live honesty line: `{live_located_shots} of {live_total_shots} live shots located` when `live_total_shots > 0`. When merged located is 0, render the gray court with `No shot locations recorded yet`.
- If `profile_vs_history_ok === false`, show a short warning that the season profile no longer matches box-score FG/3P totals (re-import profile).
- Choose fills that hold contrast in both light and dark themes; check both before committing. Prefer pattern/label density so color is not the only heat cue (a11y).

- [ ] **Step 5: Place both courts**

In `Show.tsx`, add the two courts side by side under the existing matchup content. Side-by-side from 768px up; stacked below that. Label each with the player's name and team so the two courts are never confusable.

- [ ] **Step 6: Run tests and verify by hand**

```bash
php artisan test tests/Feature/ShotZoneMatchupTest.php && npm run typecheck
```

Then `composer dev`: open a matchup for two players who have logged shots. Confirm the coverage footer matches the number of shots you skipped in Task 6, and that a zone with fewer than 5 attempts is gray with a bare count.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint
git add resources/js/Components/features/comparison/ShotZoneCourt.tsx resources/js/Pages/Comparison/Show.tsx app/Http/Controllers tests/Feature/ShotZoneMatchupTest.php
git commit -m "feat(shot-zones): render season shot courts on the matchup page"
```

---

### Task 9: Season shot-profile CSV import

Separate from player-history import. One row per player; upsert into `player_shot_zone_profiles`; matchup merges with live (Task 7).

**Files:**
- Create: migration + `app/Models/PlayerShotZoneProfile.php`, `app/Jobs/ShotZoneProfileImportJob.php`, FormRequest, Service/Repository write path, template download route, import UI entry next to history import.
- Test: `tests/Feature/ShotZoneProfileImportTest.php` (+ extend `ShotZoneMatchupTest` for profile-only heat).

**CSV headers (order-sensitive):**

```
player_id,paint_made,paint_attempted,mid_range_made,mid_range_attempted,corner_3_left_made,corner_3_left_attempted,corner_3_right_made,corner_3_right_attempted,above_break_3_made,above_break_3_attempted
```

- [ ] **Step 1: Migration + model**

One row per `player_id` (unique). Unsigned ints for each zone made/attempted. `imported_at` timestamp. Factory for tests.

- [ ] **Step 2: Failing import tests**

```php
it('upserts a season shot profile for a team player', function () { /* … */ });

it('rejects a row when zone made exceeds attempted', function () { /* … */ });

it('rejects a row when 2PT/3PT zone totals disagree with summed player_histories', function () {
    // paint+mid attempts must equal FGA-3PA; three 3PT zones equal 3PA; same for makes
});

it('accepts a profile when the player has no history yet', function () { /* … */ });

it('shows matchup heat from profile alone with no live games', function () { /* … */ });
```

- [ ] **Step 3: Job + layering**

Mirror existing CSV job patterns (`PlayerHistoryImportJob` / `CsvImportService`): queue, mark processing/failed, skip bad rows with logged errors, continue file. Re-import **replaces** the player's profile row (not additive). Players must belong to the importer's team. Implement `ShotZoneRepository::profileShotsFor` + `profileReconcilesWithHistory`.

Reconcile (when history exists): hard reject that row; do not upsert; include expected vs actual in the error log.

- [ ] **Step 4: UI**

“Import shot zone profile” + template download — **not** mixed into the history upload form. Wire route under the same auth as other team imports.

- [ ] **Step 5: Run tests and commit**

```bash
php artisan test tests/Feature/ShotZoneProfileImportTest.php tests/Feature/ShotZoneMatchupTest.php tests/Unit/Services/ShotZoneServiceTest.php
npm run typecheck && vendor/bin/pint
git add database/migrations app/Models/PlayerShotZoneProfile.php app/Jobs/ShotZoneProfileImportJob.php app/Repositories app/Services app/Http tests/Feature/ShotZoneProfileImportTest.php resources/js
git commit -m "feat(shot-zones): season shot-profile CSV import with history reconcile"
```

---

## Final verification

Run the full suite plus a manual end-to-end pass:

```bash
php artisan test
pytest tests/python        # must still pass untouched — no engine changes
npm run typecheck
npm run build
vendor/bin/pint
```

Manual, with `composer dev` running (the queue worker must be up or nothing computes):

1. Import a season shot-profile CSV for a player who already has history that matches the 2PT/3PT splits. Open matchup — court shows heat with no live games.
2. Import a mismatched profile (wrong 3PA split) — that row is rejected; prior good profile unchanged.
3. Start a live game with two rosters. Assign an assistant some opponent players; leave others for the head.
4. As head, record a home 3PT make → sheet opens with context chip → tap `above_break_3`.
5. As head, record an undelegated opponent 2PT make + zone. Confirm `opponent_score` increments.
6. As head, attempt an opponent foul — refused.
7. Record a home 2PT miss → **Skip**. Shot stays; live coverage footer reflects the skip.
8. From the assistant, record a delegated opponent shot + zone.
9. Finish the game. Confirm `player_histories` `playing_team_id` for both sides.
10. Re-open matchup: merged profile + live; zones under 5 attempts gray; live coverage separate from imported baseline.

## Known limitations — state these in the thesis

- Live locations on deleted live games are gone with the event log cascade; the **imported season profile** remains on `player_shot_zone_profiles`.
- Player-history CSV still has no zone columns — use the separate shot-profile import for baselines.
- Live Skip means live coverage &lt; 100%; it does not rewrite the imported profile. Box-score FG totals from history must still match the **imported** 2PT/3PT zone splits (enforced on profile import).
- The 5-attempt threshold means sparse zones stay gray. That is intentional — color on two attempts would advise on noise.
- Early builds must not ship the Jumpman half-court raster as UI chrome.
