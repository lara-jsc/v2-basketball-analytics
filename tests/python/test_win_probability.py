"""
Tests for analytics/win_probability/model.py

Covers compute_win_probability and compute_player_matchup.

Win probability formula:
  score = sum(stat × weight for each stat in _WIN_PROB_WEIGHTS)
  diff  = score_a - score_b
  prob_a = logistic(diff) = 1 / (1 + exp(-diff))
  prob_b = 1 - prob_a
  win_rate_a = 0.5 + 0.8 × (prob_a - 0.5)
  win_rate_b = 1 - win_rate_a
"""

import math
import pytest
from analytics.win_probability.model import compute_win_probability, compute_player_matchup


def _team(pts=0.0, reb=0.0, ast=0.0, fg_pct=0.0, blk=0.0, stl=0.0, to=0.0) -> dict:
    return {
        "avg_pts": pts,
        "avg_reb": reb,
        "avg_ast": ast,
        "avg_fg_pct": fg_pct,
        "avg_blk": blk,
        "avg_stl": stl,
        "avg_to_per_game": to,
    }


class TestComputeWinProbability:

    def test_probabilities_sum_to_one(self):
        result = compute_win_probability({
            "team_a_stats": _team(pts=100, reb=45),
            "team_b_stats": _team(pts=95,  reb=42),
        })
        total = result["team_a_win_probability"] + result["team_b_win_probability"]
        assert total == pytest.approx(1.0, abs=1e-4)

    def test_win_rates_sum_to_one(self):
        result = compute_win_probability({
            "team_a_stats": _team(pts=100),
            "team_b_stats": _team(pts=90),
        })
        assert result["team_a_win_rate"] + result["team_b_win_rate"] == pytest.approx(1.0, abs=1e-4)

    def test_equal_teams_produce_50_50_probability(self):
        stats = _team(pts=95, reb=43, ast=24, fg_pct=0.47)
        result = compute_win_probability({
            "team_a_stats": stats,
            "team_b_stats": stats,
        })
        assert result["team_a_win_probability"] == pytest.approx(0.5, abs=1e-4)
        assert result["team_b_win_probability"] == pytest.approx(0.5, abs=1e-4)

    def test_stronger_team_a_has_higher_win_probability(self):
        result = compute_win_probability({
            "team_a_stats": _team(pts=110, reb=50, ast=28, fg_pct=0.52),
            "team_b_stats": _team(pts=88,  reb=38, ast=19, fg_pct=0.41),
        })
        assert result["team_a_win_probability"] > result["team_b_win_probability"]

    def test_turnovers_penalise_the_team_with_more(self):
        result = compute_win_probability({
            "team_a_stats": _team(pts=100, to=20),  # high TO
            "team_b_stats": _team(pts=100, to=10),  # low TO
        })
        # Team B should win more often due to fewer turnovers
        assert result["team_b_win_probability"] > result["team_a_win_probability"]

    def test_win_rate_is_regressed_toward_50_percent(self):
        # prob_a > 0.5 → win_rate_a should be between 0.5 and prob_a
        result = compute_win_probability({
            "team_a_stats": _team(pts=110, reb=48),
            "team_b_stats": _team(pts=88,  reb=38),
        })
        prob_a = result["team_a_win_probability"]
        rate_a = result["team_a_win_rate"]
        assert 0.5 < rate_a < prob_a

    def test_all_values_between_0_and_1(self):
        result = compute_win_probability({
            "team_a_stats": _team(pts=120),
            "team_b_stats": _team(pts=70),
        })
        for key in ("team_a_win_probability", "team_b_win_probability",
                    "team_a_win_rate", "team_b_win_rate"):
            assert 0.0 <= result[key] <= 1.0

    def test_empty_stats_returns_equal_probability(self):
        result = compute_win_probability({
            "team_a_stats": {},
            "team_b_stats": {},
        })
        assert result["team_a_win_probability"] == pytest.approx(0.5, abs=1e-4)

    def test_manual_probability_calculation(self):
        # Team A: pts=100 → score_a = 100 × 0.30 = 30.0
        # Team B: pts=70  → score_b = 70  × 0.30 = 21.0
        # diff = 9.0
        # prob_a = 1 / (1 + exp(-9)) ≈ 0.9999
        result = compute_win_probability({
            "team_a_stats": {"avg_pts": 100.0},
            "team_b_stats": {"avg_pts": 70.0},
        })
        expected = round(1 / (1 + math.exp(-9.0)), 4)
        assert result["team_a_win_probability"] == pytest.approx(expected, abs=1e-4)


class TestComputePlayerMatchup:

    def _payload(self, a: dict, b: dict) -> dict:
        return {"player_a": a, "player_b": b}

    def test_edge_scores_sum_to_at_most_one(self):
        # Scores are fractions of total stats compared — they should not both be 1
        result = compute_player_matchup(self._payload(
            {"pts": 25, "ast": 7, "reb": 5},
            {"pts": 20, "ast": 5, "reb": 8},
        ))
        assert result["player_a_edge_score"] + result["player_b_edge_score"] <= 1.0 + 1e-6

    def test_dominant_player_a_gets_higher_edge_score(self):
        result = compute_player_matchup(self._payload(
            {"pts": 30, "ast": 10, "reb": 10, "blk": 3, "stl": 3, "fg_pct": 0.55},
            {"pts": 10, "ast": 2,  "reb": 3,  "blk": 0, "stl": 0, "fg_pct": 0.35},
        ))
        assert result["player_a_edge_score"] > result["player_b_edge_score"]

    def test_equal_players_produce_equal_edge_scores(self):
        player = {"pts": 20, "ast": 5, "reb": 7, "blk": 1, "stl": 1, "fg_pct": 0.48}
        result = compute_player_matchup(self._payload(player, player))
        assert result["player_a_edge_score"] == result["player_b_edge_score"]

    def test_stronger_stats_lists_are_mutually_exclusive(self):
        result = compute_player_matchup(self._payload(
            {"pts": 25, "ast": 8},
            {"pts": 15, "reb": 12},
        ))
        overlap = set(result["stronger_stats_a"]) & set(result["stronger_stats_b"])
        assert len(overlap) == 0

    def test_tied_stat_does_not_appear_in_either_stronger_list(self):
        # pts is equal — should appear in neither list
        result = compute_player_matchup(self._payload(
            {"pts": 20, "ast": 5},
            {"pts": 20, "ast": 3},
        ))
        assert "pts" not in result["stronger_stats_a"]
        assert "pts" not in result["stronger_stats_b"]

    def test_empty_players_return_zero_scores(self):
        result = compute_player_matchup(self._payload({}, {}))
        assert result["player_a_edge_score"] == 0.0
        assert result["player_b_edge_score"] == 0.0

    def test_edge_scores_are_rounded_to_4_decimal_places(self):
        result = compute_player_matchup(self._payload(
            {"pts": 25, "ast": 7},
            {"pts": 20, "ast": 3},
        ))
        for key in ("player_a_edge_score", "player_b_edge_score"):
            value = result[key]
            assert round(value, 4) == value
