"""
Box Plus-Minus (BPM) calculator.

Input payload (from Laravel ComputePlayerPlusMinus Job):
{
    "player_id": int,
    "stats": {
        "pts": float,
        "ast": float,
        "reb": float,
        "fg_pct": float,
        "three_p_pct": float,
        "blk": float,
        "stl": float,
        "to_per_game": float,
        "min": float
    }
}

Output:
{
    "player_id": int,
    "plus_minus": float
}
"""

from __future__ import annotations

from analytics.utils.normalizer import safe_float


# Weighting constants for BPM approximation.
# These are intentionally named and adjustable — not magic numbers.
_WEIGHT_PTS: float = 0.35
_WEIGHT_AST: float = 0.20
_WEIGHT_REB: float = 0.15
_WEIGHT_FG_PCT: float = 0.10
_WEIGHT_3P_PCT: float = 0.05
_WEIGHT_BLK: float = 0.08
_WEIGHT_STL: float = 0.12
_PENALTY_TO: float = 0.25
_MIN_THRESHOLD: float = 1.0  # avoid dividing by near-zero minutes


def compute_bpm(payload: dict) -> dict[str, object]:
    """
    Compute Box Plus-Minus for a single player.
    Returns { "player_id": int, "plus_minus": float | None }.
    plus_minus is None when the player has no minutes recorded.
    """
    player_id: int = int(payload["player_id"])
    stats: dict = payload["stats"]

    pts: float = safe_float(stats.get("pts", 0))
    ast: float = safe_float(stats.get("ast", 0))
    reb: float = safe_float(stats.get("reb", 0))
    fg_pct: float = safe_float(stats.get("fg_pct", 0))
    three_p_pct: float = safe_float(stats.get("three_p_pct", 0))
    blk: float = safe_float(stats.get("blk", 0))
    stl: float = safe_float(stats.get("stl", 0))
    to_per_game: float = safe_float(stats.get("to_per_game", 0))
    raw_minutes: float = safe_float(stats.get("min", 0))
    if raw_minutes <= 0:
        # No minutes recorded — scaling to 36 minutes would inflate the score 36×.
        return {"player_id": player_id, "plus_minus": None}
    minutes: float = max(raw_minutes, _MIN_THRESHOLD)

    # Positive contribution score
    positive: float = (
        pts * _WEIGHT_PTS
        + ast * _WEIGHT_AST
        + reb * _WEIGHT_REB
        + fg_pct * _WEIGHT_FG_PCT
        + three_p_pct * _WEIGHT_3P_PCT
        + blk * _WEIGHT_BLK
        + stl * _WEIGHT_STL
    )

    # Negative contribution (turnovers penalise)
    negative: float = to_per_game * _PENALTY_TO

    # Scale by minutes played (per 36 minutes baseline)
    raw_bpm: float = (positive - negative) * (36.0 / minutes)

    # Centre around zero — subtract a league baseline approximation
    _LEAGUE_BASELINE: float = 5.0
    plus_minus: float = round(raw_bpm - _LEAGUE_BASELINE, 2)

    return {"player_id": player_id, "plus_minus": plus_minus}
