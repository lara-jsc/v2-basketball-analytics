# HoopSense+ — System Guide (Plain English)

This guide explains how HoopSense+ works from start to finish — what each step does, why it matters, and how the numbers are calculated. No technical jargon.

---

## The Big Picture

HoopSense+ is a **pre-game coaching tool**. Before a game, a coaching staff uploads their team's roster and stats. The system then answers three questions:

1. **Who is likely to win, and by how much?**
2. **Which 5 players should we start against this opponent?**
3. **How does our player stack up against their player?**

Everything flows in this order:

```
Create Team → Add Players (roster only, via CSV or manually)
           → Add Player Game Histories (one entry per game played)
           → Stats auto-aggregated from all game entries
           → BPM auto-computed per player
           → Select two teams to compare
           → Get win probability, lineup recommendation, player matchups
```

---

## Step 1 — Create a Team

**Why:** A team is the container everything else lives inside. You cannot add players without a team. Think of it like creating a folder before you put files in it.

When you create a team, you provide:
- A **team code** (e.g., `LAL`, `GSW`) — short, unique identifier
- A **team name** (e.g., "Los Angeles Lakers")
- An optional **team logo**

Once a team exists, it becomes available in dropdowns for CSV upload and for comparison later.

---

## Step 2 — Add Players

There are two ways to add players to a team.

### Option A — Upload a CSV File

You download the official CSV template, fill in each player's information, and upload it. The system reads every row and creates or updates player records automatically.

**What the CSV contains:** Player identity info only — name, jersey number, position, height, weight, and whether they are active.

> **Important:** The roster CSV contains no stats. Stats come entirely from game history entries (Step 3 below).

### Option B — Add a Player Manually

Fill in a form with the player's basic identity details. This is useful for adding individual players who were not in the original upload.

---

## Step 3 — Add Player Game Histories

**Why:** This is where all the actual stats come from. Every game a player has played is recorded here as a separate entry. The system then reads all of those entries together to calculate the player's averages — and those averages are what power every analysis feature.

Think of it like this: the roster CSV tells the system *who is on the team*. The game history tells the system *how well each player actually plays*.

**Each game history entry records:**

| Field | Example |
|-------|---------|
| Game date | 2025-01-15 |
| Playing team | Lakers |
| Opponent team | Celtics |
| Position played | PG |
| Minutes played | 32.5 |
| Points | 22 |
| Field goals made / attempted | 8 / 16 |
| 3-pointers made / attempted | 3 / 7 |
| Free throws made / attempted | 3 / 4 |
| Offensive rebounds | 1 |
| Defensive rebounds | 5 |
| Total rebounds | 6 |
| Assists | 7 |
| Steals | 2 |
| Blocks | 1 |
| Turnovers | 3 |
| Personal / Flagrant / Technical fouls | 2 / 0 / 0 |
| Ejections / Disqualifications | 0 / 0 |
| Started the game? | Yes |
| Notes | Optional |

### Two ways to add game histories

**Option A — Upload an Excel file (.xlsx)**
Download the history template (different from the roster CSV). Fill in one row per game. The template includes dropdown menus for team selection and position. Upload it and all rows are imported at once.

**Option B — Add a game entry manually**
Use the form to enter one game at a time. Useful for adding individual games or correcting specific entries.

### What happens after you add a history entry?

Automatically, in the background:

1. The system **re-aggregates all game entries** for that player into career averages (points per game, assists per game, shooting percentages, etc.)
2. Those averaged stats are saved to the player's stat record
3. The **BPM (player rating) is recomputed** using the new averages
4. Any cached team comparisons involving this player are **invalidated** so they reflect the latest data

You do not need to trigger any of this — it happens on its own every time a history entry is added, edited, or deleted.

### Derived Stats Computed During the Rebuild

Beyond simple averages, the system also calculates three advanced efficiency stats automatically from the game history data:

---

#### Efficiency (EFF)

**What it measures:** A player's overall contribution in a single number — it rewards positive actions and penalizes negative ones.

```
EFF = Pts + Reb + Ast + Stl + Blk − Missed FG − Missed FT − TO
```

- **Missed FG** = Field Goals Attempted − Field Goals Made
- **Missed FT** = Free Throws Attempted − Free Throws Made
- All values are per-game averages

**Example:**

> Player averages: 18 pts, 6 reb, 7 ast, 1.5 stl, 1.2 blk, 8-16 FG, 3-4 FT, 2.8 TO

```
Missed FG = 16 − 8 = 8
Missed FT = 4 − 3 = 1

EFF = 18 + 6 + 7 + 1.5 + 1.2 − 8 − 1 − 2.8 = 21.9
```

A higher EFF means more overall impact per game.

---

#### Effective Field Goal % (eFG%)

**What it measures:** Shooting efficiency that gives extra credit to 3-pointers, since they are worth more than 2-pointers.

```
eFG% = (FGM + 0.5 × 3PM) / FGA
```

- **FGM** = Field Goals Made (per game average)
- **3PM** = Three-Pointers Made (per game average)
- **FGA** = Field Goals Attempted (per game average)
- The `0.5` bonus reflects that a made 3-pointer is 50% more valuable than a made 2-pointer

**Example:**

> Player averages: 8 FGM, 16 FGA, 3 three-pointers made

```
eFG% = (8 + 0.5 × 3) / 16
     = (8 + 1.5) / 16
     = 9.5 / 16
     = 0.594 → 59.4%
```

Compare to their raw FG% of `8/16 = 50%` — the eFG% of 59.4% tells a more accurate story because it accounts for the extra value of those 3-pointers.

---

#### True Shooting % (TS%)

**What it measures:** The most complete shooting efficiency stat — it accounts for all three ways a player scores: field goals, three-pointers, and free throws.

```
TS% = Pts / (2 × (FGA + 0.44 × FTA))
```

- **Pts** = Points per game
- **FGA** = Field Goals Attempted per game
- **FTA** = Free Throws Attempted per game
- `0.44` is a standard adjustment factor that accounts for the fact that not every free throw trip uses both attempts (e.g., and-ones, technical fouls)

**Example:**

> Player averages: 22 pts, 16 FGA, 4 FTA

```
TS% = 22 / (2 × (16 + 0.44 × 4))
    = 22 / (2 × (16 + 1.76))
    = 22 / (2 × 17.76)
    = 22 / 35.52
    = 0.619 → 61.9%
```

A TS% above 55% is considered good. Above 60% is excellent.

---

#### Scoring Efficiency (sc_eff)

**What it measures:** How many points a player produces per field goal attempt. A simple read on offensive output relative to shot volume.

```
sc_eff = Points per game ÷ Field Goals Attempted per game
```

**Example:**

> Player averages: 22 pts, 16 FGA

```
sc_eff = 22 ÷ 16 = 1.375
```

A score above 1.0 means the player averages more than one point per shot attempt — a strong sign of offensive efficiency.

---

#### Shooting Efficiency (sh_eff)

**What it measures:** A composite score that rewards made shots of all types and penalizes missed field goals. It captures the quality of a player's shooting decisions beyond raw percentages.

```
sh_eff = (FGM + 0.5 × 3PM + 0.44 × FTM − FGA) / FGA
```

- **FGM** = Field Goals Made
- **3PM** = Three-Pointers Made (bonus weight since they score more)
- **FTM** = Free Throws Made (0.44 factor — partial credit since FT trips don't always use both attempts)
- **FGA** = Field Goals Attempted (the denominator and the penalty)

**Example:**

> Player averages: 8 FGM, 16 FGA, 3 three-pointers made, 3 free throws made

```
sh_eff = (8 + 0.5×3 + 0.44×3 − 16) / 16
       = (8 + 1.5 + 1.32 − 16) / 16
       = −5.18 / 16
       = −0.324
```

A **positive** sh_eff means the player's shot selection adds net value. **Negative** means the missed attempts outweigh the makes — they're shooting too much or too inefficiently.

---

#### Assist-to-Turnover Ratio (AST/TO) and Steal-to-Turnover Ratio (STL/TO)

**What they measure:** Both ratios capture how well a player takes care of the ball relative to the good they create.

```
AST/TO = Assists per game ÷ Turnovers per game
STL/TO = Steals per game ÷ Turnovers per game
```

Both return `null` if turnovers = 0 (to avoid division by zero).

**Example:**

> Player averages: 7 ast, 1.5 stl, 2.8 to

```
AST/TO = 7 ÷ 2.8 = 2.50
STL/TO = 1.5 ÷ 2.8 = 0.54
```

- An AST/TO above **2.0** is considered good — the player creates at least two assists for every turnover.
- A higher STL/TO means the player takes the ball away more than they give it away.

---

#### Double-Doubles (DD2) and Triple-Doubles (TD3)

**What they measure:** Games where a player reached double digits (10+) in two or more — or three or more — of the five key stat categories: Points, Rebounds, Assists, Steals, Blocks.

The system automatically counts these across all game history entries:

- **DD2** — reached 10+ in at least 2 categories in a single game
- **TD3** — reached 10+ in at least 3 categories in a single game (also counted as a DD2)

**Example:**

> Game log: 12 pts, 11 reb, 3 ast, 1 stl, 2 blk

```
Categories with 10+: Points (12) ✓, Rebounds (11) ✓ → Double-Double
```

> Another game: 18 pts, 12 reb, 10 ast, 1 stl, 0 blk

```
Categories with 10+: Points ✓, Rebounds ✓, Assists ✓ → Triple-Double (and also a Double-Double)
```

These totals accumulate across the player's entire game history and are stored as `dd2` and `td3` in their stat record.

---

## Step 4 — Player Rating (Box Plus-Minus)

**What is it?** Every player gets a single score called **Box Plus-Minus (BPM)**. It answers: *"How much does this player help the team win compared to an average player?"*

- A **positive BPM** means the player is above average — they help the team more than they hurt it.
- A **negative BPM** means the player is below average.
- **Zero** is exactly average.

**When does it compute?** Automatically, right after a player's stats are saved. You don't do anything — it runs in the background.

---

### The BPM Formula (Plain English)

The system takes the player's stats, assigns importance weights to each one, adds up the good contributions, subtracts the bad ones, and then adjusts for how many minutes the player plays.

```
BPM = (good contributions − bad contributions) × (36 ÷ minutes played) − 5
```

**Good contributions:**

| Stat | What it measures | Weight |
|------|-----------------|--------|
| Points per game | Scoring | 35% |
| Assists per game | Playmaking | 20% |
| Rebounds per game | Board control | 15% |
| Field Goal % | Shooting efficiency | 10% |
| Steals per game | Defense / ball pressure | 12% |
| Blocks per game | Paint defense | 8% |
| 3-Point % | Perimeter shooting | 5% |

**Bad contributions (penalty):**

| Stat | What it measures | Penalty |
|------|-----------------|---------|
| Turnovers per game | Giving the ball away | 25% |

**Why divide by minutes and multiply by 36?**
This puts everyone on an equal footing. A player who scores 20 points in 40 minutes is not as efficient as one who scores 20 points in 25 minutes. The formula normalizes everything to a standard 36-minute game.

**Why subtract 5?**
This is the league baseline. It shifts the scale so that a perfectly average player lands at exactly 0.

---

### BPM Example

> **Player: Marcus Webb**
> Stats: 18 pts, 7 ast, 5 reb, 48% FG, 36% 3P, 1.2 blk, 1.5 stl, 2.8 to, 34 min

**Step 1 — Add up the good:**
```
(18 × 0.35) + (7 × 0.20) + (5 × 0.15) + (0.48 × 0.10) + (0.36 × 0.05) + (1.2 × 0.08) + (1.5 × 0.12)
=  6.30   +   1.40   +   0.75   +   0.048   +   0.018   +  0.096  +   0.18
=  8.79
```

**Step 2 — Subtract the bad:**
```
2.8 × 0.25 = 0.70
net = 8.79 − 0.70 = 8.09
```

**Step 3 — Normalize to 36 minutes:**
```
8.09 × (36 ÷ 34) = 8.09 × 1.059 = 8.57
```

**Step 4 — Subtract league baseline:**
```
8.57 − 5.0 = +3.57
```

**Result: BPM = +3.57** — Marcus is a solid above-average player. Every 36 minutes he plays, his team outperforms opponents by about 3.57 points more than if an average player were in his spot.

---

## Step 5 — Team Comparison

Once both teams have players with computed BPM scores, you can compare them head-to-head.

Select **Team A** (your team) and **Team B** (the opponent). The system will calculate:

1. **Win Probability** — which team is more likely to win, as a percentage
2. **Win Rate** — a more conservative version of win probability
3. **Team Plus-Minus** — the team's collective impact score
4. **Lineup Recommendation** — the best 5 players to start
5. **Player Matchup** — head-to-head comparison for any two players

### Results Are Not Instant

Win Probability and Lineup Recommendation are **not computed immediately** when you open the comparison page. They run in the background as separate jobs. Here is what to expect:

1. You select two teams and open the comparison page
2. The page loads right away, but the win probability and lineup sections show a **loading indicator**
3. The page automatically checks for results **every 3 seconds**
4. Once the background jobs finish (usually within a few seconds), the numbers appear automatically — no refresh needed

**Why is it done this way?** The Python analytics engine does the heavy computation. Running it in the background prevents the page from freezing or timing out while waiting for results.

**What if BPM shows "—" instead of a number?** This means the player's plus-minus hasn't been computed yet — either their game history was just added, or the background job is still running. It will fill in automatically once complete. A player showing "—" is still included in win probability calculations using their other stats.

---

### Win Probability

**What is it?** A percentage that tells you how likely each team is to win based on their combined stats.

**The formula in plain English:** The system computes a "strength score" for each team using their average stats, then converts the difference between those scores into a probability using a math function called a **logistic curve** (S-curve). The bigger the gap, the higher the probability for the stronger team — but it never reaches 0% or 100%.

**Strength Score Weights:**

| Stat | Weight |
|------|--------|
| Points per game | 30% |
| Rebounds per game | 15% |
| Assists per game | 15% |
| Field Goal % | 15% |
| Blocks per game | 8% |
| Steals per game | 8% |
| Turnovers per game | −9% (penalty) |

```
strength_score = (avg_pts × 0.30) + (avg_reb × 0.15) + (avg_ast × 0.15)
               + (avg_fg_pct × 0.15) + (avg_blk × 0.08) + (avg_stl × 0.08)
               − (avg_to × 0.09)

win_probability = 1 ÷ (1 + e^(−difference))
```

---

### Win Probability Example

> **Team A averages:** 102 pts, 44 reb, 24 ast, 46% FG, 5.2 blk, 7.8 stl, 13.5 to
> **Team B averages:** 95 pts, 40 reb, 21 ast, 43% FG, 4.1 blk, 6.5 stl, 15.0 to

**Team A strength score:**
```
(102 × 0.30) + (44 × 0.15) + (24 × 0.15) + (0.46 × 0.15) + (5.2 × 0.08) + (7.8 × 0.08) − (13.5 × 0.09)
= 30.6 + 6.6 + 3.6 + 0.069 + 0.416 + 0.624 − 1.215
= 40.69
```

**Team B strength score:**
```
(95 × 0.30) + (40 × 0.15) + (21 × 0.15) + (0.43 × 0.15) + (4.1 × 0.08) + (6.5 × 0.08) − (15.0 × 0.09)
= 28.5 + 6.0 + 3.15 + 0.0645 + 0.328 + 0.52 − 1.35
= 37.21
```

**Difference:** `40.69 − 37.21 = 3.48`

**Win probability for Team A:**
```
1 ÷ (1 + e^(−3.48)) ≈ 1 ÷ (1 + 0.031) ≈ 0.97 → 97%
```

**Result:** Team A has a **97% win probability** against Team B.

---

### Win Rate

Win rate is a **conservative version** of win probability. It pulls the number closer to 50% to avoid overconfidence.

```
win_rate = 0.5 + 0.8 × (win_probability − 0.5)
```

**Example:**
```
win_rate = 0.5 + 0.8 × (0.97 − 0.5)
         = 0.5 + 0.8 × 0.47
         = 0.5 + 0.376
         = 0.876 → 87.6%
```

So instead of showing 97%, the system displays **87.6%** — still strongly in Team A's favor, but more grounded.

---

### Team Plus-Minus

**What is it?** The team's overall impact score — a single number representing how much the team collectively outperforms an average opponent per game.

**How it's calculated:** It's the **minutes-weighted average** of each active player's BPM. Players who play more minutes have more influence on the team score.

```
Team Plus-Minus = (player1_BPM × player1_min + player2_BPM × player2_min + ...)
                ÷ (player1_min + player2_min + ...)
```

Only active players with a computed BPM and minutes played are included.

**Example:**

| Player | BPM | Minutes |
|--------|-----|---------|
| Marcus Webb | +3.57 | 34 |
| Devon Hall | +1.20 | 30 |
| Tyrell King | −0.80 | 28 |
| Jamal Cruz | +2.10 | 25 |
| Rodney Vance | +0.50 | 22 |

```
Numerator = (3.57×34) + (1.20×30) + (−0.80×28) + (2.10×25) + (0.50×22)
          = 121.38 + 36.0 − 22.4 + 52.5 + 11.0
          = 198.48

Denominator = 34 + 30 + 28 + 25 + 22 = 139 minutes

Team Plus-Minus = 198.48 ÷ 139 = +1.43
```

**Result:** This team collectively outperforms an average opponent by **+1.43 points per game**.

---

## Step 6 — Lineup Recommendation

**What is it?** Given your team vs. a specific opponent, the system ranks every active player on your team by how well they'd perform against that opponent, then picks the top 5.

**What makes it different from just using BPM?** The lineup score includes an **opponent adjustment** — players who outperform the opponent's averages in multiple stats get bonus points.

---

### Lineup Score Formula

```
lineup_score = base_score + opponent_bonus − turnover_penalty

base_score    = (pts × 0.30) + (ast × 0.15) + (reb × 0.15)
              + (blk × 0.10) + (stl × 0.10) + (fg_pct × 0.10)

opponent_bonus = (number of stats where player beats opponent avg ÷ 6) × 0.10

turnover_penalty = to_per_game × 0.20
```

**The 6 stats compared for opponent bonus:** points, assists, rebounds, blocks, steals, FG%

---

### Lineup Example

> **Opponent averages:** 18 pts, 5 ast, 6 reb, 4 blk, 2 stl, 44% FG

> **Player: Marcus Webb** — 18 pts, 7 ast, 5 reb, 48% FG, 1.2 blk, 1.5 stl, 2.8 to

**Base score:**
```
(18 × 0.30) + (7 × 0.15) + (5 × 0.15) + (1.2 × 0.10) + (1.5 × 0.10) + (0.48 × 0.10)
= 5.4 + 1.05 + 0.75 + 0.12 + 0.15 + 0.048
= 7.518
```

**Opponent comparison:**

| Stat | Marcus | Opp Avg | Marcus Wins? |
|------|--------|---------|--------------|
| Points | 18 | 18 | No (tied) |
| Assists | 7 | 5 | Yes |
| Rebounds | 5 | 6 | No |
| Blocks | 1.2 | 4 | No |
| Steals | 1.5 | 2 | No |
| FG% | 48% | 44% | Yes |

Marcus beats the opponent average in **2 out of 6** stats.

```
opponent_bonus = (2 ÷ 6) × 0.10 = 0.333 × 0.10 = 0.033
```

**Turnover penalty:**
```
2.8 × 0.20 = 0.56
```

**Final lineup score:**
```
7.518 + 0.033 − 0.56 = 6.99
```

The top 5 players by this score make the recommended starting lineup.

---

### Confidence Score

After the top 5 are selected, the system reports a **confidence level** — how sure the algorithm is that this lineup is genuinely strong.

```
confidence = 1 ÷ (1 + e^(−average_score_of_top_5))
```

A confidence close to **1.0** means the lineup clearly outperforms the opponent. Closer to **0.5** means it's more of a coin flip.

---

## Step 7 — Player vs. Player Matchup

**What is it?** Pick one player from each team. The system compares them stat-by-stat and calculates an **edge score** — the fraction of categories where each player wins.

**Stats compared (13 total):**
Points, Assists, Rebounds, Blocks, Steals, FG%, 3P%, Defensive Rebounds, Offensive Rebounds, Minutes, Efficiency, Effective FG%, True Shooting %

```
edge_score_A = (number of stats where Player A > Player B) ÷ 13
edge_score_B = (number of stats where Player B > Player A) ÷ 13
```

> Tied stats don't count for either player, so both edge scores may add up to less than 1.0.

---

### Player Matchup Example

> **Player A — Marcus Webb:** 18 pts, 7 ast, 5 reb, 1.2 blk, 1.5 stl, 48% FG, 36% 3P, 4.2 DR, 1.1 OR, 34 min
> **Player B — James Porter:** 22 pts, 4 ast, 9 reb, 3.1 blk, 0.9 stl, 44% FG, 31% 3P, 7.8 DR, 2.3 OR, 36 min

| Stat | Marcus | James | Winner |
|------|--------|-------|--------|
| Points | 18 | 22 | James |
| Assists | 7 | 4 | Marcus |
| Rebounds | 5 | 9 | James |
| Blocks | 1.2 | 3.1 | James |
| Steals | 1.5 | 0.9 | Marcus |
| FG% | 48% | 44% | Marcus |
| 3P% | 36% | 31% | Marcus |
| Def. Rebounds | 4.2 | 7.8 | James |
| Off. Rebounds | 1.1 | 2.3 | James |
| Minutes | 34 | 36 | James |
| Efficiency* | — | — | (tied or computed) |
| Eff. FG%* | — | — | |
| True Shooting%* | — | — | |

*(Efficiency stats are computed from the other stats above)

Assume Marcus wins 5 of 13, James wins 7 of 13, 1 tied.

```
Marcus edge score = 5 ÷ 13 = 0.38 → 38%
James edge score  = 7 ÷ 13 = 0.54 → 54%
```

**Result:** James Porter has the statistical edge in this matchup (54% vs 38%). The stats he dominates (pts, reb, blk) are highlighted in gold on the UI.

---

## Summary: Why Each Step Matters

| Step | What You Do | Why It Matters |
|------|------------|----------------|
| 1. Create Team | Set up a team container | Players need a team to belong to |
| 2. Add Players (CSV or manual) | Load the roster — names, numbers, positions | No players = nothing to analyze |
| 3. Add Player Game Histories | Record each game a player has played | This is the source of all stats — no history = no averages |
| 3a. Stats Rebuild (auto) | Nothing — system does it | Computes averages + EFF, eFG%, TS%, sc_eff, sh_eff, AST/TO, STL/TO, DD2, TD3 |
| 4. BPM Computed (auto) | Nothing — system does it after every history change | Rates every player so comparisons are meaningful |
| 5. Team Comparison | Select two teams | Produces win probability and lineup advice |
| 6. Lineup Recommendation | Triggered automatically | Tells you which 5 to start against this specific opponent |
| 7. Player Matchup | Pick two players | Shows who has the statistical edge in a head-to-head |

---

## Glossary

| Term | Meaning |
|------|---------|
| **BPM (Box Plus-Minus)** | A player's net contribution per 36 minutes relative to an average player. Positive = above average. |
| **Win Probability** | Raw statistical likelihood of winning, derived from team strength scores. |
| **Win Rate** | A conservative version of win probability, pulled toward 50% to avoid overconfidence. |
| **Team Plus-Minus** | Minutes-weighted average BPM across all active players — measures overall team quality. |
| **Lineup Score** | A player's value rating adjusted for the specific opponent being faced. |
| **Edge Score** | Fraction of stats where a player beats their opponent in a head-to-head matchup. |
| **Confidence** | How strongly the algorithm believes the recommended lineup will perform well. |
| **Active Player** | A player marked `is_active = true` — only these appear in lineups and comparisons. |
| **Logistic Curve** | An S-shaped math function that converts any number into a value between 0% and 100%. Used for win probability. |
| **Per-36 Normalization** | Scaling stats to a standard 36-minute game so players with different playing times can be fairly compared. |
| **Minutes-Weighted Average** | An average where players who play more minutes count more toward the team total. |
| **Game History Entry** | A single game's worth of box score stats for one player — the raw input that all averages are built from. |
| **Stats Rebuild** | The automatic process that re-averages all of a player's game history entries into a single stats record after any change. |
| **EFF (Efficiency)** | Pts + Reb + Ast + Stl + Blk − Missed FG − Missed FT − TO. A catch-all impact score per game. |
| **eFG% (Effective Field Goal %)** | (FGM + 0.5 × 3PM) / FGA. Shooting efficiency that gives extra credit to made 3-pointers. |
| **TS% (True Shooting %)** | Pts / (2 × (FGA + 0.44 × FTA)). The most complete shooting efficiency stat — accounts for FG, 3P, and FT. |
| **sc_eff (Scoring Efficiency)** | Points per game ÷ FGA per game. Points produced per shot attempt. |
| **sh_eff (Shooting Efficiency)** | (FGM + 0.5×3PM + 0.44×FTM − FGA) / FGA. Net shooting value accounting for all makes and misses. Positive = net gain. |
| **AST/TO (Assist-to-Turnover Ratio)** | Assists per game ÷ Turnovers per game. Measures playmaking value vs. ball loss. Above 2.0 is good. |
| **STL/TO (Steal-to-Turnover Ratio)** | Steals per game ÷ Turnovers per game. Measures defensive ball-winning vs. ball loss. |
| **DD2 (Double-Double)** | A game where a player reaches 10+ in at least 2 of: Points, Rebounds, Assists, Steals, Blocks. |
| **TD3 (Triple-Double)** | A game where a player reaches 10+ in at least 3 of: Points, Rebounds, Assists, Steals, Blocks. Also counted as a DD2. |
| **Background Job** | A task the system runs on its own after a user action — like recomputing BPM after a history entry is saved. The user doesn't wait for it. |
| **Polling** | The page automatically checks every 3 seconds for results that are being computed in the background (win probability, lineup). |
