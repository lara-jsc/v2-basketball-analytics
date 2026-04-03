"""
Win probability and player matchup models.

Win Probability Input:
{
    "team_a_stats": { "avg_pts": float, "avg_reb": float, ... },
    "team_b_stats": { "avg_pts": float, "avg_reb": float, ... }
}

Win Probability Output:
{
    "team_a_win_probability": float,
    "team_b_win_probability": float,
    "team_a_win_rate": float,
    "team_b_win_rate": float
}

Player Matchup Output:
{
    "player_a_edge_score": float,
    "player_b_edge_score": float,
    "stronger_stats_a": list[str],
    "stronger_stats_b": list[str]
}
"""

from __future__ import annotations

import math

from analytics.utils.normalizer import safe_float


# Win probability weights — higher weight = more predictive of winning
_WIN_PROB_WEIGHTS: dict[str, float] = {
    "avg_pts": 0.30,
    "avg_reb": 0.15,
    "avg_ast": 0.15,
    "avg_fg_pct": 0.15,
    "avg_blk": 0.08,
    "avg_stl": 0.08,
    "avg_to_per_game": -0.09,  # turnovers negative
}


def _team_score(stats: dict) -> float:
    score: float = 0.0
    for key, weight in _WIN_PROB_WEIGHTS.items():
        score += safe_float(stats.get(key, 0)) * weight
    return score


def _logistic(x: float) -> float:
    return 1.0 / (1.0 + math.exp(-x))


def compute_win_probability(payload: dict) -> dict[str, float]:
    """
    Returns win probability and win rate for both teams.
    Probabilities sum to 1.0.
    """
    team_a_stats: dict = payload.get("team_a_stats", {})
    team_b_stats: dict = payload.get("team_b_stats", {})

    score_a: float = _team_score(team_a_stats)
    score_b: float = _team_score(team_b_stats)

    diff: float = score_a - score_b
    prob_a: float = round(_logistic(diff), 4)
    prob_b: float = round(1.0 - prob_a, 4)

    # Win rate — apply a mild regression toward 0.5 compared to raw probability
    _REGRESSION: float = 0.8
    win_rate_a: float = round(0.5 + _REGRESSION * (prob_a - 0.5), 4)
    win_rate_b: float = round(1.0 - win_rate_a, 4)

    return {
        "team_a_win_probability": prob_a,
        "team_b_win_probability": prob_b,
        "team_a_win_rate": win_rate_a,
        "team_b_win_rate": win_rate_b,
    }


# Stats compared head-to-head in player matchup
_MATCHUP_STAT_KEYS: list[str] = [
    "pts", "ast", "reb", "blk", "stl", "fg_pct",
    "three_p_pct", "dr", "offensive_rebounds", "min",
]


def compute_player_matchup(payload: dict) -> dict[str, object]:
    """
    Returns edge scores and which stats each player wins.
    """
    player_a: dict = payload.get("player_a", {})
    player_b: dict = payload.get("player_b", {})

    stronger_a: list[str] = []
    stronger_b: list[str] = []

    for key in _MATCHUP_STAT_KEYS:
        val_a: float = safe_float(player_a.get(key, 0))
        val_b: float = safe_float(player_b.get(key, 0))
        if val_a > val_b:
            stronger_a.append(key)
        elif val_b > val_a:
            stronger_b.append(key)

    total: int = len(_MATCHUP_STAT_KEYS)
    edge_a: float = round(len(stronger_a) / total, 4) if total else 0.0
    edge_b: float = round(len(stronger_b) / total, 4) if total else 0.0

    return {
        "player_a_edge_score": edge_a,
        "player_b_edge_score": edge_b,
        "stronger_stats_a": stronger_a,
        "stronger_stats_b": stronger_b,
    }
