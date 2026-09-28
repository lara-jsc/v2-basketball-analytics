# Computation Bug Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the five calculation bugs found while documenting `computation.md`, then update the doc so it
describes the fixed behavior.

**Architecture:** Five small, independent fixes, each in the layer that owns it:
- matchup payload (Job)
- BPM missing minutes (Python + Job)
- cache invalidation (moved into `RebuildPlayerStats`, the one place every history write passes through)
- decimal precision (a new migration)
- radar scaling (a frontend component)

A final task updates the docs.

**Tech Stack:** Laravel 13 / Pest (SQLite `:memory:`), Python 3 / pytest, React 18 + TS (no JS unit-test framework;
use `npm run typecheck` plus a visual check).

**Spec:** The bug list in `computation.md` §11 "Known limitations" (items 1, 5, 6, 7, 8), from the
2026-09-23 documentation session. Execution step 0 copies this plan to
`docs/superpowers/plans/2026-09-23-computation-bug-fixes.md`.

**Execution:** Native (superpowers:executing-plans) is recommended. The six tasks are small and share no
interfaces, and each has its own tests, so a per-task subagent review adds cost with little gain. One reviewer
checks the whole change at the end. Say "subagent-driven" at approval to switch.

## Global Constraints

- **No commits.** The user's standing rule is to never commit unless asked, and never add Co-Authored-By.
  Every "commit" step in this skill template is replaced by "leave changes uncommitted".
- Layering: controllers do no queries; Python is called only from Jobs; `Components/ui/` is never modified.
- `plus_minus` null renders "—", never 0.
- Run `vendor/bin/pint` on touched PHP files.
- Tests: `php artisan test` (Pest) and `pytest tests/python`. Both must stay fully green.

## Review Focus

1. **Tiny but non-zero minutes** (for example 0.4 MIN average): BPM still clamps to 1 minute (×36). Only
   missing or zero minutes return null. Pinned by the Task 2 test `test_small_minutes_are_clamped_not_nulled`.
2. **A player whose last history row was deleted** (the rebuild writes zeroed stats) must still invalidate the
   team caches. Pinned by the Task 3 test "invalidates even when the player has no histories left".
3. **Matchup where one player has no stats row**: the advanced stats must go out as 0, not be left out. Pinned
   by the Task 1 test "sends zeros when a player has no stats".
4. **Negative SH-EFF on the radar** (every normal shooter is negative): it must plot inside the chart, not at 0.
   Covered by the Task 5 visual check with a real player.
5. **Existing MySQL rows stored at 2 decimals**: the migration alone doesn't restore precision. They need a
   rebuild. Covered by Task 4 Step 6 (backfill).

---

### Task 0: Save the plan into the repo

- [ ] Copy this file to `docs/superpowers/plans/2026-09-23-computation-bug-fixes.md`.

---

### Task 1: Send EFF / eFG% / TS% to the matchup engine, and retire stale matchup caches

**Problem:** `ComputePlayerMatchup::statPayload` doesn't send `eff`, `efg_pct` or `ts_pct`. Python compares 13
keys, so those 3 are always 0-vs-0 ties and each edge maxes at 10/13. Old results cached under
`matchup.{lo}.{hi}` would keep serving the broken numbers for up to 24h.

**Files:**
- Modify: `app/Jobs/ComputePlayerMatchup.php` (`statPayload`, around lines 67-84)
- Modify: `app/Services/PlayerMatchupService.php` (`cacheKey`, around lines 47-52)
- Create: `tests/Feature/Jobs/ComputePlayerMatchupTest.php`

**Interfaces:** Produces the payload keys `eff`, `efg_pct`, `ts_pct` (floats, null → 0) in `player_a` and
`player_b`, and the cache key `matchup.v2.{lo}.{hi}`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Jobs/ComputePlayerMatchupTest.php`:

```php
<?php

use App\Jobs\ComputePlayerMatchup;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Services\PlayerMatchupService;
use App\Services\PythonEngineService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * Stand in for the Python subprocess and record what the job sends. The edge
 * arithmetic itself is covered by tests/python/test_win_probability.py.
 */
function fakeMatchupEngine(): object
{
    $fake = new class extends PythonEngineService
    {
        /** @var list<array<string, mixed>> */
        public array $payloads = [];

        public function call(string $command, array $payload): array
        {
            $this->payloads[] = $payload;

            return [
                'player_a_edge_score' => 0.5,
                'player_b_edge_score' => 0.5,
                'stronger_stats_a' => [],
                'stronger_stats_b' => [],
            ];
        }
    };

    app()->instance(PythonEngineService::class, $fake);

    return $fake;
}

it('sends the advanced stats the engine compares', function () {
    $engine = fakeMatchupEngine();
    $a = Player::factory()->create();
    $b = Player::factory()->create();
    PlayerStat::factory()->for($a)->create(['eff' => 17.1, 'efg_pct' => 0.5, 'ts_pct' => 0.55]);
    PlayerStat::factory()->for($b)->create(['eff' => 16.0, 'efg_pct' => 0.51, 'ts_pct' => 0.53]);

    app()->call([new ComputePlayerMatchup($a->id, $b->id), 'handle']);

    expect($engine->payloads[0]['player_a'])->toMatchArray(['eff' => 17.1, 'efg_pct' => 0.5, 'ts_pct' => 0.55])
        ->and($engine->payloads[0]['player_b'])->toMatchArray(['eff' => 16.0, 'efg_pct' => 0.51, 'ts_pct' => 0.53]);
});

it('sends zeros when a player has no stats', function () {
    $engine = fakeMatchupEngine();
    $a = Player::factory()->create();
    $b = Player::factory()->create();

    app()->call([new ComputePlayerMatchup($a->id, $b->id), 'handle']);

    expect($engine->payloads[0]['player_a'])->toMatchArray(['eff' => 0, 'efg_pct' => 0, 'ts_pct' => 0]);
});

it('ignores matchup results cached before advanced stats were sent', function () {
    Bus::fake();
    Cache::put('matchup.1.2', ['player_a_edge_score' => 0.7692], now()->addHour());

    expect(app(PlayerMatchupService::class)->getOrDispatch(1, 2))->toBeNull();
    Bus::assertDispatched(ComputePlayerMatchup::class);
});
```

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `php artisan test tests/Feature/Jobs/ComputePlayerMatchupTest.php`

Expected: the first two FAIL (missing keys `eff` / `efg_pct` / `ts_pct`). The third FAILS because the legacy key is still served.

- [ ] **Step 3: Implement**

In `ComputePlayerMatchup::statPayload`, add after `'min' => $s?->min ?? 0,`:

```php
            'eff' => $s?->eff ?? 0,
            'efg_pct' => $s?->efg_pct ?? 0,
            'ts_pct' => $s?->ts_pct ?? 0,
```

In `PlayerMatchupService::cacheKey`:

```php
        // v2: results cached before eff/efg_pct/ts_pct were sent scored those stats as ties.
        return "matchup.v2.{$lo}.{$hi}";
```

- [ ] **Step 4: Run the tests and confirm they pass**

Run: `php artisan test tests/Feature/Jobs/ComputePlayerMatchupTest.php`. Expected: 3 passed.

- [ ] **Step 5: Leave uncommitted.**

---

### Task 2: Plus-minus is "—" when minutes are missing, not ×36

**Problem:** `compute_bpm` clamps missing or zero minutes to 1.0, which multiplies the score by 36.

**Decision:** missing or zero minutes → `plus_minus: None` (the UI renders "—"). Positive minutes below 1 are
still clamped to 1.0, as before.

**Files:**
- Modify: `analytics/plus_minus/calculator.py` (`compute_bpm`, around lines 48-84)
- Modify: `tests/python/test_bpm_calculator.py` (replace `test_fewer_minutes_does_not_divide_by_near_zero` and
  `test_missing_stats_default_to_zero`)
- Modify: `app/Jobs/ComputePlayerPlusMinus.php` (result handling, around lines 69-77)
- Create: `tests/Feature/Jobs/ComputePlayerPlusMinusTest.php`

**Interfaces:** The `bpm` command now returns `{"player_id": int, "plus_minus": float | null}`.

- [ ] **Step 1: Write the failing Python tests**

In `tests/python/test_bpm_calculator.py`, **replace** `test_fewer_minutes_does_not_divide_by_near_zero` and
`test_missing_stats_default_to_zero` with:

```python
    def test_zero_minutes_returns_none(self):
        # No minutes → a per-36 rate is meaningless; report "not available"
        result = compute_bpm(_make_payload(pts=10, min=0))
        assert result["plus_minus"] is None

    def test_missing_minutes_returns_none(self):
        result = compute_bpm({"player_id": 1, "stats": {"pts": 10}})
        assert result["plus_minus"] is None

    def test_null_minutes_returns_none(self):
        result = compute_bpm(_make_payload(pts=10, min=None))
        assert result["plus_minus"] is None

    def test_small_minutes_are_clamped_not_nulled(self):
        # 0.5 min is real playing time: clamped to 1.0, same as min=1.0
        tiny = compute_bpm(_make_payload(pts=2, min=0.5))
        one = compute_bpm(_make_payload(pts=2, min=1.0))
        assert tiny["plus_minus"] == one["plus_minus"]

    def test_missing_counting_stats_default_to_zero(self):
        # Only minutes present — other stats default to 0, should not raise
        result = compute_bpm({"player_id": 1, "stats": {"min": 36}})
        assert result["plus_minus"] == pytest.approx(-5.0)
```

- [ ] **Step 2: Run them and confirm they fail**

Run: `pytest tests/python/test_bpm_calculator.py -v`

Expected: the three `*_returns_none` tests FAIL (they get a float). The others pass.

- [ ] **Step 3: Implement in Python**

In `compute_bpm`, replace the line `minutes: float = max(safe_float(stats.get("min", 0)), _MIN_THRESHOLD)` with:

```python
    raw_minutes: float = safe_float(stats.get("min", 0))
    if raw_minutes <= 0:
        # No minutes recorded — scaling to 36 minutes would inflate the score 36×.
        return {"player_id": player_id, "plus_minus": None}
    minutes: float = max(raw_minutes, _MIN_THRESHOLD)
```

Update the docstring's return line to `{ "player_id": int, "plus_minus": float | None }`.

- [ ] **Step 4: Run and confirm pass**

Run: `pytest tests/python -v`. Expected: all pass.

- [ ] **Step 5: Write the failing PHP job test**

`tests/Feature/Jobs/ComputePlayerPlusMinusTest.php`:

```php
<?php

use App\Jobs\ComputePlayerPlusMinus;
use App\Models\PlayerStat;
use App\Services\PythonEngineService;

function fakeBpmEngine(?float $plusMinus): void
{
    app()->instance(PythonEngineService::class, new class($plusMinus) extends PythonEngineService
    {
        public function __construct(private readonly ?float $plusMinus) {}

        public function call(string $command, array $payload): array
        {
            return ['player_id' => $payload['player_id'], 'plus_minus' => $this->plusMinus];
        }
    });
}

it('stores the computed plus-minus', function () {
    fakeBpmEngine(3.04);
    $stat = PlayerStat::factory()->create(['plus_minus' => null]);

    app()->call([new ComputePlayerPlusMinus($stat->id), 'handle']);

    expect($stat->fresh()->plus_minus)->toBe(3.04);
});

it('stores null when the engine reports no plus-minus (no minutes)', function () {
    fakeBpmEngine(null);
    $stat = PlayerStat::factory()->create(['plus_minus' => 12.5]);

    app()->call([new ComputePlayerPlusMinus($stat->id), 'handle']);

    expect($stat->fresh()->plus_minus)->toBeNull();
});
```

If `PythonEngineService` has constructor dependencies, call `parent::__construct(...)` the same way as the
existing fake in `tests/Feature/LiveGame/LiveLineupSuggestionTest.php:542`. That fake takes no arguments.

- [ ] **Step 6: Run and confirm the null case fails**

Run: `php artisan test tests/Feature/Jobs/ComputePlayerPlusMinusTest.php`

Expected: "stores null…" FAILS because the value stays 12.5 (`isset` treats null as missing and returns early).

- [ ] **Step 7: Implement in the job**

In `ComputePlayerPlusMinus::handle`, replace the `isset` check and the update with:

```php
        if (! array_key_exists('plus_minus', $result)) {
            Log::error('ComputePlayerPlusMinus: missing plus_minus in engine response', [
                'playerStatId' => $this->playerStatId,
                'response' => $result,
            ]);

            return;
        }

        // Null means the player has no minutes recorded — UI renders "—".
        $plusMinus = $result['plus_minus'];
        $stat->update(['plus_minus' => $plusMinus === null ? null : (float) $plusMinus]);
```

Update the class docblock output line to `"plus_minus": float|null`.

- [ ] **Step 8: Run and confirm pass**

Run: `php artisan test tests/Feature/Jobs/ComputePlayerPlusMinusTest.php`. Expected: 2 passed.

- [ ] **Step 9: Leave uncommitted.**

---

### Task 3: Invalidate win-probability and lineup caches whenever season stats are rebuilt

**Problem:** Only `PlayerHistoryService` (manual CRUD) bumps the team cache version.
`PlayerHistoryImportJob` and `LiveGameFinalizer` don't, so their stale results live for up to 24h.

**Fix:** bump the version inside `RebuildPlayerStats` after the stats row is saved.
- Every write path already dispatches this job, so this covers them all.
- Doing it after the save (not before) means a win-probability job can't re-cache old stats under the new version.
- `WinProbabilityService` and `LineupService` share the `team.{id}.cache_version` key, so one bump clears both.
- The existing calls in `PlayerHistoryService` and `CsvController` stay. An extra bump is harmless.

**Files:**
- Modify: `app/Jobs/RebuildPlayerStats.php`
- Create: `tests/Feature/Jobs/RebuildPlayerStatsTest.php`

**Interfaces:** Consumes `WinProbabilityService::invalidateForTeam(int $teamId): void`
(`app/Services/WinProbabilityService.php:51`).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Jobs/RebuildPlayerStatsTest.php`:

```php
<?php

use App\Jobs\RebuildPlayerStats;
use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\Team;
use App\Services\LineupService;
use App\Services\WinProbabilityService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Cache::flush();
    Queue::fake(); // keep ComputePlayerPlusMinus (Python) from running
});

function cacheTeamResults(int $teamId, int $opponentId): void
{
    app(WinProbabilityService::class)->store($teamId, $opponentId, ['team_a_win_probability' => 0.6]);
    app(LineupService::class)->store($teamId, $opponentId, ['recommended_lineup' => [], 'confidence' => 0.0]);
}

it('clears cached win probability and lineup for the player team', function () {
    $team = Team::factory()->create();
    $opponent = Team::factory()->create();
    $player = Player::factory()->for($team)->create();
    PlayerHistory::factory()->for($player)->create([
        'playing_team_id' => $team->id,
        'opponent_team_id' => $opponent->id,
    ]);
    cacheTeamResults($team->id, $opponent->id);

    app()->call([new RebuildPlayerStats($player->id), 'handle']);

    expect(app(WinProbabilityService::class)->getOrDispatch($team->id, $opponent->id))->toBeNull()
        ->and(app(LineupService::class)->getOrDispatch($team->id, $opponent->id))->toBeNull();
});

it('invalidates even when the player has no histories left', function () {
    $team = Team::factory()->create();
    $opponent = Team::factory()->create();
    $player = Player::factory()->for($team)->create();
    cacheTeamResults($team->id, $opponent->id);

    app()->call([new RebuildPlayerStats($player->id), 'handle']);

    expect(app(WinProbabilityService::class)->getOrDispatch($team->id, $opponent->id))->toBeNull();
});

it('leaves unrelated teams cached', function () {
    $team = Team::factory()->create();
    [$otherA, $otherB] = Team::factory()->count(2)->create();
    $player = Player::factory()->for($team)->create();
    cacheTeamResults($otherA->id, $otherB->id);

    app()->call([new RebuildPlayerStats($player->id), 'handle']);

    expect(app(WinProbabilityService::class)->getOrDispatch($otherA->id, $otherB->id))->not->toBeNull();
});
```

- [ ] **Step 2: Run and confirm the first two fail**

Run: `php artisan test tests/Feature/Jobs/RebuildPlayerStatsTest.php`

Expected: the first two FAIL (the cached value is returned). The third passes.

- [ ] **Step 3: Implement**

In `RebuildPlayerStats`:
- add `use App\Models\Player;` and `use App\Services\WinProbabilityService;`
- add the `WinProbabilityService $winProbabilityService` parameter to `handle()`
- after `ComputePlayerPlusMinus::dispatch($stat->id);`, add:

```php
        // Season averages feed win probability and lineups. Bumping the team version here,
        // after the save, covers every history write path: CRUD, file import, live finalize.
        $teamId = Player::query()->whereKey($this->playerId)->value('team_id');
        if ($teamId !== null) {
            $winProbabilityService->invalidateForTeam((int) $teamId);
        }
```

Add step 5 to the class docblock flow: "Invalidate the team's win-probability/lineup cache version."

- [ ] **Step 4: Run and confirm pass, then run the suites that dispatch this job**

Run: `php artisan test tests/Feature/Jobs tests/Feature/LiveGame/LiveGameFinalizationTest.php tests/Feature/PlayerHistoryControllerTest.php`

Expected: all pass.

- [ ] **Step 5: Leave uncommitted.**

---

### Task 4: Store shooting fractions with 4 decimals

**Problem:**
- `fg_pct`, `ft_pct`, `three_p_pct`, `sh_eff`, `efg_pct` and `ts_pct` are `decimal(5,2)`. On MySQL,
  0.4615 is saved as 0.46.
- SQLite ignores decimal precision, so the Pest suite can't catch this. It has to be checked on MySQL (the local
  `.env` uses `DB_CONNECTION=mysql`).
- `sc_eff` stays as it is, since the code already rounds it to 2 decimals.

**Files:**
- Create: `database/migrations/2026_09_23_000001_widen_player_stats_fraction_precision.php`
- Create: `tests/Feature/PlayerStatsPrecisionTest.php`

- [ ] **Step 1: Write the regression test**

It passes on SQLite before and after the fix. It is the pin for MySQL runs.

```php
<?php

use App\Models\PlayerStat;

it('keeps four decimals on shooting fractions', function () {
    $values = [
        'fg_pct' => 0.4615, 'ft_pct' => 0.8333, 'three_p_pct' => 0.3571,
        'sh_eff' => -0.4277, 'efg_pct' => 0.5385, 'ts_pct' => 0.5403,
    ];

    $stat = PlayerStat::factory()->create($values);

    expect($stat->fresh()->only(array_keys($values)))->toBe($values);
});
```

- [ ] **Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 0–1 fractions are rounded to 4 decimals in PlayerStatsAggregator; decimal(5,2) cut them to 2. */
    private const COLUMNS = ['fg_pct', 'ft_pct', 'three_p_pct', 'sh_eff', 'efg_pct', 'ts_pct'];

    public function up(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->decimal($column, 6, 4)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->decimal($column, 5, 2)->nullable()->change();
            }
        });
    }
};
```

- [ ] **Step 3: Run the Pest suite**

Run: `php artisan test`. Expected: all pass, and the migration runs cleanly on SQLite.

- [ ] **Step 4: Checkpoint — ask the user before touching their MySQL database.**
  - First run the pre-check. decimal(6,4) holds at most 99.9999, so a hand-entered percent-scale value such as
    100.00 would make the ALTER fail. Expected: `0`.
    `php artisan tinker --execute="echo App\Models\PlayerStat::query()->where(fn(\$q)=>collect(['fg_pct','ft_pct','three_p_pct','sh_eff','efg_pct','ts_pct'])->each(fn(\$c)=>\$q->orWhereRaw(\"ABS(\$c) >= 100\")))->count();"`
  - If the count is non-zero, stop and show the user those rows.
  - Otherwise run `php artisan migrate`.

- [ ] **Step 5: Verify the column types on MySQL**

Run: `php artisan tinker --execute="collect(Schema::getColumns('player_stats'))->whereIn('name',['fg_pct','ft_pct','three_p_pct','sh_eff','efg_pct','ts_pct'])->each(fn(\$c)=>print(\$c['name'].' '.\$c['type'].PHP_EOL));"`

Expected: each prints `decimal(6,4)`.

- [ ] **Step 6: Backfill existing rows**

With `composer dev` or a queue worker running:

Only players **with game histories**. Rebuilding a player who has none would overwrite their hand-entered stats
with zeros.

`php artisan tinker --execute="App\Models\PlayerHistory::query()->distinct()->pluck('player_id')->each(fn(\$id)=>App\Jobs\RebuildPlayerStats::dispatch(\$id));"`

Then spot-check that a player's `fg_pct` now has 4 decimals.

- [ ] **Step 7: Leave uncommitted.** Mention that production (Railway) needs the same migrate plus backfill.

---

### Task 5: Scale the SH-EFF and SC-EFF radar axes to their real ranges

**Problem:** Both use `max: 100`, but SH-EFF runs from about −1 to 0.5 and SC-EFF from about 0 to 2, so both
plot at roughly 0.

**Files:**
- Modify: `resources/js/Components/features/comparison/PlayerMatchupTable.tsx` (`RADAR_KEYS`, `normalize`, and the
  `radarData` map around lines 54-87)

- [ ] **Step 1: Implement**

Replace `RADAR_KEYS` and `normalize`:

```ts
const RADAR_KEYS: Array<{ label: string; statKey: keyof PlayerStat; min: number; max: number }> = [
    // SH-EFF is negative for most shooters (−1 = never scores), so its scale starts below zero.
    { label: 'SH-EFF', statKey: 'sh_eff',   min: -1, max: 0.5 },
    { label: 'AST',    statKey: 'ast',       min: 0,  max: 15  },
    { label: 'DR',     statKey: 'dr',        min: 0,  max: 15  },
    { label: 'DD2',    statKey: 'dd2',       min: 0,  max: 82  },
    // SC-EFF is points per field-goal attempt; 2.0 is already an extreme season.
    { label: 'SC-EFF', statKey: 'sc_eff',    min: 0,  max: 2   },
    { label: 'PTS',    statKey: 'pts',       min: 0,  max: 40  },
];

function normalize(value: number | null | undefined, min: number, max: number): number {
    if (value === null || value === undefined) return 0;
    const scaled = Math.round(((value - min) / (max - min)) * 100);
    return Math.min(Math.max(scaled, 0), 100);
}
```

And in the `radarData` map:

```ts
    const radarData = RADAR_KEYS.map(({ label, statKey, min, max }) => ({
        stat: label,
        A: normalize(statA?.[statKey] as number | null, min, max),
        B: normalize(statB?.[statKey] as number | null, min, max),
    }));
```

- [ ] **Step 2: Typecheck and build**

Run: `npm run typecheck && npm run build`. Expected: no errors.

- [ ] **Step 3: Visual check**

With `composer dev` running:
- Open Team Comparison and pick two players.
- The SH-EFF and SC-EFF points should sit mid-chart, not at the centre. A −0.43 SH-EFF should plot at about 38.

- [ ] **Step 4: Leave uncommitted.**

---

### Task 6: Update the docs to match

**Files:**
- Modify: `computation.md`
- Modify: `CLAUDE.md` (the "Any new write path…" bullet)

- [ ] **Step 1: Edit `computation.md`**

- §3.1 "Good to know": add "Shows '—' if the player has no minutes recorded."
- §6.1: delete the "See Known limitations…" bullet.
- §6.2: change the ceilings to ranges ("SH-EFF: −1 to 0.5, SC-EFF: 0 to 2, others start at 0"), change the
  formula to `(stat − low end) ÷ (high end − low end) × 100`, and keep the 20 PTS → 50 example.
- §10 table: for the win probability and lineup rows, "cleared early" becomes "cleared whenever any of the
  team's players' season stats are recalculated".
- §11: remove items 1 (matchup), 5 (MySQL precision), 6 (stale caches), 7 (plus-minus ×36) and 8 (radar).
  Keep and renumber shot-probability, seeded zones, live steals/blocks, and the scorer tag.
- §12: update the radar line to the range formula.

- [ ] **Step 2: Edit `CLAUDE.md`**

The bullet becomes: "Any new write path into `player_histories` must dispatch `RebuildPlayerStats`. That job
also invalidates the team's win-probability/lineup cache (`WinProbabilityService::invalidateForTeam`)."

- [ ] **Step 3: Leave uncommitted.**

---

## Final verification

- `vendor/bin/pint` on the touched PHP files
- `php artisan test`: all green
- `pytest tests/python`: all green
- `npm run typecheck`: clean
- `git status`: only the files listed above changed, and nothing is committed
- End-to-end on MySQL: open Team Comparison for two players and confirm:
  - the matchup edge can exceed 77%
  - percentages show real decimals (e.g. 46.2%)
  - the radar's SH-EFF and SC-EFF axes are populated
