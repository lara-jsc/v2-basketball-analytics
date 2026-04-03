<?php

use App\Http\Controllers\CsvController;
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

    // ── Teams (Phase 1 — live) ─────────────────────────────────────────────
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');

    // ── CSV (Phase 1 — live) ───────────────────────────────────────────────
    Route::get('/csv/template', [CsvController::class, 'template'])->name('csv.template');
    Route::post('/csv/upload', [CsvController::class, 'upload'])->name('csv.upload');

    // ── Team Comparison (Phase 3 — stub) ──────────────────────────────────
    Route::get('/comparison', function () {
        return Inertia::render('Comparison/Index');
    })->name('comparison.index');
});

require __DIR__.'/auth.php';
