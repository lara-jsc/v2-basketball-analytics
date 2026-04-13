<?php

namespace App\Services;

use App\Models\CsvImport;
use App\Repositories\CsvImportRepository;
use App\Repositories\PlayerRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Orchestrates CSV roster import.
 *
 * Called exclusively by the ProcessCsvImport Job — never directly from controllers.
 *
 * CSV is roster-only: creates/updates player records (name, jersey, role, height, weight).
 * Stats are populated exclusively through game history entries via RebuildPlayerStats.
 */
class CsvImportService
{

    public function __construct(
        private readonly CsvImportRepository $csvImportRepository,
        private readonly PlayerRepository $playerRepository,
    ) {}

    /**
     * Process the CSV file referenced by the given CsvImport record.
     * Updates import status throughout; dispatches BPM job per player.
     */
    public function process(int $csvImportId): void
    {
        /** @var CsvImport $import */
        $import = CsvImport::findOrFail($csvImportId);

        $this->csvImportRepository->markProcessing($import);

        $storagePath = $import->filename;

        if (! Storage::exists($storagePath)) {
            $this->csvImportRepository->markFailed($import, "Uploaded file not found: {$storagePath}");
            return;
        }

        $filePath = Storage::path($storagePath);

        try {
            [$rowsImported, $errors] = $this->parseAndImport($import, $filePath);
        } catch (RuntimeException $e) {
            $this->csvImportRepository->markFailed($import, $e->getMessage());
            return;
        }

        if ($rowsImported === 0 && $errors !== []) {
            $this->csvImportRepository->markFailed($import, implode("\n", $errors));
            return;
        }

        $errorLog = $errors !== [] ? implode("\n", $errors) : null;

        $import->update([
            'status'        => CsvImport::STATUS_COMPLETED,
            'rows_imported' => $rowsImported,
            'error_log'     => $errorLog,
        ]);
    }

    /**
     * Parse the CSV and import rows.
     *
     * @return array{int, list<string>}  [rowsImported, errors]
     *
     * @throws RuntimeException  on header mismatch
     */
    private function parseAndImport(CsvImport $import, string $filePath): array
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open CSV file for reading.");
        }

        try {
            return $this->readRows($import, $handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return array{int, list<string>}
     *
     * @throws RuntimeException  on header mismatch
     */
    private function readRows(CsvImport $import, mixed $handle): array
    {
        $headerRow = fgetcsv($handle);

        if ($headerRow === false) {
            throw new RuntimeException("CSV file is empty.");
        }

        // Strip BOM and trim whitespace from all header values
        $headers = array_map(fn(string $h) => trim(ltrim($h, "\xEF\xBB\xBF")), $headerRow);

        if (! (new CsvTemplateService())->headersMatch($headers)) {
            throw new RuntimeException(
                "CSV headers do not match the required template. "
                . "Expected: " . implode(',', CsvTemplateService::HEADERS) . "\n"
                . "Received: " . implode(',', $headers)
            );
        }

        $rowsImported = 0;
        $errors       = [];
        $lineNumber   = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if (count($row) !== count($headers)) {
                $errors[] = "Line {$lineNumber}: column count mismatch (expected " . count($headers) . ", got " . count($row) . ")";
                continue;
            }

            $data = array_combine($headers, $row);

            if ($data === false) {
                $errors[] = "Line {$lineNumber}: could not parse row.";
                continue;
            }

            try {
                $this->importRow($import->team_id, $data);
                $rowsImported++;
            } catch (\Throwable $e) {
                Log::warning("CSV import line {$lineNumber} failed", ['error' => $e->getMessage()]);
                $errors[] = "Line {$lineNumber}: {$e->getMessage()}";
            }
        }

        return [$rowsImported, $errors];
    }

    /**
     * Import one CSV data row inside a DB transaction.
     * Uses jersey_number as the natural key per team — re-uploading the same
     * CSV updates existing records rather than inserting duplicates.
     *
     * @param  array<string, string>  $data
     */
    private function importRow(int $teamId, array $data): void
    {
        DB::transaction(function () use ($teamId, $data): void {
            $jerseyNumber = (int) ($data['jersey_number'] ?? 0);

            $this->playerRepository->updateOrCreateByJersey(
                teamId: $teamId,
                jerseyNumber: $jerseyNumber,
                playerData: [
                    'first_name'   => $data['first_name'] ?? '',
                    'last_name'    => $data['last_name'] ?? '',
                    'role'         => $data['role'] !== '' ? $data['role'] : null,
                    'height_feet'  => $data['height_feet'] !== '' ? (float) $data['height_feet'] : null,
                    'weight_kg'    => $data['weight_kg'] !== '' ? (float) $data['weight_kg'] : null,
                    'is_active'    => $this->parseBool($data['is_active'] ?? '1'),
                ],
            );
        });
    }

    private function parseBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes'], strict: true);
    }
}
