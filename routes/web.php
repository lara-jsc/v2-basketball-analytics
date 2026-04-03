<?php

use App\Http\Controllers\CsvController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 |--------------------------------------------------------------------------
 | Web Routes
 |--------------------------------------------------------------------------
 | All application routes are auth-gated. No guest access to app features.
 | Phase stubs are marked — controllers will be implemented per phase.
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

    // ── CSV Template Download ──────────────────────────────────────────────
    // Phase 1: template download is live; upload route stubbed for Phase 1 impl.
    Route::get('/csv/template', [CsvController::class, 'template'])->name('csv.template');

    // ── Teams (Phase 2) ────────────────────────────────────────────────────
    // TODO (Phase 2): replace closure stubs with TeamController
    Route::get('/teams', function () {
        return Inertia::render('Teams/Index');
    })->name('teams.index');

    Route::get('/teams/create', function () {
        return Inertia::render('Teams/Create');
    })->name('teams.create');

    Route::get('/teams/{team}', function () {
        return Inertia::render('Teams/Show');
    })->name('teams.show');

    // ── Players (Phase 2) ──────────────────────────────────────────────────
    // TODO (Phase 2): replace closure stubs with PlayerController
    Route::get('/teams/{team}/players', function () {
        return Inertia::render('Players/Index');
    })->name('teams.players.index');

    // ── Team Comparison + Lineup (Phase 3) ────────────────────────────────
    // TODO (Phase 3): replace closure stub with ComparisonController
    Route::get('/comparison', function () {
        return Inertia::render('Comparison/Index');
    })->name('comparison.index');
});

require __DIR__.'/auth.php';
