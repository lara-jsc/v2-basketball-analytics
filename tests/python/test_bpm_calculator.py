"""
Tests for analytics/plus_minus/calculator.py (compute_bpm)

Each test uses known inputs and manually verifies the formula:
  positive = pts×0.35 + ast×0.20 + reb×0.15 + fg_pct×0.10 + 3p_pct×0.05
             + blk×0.08 + stl×0.12
  negative = to_per_game × 0.25
  raw_bpm  = (positive - negative) × (36 / minutes)
  bpm      = round(raw_bpm - 5.0, 2)
"""

import pytest
from analytics.plus_minus.calculator import compute_bpm


def _make_payload(player_id: int = 1, **stats) -> dict:
    """Build a valid BPM payload with sensible defaults for unspecified stats."""
    defaults = {
        "pts": 0.0,
        "ast": 0.0,
        "reb": 0.0,
        "fg_pct": 0.0,
        "three_p_pct": 0.0,
        "blk": 0.0,
        "stl": 0.0,
        "to_per_game": 0.0,
        "min": 36.0,
    }
    defaults.update(stats)
    return {"player_id": player_id, "stats": defaults}


class TestComputeBpm:

    def test_returns_player_id_unchanged(self):
        result = compute_bpm(_make_payload(player_id=42))
        assert result["player_id"] == 42

    def test_result_is_a_dict_with_expected_keys(self):
        result = compute_bpm(_make_payload())
        assert "player_id" in result
        assert "plus_minus" in result

    def test_all_zero_stats_at_36_minutes_returns_minus_five(self):
        # positive=0, negative=0, raw_bpm = 0*(36/36)=0, bpm = 0-5 = -5.0
        result = compute_bpm(_make_payload(pts=0, min=36.0))
        assert result["plus_minus"] == pytest.approx(-5.0)

    def test_manual_calculation_elite_player(self):
        # pts=25, ast=7, reb=5, fg_pct=0.52, 3p_pct=0.38,
        # blk=1, stl=1.5, to=2.5, min=36
        # positive = 25×0.35 + 7×0.20 + 5×0.15 + 0.52×0.10 + 0.38×0.05
        #           + 1×0.08 + 1.5×0.12
        #         = 8.75 + 1.40 + 0.75 + 0.052 + 0.019 + 0.08 + 0.18
        #         = 11.231
        # negative = 2.5 × 0.25 = 0.625
        # raw_bpm  = (11.231 - 0.625) × (36/36) = 10.606
        # bpm      = round(10.606 - 5.0, 2) = 5.61
        result = compute_bpm(_make_payload(
            pts=25, ast=7, reb=5, fg_pct=0.52, three_p_pct=0.38,
            blk=1, stl=1.5, to_per_game=2.5, min=36.0,
        ))
        assert result["plus_minus"] == pytest.approx(5.61)

    def test_negative_bpm_for_poor_stats(self):
        # High turnovers, low production → should be well below zero
        result = compute_bpm(_make_payload(
            pts=5, ast=1, reb=2, fg_pct=0.30, to_per_game=6.0, min=36.0,
        ))
        assert result["plus_minus"] < 0

    def test_more_minutes_lowers_bpm(self):
        # Same production at 40 min vs 32 min — 40 min = lower per-36 rate
        high = compute_bpm(_make_payload(pts=20, ast=5, min=32.0))
        low  = compute_bpm(_make_payload(pts=20, ast=5, min=40.0))
        assert high["plus_minus"] > low["plus_minus"]

    def test_fewer_minutes_does_not_divide_by_near_zero(self):
        # min=0 is clamped to _MIN_THRESHOLD=1.0 — should not raise
        result = compute_bpm(_make_payload(pts=10, min=0))
        assert isinstance(result["plus_minus"], float)

    def test_bpm_is_rounded_to_2_decimal_places(self):
        result = compute_bpm(_make_payload(pts=17, ast=4, reb=6, min=33.0))
        value = result["plus_minus"]
        assert round(value, 2) == value

    def test_missing_stats_default_to_zero(self):
        # Payload with only player_id and empty stats — should not raise
        result = compute_bpm({"player_id": 1, "stats": {}})
        assert isinstance(result["plus_minus"], float)

    def test_string_stat_values_are_coerced(self):
        # Laravel may serialize floats as strings in some edge cases
        result = compute_bpm(_make_payload(pts="20.0", ast="5.0", min="36.0"))
        assert isinstance(result["plus_minus"], float)
