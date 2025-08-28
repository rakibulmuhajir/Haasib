<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth','throttle:devcli'])->group(function () {
    Route::get('/dev/cli', [\App\Http\Controllers\DevCliController::class, 'index'])->name('dev.cli');
    Route::post('/dev/cli/execute', [\App\Http\Controllers\DevCliController::class, 'execute'])->name('dev.cli.execute');
});

Route::middleware(['auth', 'verified'])->post('/commands', [\App\Http\Controllers\CommandController::class, 'execute']);

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth','require.superadmin'])->prefix('admin')->group(function () {
    Route::get('/', fn() => inertia('Admin/Dashboard'))->name('admin.dashboard');
    // add your sys tools here, e.g. user/company management views
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
