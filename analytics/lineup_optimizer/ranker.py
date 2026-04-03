"""
Lineup recommendation ranker.

Input payload (from Laravel RecommendLineup Job):
{
    "home_team_players": [
        { "player_id": int, "name": str, "pts": float, "ast": float, ... }
    ],
    "opponent_team_players": [
        { "player_id": int, "name": str, "pts": float, ... }
    ]
}

Output:
{
    "recommended_lineup": [
        { "player_id": int, "name": str, "plus_minus_score": float },
        ...  (up to 5 players)
    ],
    "confidence": float  (0.0 – 1.0)
}
"""

from __future__ import annotations

import math

from analytics.utils.normalizer import safe_float


_LINEUP_SIZE: int = 5

# Scoring weights for lineup ranking
_WEIGHT_PTS: float = 0.30
_WEIGHT_AST: float = 0.15
_WEIGHT_REB: float = 0.15
_WEIGHT_BLK: float = 0.10
_WEIGHT_STL: float = 0.10
_WEIGHT_FG_PCT: float = 0.10
_WEIGHT_OPPONENT_ADJ: float = 0.10  # bonus for outperforming opponent average in each stat
_PENALTY_TO: float = 0.20


def _opponent_avg(players: list[dict], key: str) -> float:
    values = [safe_float(p.get(key, 0)) for p in players]
    return sum(values) / len(values) if values else 0.0


def _player_score(player: dict, opponent_avgs: dict[str, float]) -> float:
    pts: float = safe_float(player.get("pts", 0))
    ast: float = safe_float(player.get("ast", 0))
    reb: float = safe_float(player.get("reb", 0))
    blk: float = safe_float(player.get("blk", 0))
    stl: float = safe_float(player.get("stl", 0))
    fg_pct: float = safe_float(player.get("fg_pct", 0))
    to: float = safe_float(player.get("to_per_game", 0))

    positive: float = (
        pts * _WEIGHT_PTS
        + ast * _WEIGHT_AST
        + reb * _WEIGHT_REB
        + blk * _WEIGHT_BLK
        + stl * _WEIGHT_STL
        + fg_pct * _WEIGHT_FG_PCT
    )

    # Bonus for outperforming opponent average
    opponent_adj: float = sum(
        _WEIGHT_OPPONENT_ADJ
        for stat_key, opp_avg in opponent_avgs.items()
        if safe_float(player.get(stat_key, 0)) > opp_avg
    ) / max(len(opponent_avgs), 1)

    negative: float = to * _PENALTY_TO

    return positive + opponent_adj - negative


def _confidence(scores: list[float]) -> float:
    """Sigmoid-based confidence derived from top-5 score spread."""
    if not scores:
        return 0.0
    avg: float = sum(scores) / len(scores)
    return round(1 / (1 + math.exp(-avg)), 4)


def recommend_lineup(payload: dict) -> dict[str, object]:
    """
    Returns the top-N home team players ranked against the opponent.
    """
    home_players: list[dict] = payload.get("home_team_players", [])
    opponent_players: list[dict] = payload.get("opponent_team_players", [])

    if not home_players:
        return {"recommended_lineup": [], "confidence": 0.0}

    stat_keys = ("pts", "ast", "reb", "blk", "stl", "fg_pct")
    opponent_avgs: dict[str, float] = {
        key: _opponent_avg(opponent_players, key) for key in stat_keys
    }

    ranked = sorted(home_players, key=lambda p: _player_score(p, opponent_avgs), reverse=True)
    top: list[dict] = ranked[:_LINEUP_SIZE]

    recommended_lineup: list[dict[str, object]] = [
        {
            "player_id": int(p["player_id"]),
            "name": str(p.get("name", "")),
            "plus_minus_score": round(_player_score(p, opponent_avgs), 3),
        }
        for p in top
    ]

    scores: list[float] = [float(r["plus_minus_score"]) for r in recommended_lineup]

    return {
        "recommended_lineup": recommended_lineup,
        "confidence": _confidence(scores),
    }
