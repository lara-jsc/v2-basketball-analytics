"""
analytics/engine.py — Entry point for all Python analytics calls.

Called exclusively by Laravel Jobs via subprocess. Never runs as a web server.

Usage (from Laravel Job via Symfony Process):
    echo '{"command": "bpm", "payload": {...}}' | python3 analytics/engine.py

Protocol:
    - Reads a single JSON object from stdin.
    - Writes a single JSON object to stdout.
    - Exits 0 on success, 1 on error (error JSON written to stdout).

Supported commands:
    bpm              — Compute Box Plus-Minus for a single player
    lineup           — Recommend optimal starting lineup
    win_probability  — Compute win probability and win rate for two teams
    player_matchup   — Compute edge scores for a 1-on-1 player comparison
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

# Ensure the project root is on sys.path so `analytics.*` imports resolve
# when Laravel Jobs invoke: python3 analytics/engine.py
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from analytics.plus_minus.calculator import compute_bpm
from analytics.lineup_optimizer.ranker import recommend_lineup
from analytics.win_probability.model import compute_win_probability
from analytics.win_probability.model import compute_player_matchup


SUPPORTED_COMMANDS = ("bpm", "lineup", "win_probability", "player_matchup")


def _error(message: str) -> dict[str, str]:
    return {"error": message}


def main() -> None:
    try:
        raw = sys.stdin.read()
        request: dict = json.loads(raw)
    except json.JSONDecodeError as exc:
        print(json.dumps(_error(f"Invalid JSON input: {exc}")))
        sys.exit(1)

    command: str = request.get("command", "")
    payload: dict = request.get("payload", {})

    if command not in SUPPORTED_COMMANDS:
        print(json.dumps(_error(f"Unknown command: '{command}'. Supported: {SUPPORTED_COMMANDS}")))
        sys.exit(1)

    try:
        if command == "bpm":
            result = compute_bpm(payload)
        elif command == "lineup":
            result = recommend_lineup(payload)
        elif command == "win_probability":
            result = compute_win_probability(payload)
        elif command == "player_matchup":
            result = compute_player_matchup(payload)
        else:
            result = _error("Unreachable")
    except (KeyError, ValueError, TypeError) as exc:
        print(json.dumps(_error(f"Engine error in '{command}': {exc}")))
        sys.exit(1)

    print(json.dumps(result))


if __name__ == "__main__":
    main()
