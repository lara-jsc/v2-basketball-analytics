<?php

namespace App\Jobs;

use App\Services\CsvImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes an uploaded CSV roster file for a team.
 *
 * Flow:
 *  1. Validate CSV headers against the spec template (case-sensitive exact match).
 *  2. For each valid row: upsert Player + PlayerStat inside a DB transaction.
 *  3. Dispatch ComputePlayerPlusMinus per successfully imported player row.
 *  4. Update CsvImport.status → completed|failed; persist rows_imported / error_log.
 */
class ProcessCsvImport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $csvImportId,
    ) {}

    public function handle(CsvImportService $service): void
    {
        $service->process($this->csvImportId);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ProcessCsvImport job failed', [
            'csvImportId' => $this->csvImportId,
            'error'       => $exception->getMessage(),
        ]);
    }
}
