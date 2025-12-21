<?php

use App\Http\Controllers\CompaniesPageController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyModulesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\UsersPageController;
use App\Modules\Accounting\Http\Controllers\CustomerController;
use App\Modules\Accounting\Http\Controllers\InvoiceController;
use App\Modules\Accounting\Http\Controllers\PaymentController;
use App\Modules\Accounting\Http\Controllers\CreditNoteController;
use App\Modules\Accounting\Http\Controllers\AccountController;
use App\Modules\Accounting\Http\Controllers\BillController;
use App\Modules\Accounting\Http\Controllers\BillPaymentController;
use App\Modules\Accounting\Http\Controllers\VendorController;
use App\Modules\Accounting\Http\Controllers\VendorCreditController;
use App\Modules\Accounting\Http\Controllers\JournalController;
use App\Modules\Accounting\Http\Controllers\TaxSettingsController;
use App\Modules\Accounting\Http\Controllers\FiscalYearController;
use App\Modules\Accounting\Http\Controllers\BankFeedController;
use App\Modules\Accounting\Http\Controllers\BankAccountController;
use App\Modules\Accounting\Http\Controllers\BankReconciliationController;
use App\Modules\Accounting\Http\Controllers\PostingTemplateController;
use App\Modules\Accounting\Http\Controllers\AccountingDefaultsController;
use App\Modules\Accounting\Http\Controllers\SaleController;
use App\Modules\Accounting\Http\Controllers\ProfitLossReportController;
use App\Modules\Inventory\Http\Controllers\ItemController;
use App\Modules\Inventory\Http\Controllers\ItemCategoryController;
use App\Modules\Inventory\Http\Controllers\WarehouseController;
use App\Modules\Inventory\Http\Controllers\StockController;
use Modules\Payroll\Http\Controllers\EmployeeController;
use Modules\Payroll\Http\Controllers\EarningTypeController;
use Modules\Payroll\Http\Controllers\DeductionTypeController;
use Modules\Payroll\Http\Controllers\LeaveTypeController;
use Modules\Payroll\Http\Controllers\LeaveRequestController;
use Modules\Payroll\Http\Controllers\PayrollPeriodController;
use Modules\Payroll\Http\Controllers\PayslipController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Welcome page for first-time users
Route::get('welcome', [\App\Http\Controllers\WelcomeController::class, 'index'])
    ->middleware(['auth'])
    ->name('welcome');

// Invitation routes (public/guest access for viewing)
Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('invitation.show');

Route::middleware(['auth'])->group(function () {
    // Invitation routes (authenticated)
    Route::post('/invite/{token}/accept', [InvitationController::class, 'accept'])->name('invitation.accept');
    Route::post('/invite/{token}/reject', [InvitationController::class, 'reject'])->name('invitation.reject');
    Route::get('/invitations/pending', [InvitationController::class, 'pending'])->name('invitations.pending');

    // Non-scoped company routes (creation and switching)
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::post('/companies/switch', [CompaniesPageController::class, 'switch'])->name('companies.switch');
    Route::get('/companies', [CompaniesPageController::class, 'index'])->name('companies.index');
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');

    // Company-scoped routes
	    Route::middleware(['identify.company'])->group(function () {
	        Route::get('/{company}', [CompanyController::class, 'show'])->name('company.show');
	        Route::put('/{company}', [CompanyController::class, 'update'])->name('company.update');
	        Route::get('/{company}/settings', [CompanyController::class, 'settings'])->name('company.settings');
	        Route::patch('/{company}/settings', [CompanyController::class, 'updateSettings'])->name('company.settings.update');
	        Route::patch('/{company}/settings/modules', [CompanyModulesController::class, 'update'])->name('company.settings.modules.update');
	        Route::get('/{company}/settings/tax-default', [CompanyController::class, 'taxDefault'])->name('company.settings.tax-default');

        // Sales (MVP)
        Route::get('/{company}/sales/create', [SaleController::class, 'create'])->name('sales.create');
        Route::post('/{company}/sales', [SaleController::class, 'store'])->name('sales.store');

        // Reports (MVP)
        Route::get('/{company}/reports/profit-loss', [ProfitLossReportController::class, 'index'])->name('reports.profit-loss');

        // Company onboarding wizard
        Route::prefix('/{company}/onboarding')->group(function () {
            Route::get('/', [\App\Http\Controllers\CompanyOnboardingController::class, 'index'])->name('onboarding.index');

            Route::get('/company-identity', [\App\Http\Controllers\CompanyOnboardingController::class, 'showCompanyIdentity'])->name('onboarding.company-identity');
            Route::post('/company-identity', [\App\Http\Controllers\CompanyOnboardingController::class, 'storeCompanyIdentity']);

            Route::get('/fiscal-year', [\App\Http\Controllers\CompanyOnboardingController::class, 'showFiscalYear'])->name('onboarding.fiscal-year');
            Route::post('/fiscal-year', [\App\Http\Controllers\CompanyOnboardingController::class, 'storeFiscalYear']);

            Route::get('/bank-accounts', [\App\Http\Controllers\CompanyOnboardingController::class, 'showBankAccounts'])->name('onboarding.bank-accounts');
            Route::post('/bank-accounts', [\App\Http\Controllers\CompanyOnboardingController::class, 'storeBankAccounts']);

            Route::get('/default-accounts', [\App\Http\Controllers\CompanyOnboardingController::class, 'showDefaultAccounts'])->name('onboarding.default-accounts');
            Route::post('/default-accounts', [\App\Http\Controllers\CompanyOnboardingController::class, 'storeDefaultAccounts']);

            Route::get('/tax-settings', [\App\Http\Controllers\CompanyOnboardingController::class, 'showTaxSettings'])->name('onboarding.tax-settings');
            Route::post('/tax-settings', [\App\Http\Controllers\CompanyOnboardingController::class, 'storeTaxSettings']);

            Route::get('/numbering', [\App\Http\Controllers\CompanyOnboardingController::class, 'showNumbering'])->name('onboarding.numbering');
            Route::post('/numbering', [\App\Http\Controllers\CompanyOnboardingController::class, 'storeNumbering']);

            Route::get('/payment-terms', [\App\Http\Controllers\CompanyOnboardingController::class, 'showPaymentTerms'])->name('onboarding.payment-terms');
            Route::post('/payment-terms', [\App\Http\Controllers\CompanyOnboardingController::class, 'storePaymentTerms']);

            Route::get('/complete', [\App\Http\Controllers\CompanyOnboardingController::class, 'showComplete'])->name('onboarding.complete');
            Route::post('/complete', [\App\Http\Controllers\CompanyOnboardingController::class, 'complete']);
        });

        Route::get('/{company}/users', [UsersPageController::class, 'index'])->name('users.index');
        Route::post('/{company}/users/invite', [UsersPageController::class, 'invite'])->name('users.invite');
        Route::put('/{company}/users/{user}/role', [UsersPageController::class, 'updateRole'])->name('users.update-role');
        Route::delete('/{company}/users/{user}', [UsersPageController::class, 'remove'])->name('users.remove');
        Route::delete('/{company}/invitations/{invitation}', [InvitationController::class, 'revoke'])->name('invitations.revoke');

        // Customer routes (Accounting module)
        Route::get('/{company}/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/{company}/customers/search', [CustomerController::class, 'search'])->name('customers.search');
        Route::get('/{company}/customers/recent', [CustomerController::class, 'recent'])->name('customers.recent');
        Route::post('/{company}/customers/quick-store', [CustomerController::class, 'quickStore'])->name('customers.quick-store');
        Route::get('/{company}/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/{company}/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/{company}/customers/{customer}', [CustomerController::class, 'show'])->whereUuid('customer')->name('customers.show');
        Route::get('/{company}/customers/{customer}/edit', [CustomerController::class, 'edit'])->whereUuid('customer')->name('customers.edit');
        Route::get('/{company}/customers/{customer}/tax-default', [CustomerController::class, 'taxDefault'])->whereUuid('customer')->name('customers.tax-default');
        Route::put('/{company}/customers/{customer}', [CustomerController::class, 'update'])->whereUuid('customer')->name('customers.update');
        Route::delete('/{company}/customers/{customer}', [CustomerController::class, 'destroy'])->whereUuid('customer')->name('customers.destroy');

        // Invoice routes (Accounting module)
        Route::get('/{company}/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/{company}/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/{company}/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/{company}/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/{company}/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('/{company}/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('/{company}/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::post('/{company}/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('/{company}/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
        Route::post('/{company}/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');

        // Payment routes (Accounting module)
        Route::get('/{company}/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/{company}/payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/{company}/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/{company}/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::get('/{company}/payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
        Route::put('/{company}/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
        Route::delete('/{company}/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

        // Credit Note routes (Accounting module)
        Route::get('/{company}/credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
        Route::get('/{company}/credit-notes/create', [CreditNoteController::class, 'create'])->name('credit-notes.create');
        Route::post('/{company}/credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');
        Route::get('/{company}/credit-notes/{credit_note}', [CreditNoteController::class, 'show'])->name('credit-notes.show');
        Route::get('/{company}/credit-notes/{credit_note}/edit', [CreditNoteController::class, 'edit'])->name('credit-notes.edit');
        Route::put('/{company}/credit-notes/{credit_note}', [CreditNoteController::class, 'update'])->name('credit-notes.update');
        Route::delete('/{company}/credit-notes/{credit_note}', [CreditNoteController::class, 'destroy'])->name('credit-notes.destroy');

        // Accounts (Chart of Accounts)
        Route::get('/{company}/accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('/{company}/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
        Route::post('/{company}/accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::get('/{company}/accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');
        Route::get('/{company}/accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
        Route::put('/{company}/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::delete('/{company}/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
        Route::get('/{company}/accounting/default-accounts', [AccountingDefaultsController::class, 'edit'])->name('accounting-defaults.edit');
        Route::patch('/{company}/accounting/default-accounts', [AccountingDefaultsController::class, 'update'])->name('accounting-defaults.update');

        // Vendors
        Route::get('/{company}/vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::get('/{company}/vendors/search', [VendorController::class, 'search'])->name('vendors.search');
        Route::get('/{company}/vendors/recent', [VendorController::class, 'recent'])->name('vendors.recent');
        Route::post('/{company}/vendors/quick-store', [VendorController::class, 'quickStore'])->name('vendors.quick-store');
        Route::get('/{company}/vendors/create', [VendorController::class, 'create'])->name('vendors.create');
        Route::post('/{company}/vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::get('/{company}/vendors/{vendor}', [VendorController::class, 'show'])->whereUuid('vendor')->name('vendors.show');
        Route::get('/{company}/vendors/{vendor}/edit', [VendorController::class, 'edit'])->whereUuid('vendor')->name('vendors.edit');
        Route::get('/{company}/vendors/{vendor}/tax-default', [VendorController::class, 'taxDefault'])->whereUuid('vendor')->name('vendors.tax-default');
        Route::put('/{company}/vendors/{vendor}', [VendorController::class, 'update'])->whereUuid('vendor')->name('vendors.update');
        Route::delete('/{company}/vendors/{vendor}', [VendorController::class, 'destroy'])->whereUuid('vendor')->name('vendors.destroy');

        // Bills
        Route::get('/{company}/bills', [BillController::class, 'index'])->name('bills.index');
        Route::get('/{company}/bills/create', [BillController::class, 'create'])->name('bills.create');
        Route::post('/{company}/bills', [BillController::class, 'store'])->name('bills.store');
        Route::get('/{company}/bills/{bill}', [BillController::class, 'show'])->name('bills.show');
        Route::get('/{company}/bills/{bill}/edit', [BillController::class, 'edit'])->name('bills.edit');
        Route::put('/{company}/bills/{bill}', [BillController::class, 'update'])->name('bills.update');
        Route::delete('/{company}/bills/{bill}', [BillController::class, 'destroy'])->name('bills.destroy');
        Route::post('/{company}/bills/{bill}/receive', [BillController::class, 'receive'])->name('bills.receive');
        Route::post('/{company}/bills/{bill}/receive-goods', [BillController::class, 'receiveGoods'])->name('bills.receive-goods');
        Route::post('/{company}/bills/{bill}/void', [BillController::class, 'void'])->name('bills.void');

        // Bill Payments
        Route::get('/{company}/bill-payments', [BillPaymentController::class, 'index'])->name('bill-payments.index');
        Route::get('/{company}/bill-payments/create', [BillPaymentController::class, 'create'])->name('bill-payments.create');
        Route::post('/{company}/bill-payments', [BillPaymentController::class, 'store'])->name('bill-payments.store');
        Route::get('/{company}/bill-payments/{payment}', [BillPaymentController::class, 'show'])->name('bill-payments.show');
        Route::delete('/{company}/bill-payments/{payment}', [BillPaymentController::class, 'destroy'])->name('bill-payments.destroy');

        // Vendor Credits
        Route::get('/{company}/vendor-credits', [VendorCreditController::class, 'index'])->name('vendor-credits.index');
        Route::get('/{company}/vendor-credits/create', [VendorCreditController::class, 'create'])->name('vendor-credits.create');
        Route::post('/{company}/vendor-credits', [VendorCreditController::class, 'store'])->name('vendor-credits.store');
        Route::get('/{company}/vendor-credits/{vendorCredit}', [VendorCreditController::class, 'show'])->name('vendor-credits.show');
        Route::get('/{company}/vendor-credits/{vendorCredit}/edit', [VendorCreditController::class, 'edit'])->name('vendor-credits.edit');
        Route::get('/{company}/vendor-credits/{vendorCredit}/apply', [VendorCreditController::class, 'apply'])->name('vendor-credits.apply');
        Route::delete('/{company}/vendor-credits/{vendorCredit}', [VendorCreditController::class, 'destroy'])->name('vendor-credits.destroy');

        // Manual Journals
        Route::get('/{company}/journals', [JournalController::class, 'index'])->name('journals.index');
        Route::get('/{company}/journals/create', [JournalController::class, 'create'])->name('journals.create');
        Route::post('/{company}/journals', [JournalController::class, 'store'])->name('journals.store');
        Route::get('/{company}/journals/{journal}', [JournalController::class, 'show'])->name('journals.show');

        // Tax Management routes
        Route::get('/{company}/tax/settings', [TaxSettingsController::class, 'index'])->name('tax.settings');
        Route::post('/{company}/tax/settings', [TaxSettingsController::class, 'updateSettings'])->name('tax.settings.update');
        Route::post('/{company}/tax/enable-saudi-vat', [TaxSettingsController::class, 'enableSaudiVAT'])->name('tax.enable-saudi-vat');

        // Tax Rates
        Route::post('/{company}/tax/rates', [TaxSettingsController::class, 'createTaxRate'])->name('tax.rates.store');
        Route::put('/{company}/tax/rates/{id}', [TaxSettingsController::class, 'updateTaxRate'])->name('tax.rates.update');
        Route::delete('/{company}/tax/rates/{id}', [TaxSettingsController::class, 'deleteTaxRate'])->name('tax.rates.destroy');

        // Tax Groups
        Route::post('/{company}/tax/groups', [TaxSettingsController::class, 'createTaxGroup'])->name('tax.groups.store');
        Route::put('/{company}/tax/groups/{id}', [TaxSettingsController::class, 'updateTaxGroup'])->name('tax.groups.update');
        Route::delete('/{company}/tax/groups/{id}', [TaxSettingsController::class, 'deleteTaxGroup'])->name('tax.groups.destroy');

        // Tax Registrations
        Route::post('/{company}/tax/registrations', [TaxSettingsController::class, 'createTaxRegistration'])->name('tax.registrations.store');
        Route::put('/{company}/tax/registrations/{id}', [TaxSettingsController::class, 'updateTaxRegistration'])->name('tax.registrations.update');
        Route::delete('/{company}/tax/registrations/{id}', [TaxSettingsController::class, 'deleteTaxRegistration'])->name('tax.registrations.destroy');

        // Tax Exemptions
        Route::post('/{company}/tax/exemptions', [TaxSettingsController::class, 'createTaxExemption'])->name('tax.exemptions.store');
        Route::put('/{company}/tax/exemptions/{id}', [TaxSettingsController::class, 'updateTaxExemption'])->name('tax.exemptions.update');
        Route::delete('/{company}/tax/exemptions/{id}', [TaxSettingsController::class, 'deleteTaxExemption'])->name('tax.exemptions.destroy');

        // Tax API endpoints
        Route::get('/{company}/tax/api/rates', [TaxSettingsController::class, 'getTaxRates'])->name('tax.api.rates');
        Route::get('/{company}/tax/api/groups', [TaxSettingsController::class, 'getTaxGroups'])->name('tax.api.groups');
        Route::post('/{company}/tax/api/calculate', [TaxSettingsController::class, 'calculateTax'])->name('tax.api.calculate');

        // Fiscal Year Management
        Route::get('/{company}/fiscal-years', [FiscalYearController::class, 'index'])->name('fiscal-years.index');
        Route::get('/{company}/fiscal-years/create', [FiscalYearController::class, 'create'])->name('fiscal-years.create');
        Route::post('/{company}/fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store');
        Route::get('/{company}/fiscal-years/{fiscalYear}', [FiscalYearController::class, 'show'])->name('fiscal-years.show');
        Route::get('/{company}/fiscal-years/{fiscalYear}/edit', [FiscalYearController::class, 'edit'])->name('fiscal-years.edit');
        Route::put('/{company}/fiscal-years/{fiscalYear}', [FiscalYearController::class, 'update'])->name('fiscal-years.update');
        Route::delete('/{company}/fiscal-years/{fiscalYear}', [FiscalYearController::class, 'destroy'])->name('fiscal-years.destroy');
        Route::post('/{company}/fiscal-years/{fiscalYear}/periods', [FiscalYearController::class, 'createPeriods'])->name('fiscal-years.periods.create');
        Route::post('/{company}/accounting-periods/{period}/close', [FiscalYearController::class, 'closePeriod'])->name('accounting-periods.close');
        Route::post('/{company}/accounting-periods/{period}/reopen', [FiscalYearController::class, 'reopenPeriod'])->name('accounting-periods.reopen');

        // Posting Templates
        Route::get('/{company}/posting-templates', [PostingTemplateController::class, 'index'])->name('posting-templates.index');
        Route::get('/{company}/posting-templates/create', [PostingTemplateController::class, 'create'])->name('posting-templates.create');
        Route::post('/{company}/posting-templates', [PostingTemplateController::class, 'store'])->name('posting-templates.store');
        Route::get('/{company}/posting-templates/{posting_template}/edit', [PostingTemplateController::class, 'edit'])->whereUuid('posting_template')->name('posting-templates.edit');
        Route::put('/{company}/posting-templates/{posting_template}', [PostingTemplateController::class, 'update'])->whereUuid('posting_template')->name('posting-templates.update');
        
        // ─────────────────────────────────────────────────────────────────
        // Banking Module
        // ─────────────────────────────────────────────────────────────────

        // Bank Feed Resolution routes (Owner Mode - Review Transactions)
        Route::get('/{company}/banking/feed', [BankFeedController::class, 'index'])->name('banking.feed.index');
        Route::post('/{company}/banking/resolve/match', [BankFeedController::class, 'resolveMatch'])->name('banking.feed.resolve.match');
        Route::post('/{company}/banking/resolve/create', [BankFeedController::class, 'resolveCreate'])->name('banking.feed.resolve.create');
        Route::post('/{company}/banking/resolve/transfer', [BankFeedController::class, 'resolveTransfer'])->name('banking.feed.resolve.transfer');
        Route::post('/{company}/banking/resolve/park', [BankFeedController::class, 'resolvePark'])->name('banking.feed.resolve.park');

        // Bank Accounts (Accountant Mode)
        Route::get('/{company}/banking/accounts', [BankAccountController::class, 'index'])->name('banking.accounts.index');
        Route::get('/{company}/banking/accounts/search', [BankAccountController::class, 'search'])->name('banking.accounts.search');
        Route::get('/{company}/banking/accounts/create', [BankAccountController::class, 'create'])->name('banking.accounts.create');
        Route::post('/{company}/banking/accounts', [BankAccountController::class, 'store'])->name('banking.accounts.store');
        Route::get('/{company}/banking/accounts/{bankAccount}', [BankAccountController::class, 'show'])->whereUuid('bankAccount')->name('banking.accounts.show');
        Route::get('/{company}/banking/accounts/{bankAccount}/edit', [BankAccountController::class, 'edit'])->whereUuid('bankAccount')->name('banking.accounts.edit');
        Route::put('/{company}/banking/accounts/{bankAccount}', [BankAccountController::class, 'update'])->whereUuid('bankAccount')->name('banking.accounts.update');
        Route::delete('/{company}/banking/accounts/{bankAccount}', [BankAccountController::class, 'destroy'])->whereUuid('bankAccount')->name('banking.accounts.destroy');

        // Bank Reconciliation (Accountant Mode)
        Route::get('/{company}/banking/reconciliation', [BankReconciliationController::class, 'index'])->name('banking.reconciliation.index');
        Route::get('/{company}/banking/reconciliation/start', [BankReconciliationController::class, 'start'])->name('banking.reconciliation.start');
        Route::post('/{company}/banking/reconciliation', [BankReconciliationController::class, 'store'])->name('banking.reconciliation.store');
        Route::get('/{company}/banking/reconciliation/{reconciliation}', [BankReconciliationController::class, 'show'])->whereUuid('reconciliation')->name('banking.reconciliation.show');
        Route::post('/{company}/banking/reconciliation/{reconciliation}/toggle', [BankReconciliationController::class, 'toggleTransaction'])->whereUuid('reconciliation')->name('banking.reconciliation.toggle');
        Route::post('/{company}/banking/reconciliation/{reconciliation}/complete', [BankReconciliationController::class, 'complete'])->whereUuid('reconciliation')->name('banking.reconciliation.complete');
        Route::post('/{company}/banking/reconciliation/{reconciliation}/cancel', [BankReconciliationController::class, 'cancel'])->whereUuid('reconciliation')->name('banking.reconciliation.cancel');

        // Bank Rules (Accountant Mode)
        Route::get('/{company}/banking/rules', [\App\Modules\Accounting\Http\Controllers\BankRuleController::class, 'index'])->name('banking.rules.index');
        Route::get('/{company}/banking/rules/create', [\App\Modules\Accounting\Http\Controllers\BankRuleController::class, 'create'])->name('banking.rules.create');
        Route::post('/{company}/banking/rules', [\App\Modules\Accounting\Http\Controllers\BankRuleController::class, 'store'])->name('banking.rules.store');
        Route::get('/{company}/banking/rules/{rule}', [\App\Modules\Accounting\Http\Controllers\BankRuleController::class, 'show'])->whereUuid('rule')->name('banking.rules.show');
        Route::get('/{company}/banking/rules/{rule}/edit', [\App\Modules\Accounting\Http\Controllers\BankRuleController::class, 'edit'])->whereUuid('rule')->name('banking.rules.edit');
        Route::put('/{company}/banking/rules/{rule}', [\App\Modules\Accounting\Http\Controllers\BankRuleController::class, 'update'])->whereUuid('rule')->name('banking.rules.update');
        Route::delete('/{company}/banking/rules/{rule}', [\App\Modules\Accounting\Http\Controllers\BankRuleController::class, 'destroy'])->whereUuid('rule')->name('banking.rules.destroy');

	        // ─────────────────────────────────────────────────────────────────
	        // Inventory Module
	        // ─────────────────────────────────────────────────────────────────
	
	        Route::middleware(['require.module:inventory'])->group(function () {
	            // Items
	            Route::get('/{company}/items', [ItemController::class, 'index'])->name('items.index');
	            Route::get('/{company}/items/search', [ItemController::class, 'search'])->name('items.search');
	            Route::get('/{company}/items/create', [ItemController::class, 'create'])->name('items.create');
	            Route::post('/{company}/items', [ItemController::class, 'store'])->name('items.store');
	            Route::get('/{company}/items/{item}', [ItemController::class, 'show'])->whereUuid('item')->name('items.show');
	            Route::get('/{company}/items/{item}/edit', [ItemController::class, 'edit'])->whereUuid('item')->name('items.edit');
	            Route::put('/{company}/items/{item}', [ItemController::class, 'update'])->whereUuid('item')->name('items.update');
	            Route::delete('/{company}/items/{item}', [ItemController::class, 'destroy'])->whereUuid('item')->name('items.destroy');
	
	            // Item Categories
	            Route::get('/{company}/item-categories', [ItemCategoryController::class, 'index'])->name('item-categories.index');
	            Route::get('/{company}/item-categories/search', [ItemCategoryController::class, 'search'])->name('item-categories.search');
	            Route::get('/{company}/item-categories/create', [ItemCategoryController::class, 'create'])->name('item-categories.create');
	            Route::post('/{company}/item-categories', [ItemCategoryController::class, 'store'])->name('item-categories.store');
	            Route::get('/{company}/item-categories/{item_category}', [ItemCategoryController::class, 'show'])->whereUuid('item_category')->name('item-categories.show');
	            Route::get('/{company}/item-categories/{item_category}/edit', [ItemCategoryController::class, 'edit'])->whereUuid('item_category')->name('item-categories.edit');
	            Route::put('/{company}/item-categories/{item_category}', [ItemCategoryController::class, 'update'])->whereUuid('item_category')->name('item-categories.update');
	            Route::delete('/{company}/item-categories/{item_category}', [ItemCategoryController::class, 'destroy'])->whereUuid('item_category')->name('item-categories.destroy');
	
	            // Warehouses
	            Route::get('/{company}/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
	            Route::get('/{company}/warehouses/search', [WarehouseController::class, 'search'])->name('warehouses.search');
	            Route::get('/{company}/warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
	            Route::post('/{company}/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
	            Route::get('/{company}/warehouses/{warehouse}', [WarehouseController::class, 'show'])->whereUuid('warehouse')->name('warehouses.show');
	            Route::get('/{company}/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->whereUuid('warehouse')->name('warehouses.edit');
	            Route::put('/{company}/warehouses/{warehouse}', [WarehouseController::class, 'update'])->whereUuid('warehouse')->name('warehouses.update');
	            Route::delete('/{company}/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->whereUuid('warehouse')->name('warehouses.destroy');
	
	            // Stock Management
	            Route::get('/{company}/stock', [StockController::class, 'index'])->name('stock.index');
	            Route::get('/{company}/stock/movements', [StockController::class, 'movements'])->name('stock.movements');
	            Route::get('/{company}/stock/adjustment', [StockController::class, 'createAdjustment'])->name('stock.adjustment.create');
	            Route::post('/{company}/stock/adjustment', [StockController::class, 'storeAdjustment'])->name('stock.adjustment.store');
	            Route::get('/{company}/stock/transfer', [StockController::class, 'createTransfer'])->name('stock.transfer.create');
	            Route::post('/{company}/stock/transfer', [StockController::class, 'storeTransfer'])->name('stock.transfer.store');
	            Route::get('/{company}/stock/items/{item}', [StockController::class, 'itemStock'])->whereUuid('item')->name('stock.item');
	        });

        // ─────────────────────────────────────────────────────────────────
        // Payroll & HR Module
        // ─────────────────────────────────────────────────────────────────

        // Employees
        Route::get('/{company}/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/{company}/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/{company}/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/{company}/employees/{employee}', [EmployeeController::class, 'show'])->whereUuid('employee')->name('employees.show');
        Route::get('/{company}/employees/{employee}/edit', [EmployeeController::class, 'edit'])->whereUuid('employee')->name('employees.edit');
        Route::put('/{company}/employees/{employee}', [EmployeeController::class, 'update'])->whereUuid('employee')->name('employees.update');
        Route::delete('/{company}/employees/{employee}', [EmployeeController::class, 'destroy'])->whereUuid('employee')->name('employees.destroy');

        // Earning Types
        Route::get('/{company}/earning-types', [EarningTypeController::class, 'index'])->name('earning-types.index');
        Route::get('/{company}/earning-types/create', [EarningTypeController::class, 'create'])->name('earning-types.create');
        Route::post('/{company}/earning-types', [EarningTypeController::class, 'store'])->name('earning-types.store');
        Route::get('/{company}/earning-types/{earning_type}/edit', [EarningTypeController::class, 'edit'])->whereUuid('earning_type')->name('earning-types.edit');
        Route::put('/{company}/earning-types/{earning_type}', [EarningTypeController::class, 'update'])->whereUuid('earning_type')->name('earning-types.update');
        Route::delete('/{company}/earning-types/{earning_type}', [EarningTypeController::class, 'destroy'])->whereUuid('earning_type')->name('earning-types.destroy');

        // Deduction Types
        Route::get('/{company}/deduction-types', [DeductionTypeController::class, 'index'])->name('deduction-types.index');
        Route::get('/{company}/deduction-types/create', [DeductionTypeController::class, 'create'])->name('deduction-types.create');
        Route::post('/{company}/deduction-types', [DeductionTypeController::class, 'store'])->name('deduction-types.store');
        Route::get('/{company}/deduction-types/{deduction_type}/edit', [DeductionTypeController::class, 'edit'])->whereUuid('deduction_type')->name('deduction-types.edit');
        Route::put('/{company}/deduction-types/{deduction_type}', [DeductionTypeController::class, 'update'])->whereUuid('deduction_type')->name('deduction-types.update');
        Route::delete('/{company}/deduction-types/{deduction_type}', [DeductionTypeController::class, 'destroy'])->whereUuid('deduction_type')->name('deduction-types.destroy');

        // Leave Types
        Route::get('/{company}/leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
        Route::get('/{company}/leave-types/create', [LeaveTypeController::class, 'create'])->name('leave-types.create');
        Route::post('/{company}/leave-types', [LeaveTypeController::class, 'store'])->name('leave-types.store');
        Route::get('/{company}/leave-types/{leave_type}/edit', [LeaveTypeController::class, 'edit'])->whereUuid('leave_type')->name('leave-types.edit');
        Route::put('/{company}/leave-types/{leave_type}', [LeaveTypeController::class, 'update'])->whereUuid('leave_type')->name('leave-types.update');
        Route::delete('/{company}/leave-types/{leave_type}', [LeaveTypeController::class, 'destroy'])->whereUuid('leave_type')->name('leave-types.destroy');

        // Leave Requests
        Route::get('/{company}/leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::get('/{company}/leave-requests/create', [LeaveRequestController::class, 'create'])->name('leave-requests.create');
        Route::post('/{company}/leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
        Route::get('/{company}/leave-requests/{leave_request}', [LeaveRequestController::class, 'show'])->whereUuid('leave_request')->name('leave-requests.show');
        Route::get('/{company}/leave-requests/{leave_request}/edit', [LeaveRequestController::class, 'edit'])->whereUuid('leave_request')->name('leave-requests.edit');
        Route::put('/{company}/leave-requests/{leave_request}', [LeaveRequestController::class, 'update'])->whereUuid('leave_request')->name('leave-requests.update');
        Route::post('/{company}/leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve'])->whereUuid('leave_request')->name('leave-requests.approve');
        Route::post('/{company}/leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject'])->whereUuid('leave_request')->name('leave-requests.reject');
        Route::delete('/{company}/leave-requests/{leave_request}', [LeaveRequestController::class, 'destroy'])->whereUuid('leave_request')->name('leave-requests.destroy');

        // Payroll Periods
        Route::get('/{company}/payroll-periods', [PayrollPeriodController::class, 'index'])->name('payroll-periods.index');
        Route::get('/{company}/payroll-periods/create', [PayrollPeriodController::class, 'create'])->name('payroll-periods.create');
        Route::post('/{company}/payroll-periods', [PayrollPeriodController::class, 'store'])->name('payroll-periods.store');
        Route::get('/{company}/payroll-periods/{payroll_period}', [PayrollPeriodController::class, 'show'])->whereUuid('payroll_period')->name('payroll-periods.show');
        Route::post('/{company}/payroll-periods/{payroll_period}/close', [PayrollPeriodController::class, 'close'])->whereUuid('payroll_period')->name('payroll-periods.close');
        Route::delete('/{company}/payroll-periods/{payroll_period}', [PayrollPeriodController::class, 'destroy'])->whereUuid('payroll_period')->name('payroll-periods.destroy');

        // Payslips
        Route::get('/{company}/payslips', [PayslipController::class, 'index'])->name('payslips.index');
        Route::get('/{company}/payslips/create', [PayslipController::class, 'create'])->name('payslips.create');
        Route::post('/{company}/payslips', [PayslipController::class, 'store'])->name('payslips.store');
        Route::get('/{company}/payslips/{payslip}', [PayslipController::class, 'show'])->whereUuid('payslip')->name('payslips.show');
        Route::get('/{company}/payslips/{payslip}/edit', [PayslipController::class, 'edit'])->whereUuid('payslip')->name('payslips.edit');
        Route::post('/{company}/payslips/{payslip}/approve', [PayslipController::class, 'approve'])->whereUuid('payslip')->name('payslips.approve');
        Route::post('/{company}/payslips/{payslip}/mark-paid', [PayslipController::class, 'markPaid'])->whereUuid('payslip')->name('payslips.mark-paid');
        Route::delete('/{company}/payslips/{payslip}', [PayslipController::class, 'destroy'])->whereUuid('payslip')->name('payslips.destroy');
	    });
});

// Module routes
require base_path('modules/FuelStation/Routes/fuel.php');

require __DIR__.'/settings.php';
