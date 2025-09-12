<?php

use App\Http\Controllers\CapabilitiesController;
use App\Http\Controllers\CommandController;
use App\Http\Controllers\CommandOverlayController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

// Command execution endpoint
Route::post('/commands', [CommandController::class, 'execute'])->middleware(['auth', 'verified']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Company Switch Routes
    Route::post('/company/{company}/switch', [CompanySwitchController::class, 'switch'])->name('company.switch');
    Route::post('/company/set-first', [CompanySwitchController::class, 'setFirstCompany'])->name('company.set-first');

    // API Routes for SPA lookups
    Route::get('/web/users/suggest', [\App\Http\Controllers\UserLookupController::class, 'suggest']);
    Route::get('/web/users/{user}', [\App\Http\Controllers\UserLookupController::class, 'show']);
    Route::get('/web/companies', [\App\Http\Controllers\CompanyLookupController::class, 'index']);
    Route::get('/web/companies/{company}/users', [\App\Http\Controllers\CompanyLookupController::class, 'users']);
    Route::get('/web/companies/{company}', [\App\Http\Controllers\CompanyLookupController::class, 'show']);

    // Reference data lookups for pickers
    Route::get('/web/countries/suggest', [\App\Http\Controllers\CountryLookupController::class, 'suggest']);
    Route::get('/web/languages/suggest', [\App\Http\Controllers\LanguageLookupController::class, 'suggest']);
    Route::get('/web/currencies/suggest', [\App\Http\Controllers\CurrencyLookupController::class, 'suggest']);
    Route::get('/web/locales/suggest', [\App\Http\Controllers\LocaleLookupController::class, 'suggest']);

    // Command capabilities and overlays
    Route::get('/web/commands/capabilities', [CapabilitiesController::class, 'index']);
    Route::get('/web/commands/overlays', [CommandOverlayController::class, 'index']);

    // Ledger Routes
    Route::prefix('ledger')->name('ledger.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Ledger\LedgerController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Ledger\LedgerController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Ledger\LedgerController::class, 'store'])->name('store');

        // Ledger Accounts Routes - must come before dynamic parameter routes
        Route::prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Ledger\LedgerAccountController::class, 'index'])->name('index');
            Route::get('/{id}', [\App\Http\Controllers\Ledger\LedgerAccountController::class, 'show'])->name('show');
        });

        // Dynamic parameter routes - must come after specific routes
        Route::get('/{id}', [\App\Http\Controllers\Ledger\LedgerController::class, 'show'])->name('show');
        Route::post('/{id}/post', [\App\Http\Controllers\Ledger\LedgerController::class, 'post'])->name('post');
        Route::post('/{id}/void', [\App\Http\Controllers\Ledger\LedgerController::class, 'void'])->name('void');
    });

    // Admin Routes
    Route::prefix('admin')->name('admin.')->group(function () {
        // Companies Routes
        Route::prefix('companies')->name('companies.')->group(function () {
            Route::get('/', function () {
                return Inertia::render('Admin/Companies/Index');
            })->name('index');

            Route::get('/create', function () {
                return Inertia::render('Admin/Companies/Create');
            })->name('create');

            Route::get('/{company}', function ($company) {
                return Inertia::render('Admin/Companies/Show', ['company' => $company]);
            })->name('show');
        });

        // Users Routes
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', function () {
                return Inertia::render('Admin/Users/Index');
            })->name('index');

            Route::get('/create', function () {
                return Inertia::render('Admin/Users/Create');
            })->name('create');

            Route::get('/{id}', function ($id) {
                return Inertia::render('Admin/Users/Show', ['id' => $id]);
            })->name('show');
        });
    });
});

require __DIR__.'/auth.php';
