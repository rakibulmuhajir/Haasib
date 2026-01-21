<?php

use App\Modules\FuelStation\Http\Controllers\AmanatController;
use App\Modules\FuelStation\Http\Controllers\CollectionController;
use App\Modules\FuelStation\Http\Controllers\CreditCustomerController;
use App\Modules\FuelStation\Http\Controllers\CreditSaleController;
use App\Modules\FuelStation\Http\Controllers\FuelReceiptController;
use App\Modules\FuelStation\Http\Controllers\AttendantHandoverController;
use App\Modules\FuelStation\Http\Controllers\FuelDashboardController;
use App\Modules\FuelStation\Http\Controllers\FuelProductSetupController;
use App\Modules\FuelStation\Http\Controllers\FuelTankQuickCreateController;
use App\Modules\FuelStation\Http\Controllers\FuelSaleController;
use App\Modules\FuelStation\Http\Controllers\FuelStationOnboardingController;
use App\Modules\FuelStation\Http\Controllers\InvestorController;
use App\Modules\FuelStation\Http\Controllers\VendorCardSettlementController;
use App\Modules\FuelStation\Http\Controllers\PumpController;
use App\Modules\FuelStation\Http\Controllers\PumpReadingController;
use App\Modules\FuelStation\Http\Controllers\RateChangeController;
use App\Modules\FuelStation\Http\Controllers\ShrinkageReportController;
use App\Modules\FuelStation\Http\Controllers\StationSettingsController;
use App\Modules\FuelStation\Http\Controllers\TankReadingController;
use App\Modules\FuelStation\Http\Controllers\DailyCloseController;
use App\Modules\FuelStation\Http\Controllers\SalesReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fuel Station Routes
|--------------------------------------------------------------------------
|
| All routes require authentication, company context, and fuel_station industry.
| Prefix: /{company}/fuel
|
*/

Route::middleware(['auth', 'identify.company', 'require.module:fuel_station'])->prefix('{company}/fuel')->group(function () {

    // Onboarding (setup wizard)
    Route::get('onboarding', [FuelStationOnboardingController::class, 'index'])->name('fuel.onboarding');
    Route::get('onboarding/status', [FuelStationOnboardingController::class, 'status'])->name('fuel.onboarding.status');
    Route::post('onboarding/station-settings', [FuelStationOnboardingController::class, 'setupStationSettings'])->name('fuel.onboarding.station-settings');
    Route::post('onboarding/accounts', [FuelStationOnboardingController::class, 'setupAccounts'])->name('fuel.onboarding.accounts');
    Route::post('onboarding/fuel-items', [FuelStationOnboardingController::class, 'setupFuelItems'])->name('fuel.onboarding.fuel-items');
    Route::post('onboarding/partners', [FuelStationOnboardingController::class, 'setupPartners'])->name('fuel.onboarding.partners');
    Route::post('onboarding/employees', [FuelStationOnboardingController::class, 'setupEmployees'])->name('fuel.onboarding.employees');
    Route::post('onboarding/tanks', [FuelStationOnboardingController::class, 'setupTanks'])->name('fuel.onboarding.tanks');
    Route::post('onboarding/pumps', [FuelStationOnboardingController::class, 'setupPumps'])->name('fuel.onboarding.pumps');
    Route::post('onboarding/rates', [FuelStationOnboardingController::class, 'setupRates'])->name('fuel.onboarding.rates');
    Route::post('onboarding/lubricants', [FuelStationOnboardingController::class, 'setupLubricants'])->name('fuel.onboarding.lubricants');
    Route::post('onboarding/initial-stock', [FuelStationOnboardingController::class, 'setupInitialStock'])->name('fuel.onboarding.initial-stock');
    Route::post('onboarding/opening-cash', [FuelStationOnboardingController::class, 'setupOpeningCash'])->name('fuel.onboarding.opening-cash');
    Route::post('onboarding/complete', [FuelStationOnboardingController::class, 'complete'])->name('fuel.onboarding.complete');

    // Dashboard
    Route::get('dashboard', [FuelDashboardController::class, 'index'])->name('fuel.dashboard');

    // Product setup (dashboard quick add)
    Route::post('products/setup', [FuelProductSetupController::class, 'store'])->name('fuel.products.setup');
    Route::post('tanks/quick-create', [FuelTankQuickCreateController::class, 'store'])->name('fuel.tanks.quick-create');

    // Pumps
    Route::get('pumps', [PumpController::class, 'index'])->name('fuel.pumps.index');
    Route::post('pumps', [PumpController::class, 'store'])->name('fuel.pumps.store');
    Route::get('pumps/{pump}', [PumpController::class, 'show'])->name('fuel.pumps.show');
    Route::put('pumps/{pump}', [PumpController::class, 'update'])->name('fuel.pumps.update');
    Route::delete('pumps/{pump}', [PumpController::class, 'destroy'])->name('fuel.pumps.destroy');

    // Rate Changes
    Route::get('rates', [RateChangeController::class, 'index'])->name('fuel.rates.index');
    Route::post('rates', [RateChangeController::class, 'store'])->name('fuel.rates.store');
    Route::get('rates/current', [RateChangeController::class, 'current'])->name('fuel.rates.current');

    // Tank Readings (with workflow)
    Route::get('tank-readings', [TankReadingController::class, 'index'])->name('fuel.tank-readings.index');
    Route::post('tank-readings', [TankReadingController::class, 'store'])->name('fuel.tank-readings.store');
    Route::get('tank-readings/{tankReading}', [TankReadingController::class, 'show'])->name('fuel.tank-readings.show');
    Route::put('tank-readings/{tankReading}', [TankReadingController::class, 'update'])->name('fuel.tank-readings.update');
    Route::post('tank-readings/{tankReading}/confirm', [TankReadingController::class, 'confirm'])->name('fuel.tank-readings.confirm');
    Route::post('tank-readings/{tankReading}/post', [TankReadingController::class, 'post'])->name('fuel.tank-readings.post');

    // Pump Readings
    Route::get('pump-readings', [PumpReadingController::class, 'index'])->name('fuel.pump-readings.index');
    Route::post('pump-readings', [PumpReadingController::class, 'store'])->name('fuel.pump-readings.store');

    // Daily Close (full daily register matching manual workflow)
    Route::get('daily-close', [DailyCloseController::class, 'create'])->name('fuel.daily-close.create');
    Route::post('daily-close', [DailyCloseController::class, 'store'])->name('fuel.daily-close.store');
    Route::get('daily-close/history', [DailyCloseController::class, 'index'])->name('fuel.daily-close.index');
    Route::get('daily-close/{transaction}', [DailyCloseController::class, 'show'])->name('fuel.daily-close.show');
    Route::get('daily-close/{transaction}/amend', [DailyCloseController::class, 'amend'])->name('fuel.daily-close.amend');
    Route::post('daily-close/{transaction}/amend', [DailyCloseController::class, 'storeAmendment'])->name('fuel.daily-close.amend.store');
    Route::post('daily-close/{transaction}/lock', [DailyCloseController::class, 'lock'])->name('fuel.daily-close.lock');
    Route::post('daily-close/{transaction}/unlock', [DailyCloseController::class, 'unlock'])->name('fuel.daily-close.unlock');
    Route::post('daily-close/lock-month', [DailyCloseController::class, 'lockMonth'])->name('fuel.daily-close.lock-month');
    Route::get('daily-close/{transaction}/amendment-chain', [DailyCloseController::class, 'amendmentChain'])->name('fuel.daily-close.amendment-chain');

    // Investors
    Route::get('investors', [InvestorController::class, 'index'])->name('fuel.investors.index');
    Route::post('investors', [InvestorController::class, 'store'])->name('fuel.investors.store');
    Route::get('investors/{investor}', [InvestorController::class, 'show'])->name('fuel.investors.show');
    Route::put('investors/{investor}', [InvestorController::class, 'update'])->name('fuel.investors.update');
    Route::post('investors/{investor}/lots', [InvestorController::class, 'addLot'])->name('fuel.investors.lots.store');
    Route::post('investors/{investor}/pay-commission', [InvestorController::class, 'payCommission'])->name('fuel.investors.pay-commission');

    // Amanat (Trust Deposits)
    Route::get('amanat', [AmanatController::class, 'index'])->name('fuel.amanat.index');
    Route::get('amanat/{customer}', [AmanatController::class, 'show'])->name('fuel.amanat.show');
    Route::post('amanat/{customer}/deposit', [AmanatController::class, 'deposit'])->name('fuel.amanat.deposit');
    Route::post('amanat/{customer}/withdraw', [AmanatController::class, 'withdraw'])->name('fuel.amanat.withdraw');

    // Attendant Handovers
    Route::get('handovers', [AttendantHandoverController::class, 'index'])->name('fuel.handovers.index');
    Route::post('handovers', [AttendantHandoverController::class, 'store'])->name('fuel.handovers.store');
    Route::get('handovers/{handover}', [AttendantHandoverController::class, 'show'])->name('fuel.handovers.show');
    Route::post('handovers/{handover}/receive', [AttendantHandoverController::class, 'receive'])->name('fuel.handovers.receive');

    // Fuel Sales
    Route::post('sales', [FuelSaleController::class, 'store'])->name('fuel.sales.store');

    // Vendor Card Settlement
    Route::get('vendor-cards/pending', [VendorCardSettlementController::class, 'pending'])->name('fuel.vendor-cards.pending');
    Route::post('vendor-cards/settle', [VendorCardSettlementController::class, 'settle'])->name('fuel.vendor-cards.settle');

    // Station Settings
    Route::get('settings', [StationSettingsController::class, 'edit'])->name('fuel.settings.edit');
    Route::put('settings', [StationSettingsController::class, 'update'])->name('fuel.settings.update');

    // Fuel Receipts (tanker deliveries)
    Route::get('receipts', [FuelReceiptController::class, 'index'])->name('fuel.receipts.index');
    Route::get('receipts/create', [FuelReceiptController::class, 'create'])->name('fuel.receipts.create');
    Route::post('receipts', [FuelReceiptController::class, 'store'])->name('fuel.receipts.store');
    Route::get('receipts/{receipt}', [FuelReceiptController::class, 'show'])->name('fuel.receipts.show');

    // Reports
    Route::get('reports/sales', [SalesReportController::class, 'index'])->name('fuel.reports.sales');
    Route::get('reports/sales/export', [SalesReportController::class, 'export'])->name('fuel.reports.sales.export');
    Route::get('reports/shrinkage', [ShrinkageReportController::class, 'index'])->name('fuel.reports.shrinkage');
    Route::get('reports/shrinkage/export', [ShrinkageReportController::class, 'export'])->name('fuel.reports.shrinkage.export');

    // Credit Customers
    Route::get('credit-customers', [CreditCustomerController::class, 'index'])->name('fuel.credit-customers.index');
    Route::get('credit-customers/{customer}', [CreditCustomerController::class, 'show'])->name('fuel.credit-customers.show');
    Route::post('credit-customers/{customer}/limit', [CreditCustomerController::class, 'updateLimit'])->name('fuel.credit-customers.limit');
    Route::post('credit-customers/{customer}/toggle-block', [CreditCustomerController::class, 'toggleBlock'])->name('fuel.credit-customers.toggle-block');

    // Credit Sales
    Route::get('credit-sales', [CreditSaleController::class, 'index'])->name('fuel.credit-sales.index');

    // Collections
    Route::get('collections', [CollectionController::class, 'index'])->name('fuel.collections.index');
    Route::get('collections/create', [CollectionController::class, 'create'])->name('fuel.collections.create');
    Route::post('collections', [CollectionController::class, 'store'])->name('fuel.collections.store');
    Route::get('collections/{collection}', [CollectionController::class, 'show'])->name('fuel.collections.show');
});
