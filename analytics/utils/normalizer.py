"""
Data parsing and stat normalization utilities.

All functions receive raw values from Laravel-serialized player data.
They must never raise — always return a safe default instead.
"""

from __future__ import annotations


def safe_float(value: object, default: float = 0.0) -> float:
    """Coerce any value to float; return default on failure."""
    try:
        return float(value)  # type: ignore[arg-type]
    except (TypeError, ValueError):
        return default


def safe_int(value: object, default: int = 0) -> int:
    """Coerce any value to int; return default on failure."""
    try:
        return int(float(value))  # type: ignore[arg-type]
    except (TypeError, ValueError):
        return default


def parse_made_attempted(value: str | None) -> tuple[int, int]:
    """
    Parse a 'made-attempted' string (e.g. '3-7') into (made, attempted).
    Returns (0, 0) on malformed input.
    """
    if not value or "-" not in str(value):
        return (0, 0)
    parts = str(value).split("-", 1)
    return (safe_int(parts[0]), safe_int(parts[1]))


def normalize_percentage(value: object) -> float:
    """
    Ensure percentage is in 0–1 range.
    Values > 1.0 are assumed to be 0–100 scale and divided by 100.
    """
    f = safe_float(value)
    return f / 100.0 if f > 1.0 else f
