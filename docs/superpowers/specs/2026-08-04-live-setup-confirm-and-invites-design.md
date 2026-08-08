# Live Game Setup Confirm & Invites — Design

**Date:** 2026-08-04  
**Status:** Approved for implementation

## Problem

1. On the opponent lineup form, Assign Assistant sits below Submit and only appears after five players are selected — easy to miss or feel like assignment “disappeared.”
2. Opponent (and home assistants) only learn about a new live game via copy-link or `/live-games` — no in-app interrupt.
3. Live Show chip shows `HOME` / `OPPONENT` without the team name; sidebar has no logged-in account, so coaches cannot tell which account/side they are on.

## Decisions

### Opponent confirm gate

1. Trigger on **Submit lineup** (not on selecting the 5th player).
2. Modal actions: **Confirm lineup** | **Assign assistant** | if already assigned, **Confirm with {name}**.
3. Assign closes the modal and expands the existing Assign Assistant panel; the panel stays **hidden** until that path.
4. Assignment remains **optional** (Confirm lineup submits with no assistant).
5. If `assistantCoachOptions` is empty, skip the modal and POST immediately.
6. Scope: opponent Show only this pass; modal component reusable for Create later.
7. Home Create flow unchanged.

### Main-only assign / exclusive ownership

1. Opponent main coach = first successful lineup submit (`opponent_main_coach_user_id`) — existing rule.
2. Only that submitter configures assistant on that submit.
3. One coach per player (You xor Assistant); unassigned stay with main — already enforced by `LiveGameDelegationWriter`.
4. Assistants never see Assign UI.

### Live-game invites

1. On create: notify all verified coaches on home + opponent teams **except** the creator.
2. Role-aware copy:
   - Opponent coaches: set up your lineup.
   - Home coaches already in `live_game_player_delegations`: you’re assigned — open the game.
   - Other home coaches: softer “your team started a live game.”
3. UI: large in-app banner (Open game / Dismiss) in AuthenticatedLayout.
4. Lifecycle: dismiss per user/game; auto-clear when they open the live game Show, or when the invite is stale (game left `setup` / finished).
5. Delivery: Laravel database notifications + Inertia shared active invite(s) + Reverb on `App.Models.User.{id}`.

### Identity

1. Live header chip uses **team name** (not only home/opponent) plus status.
2. Scoreboard highlights the viewer’s side.
3. Sidebar shows account **name + team name**; collapsed = initials.
4. Shared Inertia `auth.user` includes `team: { id, name }` and `team_id`.

## Relationship to prior spec

Extends [assistant-delegation-setup-placement-design](2026-08-04-assistant-delegation-setup-placement-design.md): atomic lineup+delegation submit stays; opponent Assign moves behind an opt-in confirm gate instead of always-visible-after-five.

## Non-goals (this pass)

- Live adaptive “change recommended lineup” from fouls/misses + history (follow-up).
- Notification inbox / bell list.
- Home Create confirm modal.
- Multi-assistant, post-submit re-assign, head-coach role field.

## Follow-up

- Live adaptive lineup recommender (live state + historical CSV / plus-minus).
- Optional: adopt confirm modal on home Create Game.
- Timekeeper / clock ownership (unchanged from prior follow-up).
