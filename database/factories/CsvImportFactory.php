<?php

namespace Database\Factories;

use App\Models\CsvImport;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CsvImport>
 */
class CsvImportFactory extends Factory
{
    protected $model = CsvImport::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'filename' => 'imports/1/roster.csv',
            'status' => CsvImport::STATUS_PENDING,
            'rows_imported' => 0,
            'error_log' => null,
        ];
    }

    public function completed(int $rows = 10): static
    {
        return $this->state([
            'status' => CsvImport::STATUS_COMPLETED,
            'rows_imported' => $rows,
        ]);
    }

    public function failed(string $error = 'Something went wrong'): static
    {
        return $this->state([
            'status' => CsvImport::STATUS_FAILED,
            'error_log' => $error,
        ]);
    }
}
