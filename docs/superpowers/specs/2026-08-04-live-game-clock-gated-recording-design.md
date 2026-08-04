# Live Game: Clock-Gated Event Recording + Mistake-Surface Hardening — Design

**Date:** 2026-08-04
**Surface:** `/live-games/{id}` (`resources/js/Pages/LiveGames/Show.tsx`)
**Implementation plan:** [`../plans/2026-08-04-live-game-clock-gated-recording.md`](../plans/2026-08-04-live-game-clock-gated-recording.md)

## Problem

A coach on the live game page can record any stat at any time. The event pad's only gate is:

```ts
const eventsDisabled = processing || snapshot.liveGame.status !== 'live' || !canRecord;
```

There is no clock term, and `LiveGameEventRecorder::validateRecording` has no clock check either.
So 2-pointers get logged with the clock stopped, free throws get logged with it running, and
every stat stays recordable at `0:00` after a period has ended.

The goal stated by the repo owner: *"I want users to avoid making opportunity for mistake — as
much as possible, if we can already not allow it, do it."* That means closing each gap on **both**
layers, so the button is dark for the same reason the API would refuse.

## Audit findings

Ranked by severity. Line references are against the working tree at the time of the audit.

### P0

| # | Finding |
|---|---|
| 1 | **Event pad ignores the clock.** `Show.tsx:217` + `LiveGameEventRecorder::validateRecording` — no clock term on either layer. |
| 2 | **"Period expired" is an invisible dead end.** At `0:00` the scoreboard reads "Clock stopped" in the same cyan as any stoppage. `LiveGameClockService.php:79-81` — `start()` returns silently when remaining is 0, so tapping Play does nothing with no feedback. |
| 3 | **Own actions don't re-clamp the selected player.** `Show.tsx:97-104` and `:114` clamp `selectedPlayerId` on load and on Echo, but `postSnapshot` (`:138`) doesn't. `selectedPlayer` (`:67`) resolves from the *roster*, so subbing out your selected player leaves the pad lit and the next tap fails server-side. |
| 4 | **Any verified user can mutate any live game.** `routes/web.php:35-42` has `auth`+`verified` only. Because `correction`/`timeout` are `team_scope: 'game'` and `validateRecording` only checks `side` for own-player and substitution events, an unrelated coach can void events or inject timeouts. Voiding replays the whole projection, so one request rewrites every plus-minus. `routes/channels.php` already gates broadcasts on `isParticipant` — HTTP was never brought in line. |

### P1

| # | Finding |
|---|---|
| 5 | **Void is one tap, no confirm, no undo.** `Timeline.tsx:15`, a 32×32 button (under the 44px minimum on a tablet-first app) on every row. Voiding a *substitution* retroactively changes who was on court for all later events. Corrections can't be voided, so the mis-tap is permanent. |
| 6 | **Double-void is possible.** The client hides the button via `voidedIds`, but the recorder never checks whether the target is already voided. |
| 7 | **Rebound and Foul send hardcoded payloads.** `EventPad.tsx:24` always sends `kind: 'defensive'`; `:27` always `personal`. `offensive_rebounds`, `technical_fouls`, `flagrant_fouls` are real columns feeding the aggregator but unreachable from the UI. **Every offensive rebound in the system is currently stored as defensive** — live data corruption, not a missing feature. |
| 8 | **No foul-out guard.** `LiveGameAlertService.php:93` warns at 3 fouls (4 in Q4), but a player at 5 can log a 6th. |
| 9 | **Timeout is unattributed.** `team_scope: 'game'` makes the home coach's timeout indistinguishable from the opponent's. |

### P2 / P3

| # | Finding |
|---|---|
| 10 | **Clock drift.** `Show.tsx:129` computes `elapsed = (Date.now() - Date.parse(server_now)) / 1000`, mixing the client's wall clock with the server's. A tablet 90s off shows a 90s-wrong clock. |
| 11 | **Passing 0:00 client-side changes nothing** — the dot keeps pulsing "Clock synced". |
| 12 | **Timeline cannot scroll.** The scroller has `overflow-y-auto`, but the section is `min-h-[300px] flex flex-col`, the scroller is `min-h-0 flex-1`, and neither the section nor its grid cell has a height cap — so the section grows and the *page* scrolls instead. `AlertsPanel.tsx:9` gets this right with `max-h-[240px]`. |
| 13 | **"N events" counts corrections and voided rows** — the number climbs when you *remove* something. |
| 14 | **Disabled buttons never say why.** 40% opacity plus `title={label}`; `disabled` also drops them from tab order. |

## Design

### The clock matrix

Basketball assumption, stated because it is encoded in code: **FIBA rules** — 5 personal fouls
disqualifies. Held in `LiveGameEventRules::MAX_PERSONAL_FOULS` so it can be changed to 6 for NBA.

| Event | Clock running | Clock stopped | At 0:00 |
|---|---|---|---|
| 2PT/3PT made, 2PT/3PT miss | allowed | blocked | blocked |
| Rebound (off/def), Assist, Turnover | allowed | blocked | blocked |
| Foul (personal/tech/flagrant) | allowed — **auto-stops the clock** | allowed | blocked |
| FT made, FT miss | blocked | allowed | blocked |
| Timeout | blocked | allowed | blocked |
| Substitution | blocked | allowed | blocked |
| Correction (void) | allowed | allowed | **allowed** |

Two decisions worth their rationale:

- **Fouls are legal in either state, and auto-stop the clock.** A foul is the boundary event: the
  whistle happens with the clock running and stops it. Gating it to one state would mean a coach
  who stops the clock before tapping Foul gets refused — punishing correct procedure. Auto-stopping
  makes the app do the right thing rather than asking the coach to remember two taps in order.
- **Corrections are exempt from every gate**, including at `0:00` and after the game is finished.
  Otherwise a mistake made at `0:00` could never be fixed.

### Clock authority

The strict matrix creates a dependency: free throws and substitutions need a stopped clock, but
`LiveGameClockService.php:21` gates every clock action on `isCreator`. Left alone, the opposing
bench could not record a free throw until the home coach happened to stop the clock.

**Any main coach may stop; only the creator may start, advance the period, or reset.** One
authoritative clock, but a whistle from either bench can stop it — which is how a scorer's table
actually works. Assistant coaches still can't stop the clock directly, but they aren't deadlocked:
fouls are recordable either way and auto-stop the clock, which is the real-game path into free
throws.

### Where enforcement lives

```
routes/web.php  ──can('record')──▶  LiveGamePolicy::record = isParticipant       (finding 4)
        │
        ▼
LiveGameEventController        validation of shape, scope, ownership
        │
        ▼
LiveGameEventRecorder          clock matrix, period end, foul-out, double-void   (1, 5, 6, 8)
   (inside lockForUpdate)      + auto-stop on foul, + timeout team stamp         (9)
        │
        ▼
LiveGameProjectionService      unchanged — already replays from non-voided events
```

`LiveGameEventRules` (new, dependency-free) holds the canonical event-type list and the clock map.
The controller's `Rule::in()` reads from it, so there is one list rather than two.
`event-catalog.ts` mirrors it on the client — the server stays authoritative; the mirror exists
only so a button is dark for the same reason the API would refuse.

### UI decisions

- **Explicit variant buttons, not a mode toggle.** Off reb / Def reb and Personal / Technical /
  Flagrant become their own buttons. A persistent "offensive rebound mode" is itself a mistake
  surface — the coach has to remember which mode is armed. The pad goes from 11 to 14 buttons, so
  it gains four group headings (Scoring / Play / Fouls / Game) rather than becoming a wall.
- **Every disabled button names the action that unblocks it** — "Recorded with the clock stopped
  — stop the clock first", not a bare 40% opacity. One unified banner below the pad replaces the
  two partial hint blocks, covering not-live, not-a-coach, clock-stopped, period-ended, and
  no-controlled-players-on-court.
- **Period-ended is a third clock state**, amber with the pulse off, reading `Q1 ended` — the one
  state that demands a specific action should not look like the state that demands nothing.
- **The timeline scroller gets `max-height: min(55vh, 520px)`.** Newest-first ordering is already
  correct, so no auto-scroll is needed. The void button goes to 44×44 and opens a confirmation that
  names the consequence; for a substitution it says on-court composition changes for every later
  event. No `aria-live` on the list: every snapshot re-renders it, so a live region would
  re-announce everything.
- **The clock anchors on snapshot receipt.** `seconds_remaining` already accounts for server-side
  elapsed time, so measuring from `Date.now()` captured when the snapshot arrived removes the wall-
  clock skew entirely.

## Out of scope, with reasons

- **Per-team timeout limits.** FIBA allocates timeouts by half with a special last-two-minutes
  rule. This app's `period_length_seconds` is configurable from 60 to 1200, so there is no honest
  number to enforce — any limit shipped would be wrong for most configurations. Attribution (which
  team called it) is in scope; the count is not.
- **`LiveGameController::index` scoping.** It lists every live game to every user. Real, but a
  separate concern from the recording surface.
- **Steal and block event types.** `LiveGamePlayerStat` has the columns and they are always 0,
  because no event type produces them. A gap, but adding event types is a feature, not hardening.
- **Overtime.** The period advance button only renders below Q4; at Q4 `0:00` with a tied score
  there is no path forward. Out of scope per the 4-period spec.
- **Spectator live updates.** `show` stays open to any verified user (the page already renders a
  read-only state), but `routes/channels.php` gates broadcasts on `isParticipant`, so a spectator's
  page will not live-update. Pre-existing inconsistency, left as-is.

## Verification

`php artisan test`, `npm run typecheck`, `vendor/bin/pint --test`, plus a two-device manual pass
following [`docs/live-game-1plus1-sync-checklist.md`](../../live-game-1plus1-sync-checklist.md).
The eleven manual checks are enumerated in Task 12 of the plan.
