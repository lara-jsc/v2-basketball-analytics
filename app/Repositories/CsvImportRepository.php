<?php

namespace App\Repositories;

use App\Models\CsvImport;

class CsvImportRepository
{
    /**
     * Create a new import record with status=pending.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CsvImport
    {
        return CsvImport::create($data);
    }

    /**
     * Mark an import as processing.
     */
    public function markProcessing(CsvImport $import): void
    {
        $import->update(['status' => CsvImport::STATUS_PROCESSING]);
    }

    /**
     * Mark an import as completed with row count.
     */
    public function markCompleted(CsvImport $import, int $rowsImported): void
    {
        $import->update([
            'status'        => CsvImport::STATUS_COMPLETED,
            'rows_imported' => $rowsImported,
        ]);
    }

    /**
     * Mark an import as failed with an error message.
     */
    public function markFailed(CsvImport $import, string $errorLog): void
    {
        $import->update([
            'status'    => CsvImport::STATUS_FAILED,
            'error_log' => $errorLog,
        ]);
    }

    /**
     * Most recent import for a team (null if none).
     */
    public function latestForTeam(int $teamId): ?CsvImport
    {
        return CsvImport::where('team_id', $teamId)->latest()->first();
    }
}
