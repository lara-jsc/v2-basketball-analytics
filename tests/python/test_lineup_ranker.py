"""
Tests for analytics/lineup_optimizer/ranker.py (recommend_lineup)

Player score formula:
  positive = pts×0.30 + ast×0.15 + reb×0.15 + blk×0.10 + stl×0.10 + fg_pct×0.10
  opp_adj  = (bonus for each stat the player beats the opponent average) / total_stats
  negative = to_per_game × 0.20
  score    = positive + opp_adj - negative
"""

import pytest
from analytics.lineup_optimizer.ranker import recommend_lineup


def _player(player_id: int, name: str = "", **stats) -> dict:
    defaults = {
        "pts": 0.0, "ast": 0.0, "reb": 0.0, "blk": 0.0, "stl": 0.0,
        "fg_pct": 0.0, "to_per_game": 0.0, "min": 30.0, "plus_minus": 0.0,
    }
    defaults.update(stats)
    return {"player_id": player_id, "name": name, **defaults}


class TestRecommendLineup:

    def test_returns_expected_keys(self):
        result = recommend_lineup({
            "home_team_players": [_player(1)],
            "opponent_team_players": [],
        })
        assert "recommended_lineup" in result
        assert "confidence" in result

    def test_returns_empty_lineup_when_no_home_players(self):
        result = recommend_lineup({
            "home_team_players": [],
            "opponent_team_players": [_player(99, pts=20)],
        })
        assert result["recommended_lineup"] == []
        assert result["confidence"] == 0.0

    def test_returns_at_most_5_players(self):
        home = [_player(i, pts=float(i)) for i in range(1, 11)]  # 10 players
        result = recommend_lineup({
            "home_team_players": home,
            "opponent_team_players": [],
        })
        assert len(result["recommended_lineup"]) <= 5

    def test_returns_all_players_when_fewer_than_5(self):
        home = [_player(i) for i in range(1, 4)]  # 3 players
        result = recommend_lineup({
            "home_team_players": home,
            "opponent_team_players": [],
        })
        assert len(result["recommended_lineup"]) == 3

    def test_highest_scoring_player_is_ranked_first(self):
        home = [
            _player(1, name="Star",    pts=30, ast=8, reb=8, stl=2, blk=2, fg_pct=0.55),
            _player(2, name="Average", pts=12, ast=3, reb=4),
            _player(3, name="Bench",   pts=5,  ast=1, reb=2),
        ]
        result = recommend_lineup({
            "home_team_players": home,
            "opponent_team_players": [],
        })
        assert result["recommended_lineup"][0]["player_id"] == 1

    def test_high_turnovers_penalise_ranking(self):
        # Same production, but player 2 has very high turnovers
        home = [
            _player(1, pts=20, ast=5, to_per_game=2.0),
            _player(2, pts=20, ast=5, to_per_game=10.0),
        ]
        result = recommend_lineup({
            "home_team_players": home,
            "opponent_team_players": [],
        })
        # Player 1 should rank above player 2
        ids = [r["player_id"] for r in result["recommended_lineup"]]
        assert ids.index(1) < ids.index(2)

    def test_lineup_items_contain_required_keys(self):
        home = [_player(1, name="Test Player", pts=20)]
        result = recommend_lineup({
            "home_team_players": home,
            "opponent_team_players": [],
        })
        item = result["recommended_lineup"][0]
        assert "player_id" in item
        assert "name" in item
        assert "plus_minus_score" in item

    def test_player_id_is_preserved_in_output(self):
        home = [_player(77, name="Lucky", pts=25)]
        result = recommend_lineup({
            "home_team_players": home,
            "opponent_team_players": [],
        })
        assert result["recommended_lineup"][0]["player_id"] == 77

    def test_outperforming_opponent_average_boosts_ranking(self):
        # Opponent avg pts ≈ 10. Player A scores 20 (above avg), Player B scores 8 (below avg)
        opponent = [_player(99, pts=10.0)] * 5
        home = [
            _player(1, pts=20.0),  # above opponent avg → gets bonus
            _player(2, pts=8.0),   # below opponent avg → no bonus
        ]
        result = recommend_lineup({
            "home_team_players": home,
            "opponent_team_players": opponent,
        })
        ids = [r["player_id"] for r in result["recommended_lineup"]]
        assert ids[0] == 1

    def test_confidence_is_between_0_and_1(self):
        home = [_player(i, pts=float(i * 5)) for i in range(1, 6)]
        result = recommend_lineup({
            "home_team_players": home,
            "opponent_team_players": [],
        })
        assert 0.0 <= result["confidence"] <= 1.0

    def test_plus_minus_score_in_output_is_rounded_to_3_places(self):
        home = [_player(1, pts=17, ast=4, reb=6)]
        result = recommend_lineup({
            "home_team_players": home,
            "opponent_team_players": [],
        })
        score = result["recommended_lineup"][0]["plus_minus_score"]
        assert round(score, 3) == score
