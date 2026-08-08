# Live Keys to Win Implementation Plan

> **For agentic workers:** Implement task-by-task. **Do not create git commits** — the human commits.

**Goal:** Add hybrid season+live Keys to win strategy to the live-game Show aside, with optional `keys_threat_spike` coach alerts.

**Architecture:** Deterministic `LiveGameKeysToWinService` computes up to 2 keys per side from season `player_stats` + live events; ProjectionService passes keys into alert sync; StateBuilder includes `keys_to_win` in the Echo snapshot; `KeysToWinPanel` renders above Coach alerts.

**Tech Stack:** Laravel/Pest, Inertia React/TS, existing live-game tokens

## Global Constraints

- No new Python command; no LLM
- Max 2 keys per side; read-only UI v1
- No auto-commits
- Spec: `docs/superpowers/specs/2026-08-07-live-keys-to-win-design.md`

## Files

| File | Responsibility |
|------|----------------|
| `app/Services/LiveGame/LiveGameKeysToWinService.php` | Compute keys |
| `app/Services/LiveGame/LiveGameAlertService.php` | Sync `keys_threat_spike` |
| `app/Services/LiveGame/LiveGameProjectionService.php` | Compute once → alerts |
| `app/Services/LiveGame/LiveGameStateBuilder.php` | Snapshot `keys_to_win` |
| `resources/js/Components/features/live-game/KeysToWinPanel.tsx` | UI |
| `resources/js/Pages/LiveGames/Show.tsx` | Mount panel |
| `resources/js/Components/features/live-game/AlertRow.tsx` | Spike label |
| `resources/js/types/index.ts` | Types |
| `tests/Unit/Services/LiveGame/LiveGameKeysToWinServiceTest.php` | Unit |
| `tests/Unit/Services/LiveGame/LiveGameAlertServiceTest.php` | Spike tests |
| `tests/Feature/LiveGame/LiveGameControllerTest.php` | Snapshot has keys |

### Task 1: Keys service + tests
### Task 2: Wire projection / alerts / builder
### Task 3: Frontend panel + types
### Task 4: Verify tests + typecheck

---
