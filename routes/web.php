<?php

use App\Http\Controllers\CsvController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 |--------------------------------------------------------------------------
 | Web Routes
 |--------------------------------------------------------------------------
 | All application routes are auth-gated. No guest access to app features.
 */

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // ── Dashboard ──────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // ── Profile (Laravel default) ──────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Teams (Phase 1 + Phase 2) ──────────────────────────────────────────
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::patch('/teams/{team}/toggle-active', [TeamController::class, 'toggleActive'])->name('teams.toggleActive');
    Route::post('/teams/{team}/logo', [TeamController::class, 'uploadLogo'])->name('teams.uploadLogo');

    // ── Players (Phase 2) ──────────────────────────────────────────────────
    Route::post('/teams/{team}/players', [PlayerController::class, 'store'])->name('players.store');
    Route::put('/players/{player}', [PlayerController::class, 'update'])->name('players.update');
    Route::delete('/players/{player}', [PlayerController::class, 'destroy'])->name('players.destroy');
    Route::patch('/players/{player}/toggle-active', [PlayerController::class, 'toggleActive'])->name('players.toggleActive');
    Route::post('/players/{player}/picture', [PlayerController::class, 'uploadPicture'])->name('players.uploadPicture');

    // ── CSV (Phase 1 — live) ───────────────────────────────────────────────
    Route::get('/csv/template', [CsvController::class, 'template'])->name('csv.template');
    Route::post('/csv/upload', [CsvController::class, 'upload'])->name('csv.upload');

    // ── Team Comparison (Phase 3 — stub) ──────────────────────────────────
    Route::get('/comparison', function () {
        return Inertia::render('Comparison/Index');
    })->name('comparison.index');
});

require __DIR__.'/auth.php';
