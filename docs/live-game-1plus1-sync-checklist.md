# Live Game 1+1 Sync Checklist

Prove dual-team real-time scoring with one Warriors device and one Lakers device.

## Prerequisites

1. MySQL is up and migrations are applied (`php artisan migrate`).
2. Seed coaches (after teams exist):

```bash
php artisan db:seed --class=TeamPlayersSeeder
php artisan db:seed --class=DemoUserSeeder
```

3. Run the app stack:

```bash
composer dev
```

4. In a second terminal:

```bash
php artisan reverb:start
```

## Accounts

| Role | Email | Password | Team |
|------|-------|----------|------|
| Warriors coach (create/clock) | `warriors@email.com` | `password123` | GSW |
| Lakers coach | `lakers@email.com` | `password123` | LAK |

## Steps

1. **Device A** — log in as `warriors@email.com`.
2. Create a live game: Home = Golden State Warriors, Opponent = Los Angeles Lakers; confirm both starting fives; Create.
3. Tap **Start game** (creator only).
4. Select a Warriors player and record **2PT made**. Confirm home score becomes 2.
5. **Device B** — log in as `lakers@email.com`, open the same `/live-games/{id}` URL.
6. Confirm Device B shows home score 2 without a manual refresh (Echo/`LiveGameStateUpdated`).
7. On Device B, select a Lakers player and record **3PT made**. Confirm opponent score becomes 3.
8. On Device A, confirm opponent score updates to 3 without refresh.
9. On Device B, confirm clock controls / Finish are hidden (non-creator).
10. On Device A, Finish the game; both teams’ histories should finalize.

## Failure signals

- Device B never updates → Reverb down, wrong `VITE_REVERB_*`, or channel auth (user not on either team).
- 422 on events → coach `team_id` not set, or player not on that coach’s active five.
- 422 on clock → only the creator can control the clock.
