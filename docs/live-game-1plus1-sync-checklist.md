# Live Game 1+1 Sync Checklist

Prove dual-coach setup and dual-team real-time scoring with one Warriors device and one Lakers device.

## Prerequisites

1. MySQL is up and migrations are applied (`php artisan migrate`).
2. Seed coaches (after teams exist):

```bash
php artisan db:seed --class=TeamPlayersSeeder
php artisan db:seed --class=DemoUserSeeder
```

3. Run the app stack (includes Reverb on port 8080):

```bash
composer dev
```

If you already have `composer dev` running from before Reverb was added to that script, start Reverb in a second terminal:

```bash
php artisan reverb:start
```

## Accounts

| Role | Email | Password | Team |
|------|-------|----------|------|
| Warriors coach (create/clock) | `warriors@email.com` | `password123` | GSW |
| Lakers coach | `lakers@email.com` | `password123` | LAK |

## Steps

### Setup (dual-coach)

1. **Device A** — log in as `warriors@email.com`.
2. Optional soft-default: open Comparison (Warriors vs Lakers) → Confirm lineup → lands on Create with AI five preselected (editable).
3. Create a live game: Your team is locked to Golden State; pick Opponent = Los Angeles Lakers; select **only** the Warriors five; Create.
4. On the Show page, confirm **Start game** is disabled and a waiting state asks for the Lakers lineup.
5. Tap **Copy link** and send `/live-games/{id}` to Device B.
6. **Device B** — log in as `lakers@email.com`, open the shared URL, submit five Lakers.
7. On Device A, confirm readiness updates via Echo (`both_lineups_ready`) and **Start game** enables without refresh.

### Live scoring

8. On Device A, tap **Start game** (creator only).
9. Select a Warriors player and record **2PT made**. Confirm home score becomes 2.
10. On Device B, confirm home score shows 2 without a manual refresh (Echo/`LiveGameStateUpdated`).
11. On Device B, select a Lakers player and record **3PT made**. Confirm opponent score becomes 3.
12. On Device A, confirm opponent score updates to 3 without refresh.
13. On Device B, confirm clock controls / Finish are hidden (non-creator).
14. On Device A, Finish the game; both teams’ histories should finalize.

## Failure signals

- Device B never updates → Reverb down, wrong `VITE_REVERB_*`, or channel auth (user not on either team).
- `Pusher error: Payload too large` → restart Reverb after raising `REVERB_MAX_REQUEST_SIZE` / `REVERB_APP_MAX_MESSAGE_SIZE` (defaults are 250000).
- 422 on events → coach `team_id` not set, or player not on that coach’s active five.
- 422 on clock / Start → only the creator can control the clock; both starting fives must be submitted first.
- Create shows both lineups → you are on a stale build; create is own-five only.
