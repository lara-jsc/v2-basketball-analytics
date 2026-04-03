from math import exp

from app.schemas import AnalyzeLineupRequest, WinRatePredictionRequest


def _player_score(player) -> float:
    positive_score = (
        player.shooting * 0.4
        + player.assists * 0.2
        + player.defensive_play * 0.3
        + player.opponent_fit * 0.1
    )
    negative_score = player.fouls * 0.07 + player.long_dribbles * 0.05
    experience_bonus = min(player.games_played, 30) * 0.01

    return positive_score - negative_score + experience_bonus


def recommend_starting_five(payload: AnalyzeLineupRequest) -> list[dict]:
    ranked_players = sorted(
        payload.players,
        key=_player_score,
        reverse=True,
    )[:5]

    return [
        {
            "player_id": player.player_id,
            "player_name": player.player_name,
            "score": round(_player_score(player), 3),
            "reason": (
                "Strong combined contribution from scoring, assists, and defense "
                "with lower penalties from fouls and long dribbles."
            ),
        }
        for player in ranked_players
    ]


def predict_win_rate(payload: WinRatePredictionRequest) -> float:
    raw_score = (
        payload.team_form_score * 0.45
        - payload.opponent_form_score * 0.35
        + payload.recommended_lineup_score * 0.20
    )
    logistic = 1 / (1 + exp(-raw_score))

    return round(logistic, 4)
