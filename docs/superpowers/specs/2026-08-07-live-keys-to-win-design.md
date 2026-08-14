# Live Game: Keys to Win — Design

**Date:** 2026-08-07  
**Surface:** `/live-games/{id}` (`resources/js/Pages/LiveGames/Show.tsx`)  
**Status:** Approved in design review (Approach 1 — deterministic PHP Keys engine)

## Problem

Coach alerts already diagnose live problems (hot/cold, fouls, runs, droughts, timeout/sub prompts) but never answer: *what is the opponent’s strength, and how do we counter it?*

Pre-game comparison has win probability and player matchups; the live console does not surface that strategy. Suggested five answers *who to put on the floor*, not *what the defensive keys are*.

**Goal:** On the live Show aside, show a compact **Keys to win** strategy block — season-based opponent strengths with deterministic counter defense and personnel — hybrid-updated when live events confirm or soften those threats, with optional spike alerts into Coach alerts when a key heats up.

## Decisions (locked)

| Decision | Choice |
|----------|--------|
| Strength source | Hybrid: season `player_stats` base + live confirmation |
| Counter shape | Strategy copy **and** personnel recommendation |
| UI placement | Separate Keys panel **above** Coach alerts; spikes may also enter the alerts list |
| Engine | Deterministic PHP (`LiveGameKeysToWinService`) — no new Python command, no LLM |
| Persistence | Keys live on the snapshot; only spikes persist as `live_game_alerts` rows |
| Actions | Read-only v1 (no apply-from-key CTA) |
| Cap | Max **2** keys per team side |

## Panel contracts

Aside order when live: **Keys to win → Coach alerts → Suggested five**.

| Panel | Job | Authoritative for |
|-------|-----|-------------------|
| Keys to win | Opponent strength + counter defense + counter player | Season base + live status |
| Coach alerts | Interrupts (existing rules + `keys_threat_spike`) | Act / look now |
| Suggested five | Who to put on the floor | Apply lineup |

Flex budget (tablet aside): Keys ~0.25 / Alerts ~0.30 / Suggested ~0.45 so Suggested five keeps apply space.

## Architecture

```
LiveGameProjectionService::rebuild
  → replay events / rebuild live stats (existing)
  → LiveGameKeysToWinService::compute (both sides)   // once per rebuild
  → LiveGameAlertService::sync (rules + keys_threat_spike from keys)
  → LiveGameStateBuilder::build (+ keys_to_win from compute result)
  → LiveGameStateUpdated (Echo snapshot)
  → Show.tsx (KeysToWinPanel + AlertsPanel + SuggestedLineupPanel)
```

Keys are computed **once** per rebuild and passed into alert sync (for spikes) and the state builder (for the panel). Do not recompute inside both services. Spike upsert/resolve uses the same active-key pattern as other player alerts (`type:playerId`).

## Engine rules

### Inputs

- Own / opponent team rosters (active players)
- Season `player_stats` (`plus_minus`, `pts`, `reb`, `ast`, `blk`, `stl`, `efg_pct`, `dr`, `pf`, …)
- Live: current period makes, miss streaks / foul trouble / hot_player signals, on-court IDs
- Viewer control is **not** required for broadcast counters; UI marks `actionable` from client `controlled_player_ids`

### Threat score (opponent)

For each active opponent player with season stats:

```
threat_score =
  0.45 * normalize(plus_minus)   // null plus_minus → 0 for ranking only
+ 0.35 * normalize(pts)
+ 0.20 * normalize(reb + ast)
```

Take top **2**. Prefer on-court players when scores are within ~10% of each other.

### Strength tag

Largest edge vs own-team median among: `scorer`, `playmaker`, `boarder`, `rim_protector`, `disruptor` (from pts/efg, ast, reb, blk, stl respectively).

### Counter (own team)

1. Prefer same `Player.role` when available  
2. Else best defensive profile for that tag (`stl` / `blk` / `dr`; prefer lower `pf`)  
3. Exclude disqualified players  
4. Snapshot stores best roster counter; UI sets actionable if counter ∈ viewer’s controlled set

### Defense copy (templates)

Deterministic short strings by tag, e.g.:

| Tag | Defense key |
|-----|-------------|
| scorer | Deny the ball; force contested looks |
| playmaker | Pressure the ball; deny easy entries |
| boarder | Box out; crash the glass |
| rim_protector | Attack early; finish through contact |
| disruptor | Protect the ball; avoid telegraphed passes |

Personnel line: `Put {Counter} on them` (note if counter is on the bench).

### Live status

| Signal | `live_status` |
|--------|----------------|
| Key is hot (≥3 makes this period / hot_player) | `confirmed` |
| Key cold or foul trouble | `fading` |
| No live signal | `season` |

### Spike alert

- Type: `keys_threat_spike`  
- Severity: `warning`  
- When: key transitions to (or remains at threshold for) `confirmed`  
- Message example: `{Name} is heating up — put {Counter} on them.`  
- Resolve when confirmation clears  
- Keys panel remains the strategy home; spike is interrupt-only

## Snapshot shape

```ts
keys_to_win: {
  home: KeysSide;
  opponent: KeysSide;
}

interface KeysSide {
  team_id: number;
  keys: KeyToWin[];
}

interface KeyToWin {
  opponent_player_id: number;
  strength_tag: string;
  threat_score: number;
  live_status: 'season' | 'confirmed' | 'fading';
  defense_key: string;
  counter_player_id: number | null;
  context: Record<string, unknown>; // season stat labels/values, reasons
}
```

UI filters by `viewerSide` (home coach sees `keys_to_win.home` as “keys vs opponent”, etc.).

## UI

**New:** `resources/js/Components/features/live-game/KeysToWinPanel.tsx`

- Heading: “Keys to win”
- Up to two rows: opponent name + tag · defense key · counter · status chip (`Season` / `Live` / `Fading`)
- Reuse live console tokens (`live-badge-*`, card/border chrome). Lucide icon (e.g. `Target`). No emoji.
- Empty: “Not enough season stats to rank opponent strengths.”
- If counter not controlled: muted note “best available — not your slot”
- `aria-labelledby` on section; Keys themselves are not `aria-live` (spikes use existing alert path)

**Modify:**

- `Show.tsx` — mount panel above `AlertsPanel`; pass filtered keys + players  
- `AlertRow.tsx` — `keys_threat_spike` → “Keys spike”  
- `resources/js/types/index.ts` — snapshot + key types  
- `LiveGameStateBuilder` — include `keys_to_win`  
- `LiveGameAlertService` — accept computed keys (or confirmed player ids) and sync `keys_threat_spike` rows

## Testing

**Unit:** `tests/Unit/Services/LiveGame/LiveGameKeysToWinServiceTest.php`

- Top-2 ranking from season weights  
- Strength tag from largest relative edge  
- Counter by role then defensive profile  
- On-court preference when scores close  
- Live status transitions  
- Null `plus_minus` / missing stats → no crash, empty or partial keys  

**Alerts:** extend `LiveGameAlertServiceTest` for create/resolve/dedupe of `keys_threat_spike`

**Snapshot:** feature or builder coverage that `keys_to_win` is present for both sides

**Frontend:** types compile; optional browser smoke for Keys heading on live Show

## Non-goals

- LLM / free-form coaching essays  
- New Python analytics command  
- Apply lineup / pre-select player from a Key row (v1)  
- Blending season and live into one numeric “exchange rate” score for ranking (live only changes status/copy, not season threat rank)

## Critique notes informing this design

From impeccable critique of the live aside: keep Keys compact; keep role collision clear (strategy vs interrupt vs recommendation); do not add a third unbounded list; spike type must be visually distinct from pure rule alerts.
