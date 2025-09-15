<?php

use App\Http\Controllers\CapabilitiesController;
use App\Http\Controllers\CommandController;
use App\Http\Controllers\CommandOverlayController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SessionTestController;
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

    // Session Test Routes
    Route::post('/session-test/store', [SessionTestController::class, 'store'])->name('session.test.store');
    Route::get('/session-test/retrieve', [SessionTestController::class, 'retrieve'])->name('session.test.retrieve');
    Route::get('/session-test/company', [SessionTestController::class, 'companySession'])->name('session.test.company');

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

    // Invoicing Routes
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'store'])->name('store');
        Route::get('/{invoice}', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'show'])->name('show');
        Route::get('/{invoice}/edit', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'edit'])->name('edit');
        Route::put('/{invoice}', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'update'])->name('update');
        Route::delete('/{invoice}', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'destroy'])->name('destroy');

        // Invoice Actions
        Route::post('/{invoice}/send', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'send'])->name('send');
        Route::post('/{invoice}/post', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'post'])->name('post');
        Route::post('/{invoice}/cancel', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'cancel'])->name('cancel');
        Route::post('/{invoice}/generate-pdf', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'generatePdf'])->name('generate-pdf');
        Route::post('/{invoice}/send-email', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'sendEmail'])->name('send-email');
        Route::post('/{invoice}/duplicate', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'duplicate'])->name('duplicate');
    });

    // Payment Routes
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Invoicing\PaymentController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Invoicing\PaymentController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Invoicing\PaymentController::class, 'store'])->name('store');
        Route::get('/{payment}', [\App\Http\Controllers\Invoicing\PaymentController::class, 'show'])->name('show');
        Route::get('/{payment}/edit', [\App\Http\Controllers\Invoicing\PaymentController::class, 'edit'])->name('edit');
        Route::put('/{payment}', [\App\Http\Controllers\Invoicing\PaymentController::class, 'update'])->name('update');
        Route::delete('/{payment}', [\App\Http\Controllers\Invoicing\PaymentController::class, 'destroy'])->name('destroy');

        // Payment Actions
        Route::post('/{payment}/allocate', [\App\Http\Controllers\Invoicing\PaymentController::class, 'allocate'])->name('allocate');
        Route::post('/{payment}/auto-allocate', [\App\Http\Controllers\Invoicing\PaymentController::class, 'autoAllocate'])->name('auto-allocate');
        Route::post('/{payment}/void', [\App\Http\Controllers\Invoicing\PaymentController::class, 'void'])->name('void');
        Route::post('/{payment}/refund', [\App\Http\Controllers\Invoicing\PaymentController::class, 'refund'])->name('refund');
    });

    // Customer Routes
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Invoicing\CustomerController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Invoicing\CustomerController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Invoicing\CustomerController::class, 'store'])->name('store');
        Route::get('/{customer}', [\App\Http\Controllers\Invoicing\CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [\App\Http\Controllers\Invoicing\CustomerController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [\App\Http\Controllers\Invoicing\CustomerController::class, 'update'])->name('update');
        Route::delete('/{customer}', [\App\Http\Controllers\Invoicing\CustomerController::class, 'destroy'])->name('destroy');

        // Customer Relations
        Route::get('/{customer}/invoices', [\App\Http\Controllers\Invoicing\CustomerController::class, 'invoices'])->name('invoices');
        Route::get('/{customer}/payments', [\App\Http\Controllers\Invoicing\CustomerController::class, 'payments'])->name('payments');
        Route::get('/{customer}/statement', [\App\Http\Controllers\Invoicing\CustomerController::class, 'statement'])->name('statement');
        Route::get('/{customer}/statistics', [\App\Http\Controllers\Invoicing\CustomerController::class, 'statistics'])->name('statistics');
    });

    // Currency Routes
    Route::prefix('currencies')->name('currencies.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Invoicing\CurrencyController::class, 'index'])->name('index');
        Route::get('/exchange-rates', [\App\Http\Controllers\Invoicing\CurrencyController::class, 'exchangeRates'])->name('exchange-rates');
        Route::post('/enable', [\App\Http\Controllers\Invoicing\CurrencyController::class, 'enable'])->name('enable');
        Route::post('/disable', [\App\Http\Controllers\Invoicing\CurrencyController::class, 'disable'])->name('disable');
        Route::post('/update-rate', [\App\Http\Controllers\Invoicing\CurrencyController::class, 'updateRate'])->name('update-rate');
        Route::post('/sync-rates', [\App\Http\Controllers\Invoicing\CurrencyController::class, 'syncRates'])->name('sync-rates');
    });

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
