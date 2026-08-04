# Live Game Clock-Gated Recording + Mistake-Surface Hardening — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make it impossible — in the UI *and* at the API — to record a live-game event that couldn't happen in a real game, and remove the remaining one-tap mistake surfaces on `/live-games/{id}`.

**Architecture:** One PHP class (`LiveGameEventRules`) becomes the single source of truth for which event types are legal against which clock state; `LiveGameEventRecorder` enforces it inside its existing `lockForUpdate` transaction, and a mirrored TypeScript catalog drives the event pad so the button is dark for the same reason the API would refuse. Clock-stop authority widens from creator-only to any main coach so the strict rules can't deadlock the opposing bench. Route-level policy authorization closes the hole where any verified user can mutate any game.

**Tech Stack:** Laravel 13, Inertia 2, React 18 + TypeScript, Tailwind, shadcn primitives (`Components/ui/dialog`), PHPUnit-style tests on `Tests\TestCase` with `RefreshDatabase` (SQLite `:memory:`), Laravel Echo/Reverb for broadcasts.

## Context

`/live-games/{id}` currently gates the event pad on `eventsDisabled = processing || status !== 'live' || !canRecord` (`resources/js/Pages/LiveGames/Show.tsx:217`) and nothing else. `LiveGameEventRecorder::validateRecording` has no clock check either. So 2-pointers get logged with the clock stopped, free throws get logged with it running, and every stat stays recordable at `0:00` after a period has ended. The audit behind this plan also found live data corruption (every offensive rebound is stored as defensive) and an authorization hole (any verified user can void any game's events).

## Global Constraints

- **FIBA rules.** 5 personal fouls disqualifies a player. Held in `LiveGameEventRules::MAX_PERSONAL_FOULS` so it can be changed to 6 for NBA rules. This is an assumption, stated because it is encoded in code.
- **Corrections are exempt from every gate**, including at `0:00` and after the game is finished. Otherwise a mistake made at `0:00` could never be fixed.
- **Layering** (`CLAUDE.md`): controllers render/redirect only; validation in the request layer; business logic in services; Eloquent in models with zero logic. Authorization belongs on routes via policy, not in controller bodies.
- **`Components/ui/` is never modified.** Extend via `className` or add to `Components/features/live-game/`.
- **Tablet (768–1024px) is the primary breakpoint.** Minimum touch target 44×44px.
- **`plus_minus` renders as `—` when null**, never `0` or blank.
- Every task ends green on `php artisan test --filter=LiveGame`, `npm run typecheck`, and `vendor/bin/pint`.

## Rules being encoded

| Event | Clock running | Clock stopped | At 0:00 |
|---|---|---|---|
| 2PT/3PT made, 2PT/3PT miss | allowed | blocked | blocked |
| Rebound (off/def), Assist, Turnover | allowed | blocked | blocked |
| Foul (personal/tech/flagrant) | allowed — **auto-stops the clock** | allowed | blocked |
| FT made, FT miss | blocked | allowed | blocked |
| Timeout | blocked | allowed | blocked |
| Substitution | blocked | allowed | blocked |
| Correction (void) | allowed | allowed | **allowed** |

Clock authority: **any main coach may stop**; **only the creator** may start, advance the period, or reset. Assistant coaches can't stop the clock directly but aren't deadlocked — fouls are recordable either way and auto-stop the clock, which is the real-game path into free throws.

**Explicitly out of scope:** per-team timeout limits (FIBA allocates by half with a last-two-minutes rule that doesn't map onto this app's configurable 1–20 minute periods, so any enforced number would be wrong for most configurations); `LiveGameController::index` listing every game to every user; adding steal/block event types.

## File Structure

**Create**
| File | Responsibility |
|---|---|
| `app/Services/LiveGame/LiveGameEventRules.php` | Canonical event-type list, clock-requirement map, foul limit. No dependencies. |
| `app/Policies/LiveGamePolicy.php` | `record` ability = `isParticipant`. |
| `resources/js/Components/features/live-game/event-catalog.ts` | TS mirror of the rules + the pad's button definitions. |
| `resources/js/Components/features/live-game/TimelineRow.tsx` | One timeline row, extracted from `Timeline.tsx`. |
| `resources/js/Components/features/live-game/VoidEventConfirmModal.tsx` | Void confirmation, following `LineupConfirmModal.tsx`. |
| `tests/Feature/LiveGame/LiveGameAuthorizationTest.php` | Non-participant is refused on every mutation route. |

**Modify**
| File | Change |
|---|---|
| `app/Models/LiveGame.php` | Add `isMainCoach()`. |
| `app/Services/LiveGame/LiveGameClockService.php` | Per-action authorization; public `stopFor()`; error instead of silent no-op on start-at-zero. |
| `app/Services/LiveGame/LiveGameEventRecorder.php` | Clock gate, period-end gate, foul-out gate, already-voided guard, foul auto-stop, timeout team stamping. |
| `app/Http/Controllers/LiveGameEventController.php` | Types from `LiveGameEventRules`; timeout becomes `own` scope. |
| `app/Http/Controllers/LiveGameController.php` | Pass `can_stop_clock` from `show()`. |
| `routes/web.php` | `->can('record', 'liveGame')` on all six mutation routes. |
| `resources/js/Pages/LiveGames/Show.tsx` | Drift-free clock, `clockState`, single selection clamp, unified reason banner, void confirm wiring. |
| `resources/js/Components/features/live-game/EventPad.tsx` | Grouped layout, O/D rebound + 3 foul kinds, per-button disabled reasons. |
| `resources/js/Components/features/live-game/Timeline.tsx` | Height cap so it actually scrolls; 44px void; honest counts. |
| `resources/js/Components/features/live-game/GameScoreboard.tsx` | Period-ended state; `canStopClock`. |
| `resources/js/Components/features/live-game/live-game-utils.ts` | `eventLabel` learns teams (for timeout attribution). |

---

## Task 1: Event rules — the single source of truth

**Files:**
- Create: `app/Services/LiveGame/LiveGameEventRules.php`
- Modify: `app/Http/Controllers/LiveGameEventController.php:28-31`
- Test: `tests/Unit/Services/LiveGame/LiveGameEventRulesTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `LiveGameEventRules` with static `types(): array`, `clockRequirement(string $type): string`, `isAllowedWhileClockRunning(string $type): bool`, `isAllowedWhileClockStopped(string $type): bool`, `isAllowedAtPeriodEnd(string $type): bool`, `stopsClock(string $type): bool`; constants `CLOCK_RUNNING`, `CLOCK_STOPPED`, `CLOCK_ANY`, `MAX_PERSONAL_FOULS`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Services/LiveGame/LiveGameEventRulesTest.php`:

```php
<?php

namespace Tests\Unit\Services\LiveGame;

use App\Services\LiveGame\LiveGameEventRules;
use Tests\TestCase;

class LiveGameEventRulesTest extends TestCase
{
    public function test_live_ball_events_require_a_running_clock(): void
    {
        foreach (['shot_made', 'shot_missed', 'rebound', 'assist', 'turnover'] as $type) {
            $this->assertTrue(LiveGameEventRules::isAllowedWhileClockRunning($type), $type);
            $this->assertFalse(LiveGameEventRules::isAllowedWhileClockStopped($type), $type);
        }
    }

    public function test_dead_ball_events_require_a_stopped_clock(): void
    {
        foreach (['free_throw_made', 'free_throw_missed', 'timeout', 'substitution'] as $type) {
            $this->assertFalse(LiveGameEventRules::isAllowedWhileClockRunning($type), $type);
            $this->assertTrue(LiveGameEventRules::isAllowedWhileClockStopped($type), $type);
        }
    }

    public function test_fouls_are_allowed_in_either_clock_state_and_stop_the_clock(): void
    {
        $this->assertTrue(LiveGameEventRules::isAllowedWhileClockRunning('foul'));
        $this->assertTrue(LiveGameEventRules::isAllowedWhileClockStopped('foul'));
        $this->assertTrue(LiveGameEventRules::stopsClock('foul'));
        $this->assertFalse(LiveGameEventRules::stopsClock('shot_made'));
    }

    public function test_only_corrections_survive_the_end_of_a_period(): void
    {
        $this->assertTrue(LiveGameEventRules::isAllowedAtPeriodEnd('correction'));

        foreach (['shot_made', 'free_throw_made', 'timeout', 'substitution', 'foul'] as $type) {
            $this->assertFalse(LiveGameEventRules::isAllowedAtPeriodEnd($type), $type);
        }
    }

    public function test_every_type_the_api_accepts_has_a_clock_requirement(): void
    {
        foreach (LiveGameEventRules::types() as $type) {
            $this->assertContains(LiveGameEventRules::clockRequirement($type), [
                LiveGameEventRules::CLOCK_RUNNING,
                LiveGameEventRules::CLOCK_STOPPED,
                LiveGameEventRules::CLOCK_ANY,
            ], $type);
        }

        $this->assertCount(12, LiveGameEventRules::types());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=LiveGameEventRulesTest`
Expected: FAIL — `Class "App\Services\LiveGame\LiveGameEventRules" not found`.

- [ ] **Step 3: Write the implementation**

`app/Services/LiveGame/LiveGameEventRules.php`:

```php
<?php

namespace App\Services\LiveGame;

/**
 * Single source of truth for which live-game event types are legal against which
 * clock state. Mirrored on the client in
 * resources/js/Components/features/live-game/event-catalog.ts — change both together.
 */
class LiveGameEventRules
{
    public const CLOCK_RUNNING = 'running';

    public const CLOCK_STOPPED = 'stopped';

    public const CLOCK_ANY = 'any';

    /** FIBA: a player is disqualified on their 5th personal foul. Set to 6 for NBA rules. */
    public const MAX_PERSONAL_FOULS = 5;

    /** Event types still recordable once the period clock reaches 0:00. */
    public const EXEMPT_AT_PERIOD_END = ['correction'];

    /** @var array<string, string> */
    private const CLOCK_REQUIREMENTS = [
        'shot_made' => self::CLOCK_RUNNING,
        'shot_missed' => self::CLOCK_RUNNING,
        'rebound' => self::CLOCK_RUNNING,
        'assist' => self::CLOCK_RUNNING,
        'turnover' => self::CLOCK_RUNNING,
        'opponent_score' => self::CLOCK_RUNNING,
        'foul' => self::CLOCK_ANY,
        'free_throw_made' => self::CLOCK_STOPPED,
        'free_throw_missed' => self::CLOCK_STOPPED,
        'timeout' => self::CLOCK_STOPPED,
        'substitution' => self::CLOCK_STOPPED,
        'correction' => self::CLOCK_ANY,
    ];

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::CLOCK_REQUIREMENTS);
    }

    public static function clockRequirement(string $type): string
    {
        return self::CLOCK_REQUIREMENTS[$type] ?? self::CLOCK_ANY;
    }

    public static function isAllowedWhileClockRunning(string $type): bool
    {
        return self::clockRequirement($type) !== self::CLOCK_STOPPED;
    }

    public static function isAllowedWhileClockStopped(string $type): bool
    {
        return self::clockRequirement($type) !== self::CLOCK_RUNNING;
    }

    public static function isAllowedAtPeriodEnd(string $type): bool
    {
        return in_array($type, self::EXEMPT_AT_PERIOD_END, true);
    }

    /** A whistle stops the clock, so recording a foul stops it too. */
    public static function stopsClock(string $type): bool
    {
        return $type === 'foul';
    }
}
```

- [ ] **Step 4: Point the controller's allow-list at the rules**

In `app/Http/Controllers/LiveGameEventController.php`, add `use App\Services\LiveGame\LiveGameEventRules;` and replace the hardcoded `$types` array (lines 28-31) plus its use in the validator:

```php
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', Rule::in(LiveGameEventRules::types())],
```

Delete the local `$types` variable. This is what makes the parity assertion in Step 1 meaningful — there is now one list, not two.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter="LiveGameEventRulesTest|LiveGameEventRecording"`
Expected: PASS (the existing recording tests confirm the controller refactor changed no behaviour).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Services/LiveGame/LiveGameEventRules.php app/Http/Controllers/LiveGameEventController.php
git add app/Services/LiveGame/LiveGameEventRules.php app/Http/Controllers/LiveGameEventController.php tests/Unit/Services/LiveGame/LiveGameEventRulesTest.php
git commit -m "feat(live-game): add LiveGameEventRules as the source of truth for event clock requirements"
```

---

## Task 2: Clock authority and the start-at-zero dead end

**Files:**
- Modify: `app/Models/LiveGame.php` (add `isMainCoach`, near `isCreator` at line 87)
- Modify: `app/Services/LiveGame/LiveGameClockService.php:19-52`, `:63-101`
- Test: `tests/Unit/Services/LiveGame/LiveGameClockServiceTest.php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: `LiveGame::isMainCoach(User $user): bool`; `LiveGameClockService::stopFor(LiveGame $game): void` (public, unauthorized — callers must have already authorized).

**Why:** `handle()` gates every action on `isCreator` (line 21). With Task 3's strict rules in place, that would leave the opposing bench unable to record a free throw or a substitution until the home coach happened to stop the clock. Separately, `start()` returns silently when the clock is at 0 (lines 79-81) — the coach taps Play and nothing at all happens.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/Services/LiveGame/LiveGameClockServiceTest.php` (match the existing file's helpers and PHPUnit style):

```php
    public function test_an_opponent_main_coach_may_stop_the_clock(): void
    {
        $game = LiveGame::factory()->withBothLineups()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
            'clock_started_at' => now()->subSeconds(90),
        ]);
        $opponentCoach = User::factory()->forTeam($game->opponentTeam)->create();
        $game->forceFill(['opponent_main_coach_user_id' => $opponentCoach->id])->save();

        $snapshot = app(LiveGameClockService::class)->handle($game, $opponentCoach, ['action' => 'stop']);

        $this->assertFalse($snapshot['clock']['running']);
        $this->assertSame(510, $snapshot['clock']['seconds_remaining']);
    }

    public function test_an_opponent_main_coach_may_not_start_the_clock(): void
    {
        $game = LiveGame::factory()->withBothLineups()->create(['status' => LiveGame::STATUS_LIVE]);
        $opponentCoach = User::factory()->forTeam($game->opponentTeam)->create();
        $game->forceFill(['opponent_main_coach_user_id' => $opponentCoach->id])->save();

        $this->expectException(ValidationException::class);

        app(LiveGameClockService::class)->handle($game, $opponentCoach, ['action' => 'start']);
    }

    public function test_a_non_coach_may_not_stop_the_clock(): void
    {
        $game = LiveGame::factory()->withBothLineups()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
            'clock_started_at' => now(),
        ]);
        $stranger = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(LiveGameClockService::class)->handle($game, $stranger, ['action' => 'stop']);
    }

    public function test_starting_an_expired_clock_reports_the_period_has_ended(): void
    {
        $game = LiveGame::factory()->withBothLineups()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_seconds_remaining' => 0,
            'clock_running' => false,
        ]);
        $creator = User::query()->findOrFail($game->created_by_user_id);

        try {
            app(LiveGameClockService::class)->handle($game, $creator, ['action' => 'start']);
            $this->fail('Expected a ValidationException for starting an expired clock.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('has ended', $exception->errors()['game'][0]);
        }

        $this->assertFalse($game->fresh()->clock_running);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=LiveGameClockServiceTest`
Expected: FAIL — the two "opponent main coach" and "non-coach" tests fail on the blanket creator check; the expired-clock test fails because `start()` currently succeeds silently with no exception.

- [ ] **Step 3: Add `isMainCoach` to the model**

In `app/Models/LiveGame.php`, immediately after `isCreator()` (line 90):

```php
    public function isMainCoach(User $user): bool
    {
        foreach ([$this->home_main_coach_user_id, $this->opponent_main_coach_user_id] as $mainCoachUserId) {
            if ($mainCoachUserId !== null && (int) $mainCoachUserId === (int) $user->id) {
                return true;
            }
        }

        return false;
    }
```

- [ ] **Step 4: Split clock authorization by action**

In `app/Services/LiveGame/LiveGameClockService.php`, replace the `isCreator` block at the top of `handle()` (lines 21-25) with:

```php
        $action = is_string($input['action'] ?? null) ? $input['action'] : '';

        if (! $this->canPerform($game, $user, $action)) {
            throw ValidationException::withMessages([
                'game' => $action === 'stop'
                    ? 'Only the game creator or a main coach can stop the clock.'
                    : 'Only the game creator can start the clock or change the period.',
            ]);
        }
```

Add the private helper below `handle()`:

```php
    private function canPerform(LiveGame $game, User $user, string $action): bool
    {
        if ($game->isCreator($user)) {
            return true;
        }

        // Either bench can whistle, so either main coach may stop the clock.
        // Starting, advancing and resetting stay with the creator so there is one authoritative clock.
        return $action === 'stop' && $game->isMainCoach($user);
    }
```

- [ ] **Step 5: Make `stop` reusable and `start` honest**

Same file. Replace the private `stop()` (lines 92-101) with a public method the recorder can call, and delegate the match arm to it:

```php
    /**
     * Stop the clock without authorization. Callers must have authorized already —
     * LiveGameEventRecorder calls this when a foul is recorded.
     */
    public function stopFor(LiveGame $game): void
    {
        $remaining = $this->refreshElapsedClock($game);

        $game->forceFill([
            'clock_seconds_remaining' => $remaining,
            'clock_running' => false,
            'clock_started_at' => null,
        ])->save();
    }
```

Update the `match` in `handle()`: `'stop' => $this->stopFor($game),`.

In `start()`, replace the silent early return (lines 79-81) with:

```php
        if ($remaining === 0) {
            throw ValidationException::withMessages([
                'game' => "Q{$game->current_period} has ended. Advance the period or reset the clock.",
            ]);
        }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter="LiveGameClockService|LiveGameClockController"`
Expected: PASS. If `test_an_expired_running_clock_is_persisted_as_stopped_at_zero` (existing, line 102) now fails, it is calling `start` on a clock that expires to 0 — change that case to assert the `ValidationException` and that the row was still persisted as stopped at zero by `refreshElapsedClock`.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint app/Models/LiveGame.php app/Services/LiveGame/LiveGameClockService.php
git add app/Models/LiveGame.php app/Services/LiveGame/LiveGameClockService.php tests/Unit/Services/LiveGame/LiveGameClockServiceTest.php
git commit -m "feat(live-game): let main coaches stop the clock and report the period-ended dead end"
```

---

## Task 3: Enforce the clock matrix when recording

**Files:**
- Modify: `app/Services/LiveGame/LiveGameEventRecorder.php:24-57` (`record`), `:59-182` (`validateRecording`)
- Test: `tests/Unit/Services/LiveGame/LiveGameEventRecorderTest.php`

**Interfaces:**
- Consumes: `LiveGameEventRules` (Task 1), `LiveGameClockService::stopFor()` (Task 2), the injected `$this->clockService->effectiveSecondsRemaining()`.
- Produces: `ValidationException` on the `game` key for every clock violation.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/Services/LiveGame/LiveGameEventRecorderTest.php`. The file's existing helpers `gameWithStarter()`, `record()`, `ownEvent()` and `assertValidationException()` are reused; add a `liveRunning()` helper alongside them that puts a live game on a running clock.

```php
    public function test_it_rejects_a_field_goal_while_the_clock_is_stopped(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, $this->ownEvent($player)),
            'game',
        );

        $this->assertDatabaseCount('live_game_events', 0);
    }

    public function test_it_records_a_field_goal_while_the_clock_is_running(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $this->runClock($game);

        $this->record($game, $user, $this->ownEvent($player));

        $this->assertDatabaseCount('live_game_events', 1);
    }

    public function test_it_rejects_a_free_throw_while_the_clock_is_running(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $this->runClock($game);

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, [
                'type' => 'free_throw_made',
                'team_scope' => 'own',
                'player_id' => $player->id,
            ]),
            'game',
        );
    }

    public function test_it_records_a_free_throw_while_the_clock_is_stopped(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);

        $this->record($game, $user, [
            'type' => 'free_throw_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
        ]);

        $this->assertDatabaseCount('live_game_events', 1);
    }

    public function test_recording_a_foul_stops_a_running_clock(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $this->runClock($game, 90);

        $snapshot = $this->record($game, $user, [
            'type' => 'foul',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['kind' => 'personal'],
        ]);

        $this->assertFalse($snapshot['clock']['running']);
        $this->assertSame(510, $snapshot['clock']['seconds_remaining']);
        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'clock_running' => false,
            'clock_started_at' => null,
        ]);
    }

    public function test_it_rejects_every_event_but_a_correction_once_the_period_has_expired(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $game->forceFill(['clock_seconds_remaining' => 0, 'clock_running' => false])->save();

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, [
                'type' => 'free_throw_made',
                'team_scope' => 'own',
                'player_id' => $player->id,
            ]),
            'game',
        );

        $this->assertDatabaseCount('live_game_events', 0);
    }

    public function test_it_allows_a_correction_once_the_period_has_expired(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $this->runClock($game);
        $this->record($game, $user, $this->ownEvent($player));
        $recorded = $game->events()->firstOrFail();

        $game->forceFill(['clock_seconds_remaining' => 0, 'clock_running' => false])->save();

        $this->record($game, $user, [
            'type' => 'correction',
            'team_scope' => 'game',
            'voids_event_id' => $recorded->id,
        ]);

        $this->assertDatabaseCount('live_game_events', 2);
    }
```

Add the helper next to the file's other helpers:

```php
    private function runClock(LiveGame $game, int $elapsedSeconds = 0): void
    {
        $game->forceFill([
            'clock_running' => true,
            'clock_started_at' => now()->subSeconds($elapsedSeconds),
        ])->save();
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=LiveGameEventRecorderTest`
Expected: FAIL — the "rejects" cases record successfully (no clock gate exists), and the foul case leaves the clock running.

- [ ] **Step 3: Add the gate to `validateRecording`**

In `app/Services/LiveGame/LiveGameEventRecorder.php`, add `use App\Services\LiveGame\LiveGameEventRules;` (same namespace, so no import needed — reference it directly). Insert after the existing finished-game check (line 117), before the substitution block:

```php
        if ($game->status === LiveGame::STATUS_LIVE && is_string($type)) {
            $periodExpired = $this->clockService->effectiveSecondsRemaining($game) === 0;

            if ($periodExpired && ! LiveGameEventRules::isAllowedAtPeriodEnd($type)) {
                $errors['game'][] = "Q{$game->current_period} has ended. Advance the period before recording.";
            } elseif ($game->clock_running && ! LiveGameEventRules::isAllowedWhileClockRunning($type)) {
                $errors['game'][] = 'This is recorded with the clock stopped. Stop the clock first.';
            } elseif (! $game->clock_running && ! LiveGameEventRules::isAllowedWhileClockStopped($type)) {
                $errors['game'][] = 'This is recorded with the clock running. Start the clock first.';
            }
        }
```

The `STATUS_LIVE` guard keeps the existing setup/finished messages primary and leaves post-game corrections ungated.

- [ ] **Step 4: Auto-stop the clock on a foul**

Same file, in `record()`, between the `LiveGameEvent::query()->create([...])` call and the `if ($game->status === LiveGame::STATUS_FINISHED ...)` branch (i.e. after line 43):

```php
            if (LiveGameEventRules::stopsClock($input['type']) && $game->clock_running) {
                $this->clockService->stopFor($game);
            }
```

Ordering is safe: the event's `clock_seconds_remaining` is stamped from `effectiveSecondsRemaining()` and `stopFor()` persists that same value, so both orders record the same number. Placing it before the projection rebuild means the rebuilt snapshot already reflects the stopped clock.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=LiveGame`
Expected: PASS. Existing tests in `LiveGameEventRecordingTest` and `LiveGameFinalizationTest` that record live-ball events on a factory game will now fail, because the factory leaves `clock_running` false — fix each by putting the game on a running clock (`'clock_running' => true, 'clock_started_at' => now()`) rather than by weakening the gate.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Services/LiveGame/LiveGameEventRecorder.php
git add app/Services/LiveGame/LiveGameEventRecorder.php tests/
git commit -m "feat(live-game): gate event recording on the clock state and auto-stop on fouls"
```

---

## Task 4: Block the disqualified player's next foul

**Files:**
- Modify: `app/Services/LiveGame/LiveGameEventRecorder.php` (`validateRecording`)
- Test: `tests/Unit/Services/LiveGame/LiveGameEventRecorderTest.php`

**Interfaces:**
- Consumes: `LiveGameEventRules::MAX_PERSONAL_FOULS` (Task 1); `LiveGamePlayerStat::personal_fouls`.
- Produces: `ValidationException` on the `player_id` key.

**Why read the projected stat rather than replay events:** `LiveGameProjectionService::rebuild` already excludes voided events when it writes `personal_fouls`, so a voided foul correctly does not count toward disqualification. Reusing that projection keeps one definition of "how many fouls does this player have".

- [ ] **Step 1: Write the failing test**

```php
    public function test_it_rejects_a_personal_foul_for_a_disqualified_player(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);

        for ($i = 0; $i < LiveGameEventRules::MAX_PERSONAL_FOULS; $i++) {
            $this->record($game, $user, [
                'type' => 'foul',
                'team_scope' => 'own',
                'player_id' => $player->id,
                'payload' => ['kind' => 'personal'],
            ]);
        }

        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $player->id,
            'personal_fouls' => LiveGameEventRules::MAX_PERSONAL_FOULS,
        ]);

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, [
                'type' => 'foul',
                'team_scope' => 'own',
                'player_id' => $player->id,
                'payload' => ['kind' => 'personal'],
            ]),
            'player_id',
        );

        $this->assertSame(
            LiveGameEventRules::MAX_PERSONAL_FOULS,
            $game->events()->where('type', 'foul')->count(),
        );
    }

    public function test_a_technical_foul_is_not_capped_by_the_personal_foul_limit(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);

        for ($i = 0; $i < LiveGameEventRules::MAX_PERSONAL_FOULS; $i++) {
            $this->record($game, $user, [
                'type' => 'foul',
                'team_scope' => 'own',
                'player_id' => $player->id,
                'payload' => ['kind' => 'personal'],
            ]);
        }

        $this->record($game, $user, [
            'type' => 'foul',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['kind' => 'technical'],
        ]);

        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $player->id,
            'technical_fouls' => 1,
        ]);
    }
```

Add `use App\Services\LiveGame\LiveGameEventRules;` and `use App\Models\LiveGamePlayerStat;` to the test file's imports as needed.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=LiveGameEventRecorderTest`
Expected: FAIL — the 6th personal foul is accepted, so no exception is thrown.

- [ ] **Step 3: Write the implementation**

In `validateRecording`, after the clock gate added in Task 3:

```php
        if ($type === 'foul' && $side !== null) {
            $foulPayload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
            $foulKind = $foulPayload['kind'] ?? 'personal';
            $fouledPlayerId = (int) ($input['player_id'] ?? 0);

            if ($foulKind === 'personal' && $fouledPlayerId > 0) {
                $personalFouls = (int) LiveGamePlayerStat::query()
                    ->where('live_game_id', $game->id)
                    ->where('player_id', $fouledPlayerId)
                    ->value('personal_fouls');

                if ($personalFouls >= LiveGameEventRules::MAX_PERSONAL_FOULS) {
                    $errors['player_id'][] = sprintf(
                        'This player already has %d personal fouls and is disqualified.',
                        LiveGameEventRules::MAX_PERSONAL_FOULS,
                    );
                }
            }
        }
```

Add `use App\Models\LiveGamePlayerStat;` to the recorder's imports.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=LiveGame`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint app/Services/LiveGame/LiveGameEventRecorder.php
git add app/Services/LiveGame/LiveGameEventRecorder.php tests/Unit/Services/LiveGame/LiveGameEventRecorderTest.php
git commit -m "feat(live-game): reject a 6th personal foul for a disqualified player"
```

---

## Task 5: Reject a second void of the same event

**Files:**
- Modify: `app/Services/LiveGame/LiveGameEventRecorder.php` (`validateRecording`)
- Test: `tests/Unit/Services/LiveGame/LiveGameEventRecorderTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `ValidationException` on the `voids_event_id` key.

**Why:** `Timeline.tsx:13` hides the void button client-side via `voidedIds`, but the recorder never checks. Two coaches acting on stale snapshots, or one double-tap that beats the in-flight guard, produce two corrections for one event — harmless to the projection (which is idempotent) but it pollutes the timeline and the event counts.

- [ ] **Step 1: Write the failing test**

```php
    public function test_it_rejects_a_second_correction_against_an_already_voided_event(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $this->runClock($game);
        $this->record($game, $user, $this->ownEvent($player));
        $recorded = $game->events()->firstOrFail();

        $this->record($game, $user, [
            'type' => 'correction',
            'team_scope' => 'game',
            'voids_event_id' => $recorded->id,
        ]);

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, [
                'type' => 'correction',
                'team_scope' => 'game',
                'voids_event_id' => $recorded->id,
            ]),
            'voids_event_id',
        );

        $this->assertSame(1, $game->events()->where('type', 'correction')->count());
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_it_rejects_a_second_correction_against_an_already_voided_event`
Expected: FAIL — a second correction row is created, so no exception is thrown.

- [ ] **Step 3: Write the implementation**

In `validateRecording`, after the foul-out block:

```php
        if ($type === 'correction') {
            $voidsEventId = $input['voids_event_id'] ?? null;

            $alreadyVoided = $voidsEventId !== null && $game->events()
                ->where('type', 'correction')
                ->where('voids_event_id', $voidsEventId)
                ->exists();

            if ($alreadyVoided) {
                $errors['voids_event_id'][] = 'That event has already been voided.';
            }
        }
```

This runs inside the existing `lockForUpdate` transaction, so two concurrent requests serialize and the second one loses.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=LiveGame`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint app/Services/LiveGame/LiveGameEventRecorder.php
git add app/Services/LiveGame/LiveGameEventRecorder.php tests/Unit/Services/LiveGame/LiveGameEventRecorderTest.php
git commit -m "fix(live-game): reject corrections that target an already-voided event"
```

---

## Task 6: Attribute timeouts to a team

**Files:**
- Modify: `app/Http/Controllers/LiveGameEventController.php:103-105`
- Modify: `app/Services/LiveGame/LiveGameEventRecorder.php` (`record`)
- Test: `tests/Feature/LiveGame/LiveGameEventRecordingTest.php`

**Interfaces:**
- Consumes: `LiveGame::sideFor()`.
- Produces: timeout events carry `team_scope: 'own'` and `payload.team_id`, which the timeline and pad use for labelling in Tasks 8 and 11.

**Why:** timeout is currently `team_scope: 'game'`, so the home coach's timeout is indistinguishable from the opponent's — and because `validateRecording` only checks `side` for own-player and substitution events, a user on neither team can inject one. `team_scope: 'own'` fixes attribution and pulls timeouts under the side check. The resolved team id is stamped into the payload server-side because `own` is relative to whoever recorded it, and the client snapshot has no way to resolve that for an event with no `player_id`.

Verified safe: `LiveGameProjectionService::rebuild` skips `own`-scope events with a null `player_id` (line 92), and `LiveGameAlertService` derives `timeout_prompt` from scoring runs and droughts, not from timeout events.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/LiveGame/LiveGameEventRecordingTest.php`:

```php
    public function test_a_timeout_is_attributed_to_the_recording_coachs_team(): void
    {
        $game = LiveGame::factory()->withBothLineups()->create(['status' => LiveGame::STATUS_LIVE]);
        $opponentCoach = User::factory()->forTeam($game->opponentTeam)->create(['email_verified_at' => now()]);
        $game->forceFill(['opponent_main_coach_user_id' => $opponentCoach->id])->save();

        $response = $this->actingAs($opponentCoach)->postJson("/live-games/{$game->id}/events", [
            'type' => 'timeout',
            'team_scope' => 'own',
        ]);

        $response->assertOk();

        $timeout = $game->events()->where('type', 'timeout')->firstOrFail();
        $this->assertSame('own', $timeout->team_scope);
        $this->assertSame((int) $game->opponent_team_id, (int) $timeout->payload['team_id']);
        $this->assertSame(0, $game->fresh()->home_score);
    }

    public function test_a_user_on_neither_team_cannot_call_a_timeout(): void
    {
        $game = LiveGame::factory()->withBothLineups()->create([
            'status' => LiveGame::STATUS_LIVE,
            'created_by_user_id' => User::factory()->create(['email_verified_at' => now()]),
        ]);
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)
            ->postJson("/live-games/{$game->id}/events", ['type' => 'timeout', 'team_scope' => 'own'])
            ->assertStatus(422);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=LiveGameEventRecordingTest`
Expected: FAIL — the controller requires `team_scope: 'game'` for timeouts, so the first test gets a 422 on `team_scope` and the second gets a 200.

- [ ] **Step 3: Move timeout to `own` scope in the controller**

In `app/Http/Controllers/LiveGameEventController.php`, change the combined block at lines 103-105 to cover corrections only, and add a timeout block above it:

```php
            if ($type === 'timeout') {
                $this->requireScope($validator, $scope, 'own');

                if (isset($input['player_id'])) {
                    $validator->errors()->add('player_id', 'Timeout events cannot have a player.');
                }

                if ($side === null) {
                    $validator->errors()->add('game', 'Only team coaches can call a timeout.');
                }
            }

            if ($type === 'correction') {
                $this->requireScope($validator, $scope, 'game');
            }
```

- [ ] **Step 4: Stamp the team in the recorder**

In `app/Services/LiveGame/LiveGameEventRecorder::record()`, build the payload before the `create` call and use it in place of `$input['payload'] ?? []`:

```php
            $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];

            if ($input['type'] === 'timeout') {
                $payload['team_id'] = $game->sideFor($user) === LiveGame::SIDE_OPPONENT
                    ? (int) $game->opponent_team_id
                    : (int) $game->home_team_id;
            }
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=LiveGame`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Http/Controllers/LiveGameEventController.php app/Services/LiveGame/LiveGameEventRecorder.php
git add app/Http/Controllers/LiveGameEventController.php app/Services/LiveGame/LiveGameEventRecorder.php tests/Feature/LiveGame/LiveGameEventRecordingTest.php
git commit -m "fix(live-game): attribute timeouts to the recording coach's team"
```

---

## Task 7: Close the authorization hole on every mutation route

**Files:**
- Create: `app/Policies/LiveGamePolicy.php`
- Create: `tests/Feature/LiveGame/LiveGameAuthorizationTest.php`
- Modify: `routes/web.php:32-42`

**Interfaces:**
- Consumes: `LiveGame::isParticipant()` (already exists, line 92).
- Produces: a `record` ability, resolved by Laravel's automatic policy discovery for `App\Models\LiveGame`.

**Why:** `routes/web.php:35-42` carries only `auth`+`verified`. Because `correction` is `team_scope: 'game'` and `validateRecording` only checks `side` for own-player and substitution events, any verified user can POST a correction against any game — and voiding replays the whole projection, so one request silently rewrites every plus-minus. `routes/channels.php` already gates broadcasts on `isParticipant`; this brings HTTP in line.

`show` and `index` stay open: the page already renders a read-only spectator state ("You are viewing this game", `Show.tsx:412-416`), so blocking the view would contradict existing intent. A spectator's page won't live-update, because the broadcast channel does gate on `isParticipant` — noted, not changed here.

- [ ] **Step 1: Write the failing test**

`tests/Feature/LiveGame/LiveGameAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature\LiveGame;

use App\Models\LiveGame;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveGameAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_on_an_unrelated_team_cannot_void_an_event(): void
    {
        [$game, $event] = $this->liveGameWithOneEvent();
        $outsider = $this->outsider();

        $this->actingAs($outsider)
            ->postJson("/live-games/{$game->id}/correction", ['voids_event_id' => $event->id])
            ->assertForbidden();

        $this->assertSame(0, $game->events()->where('type', 'correction')->count());
    }

    public function test_a_user_on_an_unrelated_team_cannot_record_an_event_or_touch_the_clock(): void
    {
        [$game] = $this->liveGameWithOneEvent();
        $outsider = $this->outsider();

        $this->actingAs($outsider)
            ->postJson("/live-games/{$game->id}/events", ['type' => 'timeout', 'team_scope' => 'own'])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->postJson("/live-games/{$game->id}/clock", ['action' => 'stop'])
            ->assertForbidden();
    }

    public function test_a_user_on_an_unrelated_team_cannot_finish_or_submit_a_lineup(): void
    {
        [$game] = $this->liveGameWithOneEvent();
        $outsider = $this->outsider();

        $this->actingAs($outsider)->post("/live-games/{$game->id}/finish")->assertForbidden();
        $this->actingAs($outsider)->post("/live-games/{$game->id}/start")->assertForbidden();
        $this->actingAs($outsider)
            ->post("/live-games/{$game->id}/lineup", ['starting_player_ids' => [1, 2, 3, 4, 5]])
            ->assertForbidden();
    }

    public function test_a_user_on_an_unrelated_team_may_still_view_the_game(): void
    {
        [$game] = $this->liveGameWithOneEvent();

        $this->actingAs($this->outsider())
            ->get("/live-games/{$game->id}")
            ->assertOk();
    }

    public function test_a_participating_coach_is_still_allowed_through(): void
    {
        [$game] = $this->liveGameWithOneEvent();
        $homeCoach = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);

        $this->actingAs($homeCoach)
            ->postJson("/live-games/{$game->id}/events", ['type' => 'timeout', 'team_scope' => 'own'])
            ->assertOk();
    }

    /** @return array{0: LiveGame, 1: \App\Models\LiveGameEvent} */
    private function liveGameWithOneEvent(): array
    {
        $game = LiveGame::factory()->withBothLineups()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
            'clock_started_at' => now(),
        ]);
        $creator = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
        $game->forceFill([
            'created_by_user_id' => $creator->id,
            'home_main_coach_user_id' => $creator->id,
        ])->save();

        $player = Player::query()->findOrFail($game->starting_player_ids[0]);

        $this->actingAs($creator)->postJson("/live-games/{$game->id}/events", [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 2],
        ])->assertOk();

        return [$game->fresh(), $game->events()->firstOrFail()];
    }

    private function outsider(): User
    {
        return User::factory()->forTeam(Team::factory()->create())->create(['email_verified_at' => now()]);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=LiveGameAuthorizationTest`
Expected: FAIL — the outsider gets 200/422 instead of 403 on the mutation routes. `test_a_user_on_an_unrelated_team_may_still_view_the_game` should already pass.

- [ ] **Step 3: Write the policy**

`app/Policies/LiveGamePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\LiveGame;
use App\Models\User;

class LiveGamePolicy
{
    /**
     * Anyone may view a live game; only participants may change it.
     * Mirrors the channel authorization in routes/channels.php.
     */
    public function record(User $user, LiveGame $liveGame): bool
    {
        return $liveGame->isParticipant($user);
    }
}
```

Laravel discovers `App\Policies\LiveGamePolicy` for `App\Models\LiveGame` automatically — no registration needed.

- [ ] **Step 4: Attach the ability to the routes**

In `routes/web.php`, add `->can('record', 'liveGame')` to the six mutation routes (leave `index`, `create`, `store` and `show` alone):

```php
    Route::post('/live-games/{liveGame}/lineup', [LiveGameController::class, 'submitLineup'])
        ->can('record', 'liveGame')
        ->name('live-games.lineup');
    Route::post('/live-games/{liveGame}/start', [LiveGameController::class, 'start'])
        ->can('record', 'liveGame')
        ->name('live-games.start');
    Route::post('/live-games/{liveGame}/finish', [LiveGameController::class, 'finish'])
        ->can('record', 'liveGame')
        ->name('live-games.finish');
    Route::post('/live-games/{liveGame}/events', [LiveGameEventController::class, 'store'])
        ->can('record', 'liveGame')
        ->name('live-games.events.store');
    Route::post('/live-games/{liveGame}/clock', [LiveGameClockController::class, 'store'])
        ->can('record', 'liveGame')
        ->name('live-games.clock.store');
    Route::post('/live-games/{liveGame}/correction', [LiveGameController::class, 'correction'])
        ->can('record', 'liveGame')
        ->name('live-games.correction');
```

The creator-only checks inside `LiveGameClockService` and `LiveGameController::finish` still apply on top — defence in depth, not a replacement.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=LiveGame`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Policies/LiveGamePolicy.php routes/web.php
git add app/Policies/LiveGamePolicy.php routes/web.php tests/Feature/LiveGame/LiveGameAuthorizationTest.php
git commit -m "fix(live-game): require participation to mutate a live game"
```

---

## Task 8: The event catalog and the rebuilt pad

**Files:**
- Create: `resources/js/Components/features/live-game/event-catalog.ts`
- Modify: `resources/js/Components/features/live-game/EventPad.tsx` (full rewrite)

**Interfaces:**
- Consumes: `LiveGameEventRules` semantics from Task 1 (mirrored, not imported).
- Produces: `ClockState`, `PadEvent`, `PAD_EVENTS`, `PAD_GROUPS`, `MAX_PERSONAL_FOULS`, `isEventAllowed()`, `clockBlockReason()`; `EventPad` props `{ selectedPlayer?, clockState, selectedPlayerPersonalFouls, blockedReason, onRecord }`. `RecordableEvent` moves to the catalog and is re-exported from `EventPad` so `Show.tsx`'s existing import keeps working.

**Why the pad changes shape:** `EventPad.tsx:24` hardcodes `kind: 'defensive'` on every rebound and `:27` hardcodes `personal` on every foul. `offensive_rebounds`, `technical_fouls` and `flagrant_fouls` are real columns feeding the aggregator, but unreachable from the UI — **every offensive rebound in the system is currently stored as defensive.** Explicit buttons rather than a mode toggle, deliberately: a persistent "offensive rebound mode" is itself a mistake surface, because the coach has to remember which mode is armed.

- [ ] **Step 1: Write the catalog**

`resources/js/Components/features/live-game/event-catalog.ts`:

```ts
import { CircleDot, Hand, ShieldAlert, Target, Timer, TrendingDown, Undo2 } from 'lucide-react';

/**
 * Mirror of App\Services\LiveGame\LiveGameEventRules. Change both together.
 * The server is authoritative; this exists so a button is dark for the same
 * reason the API would refuse it.
 */
export type ClockRequirement = 'running' | 'stopped' | 'any';
export type ClockState = 'running' | 'stopped' | 'expired';
export type EventGroupKey = 'scoring' | 'play' | 'fouls' | 'game';

/** FIBA: a player is disqualified on their 5th personal foul. */
export const MAX_PERSONAL_FOULS = 5;

export interface RecordableEvent {
    type: string;
    team_scope: 'own' | 'opponent' | 'game';
    player_id?: number;
    payload?: Record<string, unknown>;
}

export interface PadEvent {
    key: string;
    label: string;
    icon: typeof Target;
    group: EventGroupKey;
    clock: ClockRequirement;
    tone?: 'amber';
    isPersonalFoul?: boolean;
    event: Omit<RecordableEvent, 'player_id'>;
}

export const PAD_GROUPS: Array<{ key: EventGroupKey; label: string }> = [
    { key: 'scoring', label: 'Scoring' },
    { key: 'play', label: 'Play' },
    { key: 'fouls', label: 'Fouls' },
    { key: 'game', label: 'Game' },
];

export const PAD_EVENTS: PadEvent[] = [
    { key: '2pt-made', label: '2PT made', icon: Target, group: 'scoring', clock: 'running', tone: 'amber', event: { type: 'shot_made', team_scope: 'own', payload: { points: 2 } } },
    { key: '2pt-miss', label: '2PT miss', icon: TrendingDown, group: 'scoring', clock: 'running', event: { type: 'shot_missed', team_scope: 'own', payload: { points: 2 } } },
    { key: '3pt-made', label: '3PT made', icon: Target, group: 'scoring', clock: 'running', tone: 'amber', event: { type: 'shot_made', team_scope: 'own', payload: { points: 3 } } },
    { key: '3pt-miss', label: '3PT miss', icon: TrendingDown, group: 'scoring', clock: 'running', event: { type: 'shot_missed', team_scope: 'own', payload: { points: 3 } } },
    { key: 'ft-made', label: 'FT made', icon: CircleDot, group: 'scoring', clock: 'stopped', tone: 'amber', event: { type: 'free_throw_made', team_scope: 'own' } },
    { key: 'ft-miss', label: 'FT miss', icon: CircleDot, group: 'scoring', clock: 'stopped', event: { type: 'free_throw_missed', team_scope: 'own' } },

    { key: 'reb-off', label: 'Off reb', icon: Hand, group: 'play', clock: 'running', event: { type: 'rebound', team_scope: 'own', payload: { kind: 'offensive' } } },
    { key: 'reb-def', label: 'Def reb', icon: Hand, group: 'play', clock: 'running', event: { type: 'rebound', team_scope: 'own', payload: { kind: 'defensive' } } },
    { key: 'assist', label: 'Assist', icon: Undo2, group: 'play', clock: 'running', event: { type: 'assist', team_scope: 'own' } },
    { key: 'turnover', label: 'Turnover', icon: TrendingDown, group: 'play', clock: 'running', event: { type: 'turnover', team_scope: 'own' } },

    { key: 'foul-personal', label: 'Personal', icon: ShieldAlert, group: 'fouls', clock: 'any', isPersonalFoul: true, event: { type: 'foul', team_scope: 'own', payload: { kind: 'personal' } } },
    { key: 'foul-technical', label: 'Technical', icon: ShieldAlert, group: 'fouls', clock: 'any', event: { type: 'foul', team_scope: 'own', payload: { kind: 'technical' } } },
    { key: 'foul-flagrant', label: 'Flagrant', icon: ShieldAlert, group: 'fouls', clock: 'any', event: { type: 'foul', team_scope: 'own', payload: { kind: 'flagrant' } } },

    { key: 'timeout', label: 'Timeout', icon: Timer, group: 'game', clock: 'stopped', event: { type: 'timeout', team_scope: 'own' } },
];

export function isEventAllowed(clock: ClockRequirement, state: ClockState): boolean {
    if (state === 'expired') {
        return false;
    }

    return clock === 'any' || clock === state;
}

/** The reason a clock state blocks an event, phrased as the action that unblocks it. */
export function clockBlockReason(clock: ClockRequirement, state: ClockState): string | null {
    if (state === 'expired') {
        return 'The period has ended — advance the period to keep recording.';
    }

    if (isEventAllowed(clock, state)) {
        return null;
    }

    return clock === 'running'
        ? 'Recorded with the clock running — start the clock first.'
        : 'Recorded with the clock stopped — stop the clock first.';
}

/** The reason a single pad button is unavailable, or null when it is available. */
export function padEventBlockReason(
    padEvent: PadEvent,
    clockState: ClockState,
    hasSelectedPlayer: boolean,
    selectedPlayerPersonalFouls: number,
): string | null {
    if (padEvent.event.team_scope === 'own' && padEvent.group !== 'game' && !hasSelectedPlayer) {
        return 'Select an active player first.';
    }

    const clockReason = clockBlockReason(padEvent.clock, clockState);
    if (clockReason !== null) {
        return clockReason;
    }

    if (padEvent.isPersonalFoul && selectedPlayerPersonalFouls >= MAX_PERSONAL_FOULS) {
        return `${MAX_PERSONAL_FOULS} personal fouls — this player is disqualified.`;
    }

    return null;
}
```

- [ ] **Step 2: Rewrite the pad**

`resources/js/Components/features/live-game/EventPad.tsx`:

```tsx
import {
    MAX_PERSONAL_FOULS,
    PAD_EVENTS,
    PAD_GROUPS,
    padEventBlockReason,
    type ClockState,
    type PadEvent,
    type RecordableEvent,
} from './event-catalog';
import type { Player } from '@/types';

export type { RecordableEvent } from './event-catalog';

interface EventPadProps {
    selectedPlayer?: Player;
    clockState: ClockState;
    selectedPlayerPersonalFouls: number;
    /** Non-clock reason the whole pad is unavailable: not live, not a coach, request in flight. */
    blockedReason: string | null;
    onRecord: (event: RecordableEvent) => void;
}

export function EventPad({
    selectedPlayer,
    clockState,
    selectedPlayerPersonalFouls,
    blockedReason,
    onRecord,
}: EventPadProps) {
    const disqualified = selectedPlayerPersonalFouls >= MAX_PERSONAL_FOULS;

    return (
        <section className="rounded-lg border border-border bg-card p-4" aria-labelledby="event-pad-heading">
            <div className="mb-3">
                <h2 id="event-pad-heading" className="text-sm font-bold uppercase tracking-[0.1em] text-foreground">
                    Event pad
                </h2>
                <p className="mt-1 truncate text-xs text-muted-foreground">
                    {selectedPlayer
                        ? `${selectedPlayer.first_name} ${selectedPlayer.last_name} selected`
                        : 'Select an active player'}
                    {selectedPlayer && disqualified ? ` · disqualified (${MAX_PERSONAL_FOULS} fouls)` : ''}
                </p>
            </div>

            <div className="grid gap-4">
                {PAD_GROUPS.map((group) => {
                    const groupEvents = PAD_EVENTS.filter((padEvent) => padEvent.group === group.key);

                    return (
                        <div key={group.key}>
                            <p className="mb-2 text-[11px] font-bold uppercase tracking-[0.12em] text-muted-foreground">
                                {group.label}
                            </p>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-5 xl:grid-cols-3">
                                {groupEvents.map((padEvent) => (
                                    <PadButton
                                        key={padEvent.key}
                                        padEvent={padEvent}
                                        reason={
                                            blockedReason ??
                                            padEventBlockReason(
                                                padEvent,
                                                clockState,
                                                Boolean(selectedPlayer),
                                                selectedPlayerPersonalFouls,
                                            )
                                        }
                                        onRecord={() =>
                                            onRecord({
                                                ...padEvent.event,
                                                ...(padEvent.event.team_scope === 'own' &&
                                                padEvent.group !== 'game' &&
                                                selectedPlayer
                                                    ? { player_id: selectedPlayer.id }
                                                    : {}),
                                            })
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function PadButton({
    padEvent,
    reason,
    onRecord,
}: {
    padEvent: PadEvent;
    reason: string | null;
    onRecord: () => void;
}) {
    const Icon = padEvent.icon;
    const blocked = reason !== null;

    return (
        <button
            type="button"
            title={reason ?? padEvent.label}
            aria-label={blocked ? `${padEvent.label} — ${reason}` : padEvent.label}
            disabled={blocked}
            onClick={onRecord}
            className={`flex h-14 cursor-pointer flex-col items-center justify-center gap-1 rounded-md border text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-40 ${
                padEvent.tone === 'amber'
                    ? 'border-amber-300/35 bg-amber-300/10 text-amber-100 hover:bg-amber-300/20'
                    : 'border-border bg-muted/30 text-foreground hover:bg-muted/70'
            }`}
        >
            <Icon size={16} />
            <span>{padEvent.label}</span>
        </button>
    );
}
```

- [ ] **Step 3: Verify types compile**

Run: `npm run typecheck`
Expected: FAIL on `resources/js/Pages/LiveGames/Show.tsx` only — it still passes the old `disabled` prop. Task 9 fixes the call site. Do not proceed past that error into unrelated files.

- [ ] **Step 4: Commit (with Task 9, since the call site must compile)**

Hold this commit until Task 9 Step 5. The two files don't typecheck independently.

---

## Task 9: `Show.tsx` — drift-free clock, one selection clamp, one reason banner

**Files:**
- Modify: `resources/js/Pages/LiveGames/Show.tsx`

**Interfaces:**
- Consumes: `ClockState`, `MAX_PERSONAL_FOULS` from the catalog (Task 8); `can_stop_clock` from Task 10.
- Produces: `clockState`, `selectedPlayerPersonalFouls` and `blockedReason` passed into `EventPad`; `applySnapshot()` used by all three snapshot sources.

Three separate defects in this file:

1. **Clock drift** — `:129` computes `elapsed = (Date.now() - Date.parse(server_now)) / 1000`, mixing the client's wall clock with the server's. A tablet 90s off shows a 90s-wrong clock, or clamps to `0:00` and (after Task 3) locks the pad for no reason.
2. **Selection isn't clamped after your own action** — `:97-104` and `:114` clamp `selectedPlayerId` to controlled-active on load and on Echo, but `postSnapshot` (`:138`) doesn't. Sub out your own selected player and `selectedPlayer` still resolves from the *roster* (`:67`), so the pad stays lit and the next tap fails server-side.
3. **Two partial hint blocks** at `:405-416` that between them cover only "not live" and "not a coach".

- [ ] **Step 1: Replace the three `setSnapshot` sites with one helper**

Add state and helper alongside the existing `useState` block (near line 47):

```tsx
    const [snapshot, setSnapshot] = useState(initialSnapshot);
    const [snapshotReceivedAt, setSnapshotReceivedAt] = useState(() => Date.now());

    const applySnapshot = useCallback((next: LiveGameSnapshot): void => {
        setSnapshot(next);
        setSnapshotReceivedAt(Date.now());
        setClockTick(Date.now());
    }, []);
```

Import `useCallback` from `react`. Then:
- Replace the body of the `initialSnapshot` effect (`:97-104`) with `applySnapshot(initialSnapshot);` and set its deps to `[initialSnapshot, applySnapshot]`.
- In the Echo listener (`:108-116`), replace the whole callback body with `applySnapshot(nextSnapshot);` and drop `viewerSide` / `controlled_player_ids` from that effect's deps.
- In `postSnapshot` (`:138`), replace `setSnapshot(response.data); setClockTick(Date.now());` with `applySnapshot(response.data);`.

- [ ] **Step 2: Anchor the displayed clock on receipt, not on the server's wall clock**

Replace `displayedClock` (`:127-131`):

```tsx
    const displayedClock = useMemo(() => {
        if (!snapshot.clock.running) {
            return snapshot.clock;
        }

        // seconds_remaining already accounts for server-side elapsed time, so measure
        // from when this snapshot arrived. Never compare Date.now() to server_now —
        // a device with a skewed wall clock would show a wildly wrong clock.
        const elapsed = Math.max(0, Math.floor((clockTick - snapshotReceivedAt) / 1000));

        return {
            ...snapshot.clock,
            seconds_remaining: Math.max(0, snapshot.clock.seconds_remaining - elapsed),
        };
    }, [clockTick, snapshot.clock, snapshotReceivedAt]);

    const clockState: ClockState = displayedClock.seconds_remaining === 0
        ? 'expired'
        : displayedClock.running
            ? 'running'
            : 'stopped';
```

`server_now` stays in the snapshot payload — it is no longer used here, but it is not ours to remove from the state builder in this pass.

- [ ] **Step 3: One clamp, and resolve the selected player from active players**

Delete the clamping logic from the `initialSnapshot` effect and the Echo listener (done in Step 1), then add a single effect after `controlledActiveIds` is computed:

```tsx
    const controlledActiveKey = controlledActiveIds.join(',');

    useEffect(() => {
        setSelectedPlayerId((current) =>
            current !== null && controlledActiveIds.includes(current)
                ? current
                : controlledActiveIds[0] ?? null,
        );
        // controlledActiveKey is a stable stringification of controlledActiveIds
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [controlledActiveKey]);
```

Change `selectedPlayer` (`:67`) so a benched player can never leave the pad enabled:

```tsx
    const selectedPlayer = controlledRosterPlayers.find(
        (player) => player.id === selectedPlayerId && controlledActiveIds.includes(player.id),
    );

    const selectedPlayerPersonalFouls = selectedPlayer
        ? (snapshot.stats.find((stat) => stat.player_id === selectedPlayer.id)?.personal_fouls ?? 0)
        : 0;
```

- [ ] **Step 4: One reason banner, and the new pad props**

Replace `eventsDisabled` (`:217`) with a single reason string:

```tsx
    const recordingBlockedReason = !canRecord
        ? 'You are viewing this game. Only coaches tied to the home or opponent team can record events.'
        : snapshot.liveGame.status === 'setup'
            ? (snapshot.both_lineups_ready
                ? 'Start the game to enable event recording and substitutions.'
                : 'Both coaches must submit a starting five before the game can start.')
            : snapshot.liveGame.status === 'finished'
                ? 'This game is finished. Existing events can still be voided from the timeline.'
                : controlledActiveIds.length === 0
                    ? 'None of the players assigned to you are on court.'
                    : processing
                        ? 'Recording…'
                        : null;
```

Replace the `EventPad` render (`:404`) and delete both hint blocks at `:405-416`:

```tsx
                        <EventPad
                            selectedPlayer={selectedPlayer}
                            clockState={clockState}
                            selectedPlayerPersonalFouls={selectedPlayerPersonalFouls}
                            blockedReason={recordingBlockedReason}
                            onRecord={record}
                        />
                        {(recordingBlockedReason ?? clockBlockReason('running', clockState)) && (
                            <div className="rounded-lg border border-dashed border-border bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
                                {recordingBlockedReason ?? clockBlockReason('running', clockState)}
                            </div>
                        )}
```

Import `clockBlockReason` and `type ClockState` from `@/Components/features/live-game/event-catalog`.

`BenchSubstitution`'s `disabled` prop (`:399`) becomes:

```tsx
                            disabled={
                                recordingBlockedReason !== null ||
                                clockState !== 'stopped' ||
                                controlledActiveIds.length === 0
                            }
```

Substitutions require a stopped clock, matching the server rule from Task 3.

- [ ] **Step 5: Verify and commit Tasks 8 + 9 together**

Run: `npm run typecheck`
Expected: PASS.

```bash
git add resources/js/Components/features/live-game/event-catalog.ts resources/js/Components/features/live-game/EventPad.tsx resources/js/Pages/LiveGames/Show.tsx
git commit -m "feat(live-game): gate the event pad on clock state and split rebound/foul kinds"
```

---

## Task 10: Scoreboard — the period-ended state and split clock authority

**Files:**
- Modify: `resources/js/Components/features/live-game/GameScoreboard.tsx:17-49`
- Modify: `app/Http/Controllers/LiveGameController.php:241-253` (`show` props)
- Modify: `resources/js/Pages/LiveGames/Show.tsx` (prop + pass-through)

**Interfaces:**
- Consumes: `LiveGame::isMainCoach()` (Task 2).
- Produces: Inertia prop `can_stop_clock: boolean`; `GameScoreboard` prop `canStopClock`.

**Why:** at `0:00` the scoreboard reads "Clock stopped" in the same cyan as any ordinary stoppage (`:26`), so the one state that demands a specific action is indistinguishable from the one that demands nothing. And the stop button must now render for main coaches who aren't the creator.

- [ ] **Step 1: Pass the new prop from the controller**

In `LiveGameController::show()`, add to the `Inertia::render` array:

```php
            'can_stop_clock' => $liveGame->isCreator($user) || $liveGame->isMainCoach($user),
```

- [ ] **Step 2: Add the period-ended state to the scoreboard**

In `GameScoreboard.tsx`, extend the props interface with `canStopClock?: boolean;`, then inside the component:

```tsx
    const isFinished = status === 'finished';
    const periodEnded = !isFinished && clock.seconds_remaining === 0;
    const canStart = canControlClock && !periodEnded;
    const showClockToggle = clock.running ? canStopClock : canControlClock;
```

Replace the status line (`:24-27`):

```tsx
                <div
                    className={`flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] ${
                        periodEnded ? 'text-amber-200' : 'text-cyan-300'
                    }`}
                >
                    <span
                        className={`h-2 w-2 rounded-full ${
                            clock.running ? 'bg-cyan-400 animate-pulse' : periodEnded ? 'bg-amber-400' : 'bg-slate-500'
                        }`}
                    />
                    {isFinished
                        ? 'Final'
                        : periodEnded
                            ? `Q${clock.period} ended`
                            : clock.running
                                ? 'Clock synced'
                                : 'Clock stopped'}
                </div>
```

Change the toggle button's guard from `canControlClock` to `showClockToggle`, and its disabled state and title:

```tsx
                            <button
                                type="button"
                                onClick={() => onClockAction(clock.running ? 'stop' : 'start')}
                                disabled={processing || (!clock.running && !canStart)}
                                aria-label={clock.running ? 'Stop clock' : 'Start clock'}
                                title={
                                    clock.running
                                        ? 'Stop clock'
                                        : periodEnded
                                            ? `Q${clock.period} has ended — advance the period or reset the clock`
                                            : 'Start clock'
                                }
```

The `!isFinished && canControlClock &&` wrapper at `:31` becomes `!isFinished && (showClockToggle || canControlClock) &&`, with the reset and advance buttons kept inside a nested `canControlClock &&` so they stay creator-only.

- [ ] **Step 3: Wire it in `Show.tsx`**

Add `can_stop_clock: boolean;` to `LiveGameShowProps`, destructure it, and pass it through:

```tsx
                    canControlClock={isCreator && snapshot.both_lineups_ready}
                    canStopClock={can_stop_clock && snapshot.both_lineups_ready}
```

- [ ] **Step 4: Verify**

Run: `npm run typecheck && php artisan test --filter=LiveGameController`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint app/Http/Controllers/LiveGameController.php
git add app/Http/Controllers/LiveGameController.php resources/js/Components/features/live-game/GameScoreboard.tsx resources/js/Pages/LiveGames/Show.tsx
git commit -m "feat(live-game): show a period-ended clock state and let main coaches stop the clock"
```

---

## Task 11: Timeline — make it scroll, make voiding deliberate

**Files:**
- Modify: `resources/js/Components/features/live-game/Timeline.tsx` (full rewrite)
- Create: `resources/js/Components/features/live-game/TimelineRow.tsx`
- Create: `resources/js/Components/features/live-game/VoidEventConfirmModal.tsx`
- Modify: `resources/js/Components/features/live-game/live-game-utils.ts` (`eventLabel` learns teams)
- Modify: `resources/js/Pages/LiveGames/Show.tsx` (confirm-modal state)

**Interfaces:**
- Consumes: `payload.team_id` on timeout events (Task 6).
- Produces: `Timeline` props `{ events, players, teams, disabled, onRequestVoid }`; `VoidEventConfirmModal` props `{ open, onOpenChange, eventLabel, isSubstitution, onConfirm }`; `eventLabel(event, players, teams?)`.

**Why it doesn't scroll today:** the scroller has `overflow-y-auto`, but nothing constrains its height. The section is `min-h-[300px] flex flex-col`, the scroller is `min-h-0 flex-1`, and neither the section nor its grid cell has a cap — so the section grows and the *page* scrolls instead. `AlertsPanel.tsx:9` gets this right with `max-h-[240px]`.

- [ ] **Step 1: Teach `eventLabel` about teams**

In `live-game-utils.ts`, replace `eventLabel`:

```ts
import type { LiveGameEvent, Player, Team } from '@/types';

export function eventLabel(event: LiveGameEvent, players: Player[], teams: Team[] = []): string {
    if (event.type === 'timeout') {
        const teamId = typeof event.payload.team_id === 'number' ? event.payload.team_id : null;
        const team = teamId !== null ? teams.find((candidate) => candidate.id === teamId) : undefined;

        return team ? `timeout · ${team.code ?? team.name}` : 'timeout';
    }

    const player = event.player_id ? players.find((candidate) => candidate.id === event.player_id) : undefined;
    const points = typeof event.payload.points === 'number' ? ` ${event.payload.points}PT` : '';
    const kind = typeof event.payload.kind === 'string' ? ` ${event.payload.kind}` : '';
    const name = player ? ` ${playerName(player)}` : '';

    return `${event.type.replaceAll('_', ' ')}${points}${kind}${name}`;
}
```

The default `teams = []` keeps `AlertsPanel`'s existing two-argument call working.

- [ ] **Step 2: Extract the row**

`resources/js/Components/features/live-game/TimelineRow.tsx`:

```tsx
import { clockLabel, eventLabel } from './live-game-utils';
import type { LiveGameEvent, Player, Team } from '@/types';
import { Ban } from 'lucide-react';

interface TimelineRowProps {
    event: LiveGameEvent;
    players: Player[];
    teams: Team[];
    voided: boolean;
    disabled: boolean;
    onRequestVoid: (event: LiveGameEvent) => void;
}

export function TimelineRow({ event, players, teams, voided, disabled, onRequestVoid }: TimelineRowProps) {
    const voidable = event.type !== 'correction' && !voided;

    return (
        <div
            className={`grid min-h-[62px] grid-cols-[auto_1fr_auto] items-center gap-3 border-b border-border/70 px-4 py-2 last:border-b-0 ${
                voided ? 'opacity-45' : 'hover:bg-muted/25'
            }`}
        >
            <span className="font-mono text-xs text-cyan-200">
                Q{event.period}
                <br />
                {clockLabel(event.clock_seconds_remaining)}
            </span>
            <div className="min-w-0">
                <p className="truncate text-sm font-semibold capitalize text-foreground">
                    {eventLabel(event, players, teams)}
                </p>
                <p className="text-xs text-muted-foreground">
                    #{event.sequence} {event.type === 'correction' ? 'Correction' : voided ? 'Voided' : event.team_scope}
                </p>
            </div>
            {voidable ? (
                <button
                    type="button"
                    disabled={disabled}
                    onClick={() => onRequestVoid(event)}
                    aria-label={`Void event ${event.sequence}`}
                    title="Void event"
                    className="flex h-11 w-11 cursor-pointer items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:border-red-300/60 hover:bg-red-400/10 hover:text-red-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <Ban size={16} />
                </button>
            ) : (
                <span className="h-11 w-11" />
            )}
        </div>
    );
}
```

- [ ] **Step 3: Rewrite `Timeline.tsx` with a real height cap**

```tsx
import { TimelineRow } from './TimelineRow';
import type { LiveGameEvent, Player, Team } from '@/types';
import { History } from 'lucide-react';

interface TimelineProps {
    events: LiveGameEvent[];
    players: Player[];
    teams: Team[];
    disabled: boolean;
    onRequestVoid: (event: LiveGameEvent) => void;
}

export function Timeline({ events, players, teams, disabled, onRequestVoid }: TimelineProps) {
    const voidedIds = new Set(
        events
            .filter((event) => event.type === 'correction' && event.voids_event_id)
            .map((event) => event.voids_event_id),
    );
    const recordedCount = events.filter(
        (event) => event.type !== 'correction' && !voidedIds.has(event.id),
    ).length;

    return (
        <section
            className="flex min-h-[300px] flex-col rounded-lg border border-border bg-card"
            aria-labelledby="timeline-heading"
        >
            <div className="flex items-center justify-between border-b border-border px-4 py-3">
                <h2
                    id="timeline-heading"
                    className="flex items-center gap-2 text-sm font-bold uppercase tracking-[0.1em] text-foreground"
                >
                    <History size={16} className="text-cyan-300" /> Timeline
                </h2>
                <span className="text-xs text-muted-foreground">
                    {recordedCount} recorded
                    {voidedIds.size > 0 ? ` · ${voidedIds.size} voided` : ''}
                </span>
            </div>
            <div
                tabIndex={0}
                aria-label="Recorded events, newest first"
                className="min-h-0 flex-1 overflow-y-auto focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-cyan-300"
                style={{ maxHeight: 'min(55vh, 520px)' }}
            >
                {[...events].reverse().map((event) => (
                    <TimelineRow
                        key={event.id}
                        event={event}
                        players={players}
                        teams={teams}
                        voided={voidedIds.has(event.id)}
                        disabled={disabled}
                        onRequestVoid={onRequestVoid}
                    />
                ))}
                {events.length === 0 && (
                    <p className="p-6 text-center text-sm text-muted-foreground">
                        Events will appear here as they are recorded.
                    </p>
                )}
            </div>
        </section>
    );
}
```

The cap is an inline `style` because Tailwind cannot express `min()` in an arbitrary `max-h-[]` value reliably across the comma. Newest-first ordering is already correct, so no auto-scroll is needed. No `aria-live`: every snapshot re-renders the whole list, so a live region would re-announce everything.

- [ ] **Step 4: Add the confirmation modal**

`resources/js/Components/features/live-game/VoidEventConfirmModal.tsx`:

```tsx
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Ban } from 'lucide-react';

interface VoidEventConfirmModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    eventLabel: string | null;
    isSubstitution: boolean;
    onConfirm: () => void;
}

export function VoidEventConfirmModal({
    open,
    onOpenChange,
    eventLabel,
    isSubstitution,
    onConfirm,
}: VoidEventConfirmModalProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-border bg-card sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="font-display text-lg font-black uppercase tracking-[0.08em]">
                        Void this event?
                    </DialogTitle>
                    <DialogDescription className="text-sm text-muted-foreground">
                        {eventLabel ? `${eventLabel} will be removed from the score and stats. ` : ''}
                        {isSubstitution
                            ? 'This is a substitution — voiding it changes who was on court for every later event, and plus-minus is recalculated for both teams.'
                            : 'Stats and plus-minus are recalculated for both teams. Voiding cannot be undone.'}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="flex flex-col gap-2 sm:flex-col sm:space-x-0">
                    <button
                        type="button"
                        onClick={onConfirm}
                        className="flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-md border border-red-300/50 bg-red-400/15 px-4 text-xs font-bold uppercase tracking-wide text-red-100 transition-colors hover:bg-red-400/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                    >
                        <Ban size={16} /> Void event
                    </button>
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="flex min-h-11 w-full cursor-pointer items-center justify-center rounded-md border border-border px-4 text-xs font-bold uppercase tracking-wide text-muted-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                    >
                        Keep event
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 5: Wire the modal in `Show.tsx`**

Add state and handler:

```tsx
    const [pendingVoidEvent, setPendingVoidEvent] = useState<LiveGameEvent | null>(null);

    function confirmVoid(): void {
        const target = pendingVoidEvent;
        setPendingVoidEvent(null);

        if (target !== null) {
            void postSnapshot(route('live-games.correction', { liveGame: liveGame.id }), {
                voids_event_id: target.id,
            });
        }
    }
```

Delete the old `voidEvent` function (`:157-159`). Update the `Timeline` render (`:417`) and add the modal beside it:

```tsx
                        <Timeline
                            events={snapshot.events}
                            players={allPlayers}
                            teams={teams}
                            disabled={processing}
                            onRequestVoid={setPendingVoidEvent}
                        />
                        <VoidEventConfirmModal
                            open={pendingVoidEvent !== null}
                            onOpenChange={(open) => !open && setPendingVoidEvent(null)}
                            eventLabel={pendingVoidEvent ? eventLabel(pendingVoidEvent, allPlayers, teams) : null}
                            isSubstitution={pendingVoidEvent?.type === 'substitution'}
                            onConfirm={confirmVoid}
                        />
```

Import `eventLabel` alongside the existing `playerName` import, `VoidEventConfirmModal`, and add `LiveGameEvent` to the type import from `@/types`.

- [ ] **Step 6: Verify**

Run: `npm run typecheck`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Components/features/live-game/ resources/js/Pages/LiveGames/Show.tsx
git commit -m "feat(live-game): cap the timeline scroller and confirm before voiding an event"
```

---

## Task 12: Full-stack verification

**Files:** none modified — this task is the gate.

- [ ] **Step 1: Run the whole suite**

```bash
php artisan test
npm run typecheck
vendor/bin/pint --test
```

Expected: all green. `pytest tests/python` is untouched by this work but should still pass.

- [ ] **Step 2: Two-device manual pass**

Start `composer dev` (Reverb included) and follow `docs/live-game-1plus1-sync-checklist.md` with two browser profiles — `warriors@email.com` (creator) and `lakers@email.com` — through setup, then:

1. Start the game, leave the clock stopped → the Scoring live-ball buttons and the whole Play group are dark with a stated reason; FT made/miss, the Fouls group and Timeout are live.
2. Start the clock → that inverts: FT and Timeout go dark, live-ball buttons light up, Sub is disabled.
3. Record a Personal foul with the clock running → the clock stops on **both** devices without a refresh, and the FT buttons light up.
4. Let the clock reach `0:00` → the scoreboard reads "Q1 ended" in amber with the pulse off, the entire pad is dark, tapping Play reports the period has ended, and voiding a timeline row still works.
5. On the Lakers device → Stop is available, Start / Advance / Reset are not.
6. Give a player 5 personal fouls → the Personal button goes dark for that player with the disqualification reason, while Technical and Flagrant stay available.
7. Record ~25 events → the timeline scrolls inside its own panel and the page itself does not scroll horizontally or vertically because of it. Check at 768px, 1024px and 1440px.
8. Void a substitution → the dialog names the event and warns that on-court composition and plus-minus are recalculated; confirm plus-minus changes after.
9. Sub out the currently selected player → selection moves to another controlled active player and no request fails.
10. Call a Timeout from each device → each timeline row shows the calling team's code.
11. Log in as a coach on a third, unrelated team and open `/live-games/{id}` → the page renders read-only, and the void buttons are absent because `canRecord` is false.

- [ ] **Step 3: Commit any fixes found in the manual pass**

```bash
git add -A
git commit -m "fix(live-game): address findings from the two-device verification pass"
```

---

## Self-Review

**Spec coverage.** Every audit finding maps to a task: clock gate → 1, 3, 8, 9; period-ended dead end → 2, 3, 10; selection clamp → 9; authorization hole → 7; void confirm + double-void → 5, 11; rebound/foul kinds → 8; foul-out → 4; timeout attribution → 6; clock drift → 9; timeline scroll and counts → 11. Deliberately excluded, with reasons stated in "Global Constraints": timeout limits, index scoping, steal/block event types.

**Placeholder scan.** No TBDs; every code step carries the actual code. Steps that touch existing files name exact line anchors from the current working tree.

**Type consistency.** `clockRequirement` / `isAllowedWhileClockRunning` / `isAllowedWhileClockStopped` / `isAllowedAtPeriodEnd` / `stopsClock` / `MAX_PERSONAL_FOULS` are defined in Task 1 and used with those exact names in Tasks 3, 4 and 8. `stopFor()` is defined in Task 2 and called in Task 3. `isMainCoach()` is defined in Task 2 and used in Tasks 2 and 10. `ClockState` / `padEventBlockReason` / `clockBlockReason` / `RecordableEvent` are defined in Task 8's catalog and consumed in Tasks 8, 9 and 10. `can_stop_clock` (PHP prop, snake) maps to `canStopClock` (React prop, camel) in Task 10. `onRequestVoid` replaces `onVoid` consistently across Task 11's three files and the `Show.tsx` call site.

**Known ordering constraint.** Tasks 8 and 9 share one commit — `EventPad`'s new props and `Show.tsx`'s call site don't typecheck independently. Every other task commits on its own.

## Related documents

- Design/spec: [`docs/superpowers/specs/2026-08-04-live-game-clock-gated-recording-design.md`](../specs/2026-08-04-live-game-clock-gated-recording-design.md)
- Two-device manual checklist reused in Task 12: [`docs/live-game-1plus1-sync-checklist.md`](../../live-game-1plus1-sync-checklist.md)

Per-task `git commit` steps are written into this plan as the superpowers convention, but this
repo's standing preference is that commits are never made unasked — treat them as commit
*points*, and stage/commit only when the repo owner asks.
