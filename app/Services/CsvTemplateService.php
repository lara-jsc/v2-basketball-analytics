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
        return implode(',', self::HEADERS)."\n";
    }

    /**
     * @param  iterable<object|array<string, mixed>>  $players
     */
    public function generateRosterExportContent(iterable $players): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return $this->generateTemplateContent();
        }

        fputcsv($stream, self::HEADERS);

        foreach ($players as $player) {
            fputcsv($stream, [
                $this->value($player, 'first_name'),
                $this->value($player, 'last_name'),
                $this->scalar($this->value($player, 'jersey_number')),
                $this->value($player, 'role'),
                $this->scalar($this->value($player, 'height_feet')),
                $this->scalar($this->value($player, 'weight_kg')),
                $this->value($player, 'is_active') ? '1' : '0',
            ]);
        }

        rewind($stream);

        $content = stream_get_contents($stream);
        fclose($stream);

        return $content === false ? $this->generateTemplateContent() : $content;
    }

    /**
     * Validates that an uploaded CSV's first row exactly matches HEADERS.
     * Returns true on match, false otherwise.
     *
     * @param  array<int, string>  $uploadedHeaders
     */
    public function headersMatch(array $uploadedHeaders): bool
    {
        return $uploadedHeaders === self::HEADERS;
    }

    /**
     * @param  object|array<string, mixed>  $item
     */
    private function value(object|array $item, string $key): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        return $item->{$key} ?? null;
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
