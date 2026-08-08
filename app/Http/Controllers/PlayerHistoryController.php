<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerHistoryImportRequest;
use App\Http\Requests\StorePlayerHistoryRequest;
use App\Http\Requests\UpdatePlayerHistoryRequest;
use App\Jobs\PlayerHistoryImportJob;
use App\Models\CsvImport;
use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\Team;
use App\Services\PlayerHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PlayerHistoryController extends Controller
{
    public function __construct(
        private readonly PlayerHistoryService $historyService,
    ) {}

    /**
     * List all game history entries for a player.
     */
    public function index(Player $player): InertiaResponse
    {
        $filters = array_filter([
            'from' => request('from'),
            'to' => request('to'),
            'playing_team_id' => request()->integer('playing_team_id') ?: null,
            'opponent_team_id' => request()->integer('opponent_team_id') ?: null,
        ]);

        $histories = $this->historyService->listForPlayer($player, $filters);

        return Inertia::render('Players/Histories/Index', [
            'player' => $player->load('team:id,code,name'),
            'histories' => $histories,
            'teams' => Team::select('id', 'code', 'name')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    /**
     * Show form to manually add a game entry for a player.
     */
    public function create(Player $player): InertiaResponse
    {
        return Inertia::render('Players/Histories/Create', [
            'player' => $player->load('team:id,code,name'),
            'teams' => Team::select('id', 'code', 'name')->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a new game history entry and trigger stats rebuild.
     */
    public function store(StorePlayerHistoryRequest $request, Player $player): RedirectResponse
    {
        $this->historyService->store($player, [
            ...$request->validated(),
            'playing_team_id' => $player->team_id,
        ]);

        return redirect()
            ->route('player-histories.index', $player->id)
            ->with('success', 'Game entry added. Player stats are being recalculated.');
    }

    /**
     * Show form to edit an existing game history entry.
     */
    public function edit(PlayerHistory $history): InertiaResponse
    {
        return Inertia::render('Players/Histories/Edit', [
            'history' => $history->load([
                'player.team:id,code,name',
                'playingTeam:id,code,name',
                'opponentTeam:id,code,name',
            ]),
            'teams' => Team::select('id', 'code', 'name')->orderBy('name')->get(),
        ]);
    }

    /**
     * Update a game history entry and trigger stats rebuild.
     */
    public function update(UpdatePlayerHistoryRequest $request, PlayerHistory $history): RedirectResponse
    {
        $this->historyService->update($history, $request->validated());

        return redirect()
            ->route('player-histories.index', $history->player_id)
            ->with('success', 'Game entry updated. Player stats are being recalculated.');
    }

    /**
     * Hard-delete a game history entry and trigger stats rebuild.
     */
    public function destroy(PlayerHistory $history): RedirectResponse
    {
        $playerId = $history->player_id;

        $this->historyService->destroy($history);

        return redirect()
            ->route('player-histories.index', $playerId)
            ->with('success', 'Game entry removed. Player stats are being recalculated.');
    }

    /**
     * Stream an Excel (.xlsx) template with headers, one example row,
     * and dropdown data validation on playing_team_id and opponent_team_id columns.
     */
    public function downloadTemplate(): Response
    {
        $teams = Team::select('id', 'code', 'name')->orderBy('name')->get();

        $spreadsheet = new Spreadsheet;

        // ── Sheet 1: Template ────────────────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        $headers = PlayerHistoryImportJob::HEADERS;

        // Write header row (bold)
        foreach ($headers as $colIndex => $header) {
            $col = $colIndex + 1;
            $cell = $sheet->getCell([$col, 1]);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
        }

        // Example data row
        $exampleValues = [
            '2025-01-15',                     // game_date
            '',                               // opponent_team_id — pick from dropdown
            'PG',                             // position_played  — pick from dropdown
            32.5,                             // minutes_played
            22,                               // points
            8,                                // field_goals_made
            16,                               // field_goals_attempted
            3,                                // three_pointers_made
            7,                                // three_pointers_attempted
            3,                                // free_throws_made
            4,                                // free_throws_attempted
            1,                                // offensive_rebounds
            5,                                // defensive_rebounds
            6,                                // rebounds
            7,                                // assists
            2,                                // steals
            1,                                // blocks
            3,                                // turnovers
            2,                                // personal_fouls
            0,                                // flagrant_fouls
            0,                                // technical_fouls
            0,                                // ejections
            0,                                // disqualifications
            1,                                // is_started       — pick from dropdown
            'DELETE THIS ROW - EXAMPLE ONLY', // notes
        ];

        foreach ($exampleValues as $colIndex => $value) {
            $sheet->getCell([$colIndex + 1, 2])->setValue($value);
        }

        // ── Sheet 2: Teams lookup (hidden) ───────────────────────────────────
        $teamsSheet = $spreadsheet->createSheet();
        $teamsSheet->setTitle('Teams');
        $teamsSheet->setCellValue('A1', 'id');
        $teamsSheet->setCellValue('B1', 'label');

        foreach ($teams as $rowIndex => $team) {
            $teamsSheet->setCellValue([1, $rowIndex + 2], $team->id);
            $teamsSheet->setCellValue([2, $rowIndex + 2], "{$team->id} | {$team->name} ({$team->code})");
        }

        $teamCount = $teams->count();
        $teamListSource = $teamCount > 0
            ? 'Teams!$B$2:$B$'.($teamCount + 1)
            : '"No teams yet"';

        $teamsSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        // ── Dropdown validations ──────────────────────────────────────────────
        $spreadsheet->setActiveSheetIndex(0);

        $dropdowns = [
            'B' => [
                'formula1' => $teamListSource,
                'promptTitle' => 'Opponent Team',
                'prompt' => 'Select a team from the dropdown list.',
                'errorTitle' => 'Invalid team',
                'error' => 'Please select a team from the dropdown list.',
            ],
            'C' => [
                'formula1' => '"PG,SG,SF,PF,C,G,F"',
                'promptTitle' => 'Position',
                'prompt' => 'Select a position: PG, SG, SF, PF, C, G, or F.',
                'errorTitle' => 'Invalid position',
                'error' => 'Please select a valid position from the dropdown.',
            ],
            'X' => [
                'formula1' => '"1,0"',
                'promptTitle' => 'Started?',
                'prompt' => '1 = started the game, 0 = came off the bench.',
                'errorTitle' => 'Invalid value',
                'error' => 'Please enter 1 (started) or 0 (bench).',
            ],
        ];

        foreach ($dropdowns as $col => $config) {
            $range = "{$col}2:{$col}1001";
            $validation = $sheet->getDataValidation("{$col}2");
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(false);
            $validation->setShowInputMessage(true);
            $validation->setPromptTitle($config['promptTitle']);
            $validation->setPrompt($config['prompt']);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle($config['errorTitle']);
            $validation->setError($config['error']);
            $validation->setFormula1($config['formula1']);
            $validation->setSqref($range);
        }

        // Auto-size header columns
        foreach (range(1, count($headers)) as $col) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        // ── Stream response ──────────────────────────────────────────────────
        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content ?? '', 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="player-histories-template.xlsx"',
        ]);
    }

    /**
     * Accept a xlsx upload scoped to a player, validate headers, create a
     * CsvImport record, and run the PlayerHistoryImportJob synchronously so the
     * redirect can show updated rows and Inertia flash.
     */
    public function import(PlayerHistoryImportRequest $request, Player $player): RedirectResponse
    {
        $headerError = $request->validateXlsxHeaders();

        if ($headerError !== null) {
            return back()->withErrors(['file' => $headerError]);
        }

        $path = $request->file('file')->store('player-history-imports');

        /** @var CsvImport $csvImport */
        $csvImport = CsvImport::create([
            'team_id' => $player->team_id,
            'filename' => $path,
            'status' => CsvImport::STATUS_PENDING,
        ]);

        PlayerHistoryImportJob::dispatchSync($csvImport->id, $player->id);

        Inertia::flash('success', 'Game history imported successfully.');

        return back();
    }
}
