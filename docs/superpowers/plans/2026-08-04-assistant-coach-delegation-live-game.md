# Assistant Coach Delegation (Live Game) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Let the *main coach* delegate which players an *assistant coach* can record for during a live game, so the main pad/substitution UI is not overwhelming.

## Architecture (short)
- Add persistent player delegation for a live game: `live_game_player_delegations` (game + coach_user_id + player_id).
- Enforce delegation server-side when recording `own` player events and `substitution`.
- Filter the client controls in `LiveGames/Show` so each coach only sees/selects controlled players.

## Assumptions (from your answers)
- Delegation is within the same team side (`home` or `opponent`).
- Delegation applies before the live game (during `setup`) and stays fixed through the whole game.
- Main coach is the team-side coach who submits the starting five first.
- Delegated assignment applies to any active roster player (starters + bench).
- A player is controlled by exactly one coach (exclusive control between main and assistants on that side).

## Tasks

### Database + authorization primitivesno
- [ ] Add migrations:
  - [ ] `live_games`: `home_main_coach_user_id`, `opponent_main_coach_user_id`
  - [ ] New table `live_game_player_delegations` (unique on `live_game_id`, `coach_user_id`, `player_id`)

- [ ] Update `LiveGameController` to set main coach:
  - [ ] Home side: set during `live-games.store`
  - [ ] Opponent side: set during the first successful `live-games.lineup` submission

### Backend: compute controlled players for the UI
- [ ] Update `LiveGameController@show` to pass:
  - [ ] `controlled_player_ids` for the logged-in coach
  - [ ] `is_main_coach` (or equivalent) so only the main coach sees the delegation UI

### Backend: write endpoint for delegation setup
- [ ] Add route + controller/service:
  - [ ] `POST /live-games/{liveGame}/delegations`
  - [ ] Only allowed when `liveGame.status === setup`
  - [ ] Only allowed for the main coach for that side
  - [ ] Validate `assistant_coach_user_id` belongs to the same team side
  - [ ] Validate `player_ids` are active roster players on that side
  - [ ] Replace assignments for `(live_game_id, coach_user_id)` in DB

### Backend: enforce delegation at record time
- [ ] Update `LiveGameEventRecorder::validateRecording`:
  - [ ] For `own` player event types: require `player_id` is in the recorder’s controlled set
  - [ ] For `substitution`: require both `player_out_id` and `player_in_id` are in the recorder’s controlled set

### Frontend: filter controls by controlled players
- [ ] Update `resources/js/Pages/LiveGames/Show.tsx`:
  - [ ] Filter `ActiveLineup` by controlled players (during `live`)
  - [ ] Filter `BenchSubstitution` by controlled players (during `live`)
  - [ ] Ensure selected player is chosen only from controlled active players

- [ ] Update components:
  - [ ] `ActiveLineup.tsx`: remove fixed “/5” and show correct controlled-on-court count
  - [ ] `BenchSubstitution.tsx`: disable substitution trigger when there are no controlled active players

- [ ] Build delegation assignment UI in setup:
  - [ ] Visible to main coach only
  - [ ] Allows selecting assistant coach + selecting which players they control
  - [ ] Saves via `live-games.delegations.store`

### Tests
- [ ] Add feature tests:
  - [ ] main coach can record for non-delegated players
  - [ ] assistant coach can record only for delegated players
  - [ ] assistant coach gets validation errors when trying to record for unassigned players
  - [ ] delegation write endpoint requires main-coach authorization

- [ ] Add/extend unit tests:
  - [ ] `LiveGameEventRecorderTest` coverage for delegation checks on:
    - [ ] `own` player events
    - [ ] substitutions

### Seeder: create assistant coach accounts
- [ ] Create `database/seeders/AssistantCoachesSeeder.php`
  - [ ] For each team, add **4** extra coach accounts:
    - [ ] `warriors2@email.com`..`warriors5@email.com`
    - [ ] `lakers2@email.com`..`lakers5@email.com`
  - [ ] password: `password123`
  - [ ] `email_verified_at = now()`
  - [ ] `team_id` tied to the appropriate team
- [ ] Update `database/seeders/DatabaseSeeder.php` to call the new seeder

