<?php

use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\CsvController;
use App\Http\Controllers\LiveGameClockController;
use App\Http\Controllers\LiveGameController;
use App\Http\Controllers\LiveGameEventController;
use App\Http\Controllers\LiveGameDelegationController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayerHistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/live-games', [LiveGameController::class, 'index'])->name('live-games.index');
    Route::get('/live-games/create', [LiveGameController::class, 'create'])->name('live-games.create');
    Route::post('/live-games', [LiveGameController::class, 'store'])->name('live-games.store');
    Route::get('/live-games/{liveGame}', [LiveGameController::class, 'show'])->name('live-games.show');
    Route::post('/live-games/{liveGame}/lineup', [LiveGameController::class, 'submitLineup'])->name('live-games.lineup');
    Route::post('/live-games/{liveGame}/start', [LiveGameController::class, 'start'])->name('live-games.start');
    Route::post('/live-games/{liveGame}/finish', [LiveGameController::class, 'finish'])->name('live-games.finish');
    Route::post('/live-games/{liveGame}/events', [LiveGameEventController::class, 'store'])
        ->name('live-games.events.store');

    Route::post('/live-games/{liveGame}/clock', [LiveGameClockController::class, 'store'])
        ->name('live-games.clock.store');

    Route::post('/live-games/{liveGame}/delegations', [LiveGameDelegationController::class, 'store'])
        ->name('live-games.delegations.store');

    Route::post('/live-games/{liveGame}/correction', [LiveGameController::class, 'correction'])
        ->name('live-games.correction');

    // ── Profile ───────────────────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Teams (Phase 1 + 2) ───────────────────────────────────────────────
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::patch('/teams/{team}/toggle-active', [TeamController::class, 'toggleActive'])->name('teams.toggleActive');
    Route::post('/teams/{team}/logo', [TeamController::class, 'uploadLogo'])->name('teams.uploadLogo');

    // ── Players (Phase 2) ─────────────────────────────────────────────────
    Route::post('/teams/{team}/players', [PlayerController::class, 'store'])->name('players.store');
    Route::put('/players/{player}', [PlayerController::class, 'update'])->name('players.update');
    Route::delete('/players/{player}', [PlayerController::class, 'destroy'])->name('players.destroy');
    Route::patch('/players/{player}/toggle-active', [PlayerController::class, 'toggleActive'])->name('players.toggleActive');
    Route::post('/players/{player}/picture', [PlayerController::class, 'uploadPicture'])->name('players.uploadPicture');

    // ── CSV ───────────────────────────────────────────────────────────────
    Route::get('/csv/template', [CsvController::class, 'template'])->name('csv.template');
    Route::post('/csv/upload', [CsvController::class, 'upload'])->name('csv.upload');

    // ── Player Histories ──────────────────────────────────────────────────
    Route::get('/player-histories/template/download', [PlayerHistoryController::class, 'downloadTemplate'])->name('player-histories.template');
    Route::post('/players/{player}/histories/import', [PlayerHistoryController::class, 'import'])->name('player-histories.import');
    Route::get('/players/{player}/histories', [PlayerHistoryController::class, 'index'])->name('player-histories.index');
    Route::get('/players/{player}/histories/create', [PlayerHistoryController::class, 'create'])->name('player-histories.create');
    Route::post('/players/{player}/histories', [PlayerHistoryController::class, 'store'])->name('player-histories.store');
    Route::get('/player-histories/{history}/edit', [PlayerHistoryController::class, 'edit'])->name('player-histories.edit');
    Route::put('/player-histories/{history}', [PlayerHistoryController::class, 'update'])->name('player-histories.update');
    Route::delete('/player-histories/{history}', [PlayerHistoryController::class, 'destroy'])->name('player-histories.destroy');

    // ── Team Comparison (Phase 3) ─────────────────────────────────────────
    Route::get('/comparison', [ComparisonController::class, 'index'])->name('comparison.index');
    Route::post('/comparison', [ComparisonController::class, 'select'])->name('comparison.select');
    Route::get('/comparison/{teamA}/{teamB}', [ComparisonController::class, 'show'])->name('comparison.show');
});

require __DIR__.'/auth.php';
