<?php

use App\Jobs\PlayerHistoryImportJob;
use App\Models\CsvImport;
use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function buildHistoryCsv(array $rows = [], ?array $headers = null): string
{
    $stream = fopen('php://temp', 'r+');
    $headerRow = $headers ?? PlayerHistoryImportJob::HEADERS;

    fputcsv($stream, $headerRow);

    foreach ($rows as $row) {
        fputcsv($stream, $row);
    }

    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);

    return $content === false ? '' : $content;
}

function buildHistoryXlsxForController(array $rows = [], ?array $headers = null): string
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Template');

    foreach (($headers ?? PlayerHistoryImportJob::HEADERS) as $colIndex => $header) {
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

function validHistoryRow(int $opponentTeamId, string $date = '2025-01-15'): array
{
    return [
        $date,
        $opponentTeamId,
        'PG',
        31.5,
        18,
        7,
        14,
        2,
        5,
        2,
        3,
        1,
        4,
        5,
        6,
        2,
        1,
        3,
        2,
        0,
        0,
        0,
        0,
        1,
        '',
    ];
}

describe('Player history HTTP endpoints', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('downloads a player-scoped template with only valid opponents in the dropdown', function () {
        $alpha = Team::factory()->create(['name' => 'Alpha Academy', 'code' => 'ALP']);
        $bravo = Team::factory()->create(['name' => 'Bravo Ballers', 'code' => 'BRV']);
        $charlie = Team::factory()->create(['name' => 'Charlie Chargers', 'code' => 'CHA']);
        $player = Player::factory()->for($bravo)->create();

        $response = $this->actingAs($this->user)
            ->get(route('player-histories.template', $player));

        $response->assertOk();

        $tempPath = tempnam(sys_get_temp_dir(), 'player-history-template-');
        file_put_contents($tempPath, $response->getContent());

        $spreadsheet = IOFactory::load($tempPath);
        @unlink($tempPath);

        $templateSheet = $spreadsheet->getSheetByName('Template');
        $teamsSheet = $spreadsheet->getSheetByName('Teams');

        expect($templateSheet)->not->toBeNull();
        expect($teamsSheet)->not->toBeNull();
        expect($teamsSheet->getSheetState())->toBe(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        expect($templateSheet->rangeToArray('A1:Y1', null, true, true, true)[1])->toBe(array_combine(
            range('A', 'Y'),
            PlayerHistoryImportJob::HEADERS,
        ));
        expect($teamsSheet->rangeToArray('B2:B3', null, true, true, true))->toBe([
            2 => ['B' => '1 | Alpha Academy (ALP)'],
            3 => ['B' => '3 | Charlie Chargers (CHA)'],
        ]);
        expect($teamsSheet->rangeToArray('B2:B4', null, true, true, true)[4]['B'] ?? null)->toBeNull();
        expect($templateSheet->getDataValidation('B2')->getFormula1())->toBe('Teams!$B$2:$B$3');
        expect($templateSheet->getDataValidation('B2')->getShowDropDown())->toBeTrue();
        expect($templateSheet->getDataValidation('B2')->getPrompt())->toContain('valid opponents');
        expect($templateSheet->getDataValidation('C2')->getFormula1())->toBe('"PG,SG,SF,PF,C,G,F"');
        expect($templateSheet->getDataValidation('C2')->getShowDropDown())->toBeTrue();
        expect($templateSheet->getDataValidation('X2')->getFormula1())->toBe('"1,0"');
        expect($templateSheet->getDataValidation('X2')->getShowDropDown())->toBeTrue();
    });

    it('shows the create page with the current team context and opponents excluding that team', function () {
        $alpha = Team::factory()->create(['name' => 'Alpha Academy']);
        $bravo = Team::factory()->create(['name' => 'Bravo Ballers']);
        $charlie = Team::factory()->create(['name' => 'Charlie Chargers']);
        $player = Player::factory()->for($bravo)->create();

        $this->actingAs($this->user)
            ->get(route('player-histories.create', $player))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Players/Histories/Create')
                ->where('playingTeam.id', $bravo->id)
                ->where('opponentTeams', fn ($teams) => collect($teams)->pluck('id')->all() === [$alpha->id, $charlie->id]));
    });

    it('shows the edit page with the current team context and opponents excluding that team', function () {
        $alpha = Team::factory()->create(['name' => 'Alpha Academy']);
        $bravo = Team::factory()->create(['name' => 'Bravo Ballers']);
        $charlie = Team::factory()->create(['name' => 'Charlie Chargers']);
        $player = Player::factory()->for($bravo)->create();
        $history = PlayerHistory::factory()->for($player)->create([
            'playing_team_id' => $bravo->id,
            'opponent_team_id' => $alpha->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('player-histories.edit', $history))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Players/Histories/Edit')
                ->where('playingTeam.id', $bravo->id)
                ->where('opponentTeams', fn ($teams) => collect($teams)->pluck('id')->all() === [$alpha->id, $charlie->id]));
    });

    it('shows the index page with an export url that preserves active filters', function () {
        $playingTeam = Team::factory()->create(['name' => 'Bravo Ballers']);
        $opponent = Team::factory()->create(['name' => 'Alpha Academy']);
        $player = Player::factory()->for($playingTeam)->create();

        $this->actingAs($this->user)
            ->get(route('player-histories.index', [
                'player' => $player,
                'from' => '2025-01-01',
                'to' => '2025-01-31',
                'opponent_team_id' => $opponent->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Players/Histories/Index')
                ->where(
                    'exportUrl',
                    route('player-histories.export', [
                        'player' => $player,
                        'from' => '2025-01-01',
                        'to' => '2025-01-31',
                        'opponent_team_id' => $opponent->id,
                    ]),
                ));
    });

    it('exports filtered player histories as an import-safe csv', function () {
        $playingTeam = Team::factory()->create(['code' => 'BRV']);
        $keepOpponent = Team::factory()->create(['code' => 'ALP']);
        $skipOpponent = Team::factory()->create(['code' => 'CHA']);
        $player = Player::factory()->for($playingTeam)->create();

        PlayerHistory::factory()->forPlayer($player)->forTeams($playingTeam, $keepOpponent)->create([
            'game_date' => '2025-01-15',
            'position_played' => 'PG',
            'minutes_played' => 34.5,
            'points' => 21,
            'field_goals_made' => 8,
            'field_goals_attempted' => 15,
            'three_pointers_made' => 3,
            'three_pointers_attempted' => 7,
            'free_throws_made' => 2,
            'free_throws_attempted' => 2,
            'offensive_rebounds' => 1,
            'defensive_rebounds' => 4,
            'rebounds' => 5,
            'assists' => 7,
            'steals' => 2,
            'blocks' => 1,
            'turnovers' => 3,
            'personal_fouls' => 2,
            'flagrant_fouls' => 0,
            'technical_fouls' => 0,
            'ejections' => 0,
            'disqualifications' => 0,
            'is_started' => true,
            'notes' => 'playoff tune-up',
        ]);
        PlayerHistory::factory()->forPlayer($player)->forTeams($playingTeam, $skipOpponent)->create([
            'game_date' => '2025-02-02',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('player-histories.export', [
                'player' => $player,
                'from' => '2025-01-01',
                'to' => '2025-01-31',
                'opponent_team_id' => $keepOpponent->id,
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $rows = array_map(
            static fn (string $line): array => str_getcsv($line),
            preg_split('/\r\n|\r|\n/', trim($response->streamedContent())) ?: [],
        );

        expect($rows[0])->toBe(PlayerHistoryImportJob::HEADERS);
        expect($rows)->toHaveCount(2);
        expect($rows[1])->toBe([
            '2025-01-15',
            (string) $keepOpponent->id,
            'PG',
            '34.5',
            '21',
            '8',
            '15',
            '3',
            '7',
            '2',
            '2',
            '1',
            '4',
            '5',
            '7',
            '2',
            '1',
            '3',
            '2',
            '0',
            '0',
            '0',
            '0',
            '1',
            'playoff tune-up',
        ]);
    });

    it('allows an exported player history csv to be re-uploaded without duplicating rows', function () {
        $playingTeam = Team::factory()->create();
        $opponent = Team::factory()->create();
        $player = Player::factory()->for($playingTeam)->create();

        PlayerHistory::factory()->forPlayer($player)->forTeams($playingTeam, $opponent)->create([
            'game_date' => '2025-01-15',
            'points' => 19,
        ]);

        $export = $this->actingAs($this->user)
            ->get(route('player-histories.export', $player))
            ->streamedContent();

        $upload = UploadedFile::fake()->createWithContent('histories.csv', $export);

        $this->actingAs($this->user)
            ->post(route('player-histories.import', $player), ['file' => $upload])
            ->assertRedirect();

        $latestImport = CsvImport::query()->latest('id')->first();

        expect(PlayerHistory::where('player_id', $player->id)->count())->toBe(1);
        expect($latestImport?->error_log)->toBeNull();
        expect($latestImport?->status)->toBe(CsvImport::STATUS_COMPLETED);
    });

    it('accepts player history csv uploads with the exact import headers', function () {
        $playingTeam = Team::factory()->create();
        $opponent = Team::factory()->create();
        $player = Player::factory()->for($playingTeam)->create();

        $upload = UploadedFile::fake()->createWithContent(
            'histories.csv',
            buildHistoryCsv([validHistoryRow($opponent->id)]),
        );

        $this->actingAs($this->user)
            ->post(route('player-histories.import', $player), ['file' => $upload])
            ->assertRedirect();

        expect(PlayerHistory::query()
            ->where('player_id', $player->id)
            ->where('playing_team_id', $playingTeam->id)
            ->where('opponent_team_id', $opponent->id)
            ->whereDate('game_date', '2025-01-15')
            ->exists())->toBeTrue();
    });

    it('rejects player history csv uploads when the headers do not match', function () {
        $playingTeam = Team::factory()->create();
        $player = Player::factory()->for($playingTeam)->create();

        $upload = UploadedFile::fake()->createWithContent(
            'histories.csv',
            buildHistoryCsv([], ['wrong', 'headers']),
        );

        $this->actingAs($this->user)
            ->from(route('player-histories.index', $player))
            ->post(route('player-histories.import', $player), ['file' => $upload])
            ->assertSessionHasErrors('file');
    });

    it('still accepts valid xlsx uploads through the existing import route', function () {
        $playingTeam = Team::factory()->create();
        $opponent = Team::factory()->create();
        $player = Player::factory()->for($playingTeam)->create();

        $upload = UploadedFile::fake()->createWithContent(
            'histories.xlsx',
            buildHistoryXlsxForController([validHistoryRow($opponent->id)]),
        );

        $this->actingAs($this->user)
            ->post(route('player-histories.import', $player), ['file' => $upload])
            ->assertRedirect();

        expect(PlayerHistory::query()
            ->where('player_id', $player->id)
            ->where('opponent_team_id', $opponent->id)
            ->whereDate('game_date', '2025-01-15')
            ->exists())->toBeTrue();
    });

    it('rejects storing a history when the opponent matches the player current team', function () {
        $team = Team::factory()->create();
        $player = Player::factory()->for($team)->create();

        $this->actingAs($this->user)
            ->from(route('player-histories.create', $player))
            ->post(route('player-histories.store', $player), [
                'game_date' => '2025-01-15',
                'opponent_team_id' => $team->id,
            ])
            ->assertSessionHasErrors('opponent_team_id');
    });

    it('rejects updating a history when the opponent matches the player current team', function () {
        $team = Team::factory()->create();
        $opponent = Team::factory()->create();
        $player = Player::factory()->for($team)->create();
        $history = PlayerHistory::factory()->for($player)->create([
            'playing_team_id' => $team->id,
            'opponent_team_id' => $opponent->id,
        ]);

        $this->actingAs($this->user)
            ->from(route('player-histories.edit', $history))
            ->put(route('player-histories.update', $history), [
                'opponent_team_id' => $team->id,
            ])
            ->assertSessionHasErrors('opponent_team_id');
    });
});
