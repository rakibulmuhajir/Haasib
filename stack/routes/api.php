<?php

use App\Http\Controllers\Api\CommandController;
use App\Http\Controllers\Api\CurrencyApiController;
use App\Http\Controllers\Api\InvoiceApiController;
use App\Http\Controllers\Api\InvoicingRequirementsController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\InlineEditController;
use Illuminate\Support\Facades\Route;

// API Routes for Invoicing System
// All routes require authentication and company context

// Invoice Routes
Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('/', [InvoiceApiController::class, 'index'])->name('index');
    Route::post('/', [InvoiceApiController::class, 'store'])->name('store')->middleware('idempotent');
    Route::get('/{id}', [InvoiceApiController::class, 'show'])->whereUuid('id')->name('show');
    Route::put('/{id}', [InvoiceApiController::class, 'update'])->whereUuid('id')->name('update')->middleware('idempotent');
    Route::delete('/{id}', [InvoiceApiController::class, 'destroy'])->whereUuid('id')->name('destroy')->middleware('idempotent');

    // Invoice Actions
    Route::post('/{id}/send', [InvoiceApiController::class, 'markAsSent'])->whereUuid('id')->name('send')->middleware('idempotent');
    Route::post('/{id}/post', [InvoiceApiController::class, 'markAsPosted'])->whereUuid('id')->name('post')->middleware('idempotent');
    Route::post('/{id}/cancel', [InvoiceApiController::class, 'cancel'])->whereUuid('id')->name('cancel')->middleware('idempotent');
    Route::post('/{id}/generate-pdf', [InvoiceApiController::class, 'generatePdf'])->whereUuid('id')->name('generate-pdf')->middleware('idempotent');
    Route::get('/{id}/pdf-exists', [InvoiceApiController::class, 'pdfExists'])->whereUuid('id')->name('pdf-exists');
    Route::post('/{id}/send-email', [InvoiceApiController::class, 'sendEmail'])->whereUuid('id')->name('send-email')->middleware('idempotent');
    Route::post('/{id}/duplicate', [InvoiceApiController::class, 'duplicate'])->whereUuid('id')->name('duplicate')->middleware('idempotent');

    // Invoice Statistics
    Route::get('/statistics', [InvoiceApiController::class, 'statistics'])->name('statistics');

    // Bulk Operations
    Route::post('/bulk', [InvoiceApiController::class, 'bulk'])->name('bulk')->middleware('idempotent');
});

// Payment Routes
Route::prefix('payments')->name('payments.')->group(function () {
    Route::get('/', [PaymentApiController::class, 'index'])->name('index');
    Route::post('/', [PaymentApiController::class, 'store'])->name('store')->middleware('idempotent');
    Route::get('/{id}', [PaymentApiController::class, 'show'])->whereUuid('id')->name('show');
    Route::put('/{id}', [PaymentApiController::class, 'update'])->whereUuid('id')->name('update')->middleware('idempotent');
    Route::delete('/{id}', [PaymentApiController::class, 'destroy'])->whereUuid('id')->name('destroy')->middleware('idempotent');

    // Payment Actions
    Route::post('/{id}/allocate', [PaymentApiController::class, 'allocate'])->whereUuid('id')->name('allocate')->middleware('idempotent');
    Route::post('/{id}/auto-allocate', [PaymentApiController::class, 'autoAllocate'])->whereUuid('id')->name('auto-allocate')->middleware('idempotent');
    // Payment Allocation explicit endpoints
    Route::get('/{id}/allocations', [PaymentApiController::class, 'allocations'])->whereUuid('id')->name('allocations');
    Route::post('/{id}/allocations', [PaymentApiController::class, 'allocate'])->whereUuid('id')->name('allocations.store')->middleware('idempotent');
    Route::post('/{paymentId}/allocations/{allocationId}/void', [PaymentApiController::class, 'voidAllocation'])->whereUuid('paymentId')->whereUuid('allocationId')->name('allocations.void')->middleware('idempotent');
    Route::post('/{paymentId}/allocations/{allocationId}/refund', [PaymentApiController::class, 'refundAllocation'])->whereUuid('paymentId')->whereUuid('allocationId')->name('allocations.refund')->middleware('idempotent');
    Route::post('/{id}/void', [PaymentApiController::class, 'void'])->whereUuid('id')->name('void')->middleware('idempotent');
    Route::post('/{id}/refund', [PaymentApiController::class, 'refund'])->whereUuid('id')->name('refund')->middleware('idempotent');

    // Payment Statistics
    Route::get('/statistics', [PaymentApiController::class, 'statistics'])->name('statistics');
    Route::get('/{customerId}/summary', [PaymentApiController::class, 'customerSummary'])->whereUuid('customerId')->name('customer-summary');
    Route::get('/{id}/allocation-suggestions', [PaymentApiController::class, 'allocationSuggestions'])->whereUuid('id')->name('allocation-suggestions');

    // Bulk Operations
    Route::post('/bulk', [PaymentApiController::class, 'bulk'])->name('bulk')->middleware('idempotent');
});

// Currency Routes - uses web middleware for session authentication
Route::prefix('currencies')->name('api.currencies.')->middleware(['web', 'auth'])->group(function () {
    Route::get('/', [CurrencyApiController::class, 'index'])->name('index');
    Route::post('/', [CurrencyApiController::class, 'store'])->name('store');
    Route::get('/company', [CurrencyApiController::class, 'companyCurrencies'])->name('company');
    Route::get('/available', [CurrencyApiController::class, 'availableCurrencies'])->name('available');
    Route::get('/exchange-rate', [CurrencyApiController::class, 'exchangeRate'])->name('exchange-rate');
    Route::post('/convert', [CurrencyApiController::class, 'convert'])->name('convert');
    Route::post('/exchange-rate', [CurrencyApiController::class, 'updateExchangeRate'])->name('update-exchange-rate')->middleware('idempotent');
    Route::post('/enable', [CurrencyApiController::class, 'enableCurrency'])->name('enable')->middleware('idempotent');
    Route::post('/disable', [CurrencyApiController::class, 'disableCurrency'])->name('disable')->middleware('idempotent');
    Route::get('/exchange-rate-history', [CurrencyApiController::class, 'exchangeRateHistory'])->name('exchange-rate-history');
    Route::get('/latest-exchange-rates', [CurrencyApiController::class, 'latestExchangeRates'])->name('latest-exchange-rates');
    Route::get('/balances', [CurrencyApiController::class, 'currencyBalances'])->name('balances');
    Route::post('/currency-impact', [CurrencyApiController::class, 'currencyImpact'])->name('currency-impact');
    Route::post('/sync-exchange-rates', [CurrencyApiController::class, 'syncExchangeRates'])->name('sync-exchange-rates')->middleware('idempotent');
    Route::get('/symbol', [CurrencyApiController::class, 'currencySymbol'])->name('symbol');
    Route::post('/format-money', [CurrencyApiController::class, 'formatMoney'])->name('format-money');
    Route::patch('/{currency}/toggle-active', [CurrencyApiController::class, 'toggleActive'])->name('toggle-active');

    // Currency import routes
    Route::get('/import/sources', [CurrencyApiController::class, 'getImportSources'])->name('import.sources');
    Route::get('/import/search', [CurrencyApiController::class, 'searchExternalCurrencies'])->name('import.search');
    Route::post('/import/specific', [CurrencyApiController::class, 'importSpecificCurrencies'])->name('import.specific')->middleware('idempotent');
    Route::post('/import/preview', [CurrencyApiController::class, 'previewImport'])->name('import.preview')->middleware('idempotent');
    Route::post('/import', [CurrencyApiController::class, 'importCurrencies'])->name('import')->middleware('idempotent');
});

// Customer Routes
Route::prefix('customers')->name('customers.')->group(function () {
    Route::get('/', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'index'])->name('index')->middleware('permission:accounting.customers.view');
    Route::post('/', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'store'])->name('store')->middleware(['idempotent', 'permission:accounting.customers.create']);
    Route::get('/{id}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'show'])->whereUuid('id')->name('show')->middleware('permission:accounting.customers.view');
    Route::put('/{id}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'update'])->whereUuid('id')->name('update')->middleware(['idempotent', 'permission:accounting.customers.update']);
    Route::delete('/{id}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'destroy'])->whereUuid('id')->name('destroy')->middleware(['idempotent', 'permission:accounting.customers.delete']);

    // Customer Status
    Route::post('/{id}/status', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'changeStatus'])->whereUuid('id')->name('status.change')->middleware('permission:accounting.customers.update');

    // Customer Credit Limits
    Route::get('/{id}/credit-limit', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'creditLimit'])->whereUuid('id')->name('credit-limit.show')->middleware('permission:accounting.customers.manage_credit');
    Route::post('/{id}/credit-limit', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'creditLimitAdjust'])->whereUuid('id')->name('credit-limit.adjust')->middleware(['idempotent', 'permission:accounting.customers.manage_credit']);
    Route::post('/{id}/credit-limit/{creditLimitId}/approve', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'creditLimitApprove'])->whereUuid('id')->whereUuid('creditLimitId')->name('credit-limit.approve')->middleware(['idempotent', 'permission:accounting.customers.manage_credit']);
    Route::post('/{id}/credit-limit/{creditLimitId}/reject', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'creditLimitReject'])->whereUuid('id')->whereUuid('creditLimitId')->name('credit-limit.reject')->middleware(['idempotent', 'permission:accounting.customers.manage_credit']);
    Route::get('/{id}/credit-limit/history', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'creditLimitHistory'])->whereUuid('id')->name('credit-limit.history')->middleware('permission:accounting.customers.manage_credit');
    Route::post('/{id}/credit-limit/check', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'creditLimitCheck'])->whereUuid('id')->name('credit-limit.check')->middleware('permission:accounting.customers.view');

    // Customer Relations
    Route::get('/{id}/invoices', [\App\Http\Controllers\Api\CustomerApiController::class, 'invoices'])->whereUuid('id')->name('invoices')->middleware('permission:accounting.customers.view');
    Route::get('/{id}/payments', [\App\Http\Controllers\Api\CustomerApiController::class, 'payments'])->whereUuid('id')->name('payments')->middleware('permission:accounting.customers.view');
    Route::get('/{id}/statement', [\App\Http\Controllers\Api\CustomerApiController::class, 'statement'])->whereUuid('id')->name('statement')->middleware('permission:accounting.customers.view');
    Route::get('/{id}/statistics', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'statistics'])->whereUuid('id')->name('statistics')->middleware('permission:accounting.customers.view');

    // Customer Contacts
    Route::prefix('/{id}/contacts')->name('contacts.')->whereUuid('id')->group(function () {
        Route::get('/', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'contactsIndex'])->name('index')->middleware('permission:accounting.customers.manage_contacts');
        Route::post('/', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'contactsStore'])->name('store')->middleware(['idempotent', 'permission:accounting.customers.manage_contacts']);
        Route::get('/{contactId}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'contactsShow'])->whereUuid('contactId')->name('show')->middleware('permission:accounting.customers.manage_contacts');
        Route::put('/{contactId}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'contactsUpdate'])->whereUuid('contactId')->name('update')->middleware(['idempotent', 'permission:accounting.customers.manage_contacts']);
        Route::delete('/{contactId}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'contactsDestroy'])->whereUuid('contactId')->name('destroy')->middleware(['idempotent', 'permission:accounting.customers.manage_contacts']);
        Route::post('/{contactId}/set-primary', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'contactsSetPrimary'])->whereUuid('contactId')->name('set-primary')->middleware(['idempotent', 'permission:accounting.customers.manage_contacts']);
    });

    // Customer Addresses
    Route::prefix('/{id}/addresses')->name('addresses.')->whereUuid('id')->group(function () {
        Route::get('/', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'addressesIndex'])->name('index')->middleware('permission:accounting.customers.manage_contacts');
        Route::post('/', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'addressesStore'])->name('store')->middleware(['idempotent', 'permission:accounting.customers.manage_contacts']);
        Route::get('/{addressId}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'addressesShow'])->whereUuid('addressId')->name('show')->middleware('permission:accounting.customers.manage_contacts');
        Route::put('/{addressId}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'addressesUpdate'])->whereUuid('addressId')->name('update')->middleware(['idempotent', 'permission:accounting.customers.manage_contacts']);
        Route::delete('/{addressId}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'addressesDestroy'])->whereUuid('addressId')->name('destroy')->middleware(['idempotent', 'permission:accounting.customers.manage_contacts']);
        Route::post('/{addressId}/set-default', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'addressesSetDefault'])->whereUuid('addressId')->name('set-default')->middleware(['idempotent', 'permission:accounting.customers.manage_contacts']);
    });

    // Customer Groups
    Route::prefix('/{id}/groups')->name('groups.')->whereUuid('id')->group(function () {
        Route::get('/', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'groupsIndex'])->name('index')->middleware('permission:accounting.customers.manage_groups');
        Route::post('/assign', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'groupsAssign'])->name('assign')->middleware(['idempotent', 'permission:accounting.customers.manage_groups']);
        Route::delete('/{groupId}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'groupsRemove'])->whereUuid('groupId')->name('remove')->middleware(['idempotent', 'permission:accounting.customers.manage_groups']);
    });

    // Customer Communications
    Route::prefix('/{id}/communications')->name('communications.')->whereUuid('id')->group(function () {
        Route::get('/', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'communicationsIndex'])->name('index')->middleware('permission:accounting.customers.manage_comms');
        Route::post('/', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'communicationsStore'])->name('store')->middleware(['idempotent', 'permission:accounting.customers.manage_comms']);
        Route::get('/{commId}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'communicationsShow'])->whereUuid('commId')->name('show')->middleware('permission:accounting.customers.manage_comms');
        Route::delete('/{commId}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'communicationsDestroy'])->whereUuid('commId')->name('destroy')->middleware(['idempotent', 'permission:accounting.customers.manage_comms']);
        Route::get('/timeline', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'communicationsTimeline'])->name('timeline')->middleware('permission:accounting.customers.manage_comms');
    });

    // Customer Groups Management (Global)
    Route::prefix('groups')->name('groups.')->group(function () {
        Route::get('/', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'groupsGlobalIndex'])->name('index')->middleware('permission:accounting.customers.manage_groups');
        Route::post('/', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'groupsGlobalStore'])->name('store')->middleware(['idempotent', 'permission:accounting.customers.manage_groups']);
        Route::get('/{groupId}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'groupsGlobalShow'])->whereUuid('groupId')->name('show')->middleware('permission:accounting.customers.manage_groups');
        Route::put('/{groupId}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'groupsGlobalUpdate'])->whereUuid('groupId')->name('update')->middleware(['idempotent', 'permission:accounting.customers.manage_groups']);
        Route::delete('/{groupId}', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'groupsGlobalDestroy'])->whereUuid('groupId')->name('destroy')->middleware(['idempotent', 'permission:accounting.customers.manage_groups']);
        Route::get('/{groupId}/members', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'groupsMembers'])->whereUuid('groupId')->name('members')->middleware('permission:accounting.customers.manage_groups');
    });

    // Customer Search
    Route::get('/search', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'search'])->name('search')->middleware('permission:accounting.customers.view');

    // Customer Aging & Statements
    Route::get('/{id}/aging', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'aging'])->whereUuid('id')->name('aging')->middleware('permission:accounting.customers.view');
    Route::post('/{id}/aging/refresh', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'agingRefresh'])->whereUuid('id')->name('aging.refresh')->middleware(['idempotent', 'permission:accounting.customers.view']);
    Route::get('/{id}/statements', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'statements'])->whereUuid('id')->name('statements')->middleware('permission:accounting.customers.view');
    Route::post('/{id}/statements/generate', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'statementGenerate'])->whereUuid('id')->name('statements.generate')->middleware(['idempotent', 'permission:accounting.customers.view']);
    Route::get('/{id}/statements/{statementId}/download', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'statementDownload'])->whereUuid('id')->whereUuid('statementId')->name('statements.download')->middleware('permission:accounting.customers.view');

    // Company-wide Customer Analytics
    Route::get('/company/aging-summary', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'companyAgingSummary'])->name('company.aging-summary')->middleware('permission:accounting.customers.view');
    Route::get('/company/high-risk-customers', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'highRiskCustomers'])->name('company.high-risk-customers')->middleware('permission:accounting.customers.view');

    // Customer Import/Export
    Route::post('/import', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'import'])->name('import')->middleware(['idempotent', 'permission:accounting.customers.create']);
    Route::get('/import/{batchId}/status', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'importStatus'])->whereUuid('batchId')->name('import.status')->middleware('permission:accounting.customers.view');
    Route::post('/export', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'exportCustomers'])->name('export.customers')->middleware(['idempotent', 'permission:accounting.customers.view']);
    Route::get('/export/{batchId}/download', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'exportDownload'])->whereUuid('batchId')->name('export.download')->middleware('permission:accounting.customers.view');

    // Export & Bulk Operations
    Route::get('/export', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'export'])->name('export')->middleware('permission:accounting.customers.view');
    Route::post('/bulk', [\Modules\Accounting\Http\Controllers\Api\CustomerController::class, 'bulk'])->name('bulk')->middleware(['idempotent', 'permission:accounting.customers.update']);
});

// Invoice Template Routes
Route::prefix('templates')->name('templates.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Invoicing\InvoiceTemplateController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\Invoicing\InvoiceTemplateController::class, 'store'])->name('store')->middleware('idempotent');
    Route::get('/{id}', [\App\Http\Controllers\Invoicing\InvoiceTemplateController::class, 'show'])->whereUuid('id')->name('show');
    Route::put('/{id}', [\App\Http\Controllers\Invoicing\InvoiceTemplateController::class, 'update'])->whereUuid('id')->name('update')->middleware('idempotent');
    Route::delete('/{id}', [\App\Http\Controllers\Invoicing\InvoiceTemplateController::class, 'destroy'])->whereUuid('id')->name('destroy')->middleware('idempotent');

    // Template Actions
    Route::post('/{id}/apply', [\App\Http\Controllers\Invoicing\InvoiceTemplateController::class, 'apply'])->whereUuid('id')->name('apply')->middleware('idempotent');
    Route::post('/{id}/duplicate', [\App\Http\Controllers\Invoicing\InvoiceTemplateController::class, 'duplicate'])->whereUuid('id')->name('duplicate')->middleware('idempotent');
    Route::patch('/{id}/toggle-status', [\App\Http\Controllers\Invoicing\InvoiceTemplateController::class, 'toggleStatus'])->whereUuid('id')->name('toggle-status')->middleware('idempotent');

    // Template Creation from Invoice
    Route::post('/create-from-invoice', [\App\Http\Controllers\Invoicing\InvoiceTemplateController::class, 'createFromInvoice'])->name('create-from-invoice')->middleware('idempotent');

    // Template Statistics and Data
    Route::get('/statistics', [\App\Http\Controllers\Invoicing\InvoiceTemplateController::class, 'statistics'])->name('statistics');
    Route::get('/available-customers', [\App\Http\Controllers\Invoicing\InvoiceTemplateController::class, 'availableCustomers'])->name('available-customers');
    Route::post('/validate', [\App\Http\Controllers\Invoicing\InvoiceTemplateController::class, 'validateTemplate'])->name('validate');
});

// Invoicing requirements endpoint - uses web middleware for session authentication
Route::prefix('invoicing-requirements')->name('invoicing-requirements.')->middleware(['web', 'auth'])->group(function () {
    Route::get('/', [InvoicingRequirementsController::class, 'getRequirements'])->name('get');
    Route::post('/validate', [InvoicingRequirementsController::class, 'validateAdditionalInfo'])->name('validate');
});

// Universal inline edit endpoint
Route::patch('/inline-edit', [InlineEditController::class, 'patch'])->middleware(['web', 'auth']);

// Public invitation routes (no authentication required)
Route::prefix('invitations')->name('invitations.')->group(function () {
    Route::post('/{token}/accept', [\App\Http\Controllers\CompanyInvitationController::class, 'accept'])->name('accept');
    Route::post('/{token}/reject', [\App\Http\Controllers\CompanyInvitationController::class, 'reject'])->name('reject');
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()->toISOString(),
        'storage_symlink' => file_exists(public_path('storage')),
    ]);
})->name('health');

// API Documentation Route
Route::get('/docs', [DocumentationController::class, 'index'])->name('api.docs');

// Command Palette Routes - uses web middleware for session authentication
Route::prefix('commands')->name('commands.')->middleware(['web', 'auth', 'company.context', 'api.rate.limit'])->group(function () {
    Route::get('/', [CommandController::class, 'index'])->name('index');
    Route::get('/suggestions', [CommandController::class, 'suggestions'])->name('suggestions');
    Route::post('/execute', [CommandController::class, 'execute'])->name('execute')->middleware('idempotent');
    Route::post('/batch-execute', [CommandController::class, 'batchExecute'])->name('batch.execute')->middleware('idempotent');
    Route::get('/history', [CommandController::class, 'history'])->name('history');

    // Command Templates
    Route::prefix('/templates')->name('templates.')->group(function () {
        Route::get('/', [CommandController::class, 'templates'])->name('index');
        Route::post('/', [CommandController::class, 'storeTemplate'])->name('store')->middleware('idempotent');
        Route::put('/{id}', [CommandController::class, 'updateTemplate'])->name('update')->middleware('idempotent');
        Route::delete('/{id}', [CommandController::class, 'destroyTemplate'])->name('destroy');
    });
});

// API v1 Routes - Core system endpoints
Route::prefix('v1')->group(function () {
    // Setup routes - no authentication required for initialization
    Route::prefix('setup')->name('setup.')->group(function () {
        Route::get('/status', [\App\Http\Controllers\SetupController::class, 'status'])->name('status');
        Route::post('/initialize', [\App\Http\Controllers\SetupController::class, 'initialize'])->name('initialize')->middleware('idempotent');
    });

    // Public authentication routes - use web middleware for session auth
    Route::prefix('users')->name('users.')->middleware(['web'])->group(function () {
        Route::post('/login', [\App\Http\Controllers\Auth\AuthController::class, 'login'])->name('login')->middleware('idempotent');
    });

    // Company context switching (no company context required)
    Route::middleware(['web', 'auth'])->group(function () {
        Route::post('/companies/switch', [\App\Http\Controllers\CompanyController::class, 'switch'])->name('companies.switch')->middleware('idempotent');
        Route::post('/companies/{company}/switch', [\App\Http\Controllers\CompanyController::class, 'switchByUrl'])->name('companies.switch.by.url')->middleware('idempotent');
    });

    // Authenticated routes - use session authentication like the old system
    Route::middleware(['web', 'auth', 'company.context'])->group(function () {
        // User routes (except login)
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [\App\Http\Controllers\UserController::class, 'index'])->name('index');
        });

        // Company routes
        Route::prefix('companies')->name('companies.')->group(function () {
            Route::get('/', [\App\Http\Controllers\CompanyController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\CompanyController::class, 'store'])->name('store')->middleware('idempotent');
            Route::get('/{id}', [\App\Http\Controllers\CompanyController::class, 'show'])->whereUuid('id')->name('show');

            // Company invitations
            Route::get('/{companyId}/invitations', [\App\Http\Controllers\CompanyInvitationController::class, 'index'])->name('invitations.index');
            Route::post('/{companyId}/invitations', [\App\Http\Controllers\CompanyInvitationController::class, 'store'])->name('invitations.store')->middleware('idempotent');
            Route::get('/{companyId}/invitations/{invitationId}', [\App\Http\Controllers\CompanyInvitationController::class, 'show'])->name('invitations.show');
            Route::delete('/{companyId}/invitations/{invitationId}', [\App\Http\Controllers\CompanyInvitationController::class, 'destroy'])->name('invitations.destroy');
        });

        // Module routes
        Route::prefix('modules')->name('modules.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ModuleController::class, 'index'])->name('index');
            Route::post('/{id}/enable', [\App\Http\Controllers\ModuleController::class, 'enable'])->name('enable')->middleware('idempotent');
            Route::post('/{id}/disable', [\App\Http\Controllers\ModuleController::class, 'disable'])->name('disable')->middleware('idempotent');
        });

        // Ledger routes
        Route::prefix('ledger')->name('ledger.')->group(function () {
            // Period close routes
            Route::prefix('periods')->name('periods.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'index'])->name('index');
                Route::get('/statistics', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'statistics'])->name('statistics');

                // Period-specific routes
                Route::prefix('/{periodId}')->whereUuid('periodId')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'show'])->name('show');
                    Route::get('/actions', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'actions'])->name('actions');

                    // Period close workflow routes
                    Route::prefix('/close')->name('close.')->group(function () {
                        Route::post('/start', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'start'])->name('start')->middleware('idempotent');
                        Route::post('/validate', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'runValidation'])->name('validate')->middleware('idempotent');

                        // Adjustment management
                        Route::post('/adjustments', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'createAdjustment'])->name('adjustments.create')->middleware('idempotent');
                        Route::get('/adjustments', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'getAdjustments'])->name('adjustments.index');
                        Route::delete('/adjustments/{journalEntryId}', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'deleteAdjustment'])->name('adjustments.delete')->whereUuid('journalEntryId')->middleware('idempotent');

                        // Lock and complete operations
                        Route::post('/lock', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'lock'])->name('lock')->middleware('idempotent');
                        Route::post('/complete', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'complete'])->name('complete')->middleware('idempotent');
                        Route::get('/can-lock', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'canLock'])->name('can-lock');
                        Route::get('/can-complete', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'canComplete'])->name('can-complete');

                        // Report generation and management
                        Route::prefix('/reports')->name('reports.')->group(function () {
                            Route::post('/', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'generateReports'])->name('generate')->middleware('idempotent');
                            Route::get('/', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'getReports'])->name('index');
                            Route::get('/status', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'getReportStatus'])->name('status');
                            Route::get('/options', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'getReportOptions'])->name('options');
                            Route::get('/download/{reportType}', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'downloadReport'])->name('download')->where('reportType', '[a-zA-Z0-9_]+');
                            Route::delete('/{reportId}', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'deleteReport'])->name('delete')->whereUuid('reportId')->middleware('idempotent');
                        });

                        // Reopen management
                        Route::prefix('/reopen')->name('reopen.')->group(function () {
                            Route::post('/', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'reopen'])->name('reopen')->middleware('idempotent');
                            Route::get('/can-reopen', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'canReopen'])->name('can-reopen');
                            Route::get('/history', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'getReopenHistory'])->name('history');
                            Route::post('/extend-window', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'extendReopenWindow'])->name('extend-window')->middleware('idempotent');
                            Route::get('/check-expired', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'checkReopenWindowExpired'])->name('check-expired');
                        });

                        // Task management
                        Route::prefix('/tasks')->name('tasks.')->group(function () {
                            Route::patch('/{taskId}', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'updateTask'])->name('update')->whereUuid('taskId')->middleware('idempotent');
                            Route::post('/{taskId}/complete', [\App\Http\Controllers\Ledger\PeriodCloseController::class, 'completeTask'])->name('complete')->whereUuid('taskId')->middleware('idempotent');
                        });
                    });
                });
            });

            // Period close template routes
            Route::prefix('period-close/templates')->name('period-close.templates.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Ledger\PeriodCloseTemplateController::class, 'index'])->name('index');
                Route::get('/statistics', [\App\Http\Controllers\Ledger\PeriodCloseTemplateController::class, 'statistics'])->name('statistics');
                Route::post('/', [\App\Http\Controllers\Ledger\PeriodCloseTemplateController::class, 'store'])->name('store')->middleware('idempotent');
                Route::get('/{templateId}', [\App\Http\Controllers\Ledger\PeriodCloseTemplateController::class, 'show'])->name('show')->whereUuid('templateId');
                Route::put('/{templateId}', [\App\Http\Controllers\Ledger\PeriodCloseTemplateController::class, 'update'])->name('update')->whereUuid('templateId')->middleware('idempotent');
                Route::post('/{templateId}/archive', [\App\Http\Controllers\Ledger\PeriodCloseTemplateController::class, 'archive'])->name('archive')->whereUuid('templateId')->middleware('idempotent');
                Route::post('/{templateId}/sync', [\App\Http\Controllers\Ledger\PeriodCloseTemplateController::class, 'sync'])->name('sync')->whereUuid('templateId')->middleware('idempotent');
                Route::post('/{templateId}/duplicate', [\App\Http\Controllers\Ledger\PeriodCloseTemplateController::class, 'duplicate'])->name('duplicate')->whereUuid('templateId')->middleware('idempotent');
            });
        });
    });
});

// Reporting Dashboard Routes - uses Sanctum authentication
Route::prefix('reporting')->name('reporting.')->middleware(['auth:sanctum', 'company.context'])->group(function () {
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        // Dashboard main endpoints
        Route::get('/', [App\Http\Controllers\Reporting\DashboardController::class, 'index'])->name('index');
        Route::post('/refresh', [App\Http\Controllers\Reporting\DashboardController::class, 'refresh'])->name('refresh');
        Route::post('/refresh-all', [App\Http\Controllers\Reporting\DashboardController::class, 'refreshAll'])->name('refresh.all');
        Route::post('/invalidate-cache', [App\Http\Controllers\Reporting\DashboardController::class, 'invalidateCache'])->name('invalidate.cache');

        // Dashboard management endpoints
        Route::get('/status', [App\Http\Controllers\Reporting\DashboardController::class, 'status'])->name('status');
        Route::get('/stats', [App\Http\Controllers\Reporting\DashboardController::class, 'stats'])->name('stats');
        Route::get('/layouts', [App\Http\Controllers\Reporting\DashboardController::class, 'layouts'])->name('layouts');

        // Advanced KPIs for dashboard
        Route::get('/aging-kpis', [App\Http\Controllers\Reporting\DashboardController::class, 'agingKpis'])->name('aging-kpis');
    });

    // Reports endpoints
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [App\Http\Controllers\Reporting\ReportController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Reporting\ReportController::class, 'store'])->name('store')->middleware('idempotent');
        Route::get('/types', [App\Http\Controllers\Reporting\ReportController::class, 'types'])->name('types');
        Route::get('/statistics', [App\Http\Controllers\Reporting\ReportController::class, 'statistics'])->name('statistics');
        Route::get('/preview', [App\Http\Controllers\Reporting\ReportController::class, 'preview'])->name('preview');

        // Report specific endpoints
        Route::get('/{id}', [App\Http\Controllers\Reporting\ReportController::class, 'show'])->whereUuid('id')->name('show');
        Route::get('/{id}/status', [App\Http\Controllers\Reporting\ReportController::class, 'status'])->whereUuid('id')->name('status');
        Route::get('/{id}/download', [App\Http\Controllers\Reporting\ReportController::class, 'download'])->whereUuid('id')->name('download');
        Route::get('/{id}/download-with-token', [App\Http\Controllers\Reporting\ReportController::class, 'downloadWithToken'])->whereUuid('id')->name('download.token');
        Route::post('/{id}/deliver', [App\Http\Controllers\Reporting\ReportController::class, 'deliver'])->whereUuid('id')->name('deliver');
        Route::delete('/{id}', [App\Http\Controllers\Reporting\ReportController::class, 'destroy'])->whereUuid('id')->name('destroy')->middleware('idempotent');
    });

    // Advanced KPI endpoints
    Route::prefix('kpis')->name('kpis.')->group(function () {
        Route::get('/aging', [App\Http\Controllers\Reporting\ReportController::class, 'agingKpis'])->name('aging');
        Route::get('/budget', [App\Http\Controllers\Reporting\ReportController::class, 'budgetKpis'])->name('budget');
        Route::get('/advanced', [App\Http\Controllers\Reporting\ReportController::class, 'advancedKpis'])->name('advanced');
        Route::get('/currencies', [App\Http\Controllers\Reporting\ReportController::class, 'currencies'])->name('currencies');
    });

    // Transaction drilldown endpoints
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/drilldown', [App\Http\Controllers\Reporting\ReportController::class, 'drilldown'])->name('drilldown');
        Route::get('/search', [App\Http\Controllers\Reporting\ReportController::class, 'searchTransactions'])->name('search');
    });

    // Report templates endpoints
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [App\Http\Controllers\Reporting\ReportTemplateController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Reporting\ReportTemplateController::class, 'store'])->name('store')->middleware('idempotent');
        Route::get('/available', [App\Http\Controllers\Reporting\ReportTemplateController::class, 'available'])->name('available');
        Route::get('/default-configuration', [App\Http\Controllers\Reporting\ReportTemplateController::class, 'getDefaultConfiguration'])->name('default-configuration');
        Route::post('/validate-configuration', [App\Http\Controllers\Reporting\ReportTemplateController::class, 'validateConfiguration'])->name('validate-configuration');
        Route::post('/reorder', [App\Http\Controllers\Reporting\ReportTemplateController::class, 'reorder'])->name('reorder');

        // Template specific endpoints
        Route::get('/{id}', [App\Http\Controllers\Reporting\ReportTemplateController::class, 'show'])->whereUuid('id')->name('show');
        Route::put('/{id}', [App\Http\Controllers\Reporting\ReportTemplateController::class, 'update'])->whereUuid('id')->name('update');
        Route::delete('/{id}', [App\Http\Controllers\Reporting\ReportTemplateController::class, 'destroy'])->whereUuid('id')->name('destroy')->middleware('idempotent');
        Route::post('/{id}/duplicate', [App\Http\Controllers\Reporting\ReportTemplateController::class, 'duplicate'])->whereUuid('id')->name('duplicate');
    });

    // Report schedules endpoints
    Route::prefix('schedules')->name('schedules.')->group(function () {
        Route::get('/', [App\Http\Controllers\Reporting\ReportScheduleController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Reporting\ReportScheduleController::class, 'store'])->name('store')->middleware('idempotent');
        Route::get('/statistics', [App\Http\Controllers\Reporting\ReportScheduleController::class, 'statistics'])->name('statistics');
        Route::get('/upcoming', [App\Http\Controllers\Reporting\ReportScheduleController::class, 'upcoming'])->name('upcoming');

        // Schedule specific endpoints
        Route::get('/{id}', [App\Http\Controllers\Reporting\ReportScheduleController::class, 'show'])->whereUuid('id')->name('show');
        Route::put('/{id}', [App\Http\Controllers\Reporting\ReportScheduleController::class, 'update'])->whereUuid('id')->name('update');
        Route::delete('/{id}', [App\Http\Controllers\Reporting\ReportScheduleController::class, 'destroy'])->whereUuid('id')->name('destroy')->middleware('idempotent');
        Route::post('/{id}/pause', [App\Http\Controllers\Reporting\ReportScheduleController::class, 'pause'])->whereUuid('id')->name('pause');
        Route::post('/{id}/resume', [App\Http\Controllers\Reporting\ReportScheduleController::class, 'resume'])->whereUuid('id')->name('resume');
        Route::post('/{id}/trigger', [App\Http\Controllers\Reporting\ReportScheduleController::class, 'trigger'])->whereUuid('id')->name('trigger');
    });
});
