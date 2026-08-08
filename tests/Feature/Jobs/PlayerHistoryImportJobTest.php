<?php

use App\Jobs\PlayerHistoryImportJob;
use App\Models\CsvImport;
use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\Team;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build an in-memory xlsx file using PlayerHistoryImportJob::HEADERS as the
 * header row, with optional extra data rows. Returns the raw file content.
 *
 * @param  list<list<mixed>>  $rows  Data rows (no header)
 * @param  list<string>|null  $headers  Override headers for bad-header tests
 */
function buildHistoryXlsx(array $rows = [], ?array $headers = null): string
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Template');

    $hdrs = $headers ?? PlayerHistoryImportJob::HEADERS;

    foreach ($hdrs as $colIndex => $header) {
        $sheet->getCell([$colIndex + 1, 1])->setValue($header);
    }

    foreach ($rows as $rowIndex => $row) {
        foreach ($row as $colIndex => $value) {
            $sheet->getCell([$colIndex + 1, $rowIndex + 2])->setValue($value);
        }
    }

    $writer = new Xlsx($spreadsheet);

    ob_start();
    $writer->save('php://output');

    return (string) ob_get_clean();
}

/**
 * Store xlsx content on the fake disk and create a CsvImport pointing to it.
 */
function storeHistoryImport(Team $team, string $content): CsvImport
{
    $path = "player-history-imports/test-{$team->id}.xlsx";
    Storage::put($path, $content);

    return CsvImport::create([
        'team_id' => $team->id,
        'filename' => $path,
        'status' => CsvImport::STATUS_PENDING,
    ]);
}

/**
 * Return a valid data row array matching HEADERS (without playing_team_id).
 * opponent_team_id is required as a parameter since it must be a real team id.
 */
function validRow(int $opponentTeamId, string $gameDate = '2025-01-15'): array
{
    return [
        $gameDate,       // game_date
        $opponentTeamId, // opponent_team_id
        'PG',            // position_played
        32.5,            // minutes_played
        22,              // points
        8,               // field_goals_made
        16,              // field_goals_attempted
        3,               // three_pointers_made
        7,               // three_pointers_attempted
        3,               // free_throws_made
        4,               // free_throws_attempted
        1,               // offensive_rebounds
        5,               // defensive_rebounds
        6,               // rebounds
        7,               // assists
        2,               // steals
        1,               // blocks
        3,               // turnovers
        2,               // personal_fouls
        0,               // flagrant_fouls
        0,               // technical_fouls
        0,               // ejections
        0,               // disqualifications
        1,               // is_started
        '',              // notes
    ];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('PlayerHistoryImportJob', function () {

    beforeEach(function () {
        Storage::fake();
    });

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    it('imports a row and auto-fills playing_team_id from the player team', function () {
        $playingTeam = Team::factory()->create();
        $opponentTeam = Team::factory()->create();
        $player = Player::factory()->for($playingTeam)->create();

        $xlsx = buildHistoryXlsx([validRow($opponentTeam->id)]);
        $import = storeHistoryImport($playingTeam, $xlsx);

        PlayerHistoryImportJob::dispatchSync($import->id, $player->id);

        expect($import->fresh()->status)->toBe(CsvImport::STATUS_COMPLETED);
        expect($import->fresh()->rows_imported)->toBe(1);

        $this->assertDatabaseHas('player_histories', [
            'player_id' => $player->id,
            'playing_team_id' => $playingTeam->id,   // injected automatically
            'opponent_team_id' => $opponentTeam->id,
            'points' => 22,
        ]);
    });

    it('imports multiple rows', function () {
        $playingTeam = Team::factory()->create();
        $opponentTeam = Team::factory()->create();
        $player = Player::factory()->for($playingTeam)->create();

        $xlsx = buildHistoryXlsx([
            validRow($opponentTeam->id, '2025-01-15'),
            validRow($opponentTeam->id, '2025-01-20'),
        ]);
        $import = storeHistoryImport($playingTeam, $xlsx);

        PlayerHistoryImportJob::dispatchSync($import->id, $player->id);

        expect($import->fresh()->rows_imported)->toBe(2);
        expect(PlayerHistory::where('player_id', $player->id)->count())->toBe(2);
    });

    it('skips the example row that starts with DELETE THIS ROW', function () {
        $playingTeam = Team::factory()->create();
        $opponentTeam = Team::factory()->create();
        $player = Player::factory()->for($playingTeam)->create();

        $exampleRow = validRow($opponentTeam->id);
        $exampleRow[count($exampleRow) - 1] = 'DELETE THIS ROW - EXAMPLE ONLY';

        $xlsx = buildHistoryXlsx([$exampleRow, validRow($opponentTeam->id)]);
        $import = storeHistoryImport($playingTeam, $xlsx);

        PlayerHistoryImportJob::dispatchSync($import->id, $player->id);

        // Only the real row should be imported
        expect($import->fresh()->rows_imported)->toBe(1);
    });

    it('upserts on duplicate player+date+opponent instead of inserting twice', function () {
        $playingTeam = Team::factory()->create();
        $opponentTeam = Team::factory()->create();
        $player = Player::factory()->for($playingTeam)->create();

        $xlsx = buildHistoryXlsx([validRow($opponentTeam->id, '2025-03-01')]);
        $import = storeHistoryImport($playingTeam, $xlsx);
        PlayerHistoryImportJob::dispatchSync($import->id, $player->id);

        // Re-import same game date — should update, not duplicate
        $import2 = storeHistoryImport($playingTeam, $xlsx);
        PlayerHistoryImportJob::dispatchSync($import2->id, $player->id);

        expect(PlayerHistory::where('player_id', $player->id)->count())->toBe(1);
    });

    // -----------------------------------------------------------------------
    // Failure / edge cases
    // -----------------------------------------------------------------------

    it('marks import as failed when the file does not exist on storage', function () {
        $team = Team::factory()->create();
        $player = Player::factory()->for($team)->create();

        $import = CsvImport::create([
            'team_id' => $team->id,
            'filename' => 'player-history-imports/nonexistent.xlsx',
            'status' => CsvImport::STATUS_PENDING,
        ]);

        PlayerHistoryImportJob::dispatchSync($import->id, $player->id);

        expect($import->fresh()->status)->toBe(CsvImport::STATUS_FAILED);
    });

    it('marks import as failed when headers do not match', function () {
        $team = Team::factory()->create();
        $player = Player::factory()->for($team)->create();

        $xlsx = buildHistoryXlsx([], ['wrong_col', 'another_col']);
        $import = storeHistoryImport($team, $xlsx);

        PlayerHistoryImportJob::dispatchSync($import->id, $player->id);

        expect($import->fresh()->status)->toBe(CsvImport::STATUS_FAILED);
        expect($import->fresh()->error_log)->toContain('headers do not match');
    });

    it('skips rows with an invalid game_date and continues', function () {
        $playingTeam = Team::factory()->create();
        $opponentTeam = Team::factory()->create();
        $player = Player::factory()->for($playingTeam)->create();

        $badRow = validRow($opponentTeam->id);
        $badRow[0] = 'not-a-date'; // game_date

        $xlsx = buildHistoryXlsx([$badRow, validRow($opponentTeam->id, '2025-02-01')]);
        $import = storeHistoryImport($playingTeam, $xlsx);

        PlayerHistoryImportJob::dispatchSync($import->id, $player->id);

        expect($import->fresh()->rows_imported)->toBe(1);
        expect($import->fresh()->error_log)->toContain('Row 2');
    });

    it('template headers do not include playing_team_id', function () {
        expect(PlayerHistoryImportJob::HEADERS)->not->toContain('playing_team_id');
        expect(PlayerHistoryImportJob::HEADERS)->toContain('opponent_team_id');
    });
});
