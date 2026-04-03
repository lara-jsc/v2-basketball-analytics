<?php

namespace App\Jobs;

use App\Models\CsvImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Processes an uploaded CSV file row-by-row.
 *
 * Flow:
 *  1. Validate CSV headers against the spec template (case-sensitive exact match).
 *  2. For each valid row: upsert Player + PlayerStat records inside a DB transaction.
 *  3. On success: dispatch ComputePlayerPlusMinus per player row.
 *  4. Update CsvImport.status to completed|failed and persist rows_imported / error_log.
 *
 * All business logic lives in CsvImportService (injected in handle()).
 */
class ProcessCsvImport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $csvImportId,
    ) {}

    /**
     * Execute the job.
     * Full implementation in Phase 1 — see CsvImportService.
     */
    public function handle(): void
    {
        // TODO (Phase 1): inject CsvImportService, call $service->process($this->csvImportId)
    }
}
