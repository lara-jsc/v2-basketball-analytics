<?php

namespace App\Jobs;

use App\Models\CsvImport;
use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\PlayerShotZoneProfile;
use App\Repositories\CsvImportRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Processes an uploaded shot zone profile CSV for a team.
 *
 * Flow:
 *  1. Mark CsvImport as processing.
 *  2. Read and validate the header row.
 *  3. For each data row:
 *     a. Validate zone consistency (made ≤ attempted for every zone).
 *     b. Validate ownership (player belongs to importer's team).
 *     c. Reconcile with box-score histories when they exist.
 *     d. Upsert player_shot_zone_profiles (replace, not additive).
 *     Invalid rows are skipped and logged; import continues.
 *  4. Update CsvImport status → completed|failed.
 */
class ShotZoneProfileImportJob implements ShouldQueue
{
    use Queueable;

    /** Exact header order — case-sensitive */
    public const HEADERS = [
        'player_id',
        'paint_made',
        'paint_attempted',
        'mid_range_made',
        'mid_range_attempted',
        'corner_3_left_made',
        'corner_3_left_attempted',
        'corner_3_right_made',
        'corner_3_right_attempted',
        'above_break_3_made',
        'above_break_3_attempted',
    ];

    /** Zone pairs: [made_col, attempted_col] */
    private const ZONE_PAIRS = [
        'paint' => ['paint_made', 'paint_attempted'],
        'mid_range' => ['mid_range_made', 'mid_range_attempted'],
        'corner_3_left' => ['corner_3_left_made', 'corner_3_left_attempted'],
        'corner_3_right' => ['corner_3_right_made', 'corner_3_right_attempted'],
        'above_break_3' => ['above_break_3_made', 'above_break_3_attempted'],
    ];

    public function __construct(
        public readonly int $csvImportId,
        public readonly int $teamId,
    ) {}

    public function handle(CsvImportRepository $csvImportRepository): void
    {
        /** @var CsvImport $import */
        $import = CsvImport::findOrFail($this->csvImportId);

        $csvImportRepository->markProcessing($import);

        if (! Storage::exists($import->filename)) {
            $csvImportRepository->markFailed($import, "Uploaded file not found: {$import->filename}");

            return;
        }

        $content = Storage::get($import->filename);

        if ($content === null) {
            $csvImportRepository->markFailed($import, 'Could not read the uploaded file.');

            return;
        }

        try {
            [$rowsImported, $errors] = $this->processRows($content);
        } catch (RuntimeException $e) {
            $csvImportRepository->markFailed($import, $e->getMessage());

            return;
        }

        if ($rowsImported === 0 && $errors !== []) {
            $csvImportRepository->markFailed($import, implode("\n", $errors));

            return;
        }

        $import->update([
            'status' => CsvImport::STATUS_COMPLETED,
            'rows_imported' => $rowsImported,
            'error_log' => $errors !== [] ? implode("\n", $errors) : null,
        ]);
    }

    /**
     * @return array{int, list<string>} [rowsImported, errors]
     *
     * @throws RuntimeException on header mismatch
     */
    private function processRows(string $content): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        $lines = array_values($lines);

        if (count($lines) === 0) {
            throw new RuntimeException('The CSV file is empty.');
        }

        $this->assertHeaders($lines[0]);

        $rowsImported = 0;
        $errors = [];

        foreach (array_slice($lines, 1) as $lineIndex => $line) {
            $rowNumber = $lineIndex + 2;

            $values = str_getcsv($line);
            $row = array_combine(self::HEADERS, array_pad($values, count(self::HEADERS), ''));

            if ($row === false) {
                $errors[] = "Row {$rowNumber}: could not parse CSV line.";

                continue;
            }

            try {
                $this->processRow($row);
                $rowsImported++;
            } catch (RuntimeException $e) {
                Log::warning("ShotZoneProfileImportJob: skipping row {$rowNumber}", ['error' => $e->getMessage()]);
                $errors[] = "Row {$rowNumber}: {$e->getMessage()}";
            }
        }

        return [$rowsImported, $errors];
    }

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws RuntimeException
     */
    private function processRow(array $row): void
    {
        $playerId = (int) $row['player_id'];

        if ($playerId <= 0) {
            throw new RuntimeException("Invalid player_id '{$row['player_id']}'.");
        }

        // Player must belong to the importing team
        $player = Player::query()->find($playerId);

        if ($player === null) {
            throw new RuntimeException("Player {$playerId} not found.");
        }

        if ((int) $player->team_id !== $this->teamId) {
            throw new RuntimeException("Player {$playerId} does not belong to this team.");
        }

        // Cast all zone values to integers
        $data = ['player_id' => $playerId];

        foreach (array_slice(self::HEADERS, 1) as $col) {
            $data[$col] = (int) $row[$col];
        }

        // Zone integrity: made ≤ attempted for every zone
        foreach (self::ZONE_PAIRS as $zoneName => [$madeCol, $attemptedCol]) {
            if ($data[$madeCol] > $data[$attemptedCol]) {
                throw new RuntimeException(
                    "Zone '{$zoneName}': made ({$data[$madeCol]}) cannot exceed attempted ({$data[$attemptedCol]})."
                );
            }
        }

        // Reconcile with box-score history when history exists
        $this->reconcileWithHistory($player, $data);

        PlayerShotZoneProfile::updateOrCreate(
            ['player_id' => $playerId],
            array_merge($data, ['imported_at' => now()]),
        );
    }

    /**
     * Verify that zone totals are consistent with aggregated box-score histories.
     *
     * 2PT zone (paint + mid_range) must match FGA − 3PA.
     * 3PT zone (corner_left + corner_right + above_break) must match 3PA.
     * Both made totals must also match.
     *
     * Only enforced when at least one player_histories row exists with non-null FGA/3PA.
     *
     * @param  array<string, int>  $data
     *
     * @throws RuntimeException
     */
    private function reconcileWithHistory(Player $player, array $data): void
    {
        $history = PlayerHistory::where('player_id', $player->id)
            ->whereNotNull('field_goals_attempted')
            ->whereNotNull('three_pointers_attempted')
            ->selectRaw('
                SUM(field_goals_made) as fgm,
                SUM(field_goals_attempted) as fga,
                SUM(three_pointers_made) as tpm,
                SUM(three_pointers_attempted) as tpa
            ')
            ->first();

        // No history with FGA data → nothing to reconcile
        if ($history === null || $history->fga === null) {
            return;
        }

        $histFga = (int) $history->fga;
        $histTpa = (int) $history->tpa;
        $hist2pa = $histFga - $histTpa;

        $profile2pa = $data['paint_attempted'] + $data['mid_range_attempted'];
        $profile3pa = $data['corner_3_left_attempted'] + $data['corner_3_right_attempted'] + $data['above_break_3_attempted'];

        if ($profile2pa !== $hist2pa || $profile3pa !== $histTpa) {
            throw new RuntimeException(
                "Profile zone totals (2PT attempted: {$profile2pa}, 3PT attempted: {$profile3pa}) "
                ."does not match box-score history (2PT: {$hist2pa}, 3PT: {$histTpa}). "
                .'Re-check zone splits or re-import histories.'
            );
        }

        $histFgm = (int) $history->fgm;
        $histTpm = (int) $history->tpm;
        $hist2pm = $histFgm - $histTpm;

        $profile2pm = $data['paint_made'] + $data['mid_range_made'];
        $profile3pm = $data['corner_3_left_made'] + $data['corner_3_right_made'] + $data['above_break_3_made'];

        if ($profile2pm !== $hist2pm || $profile3pm !== $histTpm) {
            throw new RuntimeException(
                "Profile zone makes (2PT: {$profile2pm}, 3PT: {$profile3pm}) "
                ."does not match box-score history (2PT: {$hist2pm}, 3PT: {$histTpm}). "
                .'Re-check zone splits or re-import histories.'
            );
        }
    }

    /**
     * @throws RuntimeException
     */
    private function assertHeaders(string $headerLine): void
    {
        $actual = array_map('trim', str_getcsv($headerLine));

        if ($actual !== self::HEADERS) {
            throw new RuntimeException(
                "CSV headers do not match the required template.\n"
                .'Expected: '.implode(', ', self::HEADERS)."\n"
                .'Received: '.implode(', ', $actual)
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ShotZoneProfileImportJob failed', [
            'csvImportId' => $this->csvImportId,
            'error' => $exception->getMessage(),
        ]);
    }
}
