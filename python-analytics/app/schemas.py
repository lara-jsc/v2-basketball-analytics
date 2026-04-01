from pydantic import BaseModel, Field


class PlayerStatLine(BaseModel):
    player_id: str
    player_name: str
    shooting: float = Field(ge=0)
    assists: float = Field(ge=0)
    defensive_play: float = Field(ge=0)
    fouls: float = Field(ge=0)
    long_dribbles: float = Field(ge=0)
    games_played: int = Field(ge=0)
    opponent_fit: float = Field(default=1.0, ge=0)


class AnalyzeLineupRequest(BaseModel):
    team_id: str
    opponent_name: str
    players: list[PlayerStatLine]


class PlayerRecommendation(BaseModel):
    player_id: str
    player_name: str
    score: float
    reason: str


class AnalyzeLineupResponse(BaseModel):
    recommended_players: list[PlayerRecommendation]


class WinRatePredictionRequest(BaseModel):
    team_form_score: float
    opponent_form_score: float
    recommended_lineup_score: float


class WinRatePredictionResponse(BaseModel):
    win_rate: float
