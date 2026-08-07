# Live Game: Real-Time Lineup Refresh & Tonight-Only Eligibility — Design

**Date:** 2026-08-07  
**Surface:** `/live-games/{id}` (`resources/js/Pages/LiveGames/Show.tsx`)  
**Builds on:** [`2026-08-04-live-lineup-suggestion-design.md`](2026-08-04-live-lineup-suggestion-design.md)  
**Implementation plan:** [`../plans/2026-08-07-live-lineup-realtime-refresh.md`](../plans/2026-08-07-live-lineup-realtime-refresh.md)

## Problem

The dual-column lineup suggestion (By season / By tonight) shipped in August 2026, but two gaps remain:

1. **Stale UI:** `SuggestedLineupPanel` loads once on mount. The backend cache key already includes event sequence (`live_lineup.{game}.{team}.u{coach}.seq{N}`), but the panel does not refetch when new events arrive. After Reyes misses three straight, the coach still sees the old five until tapping Refresh.

2. **Alerts ≠ suggestions:** `LiveGameAlertService` already emits `substitution_prompt` for 3 straight misses and period-aware foul trouble, but `LiveLineupEligibilityFilter` only demotes at 4 personal fouls and ignores miss streaks entirely. The "By tonight" column does not yet reflect the same live signals the alerts surface.

**Goal:** Make "By tonight" react to the live game as events are recorded—auto-refresh the panel and bench players who alerts already flag—while "By season" stays the historical baseline.

## Confirmed data semantics

| Column | Meaning | Source |
|--------|---------|--------|
| **By season** | Average performance across **all imported past games** (`player_histories` → `player_stats`) | Not the current live game; not one game unless only one is imported |
| **By tonight** | Performance **in this live game so far** (Q1–Q4 events → `live_game_player_stats`) | Grows as the coach records events |

The two columns use the same Python `lineup` ranker; they are never blended.

## Design decisions

### 1. Refresh when the on-court lineup changes (revised 2026-08-07)

**Original:** refetch after every recorded event (`eventSequence` bump).  
**Revised (user):** refetch **only when a substitution changes the on-court five** — not on every event-pad tap (rebounds, assists, shots, etc.).

When `snapshot.active_player_ids` changes (after a substitution is recorded or a suggested five is applied), reset the poll counter and refetch the suggestion.

- Do **not** refetch on shot/foul/rebound/assist/turnover events while the same five stays on the floor.
- Keep the manual **Refresh** button for dead-ball checks between subs.
- Optional future: add a 5-minute interval as a second trigger — **out of scope** unless requested.

**Tradeoff:** Alerts still fire immediately ("Sub out Reyes — 3 straight misses"), but the **By tonight** column will not reorder until the next sub or manual refresh. That reduces noise while logging stats.

### 1a. (supersedes) Auto-refresh after each recorded event

Implemented in `8628241` but superseded by lineup-change trigger above.

### 2. Column-specific eligibility (tonight is stricter)

Extend eligibility so season and tonight use different demotion thresholds:

| Signal | By season | By tonight |
|--------|-----------|------------|
| 5 personal fouls (DQ) | excluded | excluded |
| 4 personal fouls | demoted | demoted |
| 3 personal fouls (Q1–Q3) | rankable | **demoted** |
| 3 personal fouls (Q4) | rankable until 4 | demoted at 4 (same as alerts) |
| 3 straight missed FG (`shot_missed`) | rankable | **demoted** |
| Inactive / assistant delegation | excluded/fixed (both) | excluded/fixed (both) |

**Rationale:** Season column answers "what does history say?"—a 3-foul player is still a valid historical pick. Tonight column answers "who should play *right now*?"—align with existing `substitution_prompt` alerts.

Demoted players remain available as last-resort backfill when not enough healthier bodies exist (same as current 4-foul behavior).

### 3. Shared miss-streak calculator

Extract miss-streak walking logic from `LiveGameAlertService` into `LiveGameMissStreakCalculator`:

- `shot_made` resets streak to 0 for that player
- `shot_missed` increments streak
- Threshold for demotion: **≥ 3** (matches alerts)

Both `LiveGameAlertService` and `LiveLineupEligibilityFilter` consume this helper so alert and suggestion never disagree on streak counts.

### 4. Per-column reasons in the API

Replace flat `reasons: Record<string, reason>` with:

```json
{
  "reasons": {
    "season": { "12": "foul_trouble" },
    "tonight": { "12": "cold_player", "34": "foul_trouble" }
  }
}
```

The panel's "Held back" section groups by column so a player demoted only in tonight does not look excluded from season.

Add `cold_player` to `LiveLineupReason` (TS) and `LiveLineupEligibilityFilter::REASON_COLD_PLAYER` (PHP). UI label: **"Struggling tonight — held back"**.

### 5. What we are NOT changing

- No new Python commands or blended scores
- No auto-apply substitutions
- No change to 240-second minimum sample for tonight ranking (`LiveLineupPayloadBuilder::MIN_LIVE_SECONDS`)
- No blocks/steals in event pad (separate work)
- "By season" label and data model unchanged

## Architecture

```
Event recorded → projection rebuild → snapshot broadcast
  → Show.tsx detects sequence bump
  → SuggestedLineupPanel refetch
  → LiveLineupSuggestionService (cache miss on new seq)
  → RecommendLiveLineup
       → filter(mode: season)  → Python lineup(season payload)
       → filter(mode: tonight) → Python lineup(tonight payload)
  → cache { season, tonight, reasons_season, reasons_tonight }
```

Layering unchanged: Controller → Service → Job → Python. No controller queries.

## Components

### `LiveGameMissStreakCalculator` (new)

Input: `Collection<LiveGameEvent>` (effective, non-voided events).  
Output: `array<int, int>` — `player_id => current_miss_streak`.

### `LiveLineupEligibilityFilter` (modify)

Add parameter `EligibilityMode $mode` (`Season`, `Tonight`):

- **Season:** current behavior (4-foul demotion threshold)
- **Tonight:** period-aware 3-foul demotion (Q1–Q3) + cold streak demotion via calculator

### `RecommendLiveLineup` (modify)

Run eligibility twice; shape each column with its own rankable/demoted pools and store per-column reasons in cache.

### `LiveGameSuggestionController` (modify)

Return both eligibility reason maps in JSON response.

### `SuggestedLineupPanel` + `Show.tsx` (modify)

- New prop: `eventSequence: number`
- `useEffect` on sequence change triggers refetch
- Held-back UI reads `reasons.season` / `reasons.tonight`

## Testing

| Test | Covers |
|------|--------|
| `LiveGameMissStreakCalculatorTest` | streak reset on make, increment on miss, threshold |
| `LiveLineupEligibilityFilterTest` | 3 fouls rankable in season, demoted in tonight; cold demoted tonight only |
| `LiveLineupSuggestionTest` | feature: cold player absent from tonight column, present in season when historically strong |
| Alert service regression | existing cold/foul alert tests still pass after calculator extraction |

## Success criteria

1. Coach records a **substitution** → suggestion panel refetches within ~2s (queue worker running)
2. After 3 straight misses, player absent from **By tonight** ranked five, listed under tonight held back
3. Same player can still appear in **By season** if historically rankable
4. 3 fouls in Q1–Q3 demotes in tonight only; 4 fouls demotes in both
5. Apply either column still requires stopped clock

## User decisions (grilling session)

| Decision | Choice |
|----------|--------|
| Auto-refresh trigger | **On substitution only** (on-court five changes) — revised from per-event |
| Cold shooter (3 misses) | Bench in By tonight only |
| By season data | All imported past games averaged |
| 3-foul demotion | By tonight only; season waits until 4 |
| Next step | Spec + implementation plan before code |
