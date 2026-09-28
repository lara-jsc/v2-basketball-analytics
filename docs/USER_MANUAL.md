# HoopSense+ User Manual

A simple guide for coaches and admins. No technical knowledge needed.

---

## How to read this manual

Every task has three parts:

- **Who** — the person who does it (Admin, Coach, Main coach, or Assistant coach).
- **Before you start** — what must already be done. Read arrows (→) as "then".
- **Steps** — what to click, in order. Words in **bold** are the exact words you see on the screen.

> **Tip:** Some numbers (win probability, plus-minus, suggested lineups) take a few seconds to calculate.
> While they load you will see **Computing…** or a spinner. A dash **—** means "not calculated yet". Just wait a moment.

---

## 1. What is HoopSense+?

HoopSense+ helps a coach prepare for and run a basketball game. Before the game it shows your
chances of winning, the best five players to start, and how your players match up against the
opponent. During the game you record every shot, rebound and foul, and the app keeps score and
suggests substitutions.

**Who uses it**

| Person | What they do |
|---|---|
| **Admin** | Creates accounts for coaches and decides who coaches which team. |
| **Main coach** | Runs the team: players, game history, comparisons, live games. One per team. |
| **Assistant coach** | Helps the main coach. Can record stats for the players assigned to them in a live game. |

**The menu (left side of the screen)**

**Dashboard** · **Teams & Players** · **Team Comparison** · **Player Matchup** · **Live Games** · **Accounts** (Admins only)

---

## 2. Logging in

**Who:** Everyone
**Before you start:** An Admin has created your account and given you your email and password.

1. Open HoopSense+ in your browser.
2. Type your **Email** and **Password**.
3. Click **Sign In**. You land on the **Dashboard**.

> Forgot your password? Ask your Admin to reset it (see [section 3](#3-create-a-coach-account-admin)).

### Change your password

1. Add `/profile` to the end of the site address in your browser and press Enter.
2. Scroll to **Update Password**.
3. Type your current password, then the new one twice.
4. Click **Save**.

---

## 3. Create a coach account (Admin)

**Who:** Admin
**Before you start:** Log in as Admin → the team the coach will join already exists (see [section 5](#5-teams)).

1. In the menu, click **Accounts**.
2. Click **Create account**.
3. Under **Account type**, choose **Coach**.
4. Fill in **Full name** and **Email**.
5. Under **Team**, pick the coach's team.
6. Under **Staffing**, choose one:
   - **Main coach** — the head coach. Only one per team. If the slot is taken it says "Held by …".
   - **Assistant coach** — added to the team's helpers.
   - **Not assigned** — decide later.
7. Type a **Password** and type it again in **Confirm password**.
8. Click **Create account**.
9. Give the coach their email and password. They can sign in right away and change the password later.

> **Creating another Admin:** same steps, but choose **Admin** in step 3. Admins are not tied to a team.

### Edit an account or reset a password

**Who:** Admin
**Before you start:** Log in as Admin → Accounts.

1. Find the person in the list and open their edit page.
2. Change name, email, account type or team.
3. To reset the password, type a **New password** and confirm it. Leave it blank to keep the old one.
4. Click **Save changes**.

> Changing a coach's team removes them from their old team's staff. A yellow warning tells you before you save.

### Change who coaches a team

**Who:** Admin
**Before you start:** Log in as Admin → the coach accounts already exist and belong to that team.

1. **Teams & Players** → open the team → **Edit Team**.
2. In **Manage Coach Staffing**, pick the **Main Coach** and tick the **Assistant Coaches**.
3. Click **Save Staffing**.

---

## 4. What a coach should do first (checklist)

Follow this order the first time. Each step has its own section below.

1. Log in with the account the Admin gave you → [section 2](#2-logging-in)
2. Make sure your team exists → [section 5](#5-teams)
3. Add your players → [section 6](#6-players)
4. Add each player's past games → [section 7](#7-player-game-history)
5. Compare against your next opponent → [section 8](#8-team-comparison-before-the-game)
6. On game day, run a live game → [section 9](#9-live-game-during-the-game)

---

## 5. Teams

**Who:** Admin or Coach
**Before you start:** Log in.

### Create a team

1. **Teams & Players** → **New Team**.
2. Fill in **Team Code** (short, e.g. `LAL`, max 10 letters, must be unique) and **Team Name** (e.g. `Los Angeles Lakers`).
3. Click **Create Team**.

### Add a logo / edit a team

1. **Teams & Players** → open the team → **Edit Team**.
2. Under **Upload New Logo**, pick a picture (JPEG, PNG or WebP, max 2 MB) → **Upload Logo**.
3. Change the name, code or **Status** (**Active** / **Inactive**) if needed → **Save Changes**.

---

## 6. Players

**Who:** Coach
**Before you start:** Log in → Teams & Players → open your team.

### Add one player

1. Click **Add Player**.
2. Fill in **First Name**, **Last Name**, **Jersey #**. Optional: **Role / Position**, **Height (ft)**, **Weight (kg)**.
3. Save.

### Add many players at once (spreadsheet)

1. On your team page, in **CSV Import**, click **Download Template**.
2. Open the file in Excel or Google Sheets. Fill one row per player. **Do not rename or move the column titles.**
3. Save it as CSV.
4. Drag the file onto the upload box (or click **Browse**) → **Import Data**.
5. Wait for the status to show **Imported**. If a row had a mistake, it is skipped and listed so you can fix it.

> This file only adds names, jersey numbers, positions, height and weight. Stats come from game history (next section).

### Edit, add a photo, deactivate, or remove a player

Click a player in the list. Buttons appear:

- **Game history** — see and add past games.
- **Edit player** — change details, and **Upload Picture** for a profile photo.
- **Deactivate / Activate** — an inactive player (injured, away) is hidden from lineup suggestions and comparisons but keeps their stats.
- **Remove player** — deletes the player permanently.

---

## 7. Player game history

Game history is where all stats come from. The more games you enter, the better the suggestions.

**Who:** Coach
**Before you start:** Log in → Teams & Players → your team → click a player → **Game history**.

### Add one game by hand

1. Click **Add Game**.
2. Fill in **Game Date**, **Against** (the opponent), minutes, points, rebounds and the other boxes.
3. Save. The player's averages and plus-minus update after a few seconds.

### Import many games (spreadsheet)

1. In **Import Game History**, click **Download Template**.
2. Fill one row per game. Keep the column titles exactly as they are.
3. Upload the file → **Import History**.

> You only fill in the opponent team. The app already knows which team the player plays for.

### Shot zones (optional)

**Import Shot Zone Profile** tells the app where each player usually shoots from (paint, mid-range, corner 3, etc.).
Download its template, fill it in, and click **Import Profile**.

### Fix or delete a game

Click the edit or delete button on that game's row. Stats recalculate automatically.

---

## 8. Team Comparison (before the game)

**Who:** Coach
**Before you start:** Both teams exist → each team has at least 5 **active** players → players have some game history.

1. In the menu, click **Team Comparison**.
2. Pick your team as **Home Team** and the other team as **Opponent**.
3. Click **Start Comparison**.
4. You now see:
   - **Win probability** for each team.
   - **Team Plus-Minus** — how much each team usually outscores opponents while on court.
   - **Player matchups** — pick two players to compare side by side (also found under **Player Matchup** in the menu).
5. Click **View Recommended Lineup** to see the suggested starting five.
6. Click **Confirm Lineup →** to take that five straight into a new live game (you can still change it).

---

## 9. Live Game (during the game)

This is where you record the game as it happens. Each team's coach uses **their own phone, tablet or laptop**. Both screens update together.

### Before you start

- Admin logs in → **Accounts** → creates a coach for **your team** → and a coach for the **opponent team** (see [section 3](#3-create-a-coach-account-admin)).
- Each team has at least **5 active players** (see [section 6](#6-players)).
- Both coaches are logged in on their own device, with internet.
- *(Optional)* Admin creates **assistant coach** accounts on the same team, if you want helpers to record stats (see [Assistant coaches in a live game](#assistant-coaches-in-a-live-game)).

### Step 1 — Set up the game (your coach)

1. In the menu, click **Live Games** → **New live game** (or **Set up a game**).
2. **Your team** is filled in for you.
3. Pick the **Opponent team**.
4. **Quarter length in seconds** — leave `600` for 10-minute quarters (use `720` for 12 minutes).
5. Tick exactly **5 players** as your starting five. The counter shows **5/5 selected**.
6. *(Optional)* **Assign assistants** — appears once 5 players are ticked. Choose which players each assistant coach will record (see [Assistant coaches in a live game](#assistant-coaches-in-a-live-game)).
7. Click **Create game**.

> If you see "Your account is not assigned to a team", ask the Admin to set your team.

### Step 2 — Invite the opponent coach

1. On the game page, click **Copy link**. You see "Link copied — send it to the opponent coach."
2. Send the link by text, Messenger, email, etc.
3. Your screen shows **Waiting for [opponent] lineup** until they are ready.

**Opponent coach:**

1. Log in on your own device. A notice **Live game ready to set up** appears — open it, or open the link you were sent.
2. Under **Submit your starting five**, tick 5 players.
3. Click **Submit lineup**.
4. A box asks about assistants. Choose **Confirm without assistant**, or assign some players to your assistant coaches first (see [Assistant coaches in a live game](#assistant-coaches-in-a-live-game)).

When both fives are in, a message says **Both starting fives are ready**.

### Step 3 — Start the game

1. The coach who created the game clicks **Start game**.
2. Press the **play** button next to the clock to start the clock. Press it again to stop it.

> Only the coach who created the game, or a main coach, can control the clock. Everyone else sees "Clock controlled by the team coaches."

### Step 4 — Record what happens

Each coach records **their own team only**.

1. Under **Active lineup**, tap the player who did the action.
2. Tap the action:

| Group | Buttons | Clock must be |
|---|---|---|
| **Scoring** | **2PT made**, **2PT miss**, **3PT made**, **3PT miss** | running |
| **Scoring** | **FT made**, **FT miss** (free throws) | stopped |
| **Play** | **Off reb**, **Def reb**, **Assist**, **Turnover** | running |
| **Fouls** | **Personal**, **Technical**, **Flagrant** | either |
| **Game** | **Timeout** (no player needed) | stopped |

3. After a shot, the app asks **Where was the shot taken?** Tap the area on the court (**Paint**, **Mid-range**, **Left corner 3**, **Right corner 3**, **Above the break 3**) or skip.
4. The score updates on **both** coaches' screens automatically.

> If a button is greyed out, the message tells you why — for example "start the clock first" or "Select an active player first."

**Assistant coaches:** you can only record for the players assigned to you. See the next section.

### Assistant coaches in a live game

An assistant coach helps record stats. The main coach gives each assistant **some players**, and the assistant records only those players. This way two or three people can share the work during a fast game.

**Before you start**

- Admin logs in → **Accounts** → **Create account** → **Coach** → same **Team** → **Assistant coach** → **Create account** (see [section 3](#3-create-a-coach-account-admin)).
- The assistant logs in on **their own device**.

**Main coach — hand players to an assistant**

This works for both teams: your team's coach does it in Step 1, and the opponent coach does it when submitting their lineup in Step 2.

1. Tick your 5 starting players.
2. Click **Assign assistants** to open the panel.
3. Under **Who controls each player**, pick an owner for each player:
   - **You** — you record this player.
   - **An assistant's name** — only that assistant records this player.
4. **Assistant summary** shows who has how many players, for example "Coach Ana · 3 players".
5. Continue with **Create game** or **Submit lineup**. When asked, choose **Confirm with assistant assignments**.

> A player can belong to only one assistant. Players you don't hand out stay with you.
> Assign bench players too if you want the assistant to keep recording them after a substitution.

**Assistant coach — record your players**

1. Log in. A notice **You're assigned to a live game** appears. Open it, or open the game link from your coach.
2. Your players can be tapped under **Active lineup**. The other players are marked as assigned to someone else and can't be selected.
3. Record actions the same way as in Step 4.
4. You can make substitutions for **your** players while the clock is stopped.

**What an assistant can't do**

- Start or stop the clock, change quarters, or **Start game** / **Finish**. These belong to the main coach and the coach who created the game.
- Record for players that were not assigned to them.

> If you see "None of the players assigned to you are on court", your players are on the bench. Wait until one is subbed in.

### Step 5 — Substitutions

1. **Stop the clock** first.
2. Under **Bench**, tap **Make substitution**.
3. Choose **Player out** and **Player in** → confirm.

### Step 6 — Change quarters

- When the time runs out, the scoreboard shows **Q1 ended**.
- Tap **Advance period** to move to the next quarter.
- **Reset period clock** puts the clock back to full time (it asks you to confirm, because it changes minutes played).

### Step 7 — Use the helper panels (right side)

- **Keys to win** — short goals for your team in this game.
- **Coach alerts** — warnings such as **Hot player**, **Cold player**, **Foul trouble**, **Fouled out**, **Opponent run**, **Team drought**.
- **Suggested five** — the app's best five right now, shown two ways:
  - **By season** — based on past games.
  - **By tonight** — based on how players are doing in this game.

  Tap the refresh button to update it. To use a suggestion: stop the clock → tap **Use this five** on the list you like → check the changes → **Apply lineup**.

### Step 8 — Fix a mistake

1. Find the wrong entry in the **Timeline** (newest at the top).
2. Tap the void (✕) button on that row → confirm.
3. The score and stats recalculate. Voiding cannot be undone, so record the correct action again if needed.

### Step 9 — Finish the game

1. The coach who created the game clicks **Finish** → confirm.
2. The scoreboard shows **Final**.
3. Every player's stats from this game are saved to their **Game history** automatically, for both teams.

> Mistakes can still be voided from the Timeline after the game is finished.

### Live game troubleshooting

| Problem | What to do |
|---|---|
| **Start game** is greyed out | The opponent coach has not submitted their five yet. Send the link again. |
| The other coach's score doesn't update | Check internet on both devices, then refresh the page. |
| I can see the game but can't press anything | You are not a coach of either team in this game. Ask the Admin to check your team. |
| "Substitutions need the clock stopped" | Stop the clock, then substitute. |
| "The period has ended" | Tap **Advance period**. |
| "None of the players assigned to you are on court" | You are an assistant coach; your players are on the bench. Wait for a substitution. |

---

## 10. Words you'll see

| Word | Meaning |
|---|---|
| **Plus-minus (+/-)** | Points the team scores minus points it allows while this player is on court. Higher is better. |
| **Win probability** | The app's estimate of how likely a team is to win, based on past games. |
| **Starting five** | The 5 players on court when the game starts. |
| **Active / Inactive** | Inactive players are hidden from lineups and comparisons (e.g. injured). |
| **Void** | Cancel an entry recorded by mistake. |
| **—** | Not calculated yet. Wait a few seconds. |
| **OR / TO** | Offensive rebounds / turnovers. |
