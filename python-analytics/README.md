# Python Analytics Service

This service contains the basketball recommendation and prediction logic used by the Laravel application.

## Responsibilities

- Clean imported stat data
- Calculate player impact summaries
- Recommend the best starting five for a given opponent
- Estimate win probability from historical inputs
- Later support live in-game recalculations

## Run locally

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -e .
uvicorn app.main:app --reload --port 8001
```

## Endpoints

- `GET /health`
- `POST /analyze/lineup`
- `POST /predict/win-rate`

Start simple and interpretable first. A weighted scoring model is enough for the first milestone before you move to a more advanced model.
