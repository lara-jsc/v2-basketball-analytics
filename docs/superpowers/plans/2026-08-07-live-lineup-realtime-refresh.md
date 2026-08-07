# Live Lineup Real-Time Refresh — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Auto-refresh the dual lineup suggestion panel when live events are recorded, and demote cold shooters and early foul trouble in the "By tonight" column only.

**Architecture:** Extract miss-streak logic into a shared calculator; extend `LiveLineupEligibilityFilter` with a `Season` vs `Tonight` mode so the job ranks each column against a different eligible pool; return per-column held-back reasons from the API; wire `SuggestedLineupPanel` to refetch when `snapshot.events` max sequence changes.

**Tech Stack:** Laravel 13, Inertia 2, React 18 + TypeScript, Pest/PHPUnit, Python `lineup` command (unchanged), Laravel queue (`RecommendLiveLineup`).

## Global Constraints

- **Layering** (`CLAUDE.md`): controllers render/redirect; business logic in services; jobs call Python; no Eloquent in controllers.
- **`Components/ui/` is never modified.** Extend via `className` or `Components/features/live-game/`.
- **No new Python commands.** Same `lineup` ranker, two payloads.
- **Never blend season and tonight scores.**
- **`plus_minus` renders as `—` when null** in UI.
- **Queue worker required** for suggestions (`composer dev` or `queue:listen`).
- Every task ends green on targeted `php artisan test`, `npm run typecheck`, and `vendor/bin/pint`.

## File Structure

**Create**

| File | Responsibility |
|------|----------------|
| `app/Services/LiveGame/LiveGameMissStreakCalculator.php` | Walk effective events; return per-player miss streak |
| `app/Services/LiveGame/EligibilityMode.php` | Enum: `Season`, `Tonight` |
| `tests/Unit/Services/LiveGame/LiveGameMissStreakCalculatorTest.php` | Streak calculator unit tests |

**Modify**

| File | Change |
|------|--------|
| `app/Services/LiveGame/LiveGameAlertService.php` | Delegate streak computation to calculator |
| `app/Services/LiveGame/LiveLineupEligibilityFilter.php` | Accept `EligibilityMode`; tonight-only 3-foul + cold demotion |
| `app/Jobs/RecommendLiveLineup.php` | Dual eligibility; store per-column reasons |
| `app/Http/Controllers/LiveGameSuggestionController.php` | Return nested `reasons.season` / `reasons.tonight` |
| `resources/js/types/index.ts` | Add `cold_player`; nest `reasons` by column |
| `resources/js/Components/features/live-game/SuggestedLineupPanel.tsx` | Auto-refetch prop; per-column held back |
| `resources/js/Pages/LiveGames/Show.tsx` | Pass `eventSequence` to panel |
| `tests/Unit/Services/LiveGame/LiveLineupEligibilityFilterTest.php` | Mode-specific foul/cold cases |
| `tests/Feature/LiveGame/LiveLineupSuggestionTest.php` | End-to-end tonight demotion |

---

### Task 1: Miss streak calculator

**Files:**
- Create: `app/Services/LiveGame/LiveGameMissStreakCalculator.php`
- Create: `tests/Unit/Services/LiveGame/LiveGameMissStreakCalculatorTest.php`
- Modify: `app/Services/LiveGame/LiveGameAlertService.php:20-64`

**Interfaces:**
- Consumes: `Illuminate\Support\Collection` of `LiveGameEvent` (effective events only).
- Produces: `LiveGameMissStreakCalculator::compute(Collection $events): array<int, int>` — player_id → streak count.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Services/LiveGame/LiveGameMissStreakCalculatorTest.php`:

```php
<?php

namespace Tests\Unit\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\Player;
use App\Services\LiveGame\LiveGameMissStreakCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveGameMissStreakCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_made_shot_resets_the_miss_streak(): void
    {
        $game = LiveGame::factory()->create();
        $player = Player::factory()->for($game->homeTeam)->create();

        $events = collect([
            $this->event($game, $player, 'shot_missed', 1),
            $this->event($game, $player, 'shot_missed', 2),
            $this->event($game, $player, 'shot_made', 3, ['points' => 2]),
            $this->event($game, $player, 'shot_missed', 4),
        ]);

        $streaks = app(LiveGameMissStreakCalculator::class)->compute($events);

        $this->assertSame(1, $streaks[$player->id] ?? 0);
    }

    public function test_three_consecutive_misses_yield_a_streak_of_three(): void
    {
        $game = LiveGame::factory()->create();
        $player = Player::factory()->for($game->homeTeam)->create();

        $events = collect([
            $this->event($game, $player, 'shot_missed', 1),
            $this->event($game, $player, 'shot_missed', 2),
            $this->event($game, $player, 'shot_missed', 3),
        ]);

        $streaks = app(LiveGameMissStreakCalculator::class)->compute($events);

        $this->assertSame(3, $streaks[$player->id] ?? 0);
    }

    public function test_free_throw_misses_do_not_increment_fg_miss_streak(): void
    {
        $game = LiveGame::factory()->create();
        $player = Player::factory()->for($game->homeTeam)->create();

        $events = collect([
            $this->event($game, $player, 'shot_missed', 1),
            $this->event($game, $player, 'free_throw_missed', 2),
        ]);

        $streaks = app(LiveGameMissStreakCalculator::class)->compute($events);

        $this->assertSame(1, $streaks[$player->id] ?? 0);
    }

    /** @param array<string, mixed> $payload */
    private function event(LiveGame $game, Player $player, string $type, int $sequence, array $payload = []): LiveGameEvent
    {
        return LiveGameEvent::query()->create([
            'live_game_id' => $game->id,
            'sequence' => $sequence,
            'type' => $type,
            'team_scope' => 'own',
            'player_id' => $player->id,
            'period' => 1,
            'clock_seconds_remaining' => 600,
            'payload' => $payload,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LiveGameMissStreakCalculatorTest`  
Expected: FAIL — class not found.

- [ ] **Step 3: Implement calculator**

`app/Services/LiveGame/LiveGameMissStreakCalculator.php`:

```php
<?php

namespace App\Services\LiveGame;

use App\Models\LiveGameEvent;
use Illuminate\Support\Collection;

class LiveGameMissStreakCalculator
{
    public const DEMOTION_THRESHOLD = 3;

    /**
     * @param  Collection<int, LiveGameEvent>  $events  Effective (non-voided) own-player events.
     * @return array<int, int>  player_id => current miss streak
     */
    public function compute(Collection $events): array
    {
        $streaks = [];

        foreach ($events as $event) {
            if ($event->team_scope !== 'own' || $event->player_id === null) {
                continue;
            }

            $playerId = (int) $event->player_id;

            if ($event->type === 'shot_made') {
                $streaks[$playerId] = 0;

                continue;
            }

            if ($event->type === 'shot_missed') {
                $streaks[$playerId] = ($streaks[$playerId] ?? 0) + 1;
            }
        }

        return $streaks;
    }
}
```

- [ ] **Step 4: Refactor `LiveGameAlertService` to use calculator**

Replace inline `$missStreaks` loop (lines 58–64) with:

```php
$missStreaks = app(LiveGameMissStreakCalculator::class)->compute(
    $effectiveEvents->filter(fn (LiveGameEvent $event): bool => $event->team_scope === 'own'),
);
```

Remove the duplicate increment/reset logic from the foreach body.

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=LiveGameMissStreakCalculatorTest`  
Run: `php artisan test --filter=LiveGameAlertServiceTest`  
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/LiveGame/LiveGameMissStreakCalculator.php \
  app/Services/LiveGame/LiveGameAlertService.php \
  tests/Unit/Services/LiveGame/LiveGameMissStreakCalculatorTest.php
git commit -m "feat(live-game): extract miss streak calculator for shared alert and lineup use"
```

---

### Task 2: Column-specific eligibility

**Files:**
- Create: `app/Services/LiveGame/EligibilityMode.php`
- Modify: `app/Services/LiveGame/LiveLineupEligibilityFilter.php`
- Test: `tests/Unit/Services/LiveGame/LiveLineupEligibilityFilterTest.php`

**Interfaces:**
- Consumes: `LiveGameMissStreakCalculator`, effective events from `LiveGameEvent` query.
- Produces: `LiveLineupEligibilityFilter::filter(..., EligibilityMode $mode = EligibilityMode::Season)`; new constant `REASON_COLD_PLAYER = 'cold_player'`.

- [ ] **Step 1: Write failing tests**

Add to `LiveLineupEligibilityFilterTest.php`:

```php
use App\Services\LiveGame\EligibilityMode;
use App\Models\LiveGameEvent;

public function test_three_fouls_in_q1_is_rankable_in_season_mode(): void
{
    [$game, $player] = $this->gameWithPlayer(['current_period' => 2]);
    $this->stat($game, $player, ['personal_fouls' => 3]);

    $result = $this->filter($game, [$player], mode: EligibilityMode::Season);

    $this->assertSame([$player->id], $result->rankablePlayerIds);
    $this->assertSame([], $result->demotedPlayerIds);
}

public function test_three_fouls_in_q1_is_demoted_in_tonight_mode(): void
{
    [$game, $player] = $this->gameWithPlayer(['current_period' => 2]);
    $this->stat($game, $player, ['personal_fouls' => 3]);

    $result = $this->filter($game, [$player], mode: EligibilityMode::Tonight);

    $this->assertSame([], $result->rankablePlayerIds);
    $this->assertSame([$player->id], $result->demotedPlayerIds);
    $this->assertSame(
        [$player->id => LiveLineupEligibilityFilter::REASON_FOUL_TROUBLE],
        $result->reasons,
    );
}

public function test_a_cold_shooter_is_demoted_in_tonight_mode_only(): void
{
    [$game, $player] = $this->gameWithPlayer();
    $this->stat($game, $player, ['personal_fouls' => 0]);
    foreach ([1, 2, 3] as $sequence) {
        LiveGameEvent::query()->create([
            'live_game_id' => $game->id,
            'sequence' => $sequence,
            'type' => 'shot_missed',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'period' => 1,
            'clock_seconds_remaining' => 600,
            'payload' => [],
        ]);
    }

    $season = $this->filter($game, [$player], mode: EligibilityMode::Season);
    $tonight = $this->filter($game, [$player], mode: EligibilityMode::Tonight);

    $this->assertSame([$player->id], $season->rankablePlayerIds);
    $this->assertSame([], $season->demotedPlayerIds);
    $this->assertSame([], $tonight->rankablePlayerIds);
    $this->assertSame([$player->id], $tonight->demotedPlayerIds);
    $this->assertSame(
        [$player->id => LiveLineupEligibilityFilter::REASON_COLD_PLAYER],
        $tonight->reasons,
    );
}
```

Update the private `filter()` helper to accept `EligibilityMode $mode = EligibilityMode::Season` and pass it through.

- [ ] **Step 2: Run tests — expect FAIL**

Run: `php artisan test --filter=LiveLineupEligibilityFilterTest`

- [ ] **Step 3: Implement enum and filter changes**

`app/Services/LiveGame/EligibilityMode.php`:

```php
<?php

namespace App\Services\LiveGame;

enum EligibilityMode
{
    case Season;
    case Tonight;
}
```

In `LiveLineupEligibilityFilter`:

1. Add `public const REASON_COLD_PLAYER = 'cold_player';`
2. Add `EligibilityMode $mode = EligibilityMode::Season` parameter to `filter()`
3. After foul checks, when `$mode === EligibilityMode::Tonight`:
   - Use `$foulThreshold = $game->current_period < 4 ? 3 : LiveLineupEligibilityFilter::FOUL_TROUBLE_THRESHOLD` for demotion (3 in Q1–Q3, 4 in Q4)
   - Load effective events for the game; call `LiveGameMissStreakCalculator::compute()`; demote players with streak ≥ 3

Season mode keeps existing 4-foul demotion only (ignore miss streaks).

- [ ] **Step 4: Run tests — expect PASS**

Run: `php artisan test --filter=LiveLineupEligibilityFilterTest`

- [ ] **Step 5: Commit**

```bash
git add app/Services/LiveGame/EligibilityMode.php \
  app/Services/LiveGame/LiveLineupEligibilityFilter.php \
  tests/Unit/Services/LiveGame/LiveLineupEligibilityFilterTest.php
git commit -m "feat(live-game): tonight-only eligibility for cold shooters and early foul trouble"
```

---

### Task 3: Job and API — dual eligibility + per-column reasons

**Files:**
- Modify: `app/Jobs/RecommendLiveLineup.php`
- Modify: `app/Http/Controllers/LiveGameSuggestionController.php`
- Modify: `resources/js/types/index.ts`
- Test: `tests/Feature/LiveGame/LiveLineupSuggestionTest.php`

**Interfaces:**
- Consumes: `EligibilityMode`, dual `LiveLineupEligibility` results.
- Produces: cached payload `{ season, tonight, reasons: { season: array, tonight: array } }`; JSON response with nested `reasons`.

- [ ] **Step 1: Write failing feature test**

Add to `LiveLineupSuggestionTest.php`:

```php
public function test_a_cold_shooter_is_held_back_from_tonight_but_not_season(): void
{
    $this->fakeEngine();
    [$game, $coach, $players] = $this->liveGameWithFullRoster();

    $cold = $players[0];
    foreach ($game->activePlayerIdsForSide(LiveGame::SIDE_HOME) as $playerId) {
        $this->liveStat($game, Player::query()->findOrFail($playerId), [
            'minutes_seconds' => 1080,
            'points' => 10,
        ]);
    }

    foreach ([1, 2, 3] as $sequence) {
        \App\Models\LiveGameEvent::query()->create([
            'live_game_id' => $game->id,
            'sequence' => $sequence,
            'type' => 'shot_missed',
            'team_scope' => 'own',
            'player_id' => $cold->id,
            'period' => 1,
            'clock_seconds_remaining' => 600,
            'payload' => [],
        ]);
    }

    $this->actingAs($coach)->postJson("/live-games/{$game->id}/suggested-lineup");
    $response = $this->actingAs($coach)
        ->postJson("/live-games/{$game->id}/suggested-lineup")
        ->assertOk()
        ->assertJson(['pending' => false]);

    $seasonIds = collect($response->json('suggestion.season.recommended_lineup'))->pluck('player_id');
    $tonightIds = collect($response->json('suggestion.tonight.recommended_lineup'))->pluck('player_id');

    $this->assertTrue($seasonIds->contains($cold->id), 'season may still rank a cold player');
    $this->assertFalse($tonightIds->contains($cold->id), 'tonight should not rank a cold player');
    $response->assertJsonPath("reasons.tonight.{$cold->id}", 'cold_player');
}
```

Add a helper `liveGameWithFullRoster()` if not present (5 active players with stats).

- [ ] **Step 2: Run test — expect FAIL**

Run: `php artisan test --filter=LiveLineupSuggestionTest::test_a_cold_shooter`

- [ ] **Step 3: Update `RecommendLiveLineup`**

```php
use App\Services\LiveGame\EligibilityMode;

// Replace single eligibility call with:
$seasonEligibility = $eligibilityFilter->filter($game, $control->side, $roster, $control->controlledPlayerIds, EligibilityMode::Season);
$tonightEligibility = $eligibilityFilter->filter($game, $control->side, $roster, $control->controlledPlayerIds, EligibilityMode::Tonight);

// Use $seasonEligibility for season shaped(); $tonightEligibility for tonight shaped()
// Store:
$suggestions->store(..., [
    'season' => $this->shaped($season, $seasonEligibility, $roster, $seasonEligibility->slotCount()),
    'tonight' => $this->shaped($tonight, $tonightEligibility, $roster, $tonightEligibility->slotCount()),
    'reasons' => [
        'season' => $seasonEligibility->reasons,
        'tonight' => $tonightEligibility->reasons,
    ],
]);
```

Use `$seasonEligibility` for slot-count short-circuit checks (use the stricter union where both must agree to proceed — if season has slotCount 0, abort; same for tonight rankable empty).

- [ ] **Step 4: Update controller response**

```php
$seasonEligibility = $eligibilityFilter->filter(..., EligibilityMode::Season);
$tonightEligibility = $eligibilityFilter->filter(..., EligibilityMode::Tonight);

return response()->json([
    // ...
    'reasons' => [
        'season' => $seasonEligibility->reasons,
        'tonight' => $tonightEligibility->reasons,
    ],
    'fixed_player_ids' => $seasonEligibility->fixedPlayerIds,
    'slot_count' => $seasonEligibility->slotCount(),
]);
```

- [ ] **Step 5: Update TypeScript types**

In `resources/js/types/index.ts`:

```typescript
export type LiveLineupReason =
  | 'disqualified'
  | 'foul_trouble'
  | 'cold_player'
  | 'inactive'
  | 'assigned_to_assistant';

export interface LiveLineupSuggestionResponse {
  // ...
  reasons: {
    season: Record<string, LiveLineupReason>;
    tonight: Record<string, LiveLineupReason>;
  };
}
```

- [ ] **Step 6: Run tests + typecheck**

Run: `php artisan test --filter=LiveLineupSuggestionTest`  
Run: `npm run typecheck`  
Expected: PASS (TS will fail until Task 4 updates the panel — acceptable mid-plan; complete Task 4 before final green)

- [ ] **Step 7: Commit**

```bash
git add app/Jobs/RecommendLiveLineup.php \
  app/Http/Controllers/LiveGameSuggestionController.php \
  resources/js/types/index.ts \
  tests/Feature/LiveGame/LiveLineupSuggestionTest.php
git commit -m "feat(live-game): per-column eligibility reasons in lineup suggestion API"
```

---

### Task 4: Auto-refresh panel + held-back UI

**Files:**
- Modify: `resources/js/Pages/LiveGames/Show.tsx`
- Modify: `resources/js/Components/features/live-game/SuggestedLineupPanel.tsx`

**Interfaces:**
- Consumes: `eventSequence: number` prop; nested `reasons.season` / `reasons.tonight`.
- Produces: refetch on sequence change; per-column held-back lists.

- [ ] **Step 1: Pass event sequence from Show**

In `Show.tsx`, derive sequence and pass to panel:

```typescript
const eventSequence = useMemo(
    () => snapshot.events.reduce((max, event) => Math.max(max, event.sequence), 0),
    [snapshot.events],
);

// In JSX:
<SuggestedLineupPanel
    eventSequence={eventSequence}
    // ...existing props
/>
```

- [ ] **Step 2: Auto-refetch in panel**

In `SuggestedLineupPanel.tsx`:

```typescript
interface SuggestedLineupPanelProps {
    eventSequence: number;
    // ...
}

const REASON_LABELS: Record<LiveLineupReason, string> = {
    // ...
    cold_player: 'Struggling tonight — held back',
};

// Add debounced refetch:
const debounceRef = useRef<number | null>(null);

useEffect(() => {
    if (disabled) return;

    if (debounceRef.current !== null) {
        window.clearTimeout(debounceRef.current);
    }

    debounceRef.current = window.setTimeout(() => {
        pollsRef.current = 0;
        setData(null);
        void load();
    }, 300);

    return () => {
        if (debounceRef.current !== null) {
            window.clearTimeout(debounceRef.current);
        }
    };
}, [disabled, eventSequence, load]);
```

Remove or narrow the mount-only `useEffect` so it does not double-fetch on first render (keep initial load via the sequence effect only, or gate with a `hasLoadedRef`).

Show "Updating…" near the heading when `loading && data !== null`.

- [ ] **Step 3: Per-column held back**

Replace flat `reasons` usage:

```typescript
const heldSeason = Object.entries(data?.reasons.season ?? {})
    .map(([playerId, reason]) => ({ playerId: Number(playerId), reason }))
    .filter((entry) => !fixedIds.includes(entry.playerId));

const heldTonight = Object.entries(data?.reasons.tonight ?? {})
    .map(([playerId, reason]) => ({ playerId: Number(playerId), reason }))
    .filter((entry) => !fixedIds.includes(entry.playerId));
```

Render two `<details>` blocks: "Held back (season)" and "Held back (tonight)" when non-empty.

- [ ] **Step 4: Run typecheck and manual smoke test**

Run: `npm run typecheck`  
Manual: start live game, record 3 misses for one player, confirm tonight column updates without manual refresh.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/LiveGames/Show.tsx \
  resources/js/Components/features/live-game/SuggestedLineupPanel.tsx
git commit -m "feat(live-game): auto-refresh lineup suggestions on new events"
```

---

### Task 5: Final verification

- [ ] **Step 1: Run full targeted suite**

```bash
php artisan test --filter=LiveLineup
php artisan test --filter=LiveGameMissStreakCalculatorTest
php artisan test --filter=LiveGameAlertServiceTest
npm run typecheck
vendor/bin/pint
```

Expected: all PASS.

- [ ] **Step 2: Update spec cross-link**

Ensure [`2026-08-04-live-lineup-suggestion-design.md`](../specs/2026-08-04-live-lineup-suggestion-design.md) references this follow-up spec in a one-line "See also" if desired (optional).

- [ ] **Step 3: Commit any pint fixes**

```bash
git commit -m "chore: pint formatting for live lineup realtime refresh"
```

---

## Spec coverage self-review

| Spec requirement | Task |
|------------------|------|
| Auto-refresh on event | Task 4 |
| 3-miss demotion tonight only | Tasks 2, 3 |
| 3-foul demotion tonight only (Q1–Q3) | Task 2 |
| Shared miss streak calculator | Task 1 |
| Per-column reasons API | Task 3 |
| Season column unchanged data | No task (by design) |
| No Python changes | No task |
| Success criteria tests | Tasks 2, 3, 5 |

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-07-live-lineup-realtime-refresh.md`. Design spec at `docs/superpowers/specs/2026-08-07-live-lineup-realtime-refresh-design.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks  
2. **Inline Execution** — implement tasks in this session with checkpoints

Which approach?
