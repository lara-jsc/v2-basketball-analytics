<?php

use App\Models\CsvImport;
use App\Models\Team;
use App\Repositories\CsvImportRepository;
use App\Repositories\PlayerRepository;
use App\Services\CsvImportService;
use App\Services\CsvTemplateService;
use Illuminate\Support\Facades\Storage;

describe('CsvImportService', function () {

    beforeEach(function () {
        $this->service = new CsvImportService(
            new CsvImportRepository(),
            new PlayerRepository(),
        );
        Storage::fake(); // isolate to local fake disk
    });

    /**
     * Build a valid CSV string with the given rows.
     *
     * @param  list<list<string>>  $rows
     */
    function buildCsv(array $rows = []): string
    {
        $header = implode(',', CsvTemplateService::HEADERS);
        $lines  = array_map(fn ($r) => implode(',', $r), $rows);

        return $header . "\n" . implode("\n", $lines) . "\n";
    }

    /** Store a CSV on the fake local disk and return a CsvImport record pointing to it. */
    function storeCsvImport(Team $team, string $csvContent): CsvImport
    {
        $path = "imports/{$team->id}/roster.csv";
        Storage::put($path, $csvContent);

        return CsvImport::create([
            'team_id'  => $team->id,
            'filename' => $path,
            'status'   => CsvImport::STATUS_PENDING,
        ]);
    }

    // ------------------------------------------------------------------
    // Happy path
    // ------------------------------------------------------------------
    it('marks the import as completed and creates player records', function () {
        $team = Team::factory()->create();
        $csv  = buildCsv([
            ['John', 'Doe', '23', 'Point Guard', '6.1', '85.0', '1'],
            ['Jane', 'Smith', '11', 'Center', '6.4', '100.0', '1'],
        ]);

        $import = storeCsvImport($team, $csv);

        $this->service->process($import->id);

        expect($import->fresh()->status)->toBe(CsvImport::STATUS_COMPLETED);
        expect($import->fresh()->rows_imported)->toBe(2);

        $this->assertDatabaseHas('players', ['first_name' => 'John', 'jersey_number' => 23]);
        $this->assertDatabaseHas('players', ['first_name' => 'Jane', 'jersey_number' => 11]);
    });

    it('sets is_active correctly from the CSV column', function () {
        $team = Team::factory()->create();
        $csv  = buildCsv([
            ['Active', 'Player', '1', 'PG', '6.0', '80.0', '1'],
            ['Inactive', 'Player', '2', 'SG', '6.2', '85.0', '0'],
        ]);

        $import = storeCsvImport($team, $csv);
        $this->service->process($import->id);

        $this->assertDatabaseHas('players', ['first_name' => 'Active', 'is_active' => 1]);
        $this->assertDatabaseHas('players', ['first_name' => 'Inactive', 'is_active' => 0]);
    });

    it('updates existing player (same jersey number) instead of duplicating', function () {
        $team = Team::factory()->create();
        $csv  = buildCsv([['John', 'Doe', '23', 'PG', '6.1', '85.0', '1']]);

        $import = storeCsvImport($team, $csv);
        $this->service->process($import->id);

        // Re-upload with updated name for same jersey
        $updatedCsv    = buildCsv([['Johnny', 'Doe', '23', 'PG', '6.1', '85.0', '1']]);
        $secondImport  = storeCsvImport($team, $updatedCsv);
        $this->service->process($secondImport->id);

        // Should still be 1 player — updated, not duplicated
        $count = \App\Models\Player::where('team_id', $team->id)
            ->where('jersey_number', 23)
            ->count();

        expect($count)->toBe(1);
        $this->assertDatabaseHas('players', ['first_name' => 'Johnny', 'jersey_number' => 23]);
    });

    // ------------------------------------------------------------------
    // Failure cases
    // ------------------------------------------------------------------
    it('marks import as failed when the file does not exist on storage', function () {
        $team   = Team::factory()->create();
        $import = CsvImport::create([
            'team_id'  => $team->id,
            'filename' => 'imports/99/nonexistent.csv',
            'status'   => CsvImport::STATUS_PENDING,
        ]);

        $this->service->process($import->id);

        expect($import->fresh()->status)->toBe(CsvImport::STATUS_FAILED);
    });

    it('marks import as failed when CSV headers do not match the template', function () {
        $team = Team::factory()->create();
        $csv  = "wrong_col,another_col\nfoo,bar\n";

        $import = storeCsvImport($team, $csv);
        $this->service->process($import->id);

        expect($import->fresh()->status)->toBe(CsvImport::STATUS_FAILED);
        expect($import->fresh()->error_log)->toContain('headers do not match');
    });

    it('marks import as failed when the CSV file is empty', function () {
        $team   = Team::factory()->create();
        $import = storeCsvImport($team, '');

        $this->service->process($import->id);

        expect($import->fresh()->status)->toBe(CsvImport::STATUS_FAILED);
    });

    it('records partial errors but marks as completed when at least one row succeeds', function () {
        $team = Team::factory()->create();
        // Row 2 has wrong column count — extra field
        $csv = implode(',', CsvTemplateService::HEADERS) . "\n"
            . "John,Doe,23,PG,6.1,85.0,1\n"
            . "BadRow,MissingField\n";

        $import = storeCsvImport($team, $csv);
        $this->service->process($import->id);

        expect($import->fresh()->status)->toBe(CsvImport::STATUS_COMPLETED);
        expect($import->fresh()->rows_imported)->toBe(1);
        expect($import->fresh()->error_log)->not->toBeNull();
    });
});
