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
     * Roster-only: player identity fields only — no stat columns.
     * Stats are populated exclusively through game history entries.
     */
    public const HEADERS = [
        'first_name',
        'last_name',
        'jersey_number',
        'role',
        'height_feet',
        'weight_kg',
        'is_active',
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
