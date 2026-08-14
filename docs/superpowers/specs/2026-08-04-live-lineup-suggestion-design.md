# Live Game: In-Game Lineup Suggestion — Season vs Tonight, Side by Side — Design

**Date:** 2026-08-04
**Surface:** `/live-games/{id}` (`resources/js/Pages/LiveGames/Show.tsx`)
**Implementation plan:** approved plan file (`~/.claude/plans/fizzy-snuggling-gem.md`)

## Problem

A coach watches a player fall apart mid-game — picking up fouls, missing everything, turning it over.
`LiveGameAlertService` already notices. It emits `substitution_prompt` alerts reading
*"Sub out Reyes — 4 personal fouls"* and *"Sub out Cruz — 3 straight misses."*

The alert names the problem and stops there. The coach still has to work out the answer from memory,
mid-game, under a clock. Meanwhile the system holds two bodies of evidence it never shows together:

| Evidence | Where it lives | Who reads it today |
|---|---|---|
| Season history | `player_stats`, aggregated from CSV-imported `player_histories` | Python `lineup` command, pre-game comparison page only |
| Tonight | `live_game_player_stats`, rebuilt from the event stream by `LiveGameProjectionService` | `ActiveLineup`, `AlertsPanel` — never the recommender |

The two pipelines are entirely disjoint. No live route, job, or cache key feeds live-game state into
`RecommendLineup`, and `LiveGames/Show` receives no `lineup` prop at all.

**Goal:** at a dead ball, one tap shows two five-man lineups side by side — *what the season says*
next to *what tonight says* — and the coach picks one. The system does not blend them and does not
decide for the coach.

## Design decisions

### 1. Live data gates. Season data ranks. The two are never added together.

An 8-minute on/off margin and a 20-game fitted rating are not the same unit. Blending them requires
defending an exchange rate that nothing in the data justifies. So the two roles are split:

- **Live data decides eligibility** (legality): 5+ personal fouls → excluded; inactive → excluded.
- **Season data decides rank** among the eligible, via the existing Python `lineup` command, unchanged.
- **Tonight gets its own column**, ranked by the *same formula fed different data*.

Defence: one documented ranking function; the columns differ only in which sample feeds it.

### 1a. The suggestion only fills the slots this coach controls.

The on-court five is shared between a bench's two coaches, but each may only substitute their own
players. A suggestion that proposes moving the assistant's players is one the coach cannot act on, so
an uncontrolled player is never a candidate:

| Player | Treatment |
|---|---|
| On court, controlled | A **slot** — open to replacement |
| On court, **not** controlled | **Fixed** — shown dimmed, holds a place in the five, never a candidate |
| Bench, controlled, eligible | A **candidate** |
| Bench, **not** controlled | **Excluded** — irrelevant to this coach |

`slot_count = |on-court ∩ controlled|`, and the ranker fills exactly that many places. With no
assistant assigned the coach controls all five, so `slot_count` is 5 and nothing changes.

Disqualification outranks fixed status: a fouled-out player cannot hold a place in the five,
whoever they are assigned to.

**This makes the suggestion per-coach, so the cache key is too.**
`analytics/lineup_optimizer/ranker.py` hardcodes `_LINEUP_SIZE = 5` and returns `ranked[:5]`, so a
bench-wide ranking cannot be sliced per coach after the fact — the coach's best available player
might rank sixth and never appear. The payload is therefore pre-scoped to the controlled pool, and
the key carries the coach:

```
live_lineup.{liveGameId}.{teamId}.u{coachUserId}.seq{maxEventSequence}
```

At most two coaches per bench, so this at most doubles the Python calls inside an already-queued job.

### 2. Same formula, two data sources — no new Python code.

`analytics/lineup_optimizer/ranker.py` weights `pts .30, ast .15, reb .15, blk .10, stl .10,
fg_pct .10, −to_per_game .20` plus a small opponent adjustment. Call it twice per request:

| Column | `home_team_players` / `opponent_team_players` fed from |
|---|---|
| BY SEASON | `player_stats` season averages, via existing `ComparisonAggregatorService::toEnginePayload` |
| BY TONIGHT | `live_game_player_stats`, normalised to per-36-minute rates |

`SUPPORTED_COMMANDS` is untouched. No new analytics module. Nothing under `analytics/` changes.

### 3. The two columns show different *kinds* of information, deliberately.

If both columns print a `+/−`-looking number, the eye subtracts them — re-creating the blend in the
coach's head without the statistics behind it. So:

- **Season column** → the ranker's `plus_minus_score`, labelled **SCORE**. Not "+/−", which it isn't.
- **Tonight column** → tonight's raw box line: `18p · 0to · 2pf`. Facts, not a score.

Nothing to subtract.

### 4. It's a Sheet, not a panel.

`LiveGameEventRules::CLOCK_REQUIREMENTS` maps `substitution` → **stopped**. The coach can only act on
this at a dead ball, so it never needs to be glanceable mid-play.

`Show.tsx` already stacks `GameScoreboard`, `ActiveLineup`, `EventPad` (14 buttons),
`BenchSubstitution`, `AlertsPanel`, and `Timeline`. At the primary 768–1024px breakpoint that is full.
A permanent two-column section would cost ~10 rows and compete with the pad the coach touches every
possession.

Instead: one `★ Suggest 5` trigger beside the existing `⇄ Sub` button, opening the same
`<Sheet side="left">` pattern `BenchSubstitution.tsx:35` already uses. Zero persistent surface added,
and the interaction is already learned.

### 5. Tonight's column needs a minimum sample, and will be near-empty in Q1. That is correct.

Per-36 rates extrapolated from 3 minutes are absurd — 4 points in 3 minutes projects to 48 per 36.
Players under **240 seconds** played tonight are not ranked; they fall into a trailing
*"no live sample yet"* group.

Early in Q1 the TONIGHT column may hold only the starters, or nothing at all. The empty state says so
plainly. Tonight's data genuinely has no opinion yet, and the interface should not invent one.

## Architecture

Follows the layering in `CLAUDE.md`: controller renders, service decides, job calls Python.

```
Coach taps ★ Suggest 5  (clock stopped, status = live)
        ↓
POST live-games.suggested-lineup  →  LiveGameSuggestionController::store
        ↓  LiveGame::sideFor(user) resolves the side
        ↓  LiveLineupEligibilityFilter        ← live data gates
        ↓  LiveLineupPayloadBuilder           ← two payloads
        ↓  LiveLineupSuggestionService::getOrDispatch
        ↓      cache hit  → JSON { suggestion, pending: false }
        ↓      cache miss → dispatch RecommendLiveLineup, JSON { null, pending: true }
        ↓
RecommendLiveLineup (queued)
        ↓  PythonEngineService::call('lineup', seasonPayload)
        ↓  PythonEngineService::call('lineup', tonightPayload)
        ↓  LiveLineupSuggestionService::store
        ↓
Coach taps USE THIS → ApplyLineupConfirmModal → POST live-games.lineup.apply
        ↓  LiveGameLineupApplier: diff vs on-court, one transaction,
           LiveGameEventRecorder::record per OUT→IN pair
```

**Cache key** includes the game's current event sequence:

```
live_lineup.{liveGameId}.{teamId}.seq{maxEventSequence}
```

Sequence-keyed means **no invalidation logic is needed** — the next recorded event naturally produces
a new key, so the suggestion can never be stale relative to the event stream. Short TTL (15 min),
following `LineupService`'s orphan-and-TTL approach rather than explicit forgets.

## Components

### `LiveLineupEligibilityFilter` (new)

Given a `LiveGame`, a side, and `controlled_player_ids`, partitions the roster and returns a reason
for every player who is not a plain candidate. Checked in this order — the first match wins:

| Condition | Result | Reason code |
|---|---|---|
| `personal_fouls >= LiveGameEventRules::MAX_PERSONAL_FOULS` | excluded | `disqualified` |
| `Player::is_active === false` | excluded | `inactive` |
| not in `controlled_player_ids`, on court | fixed (holds a slot) | `assigned_to_assistant` |
| not in `controlled_player_ids`, on bench | excluded | `assigned_to_assistant` |
| `personal_fouls >= 4` | candidate, demoted to backfill | `foul_trouble` |
| otherwise | rankable candidate | — |

`slotCount()` is `LiveGameEventRules::LINEUP_SIZE` minus the fixed players.

Reasons surface in the UI — this mirrors the disabled-with-reason pattern `padEventBlockReason`
already uses in `EventPad`. Fixed players get their own dimmed rows in each column rather than an
entry in the "Held back" list, so the same fact is not stated twice.

### `LiveLineupPayloadBuilder` (new)

Emits the exact per-player key shape `ComparisonAggregatorService::toEnginePayload` produces
(`player_id, name, pts, reb, ast, blk, stl, fg_pct, to_per_game, min, plus_minus`).

- **Season:** delegate to `ComparisonAggregatorService::toEnginePayload`. Reuse, don't reimplement.
- **Tonight:** from `live_game_player_stats`, `rate = total / (minutes_seconds / 60) * 36`;
  `fg_pct` from made/attempted with a zero-attempt guard; skip anyone under 240 seconds.

### `RecommendLiveLineup` (new job)

Modelled on `app/Jobs/RecommendLineup.php`. Two `PythonEngineService::call('lineup', …)` invocations,
stored via the suggestion service. Engine `RuntimeException` is logged and swallowed exactly as
`RecommendLineup` does, so a Python failure leaves the cache empty rather than breaking the console.

### `LiveGameLineupApplier` (new)

Applying a five is up to five substitutions. Compute the diff (out = on-court but not in the chosen
five; in = chosen five but not on-court), then inside one `DB::transaction` call
`LiveGameEventRecorder::record()` per pair so every existing validation still runs. Reject the whole
apply if any pair is invalid — a half-applied five is worse than none.

**Known tradeoff:** the recorder rebuilds the projection and broadcasts per call, so a 3-for-3 sub
does three rebuilds. Correct but wasteful; acceptable for MVP rather than bypassing the recorder and
duplicating its validation.

### Frontend

`SuggestedLineupSheet.tsx` (new) — `<Sheet side="left">`, two columns, reusing the console's existing
class vocabulary: `rounded-lg border border-border bg-card`, headings
`text-sm font-bold uppercase tracking-[0.1em]` with a lucide icon in `text-amber-300`, jersey numbers
`font-mono text-amber-200`, rows `min-h-12`, focus rings `focus-visible:ring-cyan-300`.

- Fixed players render first as dimmed rows labelled `Fixed · assistant's`, with no metric — they
  hold a place in the five but are not the coach's to move.
- `✓` marks players present in **both** columns, with a text legend — never colour alone.
- Footer states how many of the five are the coach's to change.
- Excluded players listed below with their reason string.
- Pending state while the job runs, matching `AlertsPanel`'s muted-text empty state.
- Each column's `USE THIS` is disabled only while the clock runs, or when the recommended five is
  already on the floor. It is **not** gated on fixed players: every candidate is one the coach
  controls, so a fixed player staying on court can never make the change illegal.
- `slot_count === 0` (the assistant holds all five on court) shows a plain explanatory state rather
  than empty columns.

`ApplyLineupConfirmModal.tsx` (new) — lists the exact OUT → IN pairs before committing, mirroring
`VoidEventConfirmModal` / `ClockActionConfirmModal` / `LineupConfirmModal`. The console already
confirms every multi-effect action, and this is the largest one.

`BenchSubstitution.tsx` gains the `★ Suggest 5` trigger. `Show.tsx` gains `requestSuggestion()` and
`applyLineup()` alongside the existing `record()` / `substitute()` / `postLineup()` handlers.
`types/index.ts` gains `LiveLineupSuggestion` near the existing `LineupRecommendation`.

## Testing

| Test | Covers |
|---|---|
| `tests/Unit/Services/LiveGame/LiveLineupEligibilityFilterTest.php` | DQ at 5 fouls, demotion at 4, inactive exclusion, delegation lock |
| `tests/Unit/Services/LiveGame/LiveLineupPayloadBuilderTest.php` | per-36 arithmetic, the 240-second floor, zero-attempt `fg_pct`, zero-minutes divide |
| `tests/Feature/LiveGame/LiveLineupSuggestionTest.php` | policy gate, clock-running blocks apply, pending → cached transition, correct substitution events, partial-invalid apply rolls back |

No Python test changes — the `lineup` command is unmodified, so existing `tests/python/` coverage
still applies unchanged.

## Known constraint (recorded, not fixed here)

Finalization writes **one `player_histories` row per participating player per team** — a 10-v-10 game
produces 20 rows, not one. That row is keyed `(player_id, game_date, opponent_team_id)` by the
`uq_player_game_opponent` index and by `PlayerHistoryRepository::upsert()`. **`live_game_id` is not
part of the key.**

Consequently two live games between the same two teams on the same date cannot both survive:

1. The unique index allows only one row per player per opponent per date, so the second game's box
   score *overwrites* the first's rather than adding to it.
2. `LiveGameFinalizer.php:72-81` deletes every row for that (playing team, opponent, date) whose notes
   match `/^Finalized from live game #\d+$/` — a regex matching *any* game id, and a delete that is
   not scoped to the second game's participants.

Same-day rematches and doubleheaders therefore lose the earlier game, and the season aggregate
`RebuildPlayerStats` derives ends up short a game. Re-finalizing the *same* game remains correctly
idempotent, and manual or CSV rows for the same matchup are protected by the validation error at
`LiveGameFinalizer.php:66`.

Out of scope here — it needs its own migration, a `live_game_id`-scoped replace, and backfill
thinking. Recorded so the limitation is on the record for the thesis write-up.

## Out of scope

- The doubleheader constraint above — separate work, separate migration.
- No blended score, and no stored recommendation table — cache only, like `LineupService`.
- No change to `analytics/` at all.
- No opponent-side suggestions beyond what per-side resolution already gives.
- The dead `analytics/app/` FastAPI scaffolding stays untouched.
