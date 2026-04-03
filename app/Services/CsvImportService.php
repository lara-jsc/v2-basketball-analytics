<?php

namespace App\Services;

use App\Jobs\ComputePlayerPlusMinus;
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
 * CSV column → DB mapping:
 *   first_name, last_name, jersey_number, role, height_feet, weight_kg, is_active → players
 *   pc, sd, 3P%, 3PT, AST, AST/TO, BLK, DD2, DQ, DR, EJECT, FG, FG%, FLAG,
 *   FT, FT%, GP, GS, MIN, OR → offensive_rebounds, PF, PTS, REB, SC-EFF, SH-EFF,
 *   STL, STL/TO, TD3, TECH, TO → to_per_game  →  player_stats
 */
class CsvImportService
{
    /** CSV header → player_stats column mapping (non-obvious names only) */
    private const STAT_COLUMN_MAP = [
        '3P%'     => 'three_p_pct',
        '3PT'     => 'three_pt',
        'AST'     => 'ast',
        'AST/TO'  => 'ast_to',
        'BLK'     => 'blk',
        'DD2'     => 'dd2',
        'DQ'      => 'dq',
        'DR'      => 'dr',
        'EJECT'   => 'eject',
        'FG'      => 'fg',
        'FG%'     => 'fg_pct',
        'FLAG'    => 'flag',
        'FT'      => 'ft',
        'FT%'     => 'ft_pct',
        'GP'      => 'gp',
        'GS'      => 'gs',
        'MIN'     => 'min',
        'OR'      => 'offensive_rebounds',  // reserved word: "or"
        'PF'      => 'pf',
        'PTS'     => 'pts',
        'REB'     => 'reb',
        'SC-EFF'  => 'sc_eff',
        'SH-EFF'  => 'sh_eff',
        'STL'     => 'stl',
        'STL/TO'  => 'stl_to',
        'TD3'     => 'td3',
        'TECH'    => 'tech',
        'TO'      => 'to_per_game',         // reserved word: "to"
        'pc'      => 'pc',
        'sd'      => 'sd',
    ];

    /** Player-level CSV columns (not stored in player_stats) */
    private const PLAYER_COLUMNS = [
        'first_name', 'last_name', 'jersey_number', 'role',
        'height_feet', 'weight_kg', 'is_active',
    ];

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
                $playerStatId = $this->importRow($import->team_id, $data);
                ComputePlayerPlusMinus::dispatch($playerStatId);
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
     * Returns the PlayerStat ID so the BPM job can be dispatched.
     *
     * @param  array<string, string>  $data
     */
    private function importRow(int $teamId, array $data): int
    {
        return DB::transaction(function () use ($teamId, $data): int {
            $jerseyNumber = (int) ($data['jersey_number'] ?? 0);

            $player = $this->playerRepository->updateOrCreateByJersey(
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

            $statData = [];

            foreach (self::STAT_COLUMN_MAP as $csvCol => $dbCol) {
                $raw = $data[$csvCol] ?? null;
                $statData[$dbCol] = ($raw !== null && $raw !== '') ? $this->castStat($dbCol, $raw) : null;
            }

            $stat = $this->playerRepository->upsertStat($player, $statData);

            return $stat->id;
        });
    }

    private function parseBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes'], strict: true);
    }

    /** Cast a raw CSV string to the correct PHP type for a given stat column. */
    private function castStat(string $dbCol, string $raw): mixed
    {
        // Integer columns
        if (in_array($dbCol, ['gp', 'gs', 'dd2', 'td3', 'dq', 'eject', 'flag', 'tech'], strict: true)) {
            return (int) $raw;
        }

        // String columns stored as-is (made-attempted strings)
        if (in_array($dbCol, ['three_pt', 'fg', 'ft', 'pc', 'sd'], strict: true)) {
            return $raw;
        }

        // Everything else is decimal/float
        return (float) $raw;
    }
}
