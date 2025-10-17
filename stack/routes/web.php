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

        // Period Close Routes
        Route::prefix('periods')->name('periods.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Ledger\PeriodClosePageController::class, 'index'])
                ->middleware('permission:period-close.view')->name('index');
            Route::get('/statistics', [\App\Http\Controllers\Ledger\PeriodClosePageController::class, 'statistics'])
                ->middleware('permission:period-close.view')->name('statistics');

            // Period-specific routes
            Route::prefix('/{periodId}')->whereUuid('periodId')->group(function () {
                Route::get('/', [\App\Http\Controllers\Ledger\PeriodClosePageController::class, 'show'])
                    ->middleware('permission:period-close.view')->name('show');
                Route::get('/start', [\App\Http\Controllers\Ledger\PeriodClosePageController::class, 'start'])
                    ->middleware('permission:period-close.start')->name('start');
            });
        });

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

            // Journal Batches Routes
            Route::prefix('batches')->name('batches.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Ledger\JournalBatchController::class, 'index'])
                    ->middleware('permission:ledger.view')->name('index');

                Route::get('/create', [\App\Http\Controllers\Ledger\JournalBatchController::class, 'create'])
                    ->middleware('permission:ledger.entries.create')->name('create');

                Route::get('/{batch}', [\App\Http\Controllers\Ledger\JournalBatchController::class, 'show'])
                    ->middleware('permission:ledger.view')->name('show');

                Route::get('/{batch}/edit', [\App\Http\Controllers\Ledger\JournalBatchController::class, 'edit'])
                    ->middleware('permission:ledger.entries.update')->name('edit');

                Route::post('/{batch}/approve', [\App\Http\Controllers\Ledger\JournalBatchController::class, 'approve'])
                    ->middleware('permission:ledger.entries.approve')->name('approve');

                Route::post('/{batch}/post', [\App\Http\Controllers\Ledger\JournalBatchController::class, 'post'])
                    ->middleware('permission:ledger.entries.post')->name('post');

                Route::delete('/{batch}', [\App\Http\Controllers\Ledger\JournalBatchController::class, 'destroy'])
                    ->middleware('permission:ledger.entries.delete')->name('destroy');
            });
        });

        // Bank Reconciliation Routes
        Route::prefix('bank-reconciliation')->name('bank-reconciliation.')->group(function () {
            // Statement Import Routes
            Route::get('/import', [\App\Http\Controllers\Ledger\BankStatementImportController::class, 'index'])
                ->middleware('permission:bank_statements.view')->name('import');
            Route::post('/statements/import', [\App\Http\Controllers\Ledger\BankStatementImportController::class, 'store'])
                ->middleware('permission:bank_statements.import')->name('statements.import');

            // Statement Management Routes
            Route::prefix('statements')->name('statements.')->group(function () {
                Route::get('/{statement}', [\App\Http\Controllers\Ledger\BankStatementImportController::class, 'show'])
                    ->whereUuid('statement')->middleware('permission:bank_statements.view')->name('show');
                Route::get('/{statement}/status', [\App\Http\Controllers\Ledger\BankStatementImportController::class, 'status'])
                    ->whereUuid('statement')->middleware('permission:bank_statements.view')->name('status');
                Route::get('/{statement}/download', [\App\Http\Controllers\Ledger\BankStatementImportController::class, 'download'])
                    ->whereUuid('statement')->middleware('permission:bank_statements.view')->name('download');
                Route::delete('/{statement}', [\App\Http\Controllers\Ledger\BankStatementImportController::class, 'destroy'])
                    ->whereUuid('statement')->middleware('permission:bank_statements.delete')->name('destroy');
            });

            // Reconciliation Management Routes
            Route::prefix('reconciliations')->name('reconciliations.')->group(function () {
                Route::get('/{reconciliation}', [\App\Http\Controllers\Ledger\BankReconciliationController::class, 'show'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliations.view')->name('show');
                Route::post('/{reconciliation}/auto-match', [\App\Http\Controllers\Ledger\BankReconciliationController::class, 'autoMatch'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_matches.auto_match')->name('auto-match');
                Route::post('/{reconciliation}/matches', [\App\Http\Controllers\Ledger\BankReconciliationController::class, 'createMatch'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_matches.create')->name('matches.store');
                Route::delete('/{reconciliation}/matches/{match}', [\App\Http\Controllers\Ledger\BankReconciliationController::class, 'deleteMatch'])
                    ->whereUuid('reconciliation')->whereUuid('match')->middleware('permission:bank_reconciliation_matches.delete')->name('matches.destroy');

                // Lifecycle Management Routes
                Route::post('/{reconciliation}/complete', [\App\Http\Controllers\Ledger\BankReconciliationStatusController::class, 'complete'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliations.complete')->name('complete');
                Route::post('/{reconciliation}/lock', [\App\Http\Controllers\Ledger\BankReconciliationStatusController::class, 'lock'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliations.lock')->name('lock');
                Route::post('/{reconciliation}/reopen', [\App\Http\Controllers\Ledger\BankReconciliationStatusController::class, 'reopen'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliations.reopen')->name('reopen');
                Route::get('/{reconciliation}/status', [\App\Http\Controllers\Ledger\BankReconciliationStatusController::class, 'status'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliations.view')->name('status');
                Route::get('/{reconciliation}/history', [\App\Http\Controllers\Ledger\BankReconciliationStatusController::class, 'history'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliations.view')->name('history');

                // Adjustment Routes
                Route::get('/{reconciliation}/adjustments', [\App\Http\Controllers\Ledger\BankReconciliationAdjustmentController::class, 'index'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_adjustments.view')->name('adjustments.index');
                Route::post('/{reconciliation}/adjustments', [\App\Http\Controllers\Ledger\BankReconciliationAdjustmentController::class, 'store'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_adjustments.create')->name('adjustments.store');
                Route::get('/{reconciliation}/adjustments/{adjustment}', [\App\Http\Controllers\Ledger\BankReconciliationAdjustmentController::class, 'show'])
                    ->whereUuid('reconciliation')->whereUuid('adjustment')->middleware('permission:bank_reconciliation_adjustments.view')->name('adjustments.show');
                Route::put('/{reconciliation}/adjustments/{adjustment}', [\App\Http\Controllers\Ledger\BankReconciliationAdjustmentController::class, 'update'])
                    ->whereUuid('reconciliation')->whereUuid('adjustment')->middleware('permission:bank_reconciliation_adjustments.update')->name('adjustments.update');
                Route::delete('/{reconciliation}/adjustments/{adjustment}', [\App\Http\Controllers\Ledger\BankReconciliationAdjustmentController::class, 'destroy'])
                    ->whereUuid('reconciliation')->whereUuid('adjustment')->middleware('permission:bank_reconciliation_adjustments.delete')->name('adjustments.destroy');
                Route::get('/{reconciliation}/adjustments/types', [\App\Http\Controllers\Ledger\BankReconciliationAdjustmentController::class, 'getAdjustmentTypes'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_adjustments.view')->name('adjustments.types');
                Route::post('/{reconciliation}/adjustments/preview', [\App\Http\Controllers\Ledger\BankReconciliationAdjustmentController::class, 'getAdjustmentPreview'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_adjustments.view')->name('adjustments.preview');

                // Report Routes
                Route::get('/{reconciliation}/reports', [\App\Http\Controllers\Ledger\BankReconciliationReportController::class, 'index'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_reports.view')->name('reports.index');
                Route::get('/{reconciliation}/reports/{reportType}', [\App\Http\Controllers\Ledger\BankReconciliationReportController::class, 'show'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_reports.view')->name('reports.show');
                Route::post('/{reconciliation}/reports/{reportType}/export', [\App\Http\Controllers\Ledger\BankReconciliationReportController::class, 'export'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_reports.export')->name('reports.export');
                Route::get('/{reconciliation}/reports/download/{filename}', [\App\Http\Controllers\Ledger\BankReconciliationReportController::class, 'download'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_reports.export')->name('reports.download');
                Route::get('/{reconciliation}/reports/variance', [\App\Http\Controllers\Ledger\BankReconciliationReportController::class, 'variance'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_reports.view')->name('reports.variance');
                Route::get('/{reconciliation}/reports/audit', [\App\Http\Controllers\Ledger\BankReconciliationReportController::class, 'audit'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_reports.view')->name('reports.audit');
                Route::get('/{reconciliation}/reports/metrics', [\App\Http\Controllers\Ledger\BankReconciliationReportController::class, 'metrics'])
                    ->whereUuid('reconciliation')->middleware('permission:bank_reconciliation_reports.view')->name('reports.metrics');
            });
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

    // Company Routes (Primary Navigation)
    Route::prefix('companies')->name('companies.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CompanyController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\CompanyController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\CompanyController::class, 'store'])->name('store')->middleware('idempotent');
        Route::post('/bulk', [\App\Http\Controllers\CompanyController::class, 'bulk'])->name('bulk');
        Route::get('/{company}', [\App\Http\Controllers\CompanyController::class, 'show'])->whereUuid('company')->name('show');

        // Company Management
        Route::get('/{company}/edit', [\App\Http\Controllers\CompanyController::class, 'edit'])->whereUuid('company')->name('edit');
        Route::patch('/{company}', [\App\Http\Controllers\CompanyController::class, 'update'])->whereUuid('company')->name('update')->middleware('idempotent');
        Route::delete('/{company}', [\App\Http\Controllers\CompanyController::class, 'destroy'])->whereUuid('company')->name('destroy')->middleware('idempotent');

        // Company Users
        Route::get('/{company}/users', [\App\Http\Controllers\CompanyController::class, 'users'])->whereUuid('company')->name('users');
        Route::post('/{company}/users/{user}/role', [\App\Http\Controllers\CompanyController::class, 'updateUserRole'])->whereUuid('company')->whereUuid('user')->name('users.update.role');
        Route::delete('/{company}/users/{user}', [\App\Http\Controllers\CompanyController::class, 'removeUser'])->whereUuid('company')->whereUuid('user')->name('users.remove');
        Route::post('/{company}/users/{user}/status', [\App\Http\Controllers\CompanyController::class, 'toggleUserStatus'])->whereUuid('company')->whereUuid('user')->name('users.toggle.status');

        // Company Invitations
        Route::get('/{company}/invitations', [\App\Http\Controllers\CompanyController::class, 'invitations'])->whereUuid('company')->name('invitations');
        Route::post('/{company}/invitations', [\App\Http\Controllers\CompanyController::class, 'sendInvitation'])->whereUuid('company')->name('invitations.send')->middleware('idempotent');
        Route::get('/{company}/invitations/{invitation}', [\App\Http\Controllers\CompanyController::class, 'showInvitation'])->whereUuid('company')->whereUuid('invitation')->name('invitations.show');
        Route::post('/{company}/invitations/{invitation}/resend', [\App\Http\Controllers\CompanyController::class, 'resendInvitation'])->whereUuid('company')->whereUuid('invitation')->name('invitations.resend');
        Route::post('/{company}/invitations/{invitation}/revoke', [\App\Http\Controllers\CompanyController::class, 'revokeInvitation'])->whereUuid('company')->whereUuid('invitation')->name('invitations.revoke');

        // Company Settings
        Route::get('/{company}/settings', [\App\Http\Controllers\CompanyController::class, 'settings'])->whereUuid('company')->name('settings');
        Route::get('/{company}/modules', [\App\Http\Controllers\CompanyController::class, 'modules'])->whereUuid('company')->name('modules');
        Route::get('/{company}/audit', [\App\Http\Controllers\CompanyController::class, 'audit'])->whereUuid('company')->name('audit');
    });

    // Admin Routes (Super Admin Only)
    Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission:admin.access'])->group(function () {
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
