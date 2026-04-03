from fastapi import FastAPI

from app.schemas import (
    AnalyzeLineupRequest,
    AnalyzeLineupResponse,
    PlayerRecommendation,
    WinRatePredictionRequest,
    WinRatePredictionResponse,
)
from app.services.recommendation import (
    recommend_starting_five,
    predict_win_rate,
)

app = FastAPI(
    title="Basketball Analytics Service",
    version="0.1.0",
)


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/analyze/lineup", response_model=AnalyzeLineupResponse)
def analyze_lineup(payload: AnalyzeLineupRequest) -> AnalyzeLineupResponse:
    recommendations = recommend_starting_five(payload)

    return AnalyzeLineupResponse(
        recommended_players=[
            PlayerRecommendation(**player) for player in recommendations
        ]
    )


@app.post("/predict/win-rate", response_model=WinRatePredictionResponse)
def predict_win_rate_endpoint(
    payload: WinRatePredictionRequest,
) -> WinRatePredictionResponse:
    return WinRatePredictionResponse(
        win_rate=predict_win_rate(payload),
    )
