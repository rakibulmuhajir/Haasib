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

// SPA lookups via web guard (avoids Sanctum issues in local/dev)
Route::middleware(['auth'])->group(function () {
    // Me / companies via web guard (avoids Sanctum 401s in SPA)
    Route::get('/web/me/companies', [\App\Http\Controllers\MeController::class, 'companies']);
    Route::get('/web/users/suggest', [\App\Http\Controllers\UserLookupController::class, 'suggest']);
    Route::get('/web/users/{user}', [\App\Http\Controllers\UserLookupController::class, 'show']);
    Route::get('/web/companies', [\App\Http\Controllers\CompanyLookupController::class, 'index']);
    Route::get('/web/companies/{company}/users', [\App\Http\Controllers\CompanyLookupController::class, 'users']);
    Route::get('/web/companies/{company}', [\App\Http\Controllers\CompanyLookupController::class, 'show']);
    // Invitations via web guard
    Route::get('/web/companies/{company}/invitations', [\App\Http\Controllers\InvitationController::class, 'companyInvitations']);
    Route::post('/web/companies/{company}/invite', [\App\Http\Controllers\CompanyController::class, 'invite']);
    Route::post('/web/invitations/{id}/revoke', [\App\Http\Controllers\InvitationController::class, 'revoke']);
    Route::post('/web/companies/switch', [\App\Http\Controllers\MeController::class, 'switch']);

    // Reference data lookups for pickers
    Route::get('/web/countries/suggest', [\App\Http\Controllers\CountryLookupController::class, 'suggest']);
    Route::get('/web/languages/suggest', [\App\Http\Controllers\LanguageLookupController::class, 'suggest']);
    Route::get('/web/currencies/suggest', [\App\Http\Controllers\CurrencyLookupController::class, 'suggest']);
    Route::get('/web/locales/suggest', [\App\Http\Controllers\LocaleLookupController::class, 'suggest']);
    // Capabilities for CLI palette (server-authoritative)
    Route::get('/web/commands/capabilities', [\App\Http\Controllers\CapabilitiesController::class, 'index']);
    // Command overlays for the palette (UI control only)
    Route::get('/web/commands/overlays', [\App\Http\Controllers\CommandOverlayController::class, 'index']);
});

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
    // Companies
    Route::get('/companies', fn() => inertia('Admin/Companies/Index'))
        ->name('admin.companies.index');
    Route::get('/companies/create', fn() => inertia('Admin/Companies/Create'))
        ->name('admin.companies.create');
    Route::get('/companies/{company}', fn(string $company) => inertia('Admin/Companies/Show', ['company' => $company]))
        ->name('admin.companies.show');

    // Users
    Route::get('/users', fn() => inertia('Admin/Users/Index'))
        ->name('admin.users.index');
    Route::get('/users/create', fn() => inertia('Admin/Users/Create'))
        ->name('admin.users.create');
    Route::get('/users/{id}', fn(string $id) => inertia('Admin/Users/Show', ['id' => $id]))
        ->name('admin.users.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
