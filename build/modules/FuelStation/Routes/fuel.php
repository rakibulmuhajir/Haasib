<?php

use App\Modules\FuelStation\Http\Controllers\AmanatController;
use App\Modules\FuelStation\Http\Controllers\AttendantHandoverController;
use App\Modules\FuelStation\Http\Controllers\FuelDashboardController;
use App\Modules\FuelStation\Http\Controllers\FuelSaleController;
use App\Modules\FuelStation\Http\Controllers\FuelStationOnboardingController;
use App\Modules\FuelStation\Http\Controllers\InvestorController;
use App\Modules\FuelStation\Http\Controllers\ParcoSettlementController;
use App\Modules\FuelStation\Http\Controllers\PumpController;
use App\Modules\FuelStation\Http\Controllers\PumpReadingController;
use App\Modules\FuelStation\Http\Controllers\RateChangeController;
use App\Modules\FuelStation\Http\Controllers\TankReadingController;
use App\Modules\FuelStation\Http\Controllers\ShiftCloseController;
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
    Route::post('onboarding/accounts', [FuelStationOnboardingController::class, 'setupAccounts'])->name('fuel.onboarding.accounts');
    Route::post('onboarding/fuel-items', [FuelStationOnboardingController::class, 'setupFuelItems'])->name('fuel.onboarding.fuel-items');
    Route::post('onboarding/complete', [FuelStationOnboardingController::class, 'complete'])->name('fuel.onboarding.complete');

    // Dashboard
    Route::get('dashboard', [FuelDashboardController::class, 'index'])->name('fuel.dashboard');

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

    // Shift Close (Daily Profit Posting)
    Route::get('shift-close', [ShiftCloseController::class, 'create'])->name('fuel.shift-close.create');
    Route::post('shift-close', [ShiftCloseController::class, 'store'])->name('fuel.shift-close.store');

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

    // Parco Settlement
    Route::get('parco/pending', [ParcoSettlementController::class, 'pending'])->name('fuel.parco.pending');
    Route::post('parco/settle', [ParcoSettlementController::class, 'settle'])->name('fuel.parco.settle');
});
