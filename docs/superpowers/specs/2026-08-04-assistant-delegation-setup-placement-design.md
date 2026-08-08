# Assistant Delegation Setup Placement — Design

**Date:** 2026-08-04  
**Status:** Approved for implementation

## Problem

Assigning an assistant coach on the live game Show waiting screen mixes staffing with the live cockpit. The home creator also owns the clock; the opponent joins via link — so each side must configure assistants in their own setup moment, not only after create.

## Decisions

1. **Per-game staffing** — not a separate nav / team-wide defaults.
2. **Mirrored sides** — home configures on Create; opponent configures on join/lineup submit.
3. **One assistant per side (v1)** — unassigned players stay with that side’s main coach.
4. **Full active roster** — You/Assistant ownership for every active roster player.
5. **Collapsed UI** — default is a button; expand to configure; Done collapses again.
6. **Atomic submit** — optional `assistant_coach_user_id` + `delegated_player_ids` ride with Create Game / Submit lineup.
7. **No Show Delegate Controls panel** — remove post-create setup panel and standalone UI save.
8. **Clock unchanged** — still home creator (Timekeeper role is a follow-up).

## Placement & flow

### Home (Create)

1. Opponent + quarter length + starting five.
2. When five selected and opponent chosen: **Assign an assistant** button (if teammate coaches exist).
3. Expand → pick one coach from a tappable list → set each roster player to **You** or **Assistant**.
4. Done collapses to `{name} · {N} players` (or Remove assistant).
5. **Create Game** once — persists lineup + optional delegations.

### Opponent (shared link → Show lineup form)

Same Assign panel on the lineup form; **Submit lineup** persists lineup + optional delegations. Main coach for that side is set on first successful lineup submit (existing rule).

### Waiting / live Show

No Delegate Controls panel. Live pad still filters by `controlled_player_ids`.

## Assign panel UI

- Visible only when lineup ready (+ opponent on Create) and `assistantCoachOptions.length > 0`.
- Collapsed: `Assign an assistant` or `{Assistant name} · {N} players`.
- Expanded: coach list (not dropdown); roster rows with You | Assistant (default You); Done; Remove assistant.
- Touch targets ≥44px; `aria-expanded` on toggle.
- Validation on submit: assistant and ≥1 delegated player required together; neither alone.

## Data & API

| Action | Optional fields |
|--------|-----------------|
| `POST /live-games` | `assistant_coach_user_id`, `delegated_player_ids` |
| `POST /live-games/{id}/lineup` | same |

- Writer validates same-team, email-verified assistant ≠ main; players active on that side; exclusive ownership rows in `live_game_player_delegations`.
- Retire `POST …/delegations` from the UI/routes; tests hit store/lineup.

## Non-goals (v1)

- Separate nav, multi-assistant, post-submit edit, Timekeeper / clock ownership change, modal-after-CTA, auto presets, assistant self-claim.

## Follow-up

Timekeeper (clock-only; possibly Timekeeper creates the game) — separate plan after this ships.
