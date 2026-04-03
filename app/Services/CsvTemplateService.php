<?php

namespace App\Services;

/**
 * Generates the downloadable CSV roster template.
 *
 * The header row is the canonical contract for CSV uploads.
 * Upload validation must reject any file that does not match exactly.
 */
class CsvTemplateService
{
    /**
     * Exact CSV header columns in spec order (case-sensitive).
     * Mapping notes:
     *   OR  → offensive_rebounds in DB  (MySQL reserved word avoided)
     *   TO  → to_per_game in DB         (MySQL reserved word avoided)
     */
    public const HEADERS = [
        'first_name',
        'last_name',
        'jersey_number',
        'role',
        'height_feet',
        'weight_kg',
        'is_active',
        'pc',
        'sd',
        '3P%',
        '3PT',
        'AST',
        'AST/TO',
        'BLK',
        'DD2',
        'DQ',
        'DR',
        'EJECT',
        'FG',
        'FG%',
        'FLAG',
        'FT',
        'FT%',
        'GP',
        'GS',
        'MIN',
        'OR',
        'PF',
        'PTS',
        'REB',
        'SC-EFF',
        'SH-EFF',
        'STL',
        'STL/TO',
        'TD3',
        'TECH',
        'TO',
    ];

    /**
     * Returns the raw CSV content string (header row only).
     */
    public function generateTemplateContent(): string
    {
        return implode(',', self::HEADERS) . "\n";
    }

    /**
     * Validates that an uploaded CSV's first row exactly matches HEADERS.
     * Returns true on match, false otherwise.
     *
     * @param array<int, string> $uploadedHeaders
     */
    public function headersMatch(array $uploadedHeaders): bool
    {
        return $uploadedHeaders === self::HEADERS;
    }
}
