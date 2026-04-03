# Computation Documentation

This document explains how the current repository computes:

- player plus/minus
- team aggregate stats
- team plus/minus
- win probability
- win rate
- recommended lineup / starting 5
- player-vs-player comparison

It is based on the code paths that are actively wired into the Laravel app:

- Laravel jobs prepare the payloads
- `analytics/engine.py` runs the Python computations
- the results are cached and then shown in the UI

## 1. Where the numbers come from

### Source data

Most computations use the latest `player_stats` row of each player.

Team-level comparison only includes players who are:

- assigned to the selected team
- `is_active = true`

This is loaded by `ComparisonRepository::activPlayersWithStats()`.

### CSV import and manual entry

Stats can enter the system in two ways:

- manual player create/update
- CSV upload

Both paths populate `player_stats`, and the Python BPM job is then dispatched to compute `plus_minus`.

### Missing values

The Python side uses `safe_float()` for numeric values.

That means:

- invalid or missing numbers become `0.0`
- computations do not crash because of bad numeric input

On the Laravel side:

- `aggregateStats()` returns `0.0` for every team average if no stat rows exist
- `teamPlusMinus()` returns `null` if no eligible player has a computed `plus_minus`

## 2. Player Plus-Minus

Player plus-minus is computed in `analytics/plus_minus/calculator.py`.

### Input fields used

- `pts`
- `ast`
- `reb`
- `fg_pct`
- `three_p_pct`
- `blk`
- `stl`
- `to_per_game`
- `min`

### Formula

Positive contribution:

```text
positive =
  (pts * 0.35) +
  (ast * 0.20) +
  (reb * 0.15) +
  (fg_pct * 0.10) +
  (three_p_pct * 0.05) +
  (blk * 0.08) +
  (stl * 0.12)
```

Negative contribution:

```text
negative = to_per_game * 0.25
```

Minutes normalization:

```text
minutes = max(min, 1.0)
raw_bpm = (positive - negative) * (36.0 / minutes)
```

League baseline adjustment:

```text
plus_minus = round(raw_bpm - 5.0, 2)
```

### Important notes

- `min` is clamped to at least `1.0` to avoid division by zero or near-zero inflation.
- The result is stored in `player_stats.plus_minus`.
- If the BPM job fails, `plus_minus` stays `null`.

## 3. Team Aggregate Stats

Team aggregate stats are built in `ComparisonAggregatorService::aggregateStats()`.

Only active players are included, and only their latest stats row is used.

### Averages computed

For a selected team:

```text
avg_pts         = round(mean(pts), 2)
avg_reb         = round(mean(reb), 2)
avg_ast         = round(mean(ast), 2)
avg_fg_pct      = round(mean(fg_pct), 2)
avg_blk         = round(mean(blk), 2)
avg_stl         = round(mean(stl), 2)
avg_to_per_game = round(mean(to_per_game), 2)
```

If the team has no available stats rows, every average above becomes `0.0`.

These aggregated values are the direct input to the win probability model.

## 4. Team Plus-Minus

Team plus-minus is computed in `ComparisonAggregatorService::teamPlusMinus()`.

### Eligible players

A player is included only if:

- the player is active
- `plus_minus` is not `null`
- `min > 0`

### Formula

This is a minutes-weighted average:

```text
team_plus_minus =
  round(sum(player_plus_minus * player_minutes) / sum(player_minutes), 2)
```

If no player is eligible, the result is `null`.

## 5. Win Probability

Win probability is computed in `analytics/win_probability/model.py`.

The Laravel job `ComputeWinProbability` sends this payload:

```text
team_a_stats = aggregateStats(team A)
team_b_stats = aggregateStats(team B)
```

### Team score formula

Each team gets a weighted score:

```text
team_score =
  (avg_pts * 0.30) +
  (avg_reb * 0.15) +
  (avg_ast * 0.15) +
  (avg_fg_pct * 0.15) +
  (avg_blk * 0.08) +
  (avg_stl * 0.08) +
  (avg_to_per_game * -0.09)
```

Turnovers reduce the score because the turnover weight is negative.

### Probability formula

```text
diff   = team_score_a - team_score_b
prob_a = 1 / (1 + e^(-diff))
prob_b = 1 - prob_a
```

Stored output:

```text
team_a_win_probability = round(prob_a, 4)
team_b_win_probability = round(prob_b, 4)
```

### Interpretation

- if both teams are equal, the probability is close to `0.5000` each
- if team A has stronger weighted averages, `team_a_win_probability` rises above `0.5000`
- the two win probabilities always sum to `1.0` after rounding logic

## 6. Win Rate

Win rate is derived from win probability in the same Python file.

It is not a separate model. It is a regressed version of the raw probability.

### Formula

```text
win_rate_a = round(0.5 + 0.8 * (prob_a - 0.5), 4)
win_rate_b = round(1.0 - win_rate_a, 4)
```

### Interpretation

This moves the result slightly closer to `50%`.

Example:

- a raw probability of `0.90` becomes a softer win rate of `0.82`
- a raw probability of `0.10` becomes a softer win rate of `0.18`

So:

- `win_probability` is the stronger model output
- `win_rate` is the milder, less extreme display value

## 7. Recommended Lineup / Starting 5

The recommended lineup is computed in `analytics/lineup_optimizer/ranker.py`.

The Laravel job `RecommendLineup` sends:

- all active players from the home team
- all active players from the opponent team

### Opponent averages

First, the model computes opponent averages for these stats:

- `pts`
- `ast`
- `reb`
- `blk`
- `stl`
- `fg_pct`

For each stat:

```text
opponent_avg(stat) = mean(opponent_players[stat])
```

### Player lineup score

For every player on the home team:

```text
positive =
  (pts * 0.30) +
  (ast * 0.15) +
  (reb * 0.15) +
  (blk * 0.10) +
  (stl * 0.10) +
  (fg_pct * 0.10)
```

Turnover penalty:

```text
negative = to_per_game * 0.20
```

Opponent adjustment bonus:

```text
opponent_adj =
  (
    0.10 for each tracked stat where player_stat > opponent_avg(stat)
  ) / number_of_tracked_stats
```

Because there are 6 tracked comparison stats, the maximum opponent adjustment is:

```text
0.10
```

Final lineup score:

```text
player_lineup_score = positive + opponent_adj - negative
```

### Selection logic

- sort home-team players by `player_lineup_score` descending
- take the top 5 players
- return them as `recommended_lineup`

Each returned player includes:

- `player_id`
- `name`
- `plus_minus_score`

### Important note about `plus_minus_score`

In the lineup result, `plus_minus_score` is only the lineup ranking score.

It is not the stored BPM/player `plus_minus` from `player_stats.plus_minus`.

The field name is a UI/API label, but the formula is the lineup score described above.

### Confidence score

Confidence is based on the average score of the recommended top 5:

```text
avg_top_scores = mean(recommended_lineup.plus_minus_score)
confidence     = round(1 / (1 + e^(-avg_top_scores)), 4)
```

Interpretation:

- higher average lineup scores produce higher confidence
- confidence is between `0.0` and `1.0`

## 8. Player-vs-Player Comparison

Player matchup logic is computed in `analytics/win_probability/model.py` by `compute_player_matchup()`.

The Laravel job `ComputePlayerMatchup` sends the latest stat row of both selected players.

### Stats that affect the edge score

These 10 stat keys are used:

- `pts`
- `ast`
- `reb`
- `blk`
- `stl`
- `fg_pct`
- `three_p_pct`
- `dr`
- `offensive_rebounds`
- `min`

### Comparison rule

For each of the 10 stats:

- if player A's value is higher, player A wins that stat
- if player B's value is higher, player B wins that stat
- if equal, nobody gets the point

### Edge score formula

```text
edge_score_a = round(number_of_stats_won_by_a / 10, 4)
edge_score_b = round(number_of_stats_won_by_b / 10, 4)
```

The response also includes:

- `stronger_stats_a`: list of stat keys won by player A
- `stronger_stats_b`: list of stat keys won by player B

### Important limitation

The UI table displays more stats than the actual matchup edge model uses.

Displayed in the table but not included in `compute_player_matchup()` edge scoring:

- `plus_minus`
- `to_per_game`
- `ft_pct`
- `ast_to`
- `stl_to`
- `sc_eff`
- `sh_eff`
- `pf`
- `gp`

That means those values may appear in the comparison table, but they do not change:

- `player_a_edge_score`
- `player_b_edge_score`
- `stronger_stats_a`
- `stronger_stats_b`

## 9. End-to-End Flow

### Player plus/minus flow

1. A player stat row is created or updated.
2. Laravel dispatches `ComputePlayerPlusMinus`.
3. The Python engine runs the BPM formula.
4. The result is saved into `player_stats.plus_minus`.

### Team comparison flow

1. User opens comparison page for Team A vs Team B.
2. Laravel loads active players and latest stats.
3. Laravel computes local team aggregates and team plus-minus.
4. If cache is missing:
   - dispatch `ComputeWinProbability`
   - dispatch `RecommendLineup`
5. Frontend polls until results exist.

### Player matchup flow

1. User selects one player from each team.
2. If cache is missing, Laravel dispatches `ComputePlayerMatchup`.
3. Frontend polls until the matchup result is available.

## 10. Caching Rules

### Win probability

- cache TTL: 24 hours
- cache key is symmetric: Team A vs Team B is the same as Team B vs Team A
- team cache versions are included in the key
- CSV upload invalidates related team comparison cache by incrementing team cache version

### Lineup recommendation

- cache TTL: 24 hours
- cache key is ordered: home team vs opponent is not the same as opponent vs home team
- team cache versions are included in the key

### Player matchup

- cache TTL: 24 hours
- cache key is symmetric by player ID ordering

## 11. Rounding Rules Summary

- player `plus_minus`: 2 decimals
- team aggregate stats: 2 decimals
- team plus-minus: 2 decimals
- lineup player score (`plus_minus_score`): 3 decimals
- lineup confidence: 4 decimals
- win probability: 4 decimals
- win rate: 4 decimals
- player edge score: 4 decimals

## 12. File Map

Main files that define the current computations:

- `app/Services/ComparisonAggregatorService.php`
- `app/Jobs/ComputePlayerPlusMinus.php`
- `app/Jobs/ComputeWinProbability.php`
- `app/Jobs/RecommendLineup.php`
- `app/Jobs/ComputePlayerMatchup.php`
- `app/Services/PythonEngineService.php`
- `analytics/engine.py`
- `analytics/plus_minus/calculator.py`
- `analytics/lineup_optimizer/ranker.py`
- `analytics/win_probability/model.py`

## 13. Short Formula Reference

### Player plus-minus

```text
plus_minus =
  round(
    (
      (
        pts*0.35 + ast*0.20 + reb*0.15 + fg_pct*0.10 +
        three_p_pct*0.05 + blk*0.08 + stl*0.12
      ) - (to_per_game*0.25)
    ) * (36 / max(min, 1.0)) - 5.0,
    2
  )
```

### Team plus-minus

```text
team_plus_minus = round(sum(plus_minus * min) / sum(min), 2)
```

### Team score for win probability

```text
team_score =
  avg_pts*0.30 +
  avg_reb*0.15 +
  avg_ast*0.15 +
  avg_fg_pct*0.15 +
  avg_blk*0.08 +
  avg_stl*0.08 -
  avg_to_per_game*0.09
```

### Win probability

```text
prob_a = 1 / (1 + e^-(team_score_a - team_score_b))
prob_b = 1 - prob_a
```

### Win rate

```text
win_rate_a = 0.5 + 0.8 * (prob_a - 0.5)
win_rate_b = 1 - win_rate_a
```

### Lineup score

```text
player_lineup_score =
  (
    pts*0.30 + ast*0.15 + reb*0.15 +
    blk*0.10 + stl*0.10 + fg_pct*0.10
  ) +
  opponent_adjustment -
  (to_per_game*0.20)
```

### Player matchup edge score

```text
edge_score = stats_won / 10
```
