<?php

namespace App\Jobs;

use App\Actions\UpsertPlayerHistoryAction;
use App\Models\CsvImport;
use App\Repositories\CsvImportRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Processes an uploaded player history Excel (.xlsx) file.
 *
 * Flow:
 *  1. Mark CsvImport as processing.
 *  2. Load the xlsx and validate headers on the Template sheet.
 *  3. For each data row: validate + upsert via UpsertPlayerHistoryAction.
 *     Invalid rows are skipped and logged; import continues.
 *  4. Dispatch RebuildPlayerStats per unique player_id in the file.
 *  5. Update CsvImport status → completed|failed.
 */
class PlayerHistoryImportJob implements ShouldQueue
{
    use Queueable;

    /** Exact header order — case-sensitive (required columns) */
    public const HEADERS = [
        'game_date', 'playing_team_id', 'opponent_team_id',
        'position_played', 'minutes_played', 'points',
        'field_goals_made', 'field_goals_attempted',
        'three_pointers_made', 'three_pointers_attempted',
        'free_throws_made', 'free_throws_attempted',
        'offensive_rebounds', 'defensive_rebounds', 'rebounds',
        'assists', 'steals', 'blocks', 'turnovers',
        'personal_fouls', 'flagrant_fouls', 'technical_fouls',
        'ejections', 'disqualifications',
        'is_started', 'notes',
    ];

    /**
     * Accepted aliases for the optional plus_minus column (case-insensitive).
     * When present as the column immediately after HEADERS, it is mapped to 'plus_minus'.
     */
    public const PLUS_MINUS_ALIASES = ['plus_minus', '+/-', 'plus/minus'];

    /** Column index (1-based) for team dropdown columns */
    private const PLAYING_TEAM_COL  = 2; // playing_team_id
    private const OPPONENT_TEAM_COL = 3; // opponent_team_id

    /** Column index (1-based) for the game_date column */
    private const GAME_DATE_COL = 1;

    /** Set to true during handle() if the file contains a recognized plus_minus column */
    private bool $hasPlusMinusColumn = false;

    public function __construct(
        public readonly int $csvImportId,
        public readonly int $playerId,
    ) {}

    public function handle(
        CsvImportRepository $csvImportRepository,
        UpsertPlayerHistoryAction $upsertAction,
    ): void {
        /** @var CsvImport $import */
        $import = CsvImport::findOrFail($this->csvImportId);

        $csvImportRepository->markProcessing($import);

        if (! Storage::exists($import->filename)) {
            $csvImportRepository->markFailed($import, "Uploaded file not found: {$import->filename}");
            return;
        }

        $filePath = Storage::path($import->filename);

        try {
            [$rowsImported, $errors] = $this->processRows($filePath, $upsertAction);
        } catch (RuntimeException $e) {
            // Header mismatch or unreadable file — fatal
            $csvImportRepository->markFailed($import, $e->getMessage());
            return;
        }

        RebuildPlayerStats::dispatch($this->playerId);

        if ($rowsImported === 0 && $errors !== []) {
            $csvImportRepository->markFailed($import, implode("\n", $errors));
            return;
        }

        $import->update([
            'status'        => CsvImport::STATUS_COMPLETED,
            'rows_imported' => $rowsImported,
            'error_log'     => $errors !== [] ? implode("\n", $errors) : null,
        ]);
    }

    /**
     * @return array{int, list<string>}  [rowsImported, errors]
     *
     * @throws RuntimeException  on header mismatch or unreadable file
     */
    private function processRows(string $filePath, UpsertPlayerHistoryAction $upsertAction): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (Throwable $e) {
            throw new RuntimeException("Could not read the uploaded Excel file: {$e->getMessage()}");
        }

        $sheet = $spreadsheet->getSheetByName('Template') ?? $spreadsheet->getActiveSheet();

        $this->assertHeaders($sheet);

        $rowsImported = 0;
        $errors       = [];
        $highestRow   = $sheet->getHighestDataRow();

        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $rowData = $this->readRow($sheet, $rowIndex);

            // Skip completely empty rows
            if ($this->isEmptyRow($rowData)) {
                continue;
            }

            // Skip the example row left over from the template
            $notes = strtoupper(trim((string) ($rowData['notes'] ?? '')));
            if (str_starts_with($notes, 'DELETE THIS ROW')) {
                continue;
            }

            try {
                $upsertAction->execute($this->playerId, $rowData);
                $rowsImported++;
            } catch (RuntimeException $e) {
                Log::warning("PlayerHistoryImportJob: skipping row {$rowIndex}", ['error' => $e->getMessage()]);
                $errors[] = "Row {$rowIndex}: {$e->getMessage()}";
            }
        }

        return [$rowsImported, $errors];
    }

    /**
     * Validate that the sheet's required header columns match HEADERS exactly.
     * An optional plus_minus column (any alias from PLUS_MINUS_ALIASES) may follow.
     * Sets $this->hasPlusMinusColumn if the optional column is present.
     *
     * @throws RuntimeException
     */
    private function assertHeaders(Worksheet $sheet): void
    {
        $actual = [];

        foreach (range(1, count(self::HEADERS)) as $col) {
            $actual[] = trim((string) $sheet->getCell([$col, 1])->getValue());
        }

        if ($actual !== self::HEADERS) {
            throw new RuntimeException(
                "Excel headers do not match the required template.\n"
                . "Expected: " . implode(', ', self::HEADERS) . "\n"
                . "Received: " . implode(', ', $actual)
            );
        }

        // Check optional plus_minus column immediately after the required columns
        $optionalCol   = count(self::HEADERS) + 1;
        $optionalValue = strtolower(trim((string) $sheet->getCell([$optionalCol, 1])->getValue()));

        if ($optionalValue !== '') {
            if (! in_array($optionalValue, self::PLUS_MINUS_ALIASES, strict: true)) {
                throw new RuntimeException(
                    "Unexpected column after required headers: '{$optionalValue}'. "
                    . "Only an optional plus_minus column (aliases: " . implode(', ', self::PLUS_MINUS_ALIASES) . ") is allowed here."
                );
            }

            $this->hasPlusMinusColumn = true;
        }
    }

    /**
     * Read a single data row and return a keyed array matching HEADERS.
     *
     * Team columns store dropdown label strings like "3 | Lakers (LAL)".
     * This method extracts the numeric ID so the action receives a plain integer.
     *
     * @return array<string, mixed>
     */
    private function readRow(Worksheet $sheet, int $rowIndex): array
    {
        $data = [];

        foreach (self::HEADERS as $colIndex => $header) {
            $col  = $colIndex + 1;
            $cell = $sheet->getCell([$col, $rowIndex]);

            $value = match ($col) {
                self::PLAYING_TEAM_COL,
                self::OPPONENT_TEAM_COL => $this->extractTeamId($cell->getValue()),

                self::GAME_DATE_COL => $this->resolveDate($cell),

                default => $cell->getValue(),
            };

            $data[$header] = $value;
        }

        if ($this->hasPlusMinusColumn) {
            $plusMinusCol        = count(self::HEADERS) + 1;
            $raw                 = $sheet->getCell([$plusMinusCol, $rowIndex])->getValue();
            $data['plus_minus']  = ($raw !== null && $raw !== '') ? (float) $raw : null;
        }

        return $data;
    }

    /**
     * Parse the team ID from a dropdown label like "3 | Lakers (LAL)" or a plain integer.
     */
    private function extractTeamId(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return $raw;
        }

        $str = trim((string) $raw);

        // Dropdown format: "3 | Team Name (CODE)"
        if (preg_match('/^(\d+)\s*\|/', $str, $matches)) {
            return (int) $matches[1];
        }

        // Plain integer typed directly by the user
        return $str;
    }

    /**
     * Resolve a date cell to a YYYY-MM-DD string.
     * Handles Excel serial date numbers and pre-formatted strings.
     */
    private function resolveDate(mixed $cell): string
    {
        $raw = $cell->getValue();

        if ($raw === null || $raw === '') {
            return '';
        }

        // Excel stores dates as float serial numbers
        if (is_numeric($raw)) {
            try {
                $dt = SpreadsheetDate::excelToDateTimeObject((float) $raw);
                return $dt->format('Y-m-d');
            } catch (Throwable) {
                return (string) $raw;
            }
        }

        return trim((string) $raw);
    }

    /**
     * Returns true if every value in the row is null or an empty string.
     *
     * @param  array<string, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    public function failed(Throwable $exception): void
    {
        Log::error('PlayerHistoryImportJob failed', [
            'csvImportId' => $this->csvImportId,
            'error'       => $exception->getMessage(),
        ]);
    }
}
