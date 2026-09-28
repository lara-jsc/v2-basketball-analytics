# HoopSense+ — How the Numbers Are Calculated

This is a plain-language reference for **every calculation HoopSense+ currently performs**, organized by the
part of the app where you see it. Each item answers four questions:

- **What it tells you** — the question the number answers
- **How it's calculated** — in words, plus one simple formula
- **Example** — small numbers you can check by hand
- **Where you see it** — the page or panel

Percentages are stored internally as fractions (0.45) and shown as percentages (45.0%). A dash **"—"** means
"not calculated yet" or "not enough data" — it never means zero.

> Last checked against the code: 2026-09-23.

---

## Contents

1. [Where the numbers come from](#1-where-the-numbers-come-from)
2. [Teams & Players — season stats](#2-teams--players--season-stats)
3. [Plus-minus](#3-plus-minus)
4. [Team Comparison — win probability](#4-team-comparison--win-probability)
5. [Recommended Lineup (Starting 5)](#5-recommended-lineup-starting-5)
6. [Player Matchup — player vs player](#6-player-matchup--player-vs-player)
7. [Shooting Analysis — shot zones](#7-shooting-analysis--shot-zones)
8. [Live Games](#8-live-games)
9. [Dashboard](#9-dashboard)
10. [When numbers refresh](#10-when-numbers-refresh)
11. [Known limitations](#11-known-limitations)
12. [Formula cheat sheet](#12-formula-cheat-sheet)
13. [Where it lives in the code](#13-where-it-lives-in-the-code)

---

## 1. Where the numbers come from

Everything starts from **game histories** — one row per player per game (points, rebounds, shots made and
attempted, minutes, fouls, and so on).

Game histories get into the system three ways:

- typed in by hand on a player's **Game History** page
- imported from a CSV/XLSX file
- saved automatically when a **Live Game** is finished (see [8.9](#89-finishing-a-game))

Whenever a player's game history changes, the system recalculates in two steps:

1. **Season stats** — all of that player's games are combined into season averages and percentages (section 2).
2. **Plus-minus** — the new season stats are fed into the plus-minus formula (section 3).

The **roster import** (names, jersey numbers, roles, height, weight) does **not** create or change any stats.

The calculations run in the background, so a number can briefly show "—" right after an update.

---

## 2. Teams & Players — season stats

**Where you see it:** the **Team** page player table, the Player Matchup table, and as inputs to every other
calculation.

### 2.1 Games played and started

- **GP** = number of games in the player's history.
- **GS** = number of those games the player started.

### 2.2 Per-game averages

**What it tells you:** a player's typical output per game.

**How:** the average across all games. Games where that stat was left blank are skipped rather than counted as
zero. Rounded to 2 decimals.

**Covers:** MIN, PTS, REB, OR (offensive rebounds), DR (defensive rebounds), AST, STL, BLK, TO, PF.

**Example:** points of 12, 18 and 15 over three games → (12 + 18 + 15) ÷ 3 = **15.00 PTS**.

### 2.3 Season totals (counts, not averages)

- Flagrant fouls, technical fouls, ejections and disqualifications are **added up** across the season.
- FG, FT and 3PT are also shown as "made–attempted" totals, for example `60-130`.

### 2.4 Shooting percentages — FG%, 3P%, FT%

**What it tells you:** how often the player's shots go in.

**How:** season total made ÷ season total attempted. This is *not* the average of each game's percentage.

```
FG% = total field goals made ÷ total field goals attempted
```

**Example:** 60 made out of 130 attempted → 60 ÷ 130 = **46.2%**.

**Good to know:** shows "—" if the player has no attempts.

### 2.5 Ratios — AST/TO and STL/TO

```
AST/TO = assists per game ÷ turnovers per game
STL/TO = steals per game ÷ turnovers per game
```

**Example:** 4.0 AST and 2.0 TO → **2.00 AST/TO**. Shows "—" if the player has no turnovers.

### 2.6 Efficiency stats

All five use **per-game** numbers. The running example is a player averaging 15 PTS, 6 REB, 4 AST, 1.2 STL,
0.5 BLK and 2 TO, who shoots 6 of 13 from the field (2 of those makes are threes) and 1 of 2 from the free-throw
line.

| Stat | What it tells you | Formula | Example |
|---|---|---|---|
| **SC-EFF** (scoring efficiency) | Points produced per shot taken | PTS ÷ FGA per game | 15 ÷ 13 = **1.15** |
| **SH-EFF** (shooting efficiency) | Shot value vs. shots taken; usually negative | (FGM + 0.5×3PM + 0.44×FTM − FGA) ÷ FGA | (6 + 1 + 0.44 − 13) ÷ 13 = **−0.43** |
| **EFF** (efficiency rating) | Overall box-score contribution | PTS + REB + AST + STL + BLK − missed FG − missed FT − TO | 26.7 − 7 − 1 − 2 = **16.7** |
| **eFG%** (effective FG%) | FG%, with threes counted as worth 1.5 twos | (FGM + 0.5 × 3PM) ÷ FGA | (6 + 1) ÷ 13 = **53.8%** |
| **TS%** (true shooting) | Scoring efficiency including free throws | PTS ÷ (2 × (FGA + 0.44 × FTA)) | 15 ÷ 27.76 = **54.0%** |

**Good to know:** eFG% and TS% are capped at 100%. All five show "—" when the player has no shot attempts.

### 2.7 Double-doubles and triple-doubles

In each game, the system counts how many of **points, rebounds, assists, steals, blocks** reached 10 or more:

- 2 of them → a **double-double (DD2)**
- 3 or more → a **triple-double (TD3)**, which also counts as a double-double

### 2.8 Usual position

The position the player played most often in their game history.

### 2.9 Per-game FG% on the Game History page

**Where:** Game History table.

For a single game: made ÷ attempted × 100, shown to 1 decimal. Example: 6 of 13 → **46.2%**.

---

## 3. Plus-minus

### 3.1 Player plus-minus

**What it tells you:** a single rating of a player's overall impact, estimated from the box score. Positive is
good; about 0 is average.

**How it's calculated:**

1. Give each stat a weight and add them up:
   - points × 0.35
   - assists × 0.20
   - rebounds × 0.15
   - FG% × 0.10
   - 3P% × 0.05
   - blocks × 0.08
   - steals × 0.12
2. Subtract turnovers × 0.25.
3. Scale it to a **36-minute** game (× 36 ÷ minutes per game), so bench players are compared fairly with starters.
4. Subtract a baseline of **5**, so an average player lands near zero.

```
plus-minus = (0.35·PTS + 0.20·AST + 0.15·REB + 0.10·FG% + 0.05·3P% + 0.08·BLK + 0.12·STL − 0.25·TO)
             × (36 ÷ MIN) − 5
```

**Example:** a player averages 15 PTS, 4 AST, 6 REB, 45% FG, 35% 3P, 0.5 BLK, 1.2 STL, 2 TO in 30 minutes:

- weighted stats = 7.20
- minus turnovers (2 × 0.25 = 0.50) → 6.70
- × 36/30 → 8.04
- − 5 → **+3.04**

**Where you see it:** Team page, Player Matchup, and as an input to Team Plus-Minus, the lineup OVR and Keys to Win.

**Good to know:**
- This is an *estimate* from averages, not true on-court plus-minus (see [8.3](#83-live-plus-minus) for the live
  version).
- Shows "—" until the calculation finishes.
- Shows "—" if the player has no minutes recorded, since there is nothing to scale to 36 minutes.
- Percentages count as fractions (0.45), so FG% and 3P% move the result very little.

### 3.2 Team plus-minus

**What it tells you:** the team's overall rating, where players who play more minutes count more.

**How:** a minutes-weighted average over **active** players who have a plus-minus and played minutes.

```
team plus-minus = Σ(player plus-minus × player minutes) ÷ Σ(player minutes)
```

**Example:**

| Player | Plus-minus | Minutes | Plus-minus × minutes |
|---|---|---|---|
| A | +3.0 | 30 | +90 |
| B | −1.0 | 20 | −20 |
| C | +2.0 | 10 | +20 |

(90 − 20 + 20) ÷ 60 = **+1.50**

**Where you see it:** Team page header, Team Comparison. It is worked out when the page loads and is never saved.

---

## 4. Team Comparison — win probability

**Where you see it:** **Team Comparison** page (pick two teams).

### 4.1 Team averages

For each team, the average of the active players' season stats: PTS, REB, AST, FG%, BLK, STL and TO. Each
stat row highlights the better team; for **turnovers, lower is better**.

### 4.2 Team strength score

A weighted total of the team averages. Turnovers count against the team.

```
team score = 0.30·PTS + 0.15·REB + 0.15·AST + 0.15·FG% + 0.08·BLK + 0.08·STL − 0.09·TO
```

### 4.3 Win probability

**What it tells you:** the chance that each team wins this matchup.

**How:** the difference between the two team scores is converted into a probability with an S-shaped
(logistic) curve:
- equal scores → 50% / 50%
- a bigger gap → a more lopsided result

The two probabilities always add up to 100%.

```
Team A win probability = 1 ÷ (1 + e^−(score A − score B))
Team B win probability = 1 − Team A win probability
```

**Example:**

| Team | PTS | REB | AST | FG% | BLK | STL | TO | Score |
|---|---|---|---|---|---|---|---|---|
| A | 12 | 5 | 3 | 45% | 0.5 | 1.0 | 2.0 | 4.81 |
| B | 10 | 5 | 2.5 | 42% | 0.4 | 0.8 | 2.5 | 4.06 |

Gap = 0.75 → Team A **67.9%**, Team B **32.1%**.

### 4.4 Win rate

**What it tells you:** a more cautious version of win probability. It is pulled 20% of the way back toward
50/50, so the app doesn't sound over-confident.

```
Team A win rate = 0.5 + 0.8 × (Team A win probability − 0.5)
```

**Example:** a 67.9% probability → 50% + 0.8 × 17.9% = **64.3%**.

**Good to know:** shows a pending state while the calculation runs, then fills in.

---

## 5. Recommended Lineup (Starting 5)

**Where you see it:** Team Comparison → **Recommended Lineup** (for the home team against the chosen opponent).

### 5.1 Lineup score per player

**What it tells you:** how strong each player is for *this specific opponent*.

**How:**

1. Weighted stats:
   - points × 0.30
   - assists × 0.15
   - rebounds × 0.15
   - blocks × 0.10
   - steals × 0.10
   - FG% × 0.10
2. **Opponent bonus:** compare the player with the opponent team's *average player* on PTS, AST, REB, BLK, STL
   and FG%. Each stat the player beats earns a share of a 0.10 bonus: beating all 6 = +0.10, beating 3 = +0.05.
3. Subtract turnovers × 0.20.

```
lineup score = 0.30·PTS + 0.15·AST + 0.15·REB + 0.10·BLK + 0.10·STL + 0.10·FG%
               + 0.10 × (stats beaten ÷ 6) − 0.20·TO
```

**Example:** 15 PTS, 4 AST, 6 REB, 0.5 BLK, 1.2 STL, 45% FG, 2 TO, and better than the opponent average in all 6 stats:

- weighted stats = 6.215
- opponent bonus = +0.10
- turnovers = −0.40
- → **5.915**

### 5.2 Picking the five

All the home team's active players are ranked by lineup score, and the **top 5** are recommended.

### 5.3 Confidence

**What it tells you:** how strong the recommended five are as a group.

**How:** the average of the five lineup scores, passed through the same S-curve as win probability.

```
confidence = 1 ÷ (1 + e^−(average of top-5 scores))
```

**Example:** scores of 5.92, 5.08, 3.78, 3.33 and 2.49 → average 4.12 → **98.4%**.

**Good to know:** lineup scores are usually well above zero, so confidence tends to sit very close to 100%.
Treat it as a relative signal, not a true probability.

### 5.4 Lineup window — OVR rating and net score

These two numbers are worked out on the page itself when the lineup window opens.

- **OVR (overall rating)**, a game-style player rating from 60 to 99:
  ```
  OVR = 1.8·PTS + 30·FG% + 1.2·AST + 0.8·REB + 0.5·plus-minus   (rounded, then kept between 60 and 99)
  ```
  Example: 22 PTS, 48% FG, 5 AST, 7 REB, +4 plus-minus → 39.6 + 14.4 + 6 + 5.6 + 2 = **68**.
- **Net score** = the five players' lineup scores added together.

---

## 6. Player Matchup — player vs player

**Where you see it:** Team Comparison → pick one player from each team.

### 6.1 Edge score

**What it tells you:** which player comes out ahead across a set of head-to-head stats.

**How:** the two players are compared on **13 stats**:
- PTS, AST, REB, BLK, STL
- FG%, 3P%
- DR, OR, MIN
- EFF, eFG%, TS%

Whoever is higher wins that stat, and a tie counts for nobody. Each player's edge score is the share of the 13
stats they won.

```
edge score = stats won ÷ 13
```

**Example:** Player A wins 8 stats and Player B wins 4 (one tie) → A **61.5%**, B **30.8%**.

The stats each player won are **highlighted** in the table.

**Good to know:**
- Every stat counts equally: winning minutes counts as much as winning points.

### 6.2 Radar chart

**How:** each stat is placed on a 0–100 scale between a fixed low end and high end. Anything past either end
is capped at 0 or 100.

| Stat | Low end | High end |
|---|---|---|
| PTS | 0 | 40 |
| AST | 0 | 15 |
| DR | 0 | 15 |
| DD2 | 0 | 82 |
| SH-EFF | −1 | 0.5 (most shooters are negative) |
| SC-EFF | 0 | 2 |

```
radar value = (stat − low end) ÷ (high end − low end) × 100
```

**Example:**
- 20 PTS → 20 ÷ 40 = **50**
- an SH-EFF of −0.43 → 0.57 ÷ 1.5 = **38**

### 6.3 Plus-minus row

Each player's plus-minus (section 3.1) is shown side by side. It is **not** one of the 13 edge stats.

---

## 7. Shooting Analysis — shot zones

**Where you see it:**
- Team Comparison → Player Matchup (two mini courts, one per player)
- Shot zone import on the Game History page
- Zone tagging during a Live Game

> **Short answer to "who is more likely to make a shot from this spot?"** The system does **not** currently
> calculate that. It has no shot-probability model. For each player it shows how often their shots from each
> zone have gone in so far (made ÷ attempted). The two players' courts use the **same color scale**, so you can
> compare them by eye, but the system does not name a "better shooter" for each zone.

### 7.1 The five zones

| Zone | Shot value |
|---|---|
| Paint | 2 |
| Mid-range | 2 |
| Left corner 3 | 3 |
| Right corner 3 | 3 |
| Above-the-break 3 | 3 |

### 7.2 Where the zone numbers come from

Each zone's numbers combine two sources:

1. **Season profile:** made and attempted per zone, imported from a file.
2. **Live-tagged shots:** during a live game, a made or missed field goal can be tagged with its zone. Free
   throws are never tagged. Shots that were later voided by a correction are excluded.

```
zone made      = profile made      + live-tagged made
zone attempted = profile attempted + live-tagged attempted
```

### 7.3 Zone make percentage

```
zone make % = zone made ÷ zone attempted × 100
```

**Example:** paint 30 of 55 → **54.5%**.

### 7.4 Points per shot (PPS)

**What it tells you:** how many points a shot from that zone is worth on average. This is fairer than make % for
comparing threes with twos.

```
PPS = zone make % × shot value (2 or 3)
```

**Example:**

| Zone | Made / attempted | Make % | Shot value | PPS |
|---|---|---|---|---|
| Paint | 30 / 55 | 54.5% | 2 | **1.09** |
| Right corner 3 | 6 / 15 | 40.0% | 3 | **1.20** |

The corner three scores more points per shot, even though it goes in less often.

### 7.5 Minimum sample

A zone needs **at least 5 attempts** to show a percentage and a color. With fewer attempts it is shown grey and
displays only made/attempted.

### 7.6 Hot/cold colors

The zone color comes from PPS on a fixed scale: **0.7 PPS or less = cold (blue)** and **1.4 PPS or more = hot
(amber)**, blending in between. The scale is the same for every player.

### 7.7 Top zone ("Usually shoots from…")

**What it tells you:** the zone the player shoots from **most often**. It is not necessarily their best zone.

**How:**
- The zone with the most attempts wins. A tie goes to the zone listed first in 7.1.
- **Share of shots** = attempts in that zone ÷ all zone attempts.

**Example:**

| Zone | Attempts |
|---|---|
| Paint | 55 |
| Mid-range | 30 |
| Left corner 3 | 13 |
| Right corner 3 | 15 |
| Above-the-break 3 | 50 |
| **Total** | **163** |

→ "Usually shoots from **Paint** · **34%** of shots · 54.5% make".

### 7.8 Profile vs. history check

The system checks that the imported zone profile agrees with the player's game history:
- paint + mid-range attempts = total 2-point attempts
- the three 3-point zones added together = total 3-point attempts

If they don't match, a warning is shown under the court.

---

## 8. Live Games

**Where you see it:** **Live Games** → open a game. Everything here is recalculated from the event log every
time an event is recorded or corrected.

### 8.1 Score and live box score

- A made shot adds its points (2 or 3), and a made free throw adds 1.
- Opponent scoring is entered as points.
- Each event updates the player's live line:
  - FGM/FGA, 3PM/3PA, FTM/FTA
  - offensive/defensive rebounds, assists, turnovers
  - personal, technical and flagrant fouls

### 8.2 Minutes played

Each time a player is on the court counts as a stint. Minutes played = total game time across all their stints.

```
game time elapsed = (period − 1) × period length + (period length − clock remaining)
stint length      = elapsed time when the player came off − elapsed time when they went on
```

### 8.3 Live plus-minus

**What it tells you:** the team's point difference while the player was on the court. This is *real*
plus-minus, unlike the season estimate in section 3.

**How:** every time either team scores, every player on the court gets **+points** if their team scored and
**−points** if the other team scored.

**Example:** a player is on the court for a run where their team scores 7 and the opponent scores 3 → **+4**.

### 8.4 Game clock

```
time left = clock at last start − seconds since the clock started   (never below 0:00)
```

### 8.5 Foul rules

| Rule | Threshold |
|---|---|
| Fouled out | **5** personal fouls |
| Foul trouble, periods 1–3 | **3** personal fouls |
| Foul trouble, period 4 | **4** personal fouls |

Only personal fouls count; technicals don't.

### 8.6 Miss streak

Missed shots in a row. A made shot resets the streak to 0, and free throws don't count. **3 misses in a row =
cold.**

### 8.7 Alerts

| Alert | Fires when |
|---|---|
| Hot player | 3 or more made shots in the current period |
| Cold player | 3 missed shots in a row |
| Foul trouble | at the thresholds in 8.5; flagged "disqualified" at 5 |
| Opponent run | the other team scores **8 or more** unanswered points (any score by your team resets it) |
| Team drought | **4 or more** empty possessions in a row: each missed shot, missed free throw or turnover adds 1, and any made shot or free throw resets it |
| Timeout prompt | an opponent run or a drought is active |
| Substitution prompt | a player is in foul trouble (checked first) or cold |
| Threat spike | a Keys to Win player turns "confirmed" (see 8.8) |

### 8.8 Keys to Win

**What it tells you:** the opponent's two most dangerous players, what makes each one dangerous, and who on
your team should guard them.

**Step 1 — threat score.** It uses each opponent player's season stats. Each ingredient is first rescaled 0–1
within the opponent roster: the best player = 1, the lowest = 0.

```
threat score = 0.45 × plus-minus + 0.35 × points + 0.20 × (rebounds + assists)   (each rescaled 0–1)
```

The **top 2** are shown. If two players are within 10% of each other, the one currently on the court ranks
higher.

**Step 2 — strength tag.** The player is compared with **your** team's median player. The largest edge becomes
the tag:

| Tag | Compared on |
|---|---|
| Scorer | points (or eFG%) |
| Playmaker | assists |
| Boarder | rebounds |
| Rim protector | blocks |
| Disruptor | steals |

**Step 3 — counter pick.**
- The pick comes from your players who haven't fouled out, preferring the same role as the threat.
- It is whoever has the highest defense score for that tag, minus 0.1 × their fouls per game.

| Tag | Defense score |
|---|---|
| Scorer | 0.4 STL + 0.4 DR + 0.2 BLK |
| Playmaker | 0.5 STL + 0.3 DR + 0.2 BLK |
| Boarder | 0.5 REB + 0.3 DR + 0.2 BLK |
| Rim protector | 0.35 BLK + 0.35 DR + 0.3 STL |
| Disruptor | 0.55 STL + 0.25 DR + 0.2 BLK |

**Step 4 — live status.**
- **Confirmed** — 3 or more made shots this period.
- **Fading** — cold (3 misses in a row) or in foul trouble.
- **Season** — otherwise; the pick is still based on season numbers.

### 8.9 Live lineup suggestion

**What it tells you:** the best players to fill the open spots on the floor right now, shown two ways: by
**season** form and by **tonight's** form.

**Step 1 — who can be suggested.** Rules are checked in this order:

1. Fouled out (5 fouls) → never.
2. Inactive → never.
3. Assigned to another coach → not suggested. If they're on the court, their spot counts as **fixed**.
4. Foul trouble → moved to the bottom:
   - Season column: 4 or more fouls.
   - Tonight column: 3 or more in periods 1–3, 4 or more in period 4.
5. Cold (3 misses in a row) → moved to the bottom, in the Tonight column only.

**Open spots** = 5 − fixed players.

**Step 2 — rank.** Both columns use the lineup score from section 5.1.
- **Season:** uses season stats.
- **Tonight:** uses tonight's stats scaled to a 36-minute rate. Players with less than **4 minutes** on the court
  are skipped.
  ```
  tonight's rate = tonight's total × 36 ÷ minutes played tonight
  ```
  Example: 8 points in 12 minutes → 8 × 36/12 = **24 points per 36**. FG% is used as is.

Open spots are filled from the top of each ranking. Players moved to the bottom fill any leftover spots and show no score.

"**N of M appear in both lists**" counts the players suggested in both columns.

### 8.10 Finishing a game

When a game ends, each player who started, played minutes or recorded any stat gets a **game history row**
saved:
- minutes (seconds ÷ 60)
- points and all shooting numbers
- rebounds, assists, steals, blocks, turnovers and fouls

That triggers the normal season-stat and plus-minus recalculation (section 1).

Live plus-minus and zone tags are **not** copied into the game history.

---

## 9. Dashboard

The Dashboard cards (Team Win Rate, Live Plus-Minus, Active Teams) and its two charts are **placeholders**.
They show sample data or "No data yet". **No calculations run for the Dashboard yet.**

---

## 10. When numbers refresh

| Result | Recalculated when… | Kept for |
|---|---|---|
| Season stats and plus-minus | A player's game history is added, edited, deleted or imported, or a live game is finished | Stored until the next change |
| Team averages, team plus-minus | Every page load | Not stored |
| Win probability, win rate | Team Comparison is opened and there is no stored result | Up to 24 hours; cleared whenever any of the team's players' season stats are recalculated |
| Recommended lineup | Same as win probability | Up to 24 hours; cleared the same way |
| Player matchup | The matchup is opened | Up to 24 hours |
| Keys to Win, alerts, live stats | Every recorded event | Recalculated every time |
| Live lineup suggestion | The panel is opened after any new event | Refreshed on every new event |

---

## 11. Known limitations

These describe how the system behaves **today**.

1. **No shot-probability model.** Zone percentages are plain made ÷ attempted, with only a 5-attempt minimum. There
   is no adjustment for small samples and no head-to-head zone comparison.
2. **Seeded demo shot zones are made up from fixed splits.** Every demo player gets the same pattern (60% paint /
   40% mid-range for twos, 20% / 20% / 60% for threes), so demo zone differences only reflect overall 2PT and 3PT %.
3. **Live games don't record steals or blocks**, so they are always 0 in the live box score and in the Tonight
   lineup ranking.
4. **"Scorer" tag mixes scales.** It compares points (for example +5) with eFG% (for example +0.05), so points
   almost always decide it.

---

## 12. Formula cheat sheet

```
FG% / 3P% / FT%   = season made ÷ season attempted
AST/TO            = AST ÷ TO                     STL/TO = STL ÷ TO
SC-EFF            = PTS ÷ FGA
SH-EFF            = (FGM + 0.5·3PM + 0.44·FTM − FGA) ÷ FGA
EFF               = PTS + REB + AST + STL + BLK − (FGA−FGM) − (FTA−FTM) − TO
eFG%              = (FGM + 0.5·3PM) ÷ FGA
TS%               = PTS ÷ (2·(FGA + 0.44·FTA))

Player plus-minus = (0.35·PTS + 0.20·AST + 0.15·REB + 0.10·FG% + 0.05·3P% + 0.08·BLK + 0.12·STL − 0.25·TO)·(36 ÷ MIN) − 5
Team plus-minus   = Σ(plus-minus × MIN) ÷ Σ MIN        (active players with minutes)

Team score        = 0.30·PTS + 0.15·REB + 0.15·AST + 0.15·FG% + 0.08·BLK + 0.08·STL − 0.09·TO
Win probability   = 1 ÷ (1 + e^−(score A − score B))
Win rate          = 0.5 + 0.8 × (win probability − 0.5)

Lineup score      = 0.30·PTS + 0.15·AST + 0.15·REB + 0.10·BLK + 0.10·STL + 0.10·FG% + 0.10·(beaten ÷ 6) − 0.20·TO
Lineup confidence = 1 ÷ (1 + e^−(average top-5 score))
OVR               = clamp(60, 99, round(1.8·PTS + 30·FG% + 1.2·AST + 0.8·REB + 0.5·plus-minus))

Matchup edge      = stats won ÷ 13
Radar value       = (stat − low end) ÷ (high end − low end) × 100   (kept between 0 and 100)

Zone make %       = made ÷ attempted × 100          (shown when attempted ≥ 5)
Points per shot   = zone make % × 2 or 3
Top-zone share    = top-zone attempts ÷ all zone attempts

Live plus-minus   = team points − opponent points while on court
Per-36 (tonight)  = tonight's total × 36 ÷ minutes tonight   (min. 4 minutes)
Threat score      = 0.45·plus-minus + 0.35·PTS + 0.20·(REB+AST)   (each rescaled 0–1 across the roster)
```

---

## 13. Where it lives in the code

For developers. All paths are relative to the repository root.

| Calculation | File |
|---|---|
| Season stats (section 2) | `app/Services/PlayerStatsAggregator.php` (run by `app/Jobs/RebuildPlayerStats.php`) |
| Player plus-minus | `analytics/plus_minus/calculator.py` (run by `app/Jobs/ComputePlayerPlusMinus.php`) |
| Team averages, team plus-minus | `app/Services/ComparisonAggregatorService.php` |
| Win probability, win rate, matchup edge | `analytics/win_probability/model.py` (run by `ComputeWinProbability`, `ComputePlayerMatchup`) |
| Lineup score and confidence | `analytics/lineup_optimizer/ranker.py` (run by `RecommendLineup`, `RecommendLiveLineup`) |
| OVR, net score | `resources/js/Components/features/lineup/LineupModal.tsx` |
| Radar chart | `resources/js/Components/features/comparison/PlayerMatchupTable.tsx` |
| Team plus-minus (Team page) | `resources/js/Pages/Teams/Show.tsx` |
| Shot zones | `app/Services/ShotZoneService.php`, `app/Repositories/ShotZoneRepository.php`, `app/Enums/ShotZone.php` |
| Zone colors | `resources/js/Components/features/comparison/ShotZoneCourt.tsx` |
| Live box score, minutes, live plus-minus | `app/Services/LiveGame/LiveGameProjectionService.php` |
| Alerts | `app/Services/LiveGame/LiveGameAlertService.php`, `LiveGameMissStreakCalculator.php` |
| Keys to Win | `app/Services/LiveGame/LiveGameKeysToWinService.php` |
| Live lineup suggestion | `app/Services/LiveGame/LiveLineupEligibilityFilter.php`, `LiveLineupPayloadBuilder.php`, `app/Jobs/RecommendLiveLineup.php` |
| Finishing a game | `app/Services/LiveGame/LiveGameFinalizer.php` |

The Python code under `analytics/app/` (a separate web-service version with its own formulas) is **not used** by
the app.
