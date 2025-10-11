<?php

use App\Http\Controllers\CapabilitiesController;
use App\Http\Controllers\CommandController;
use App\Http\Controllers\CommandOverlayController;
use App\Http\Controllers\CompanyRoleController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SessionTestController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Check if system is initialized
    $setupService = app(\App\Services\SetupService::class);
    if (! $setupService->isInitialized()) {
        return redirect()->route('setup.page');
    }
    
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});


// Setup routes - no authentication required
Route::prefix('setup')->name('setup.')->group(function () {
    Route::get('/page', function () {
        return Inertia::render('Setup/UserSelection');
    })->name('page');
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

    // Settings Routes
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', function () {
            return Inertia::render('Settings/Index');
        })->name('index');
    });

    // Company Switch Routes
    Route::post('/company/{company}/switch', [CompanySwitchController::class, 'switch'])->name('company.switch');
    Route::post('/company/set-first', [CompanySwitchController::class, 'setFirstCompany'])->name('company.set-first');
    Route::post('/company/clear-context', [CompanySwitchController::class, 'clearContext'])->name('company.clear-context');

    // Company Role Management Routes
    Route::prefix('companies/{company}/roles')->name('companies.roles.')->group(function () {
        Route::get('/', [CompanyRoleController::class, 'index'])
            ->middleware('permission:users.roles.assign')
            ->name('index');
        Route::put('/users/{user}', [CompanyRoleController::class, 'updateRole'])
            ->middleware('permission:users.roles.assign')
            ->name('update');
        Route::delete('/users/{user}', [CompanyRoleController::class, 'removeUser'])
            ->middleware('permission:users.deactivate')
            ->name('remove');
    });

    // Session Test Routes
    Route::post('/session-test/store', [SessionTestController::class, 'store'])->name('session.test.store');
    Route::get('/session-test/retrieve', [SessionTestController::class, 'retrieve'])->name('session.test.retrieve');
    Route::get('/session-test/company', [SessionTestController::class, 'companySession'])->name('session.test.company');

    // API Routes for SPA lookups

    // User Settings API Routes
    Route::prefix('api/settings')->name('api.settings.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\UserSettingsController::class, 'index'])->name('index');
        Route::get('/{group}', [App\Http\Controllers\Api\UserSettingsController::class, 'show'])->name('show');
        Route::patch('/', [App\Http\Controllers\Api\UserSettingsController::class, 'update'])->name('update');
        Route::patch('/{group}/{key}', [App\Http\Controllers\Api\UserSettingsController::class, 'updateSetting'])->name('update.setting');
    });

    // Company Currency API Routes
    Route::prefix('api/companies/{company}/currencies')->name('companies.currencies.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'index'])->name('index');
        Route::get('/available', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'available'])->name('available');
        Route::post('/', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'store'])
            ->middleware('permission:companies.currencies.enable')->name('store');
        Route::delete('{currency}', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'destroy'])
            ->middleware('permission:companies.currencies.disable')->name('destroy');

        // Exchange Rate Routes
        Route::get('/exchange-rates', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'exchangeRates'])->name('exchange-rates');
        Route::post('/exchange-rates', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'storeExchangeRate'])
            ->middleware('permission:companies.currencies.exchange-rates.update')->name('exchange-rates.store');
        Route::get('/exchange-rates/{rateId}', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'getExchangeRate'])->name('exchange-rates.show');
        Route::patch('/exchange-rates/{rateId}', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'updateExchangeRate'])
            ->middleware('permission:companies.currencies.exchange-rates.update')->name('exchange-rates.update');
        Route::post('/exchange-rates/sync', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'syncExchangeRates'])
            ->middleware('permission:companies.currencies.exchange-rates.update')->name('exchange-rates.sync');
        Route::patch('/base-currency', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'updateBaseCurrency'])
            ->middleware('permission:companies.currencies.set-base')->name('base-currency.update');

        // Legacy route for backward compatibility
        Route::get('{currency}/exchange-rate', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'getExchangeRate'])->name('exchange-rate.get');
        Route::patch('{currency}/exchange-rate', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'updateExchangeRate'])->name('exchange-rate');
        Route::match(['get', 'patch'], '{currency}/exchange-rates/{rateId}', [App\Http\Controllers\Api\CompanyCurrencyController::class, 'updateSpecificRate'])->name('exchange-rate.update');
    });
    Route::get('/web/users/suggest', [\App\Http\Controllers\UserLookupController::class, 'suggest']);
    Route::get('/web/users/{user}', [\App\Http\Controllers\UserLookupController::class, 'show']);
    Route::get('/web/companies', [\App\Http\Controllers\CompanyLookupController::class, 'index']);
    Route::get('/web/companies/{company}/users', [\App\Http\Controllers\CompanyLookupController::class, 'users']);
    Route::get('/web/companies/{company}', [\App\Http\Controllers\CompanyLookupController::class, 'show']);
    Route::get('/web/customers/suggest', [\App\Http\Controllers\CustomerLookupController::class, 'suggest']);
    Route::get('/web/customers/{customer}', [\App\Http\Controllers\CustomerLookupController::class, 'show']);
    Route::get('/web/invoices/suggest', [\App\Http\Controllers\InvoiceLookupController::class, 'suggest']);
    Route::get('/web/invoices/{invoice}', [\App\Http\Controllers\InvoiceLookupController::class, 'show']);
    Route::patch('/web/companies/{company}/activate', [\App\Http\Controllers\CompanyController::class, 'activate']);
    Route::patch('/web/companies/{company}/deactivate', [\App\Http\Controllers\CompanyController::class, 'deactivate']);
    Route::delete('/web/companies/{company}', [\App\Http\Controllers\CompanyController::class, 'destroy']);

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
        Route::get('/', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'index'])
            ->middleware('permission:invoices.view')->name('index');
        Route::get('/export', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'export'])
            ->middleware('permission:invoices.export')->name('export');
        Route::get('/create', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'create'])
            ->middleware('permission:invoices.create')->name('create');
        Route::post('/', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'store'])
            ->middleware('permission:invoices.create')->middleware('idempotent')->name('store');
        Route::get('/{invoice}', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'show'])
            ->whereUuid('invoice')->middleware('permission:invoices.view')->name('show');
        Route::get('/{invoice}/edit', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'edit'])
            ->whereUuid('invoice')->middleware('permission:invoices.update')->name('edit');
        Route::put('/{invoice}', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'update'])
            ->whereUuid('invoice')->middleware('permission:invoices.update')->name('update');
        Route::delete('/{invoice}', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'destroy'])
            ->whereUuid('invoice')->middleware('permission:invoices.delete')->name('destroy');

        // Invoice Actions
        Route::post('/{invoice}/send', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'send'])
            ->whereUuid('invoice')->middleware('permission:invoices.send')->name('send');
        Route::post('/{invoice}/post', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'post'])
            ->whereUuid('invoice')->middleware('permission:invoices.post')->name('post');
        Route::post('/{invoice}/cancel', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'cancel'])
            ->whereUuid('invoice')->middleware('permission:invoices.delete')->name('cancel');
        Route::post('/{invoice}/update-status', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'updateStatus'])
            ->whereUuid('invoice')->middleware('permission:invoices.update')->name('update-status');
        Route::post('/{invoice}/generate-pdf', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'generatePdf'])
            ->whereUuid('invoice')->middleware('permission:invoices.view')->name('generate-pdf');
        Route::post('/{invoice}/send-email', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'sendEmail'])
            ->whereUuid('invoice')->middleware('permission:invoices.send')->name('send-email');
        Route::post('/{invoice}/duplicate', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'duplicate'])
            ->whereUuid('invoice')->middleware('permission:invoices.create')->name('duplicate');

        // Bulk operations
        Route::post('/bulk', [\App\Http\Controllers\Invoicing\InvoiceController::class, 'bulk'])->name('bulk');
    });

    // Payment Routes
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Invoicing\PaymentController::class, 'index'])
            ->middleware('permission:payments.view')->name('index');
        Route::get('/create', [\App\Http\Controllers\Invoicing\PaymentController::class, 'create'])
            ->middleware('permission:payments.create')->name('create');
        Route::post('/', [\App\Http\Controllers\Invoicing\PaymentController::class, 'store'])
            ->middleware('permission:payments.create')->name('store');
        Route::get('/{payment}', [\App\Http\Controllers\Invoicing\PaymentController::class, 'show'])
            ->whereUuid('payment')->middleware('permission:payments.view')->name('show');
        Route::get('/{payment}/edit', [\App\Http\Controllers\Invoicing\PaymentController::class, 'edit'])
            ->whereUuid('payment')->middleware('permission:payments.update')->name('edit');
        Route::put('/{payment}', [\App\Http\Controllers\Invoicing\PaymentController::class, 'update'])
            ->whereUuid('payment')->middleware('permission:payments.update')->name('update');
        Route::delete('/{payment}', [\App\Http\Controllers\Invoicing\PaymentController::class, 'destroy'])
            ->whereUuid('payment')->middleware('permission:payments.delete')->name('destroy');

        // Payment Actions
        Route::post('/{payment}/allocate', [\App\Http\Controllers\Invoicing\PaymentController::class, 'allocate'])
            ->whereUuid('payment')->middleware('permission:payments.allocate')->name('allocate');
        Route::post('/{payment}/auto-allocate', [\App\Http\Controllers\Invoicing\PaymentController::class, 'autoAllocate'])
            ->whereUuid('payment')->middleware('permission:payments.allocate')->name('auto-allocate');
        Route::post('/{payment}/void', [\App\Http\Controllers\Invoicing\PaymentController::class, 'void'])
            ->whereUuid('payment')->middleware('permission:payments.delete')->name('void');
        Route::post('/{payment}/refund', [\App\Http\Controllers\Invoicing\PaymentController::class, 'refund'])
            ->whereUuid('payment')->middleware('permission:payments.refund')->name('refund');
    });

    // Customer Routes
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Invoicing\CustomerController::class, 'index'])
            ->middleware('permission:customers.view')->name('index');
        Route::get('/export', [\App\Http\Controllers\Invoicing\CustomerController::class, 'export'])
            ->middleware('permission:customers.export')->name('export');
        Route::get('/create', [\App\Http\Controllers\Invoicing\CustomerController::class, 'create'])
            ->middleware('permission:customers.create')->name('create');
        Route::post('/', [\App\Http\Controllers\Invoicing\CustomerController::class, 'store'])
            ->middleware('permission:customers.create')->name('store');
        Route::get('/{customer}', [\App\Http\Controllers\Invoicing\CustomerController::class, 'show'])
            ->whereUuid('customer')->middleware('permission:customers.view')->name('show');
        Route::get('/{customer}/edit', [\App\Http\Controllers\Invoicing\CustomerController::class, 'edit'])
            ->whereUuid('customer')->middleware('permission:customers.update')->name('edit');
        Route::put('/{customer}', [\App\Http\Controllers\Invoicing\CustomerController::class, 'update'])
            ->whereUuid('customer')->middleware('permission:customers.update')->name('update');
        Route::delete('/{customer}', [\App\Http\Controllers\Invoicing\CustomerController::class, 'destroy'])
            ->whereUuid('customer')->middleware('permission:customers.delete')->name('destroy');

        // Customer Relations
        Route::get('/{customer}/invoices', [\App\Http\Controllers\Invoicing\CustomerController::class, 'invoices'])->whereUuid('customer')->name('invoices');
        Route::get('/{customer}/payments', [\App\Http\Controllers\Invoicing\CustomerController::class, 'payments'])->whereUuid('customer')->name('payments');
        Route::get('/{customer}/statement', [\App\Http\Controllers\Invoicing\CustomerController::class, 'statement'])->whereUuid('customer')->name('statement');
        Route::get('/{customer}/statistics', [\App\Http\Controllers\Invoicing\CustomerController::class, 'statistics'])->whereUuid('customer')->name('statistics');

        // Bulk operations
        Route::post('/bulk', [\App\Http\Controllers\Invoicing\CustomerController::class, 'bulk'])->name('bulk');
    });

    // Legacy Currency Routes - Redirect to Settings
    Route::prefix('currencies')->name('currencies.')->group(function () {
        Route::get('/', function () {
            return redirect()->route('settings.index', ['group' => 'currency']);
        })->name('index');

        Route::get('/exchange-rates', function () {
            return redirect()->route('settings.index', ['group' => 'currency']);
        })->name('exchange-rates');

        // All other currency routes redirect to settings with currency tab
        Route::any('{any}', function () {
            return redirect()->route('settings.index', ['group' => 'currency']);
        })->where('any', '.*');
    });

    // Ledger Routes
    Route::prefix('ledger')->name('ledger.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Ledger\LedgerController::class, 'index'])
            ->middleware('permission:ledger.view')->name('index');
        Route::get('/create', [\App\Http\Controllers\Ledger\LedgerController::class, 'create'])
            ->middleware('permission:ledger.entries.create')->name('create');
        Route::post('/', [\App\Http\Controllers\Ledger\LedgerController::class, 'store'])
            ->middleware('permission:ledger.entries.create')->name('store');

        // Ledger Accounts Routes - must come before dynamic parameter routes
        Route::prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Ledger\LedgerAccountController::class, 'index'])
                ->middleware('permission:ledger.view')->name('index');
            Route::get('/{id}', [\App\Http\Controllers\Ledger\LedgerAccountController::class, 'show'])
                ->whereUuid('id')->middleware('permission:ledger.view')->name('show');
        });

        // Ledger Reports Routes
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/trial-balance', [\App\Http\Controllers\Ledger\LedgerReportController::class, 'trialBalance'])
                ->middleware('permission:ledger.reports.view')->name('trial-balance');
            Route::get('/balance-sheet', [\App\Http\Controllers\Ledger\LedgerReportController::class, 'balanceSheet'])
                ->middleware('permission:ledger.reports.view')->name('balance-sheet');
            Route::get('/income-statement', [\App\Http\Controllers\Ledger\LedgerReportController::class, 'incomeStatement'])
                ->middleware('permission:ledger.reports.view')->name('income-statement');
        });

        // Journal Routes
        Route::prefix('journal')->name('journal.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Ledger\JournalController::class, 'index'])
                ->middleware('permission:ledger.view')->name('index');
            Route::get('/create', [\App\Http\Controllers\Ledger\JournalController::class, 'create'])
                ->middleware('permission:ledger.entries.create')->name('create');
        });

        // Dynamic parameter routes - must come after specific routes
        Route::get('/{id}', [\App\Http\Controllers\Ledger\LedgerController::class, 'show'])
            ->whereUuid('id')->middleware('permission:ledger.view')->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Ledger\LedgerController::class, 'edit'])
            ->whereUuid('id')->middleware('permission:ledger.entries.update')->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Ledger\LedgerController::class, 'update'])
            ->whereUuid('id')->middleware('permission:ledger.entries.update')->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Ledger\LedgerController::class, 'destroy'])
            ->whereUuid('id')->middleware('permission:ledger.entries.delete')->name('destroy');
        Route::post('/{id}/post', [\App\Http\Controllers\Ledger\LedgerController::class, 'post'])
            ->whereUuid('id')->middleware('permission:ledger.entries.post')->name('post');
        Route::post('/{id}/void', [\App\Http\Controllers\Ledger\LedgerController::class, 'void'])
            ->whereUuid('id')->middleware('permission:ledger.entries.void')->name('void');
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
