# Lineup Suggestion: Refresh on Substitution Only — Follow-up Plan

> **For agentic workers:** Small follow-up to `2026-08-07-live-lineup-realtime-refresh.md` Task 4.

**Goal:** Stop refetching the suggested five on every event-pad tap; refetch only when the on-court lineup changes.

**Architecture:** Replace `eventSequence` dependency in `SuggestedLineupPanel` with a stable key derived from sorted `activePlayerIds`. Backend cache still uses event sequence — a sub bumps sequence anyway, so cache invalidation stays correct.

## Global Constraints

- Keep tonight demotion rules (3 misses, 3 fouls Q1–Q3) unchanged.
- Keep manual Refresh button.
- No co-author trailers in commits.

---

### Task 1: Switch refresh trigger to lineup change

**Files:**
- Modify: `resources/js/Pages/LiveGames/Show.tsx`
- Modify: `resources/js/Components/features/live-game/SuggestedLineupPanel.tsx`

**Changes:**

1. In `Show.tsx`, replace `eventSequence` with `lineupKey`:

```typescript
const lineupKey = useMemo(
    () => [...ownActiveIds].sort((a, b) => a - b).join(','),
    [ownActiveIds],
);
```

Pass `lineupKey` to `SuggestedLineupPanel` instead of `eventSequence`.

2. In `SuggestedLineupPanel.tsx`:
   - Rename prop `eventSequence` → `lineupKey: string`
   - Update `useEffect` dependency from `eventSequence` to `lineupKey`
   - Remove debounce (subs are discrete; optional 100ms if batch apply fires twice)

3. Initial load on mount unchanged (first `lineupKey` still triggers fetch).

**Manual test:** Record 3 misses for a starter — panel should **not** refetch. Record a substitution — panel **should** refetch.

- [ ] **Step 1:** Implement changes
- [ ] **Step 2:** `npm run typecheck`
- [ ] **Step 3:** Commit: `fix(live-game): refresh lineup suggestion only when on-court five changes`

---

### Task 2: Update spec success criteria (docs only)

- [ ] Note revision in `docs/superpowers/specs/2026-08-07-live-lineup-realtime-refresh-design.md` (already done)
