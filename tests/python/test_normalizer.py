"""
Tests for analytics/utils/normalizer.py

Verifies that all coercion and parsing helpers handle valid inputs,
edge cases, and malformed values without raising.
"""

import pytest
from analytics.utils.normalizer import (
    safe_float,
    safe_int,
    parse_made_attempted,
    normalize_percentage,
)


class TestSafeFloat:
    def test_converts_int_to_float(self):
        assert safe_float(10) == 10.0

    def test_converts_string_number(self):
        assert safe_float("3.14") == pytest.approx(3.14)

    def test_returns_default_for_none(self):
        assert safe_float(None) == 0.0

    def test_returns_default_for_empty_string(self):
        assert safe_float("") == 0.0

    def test_returns_custom_default_on_failure(self):
        assert safe_float("abc", default=-1.0) == -1.0

    def test_handles_negative_values(self):
        assert safe_float("-7.5") == pytest.approx(-7.5)

    def test_handles_zero(self):
        assert safe_float(0) == 0.0

    def test_handles_boolean_true(self):
        assert safe_float(True) == 1.0

    def test_handles_boolean_false(self):
        assert safe_float(False) == 0.0


class TestSafeInt:
    def test_converts_int(self):
        assert safe_int(5) == 5

    def test_converts_float_string(self):
        assert safe_int("3.9") == 3  # truncates, not rounds

    def test_returns_default_for_none(self):
        assert safe_int(None) == 0

    def test_returns_default_for_garbage(self):
        assert safe_int("xyz", default=-1) == -1

    def test_handles_negative(self):
        assert safe_int(-4) == -4


class TestParseMadeAttempted:
    def test_parses_standard_format(self):
        assert parse_made_attempted("8-15") == (8, 15)

    def test_parses_zeros(self):
        assert parse_made_attempted("0-0") == (0, 0)

    def test_returns_zeros_for_none(self):
        assert parse_made_attempted(None) == (0, 0)

    def test_returns_zeros_for_missing_dash(self):
        assert parse_made_attempted("815") == (0, 0)

    def test_returns_zeros_for_empty_string(self):
        assert parse_made_attempted("") == (0, 0)

    def test_parses_first_dash_only_when_multiple(self):
        # "3-7-extra" — only first dash split
        made, attempted = parse_made_attempted("3-7-extra")
        assert made == 3
        # attempted part is "7-extra" → safe_int("7-extra") → 0 (can't parse) or 7
        # safe_int uses int(float(...)) which fails on "7-extra" → returns 0
        assert attempted == 0


class TestNormalizePercentage:
    def test_value_already_in_0_to_1_range_unchanged(self):
        assert normalize_percentage(0.51) == pytest.approx(0.51)

    def test_value_above_1_divided_by_100(self):
        assert normalize_percentage(51.0) == pytest.approx(0.51)

    def test_zero_stays_zero(self):
        assert normalize_percentage(0) == 0.0

    def test_exactly_1_unchanged(self):
        assert normalize_percentage(1.0) == pytest.approx(1.0)

    def test_handles_string_input(self):
        assert normalize_percentage("0.45") == pytest.approx(0.45)

    def test_handles_none_returns_zero(self):
        assert normalize_percentage(None) == 0.0
