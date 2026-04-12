/**
 * Formatting utilities for advanced player stats.
 *
 * All formatters return a display-ready string — never raw null or undefined.
 */

/**
 * Format EFF (Efficiency Rating).
 * One decimal place, no symbol. Negative values are valid.
 *
 * @example formatEff(31.0)  → "31.0"
 * @example formatEff(-4.5)  → "-4.5"
 * @example formatEff(null)  → "—"
 */
export function formatEff(value: number | null): string {
    if (value === null) return '—';
    return value.toFixed(1);
}

/**
 * Format a percentage stat (eFG%, TS%).
 * One decimal place with "%" suffix. Input is already in percentage form (e.g., 69.4).
 *
 * @example formatPercent(69.4)  → "69.4%"
 * @example formatPercent(65.1)  → "65.1%"
 * @example formatPercent(null)  → "—"
 */
export function formatPercent(value: number | null): string {
    if (value === null) return '—';
    return `${value.toFixed(1)}%`;
}

/**
 * Format Plus/Minus.
 * Positive values get a "+" prefix. Zero shows as "0" (no sign). Negative as-is.
 * Whole numbers show without decimals; fractional values show one decimal.
 *
 * @example formatPlusMinus(14)   → "+14"
 * @example formatPlusMinus(-3)   → "-3"
 * @example formatPlusMinus(0)    → "0"
 * @example formatPlusMinus(7.5)  → "+7.5"
 * @example formatPlusMinus(null) → "—"
 */
export function formatPlusMinus(value: number | null): string {
    if (value === null) return '—';
    if (value === 0) return '0';

    const abs = Math.abs(value);
    const display = Number.isInteger(abs) ? String(abs) : abs.toFixed(1);

    return value > 0 ? `+${display}` : `-${display}`;
}
