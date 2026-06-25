<?php

use App\Modules\Umrah\Http\Controllers\AgentController;
use App\Modules\Umrah\Http\Controllers\DashboardController;
use App\Modules\Umrah\Http\Controllers\DriverController;
use App\Modules\Umrah\Http\Controllers\ReportController;
use App\Modules\Umrah\Http\Controllers\TransportServiceController;
use App\Modules\Umrah\Http\Controllers\VisaGroupController;
use App\Modules\Umrah\Http\Controllers\VisaServiceController;
use App\Modules\Umrah\Http\Controllers\VisaVendorController;
use App\Modules\Umrah\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'identify.company', 'require.module:umrah'])
    ->prefix('{company}/umrah')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('umrah.dashboard');

        Route::get('agents', [AgentController::class, 'index'])->name('umrah.agents.index');
        Route::get('agents/create', [AgentController::class, 'create'])->name('umrah.agents.create');
        Route::post('agents', [AgentController::class, 'store'])->name('umrah.agents.store');
        Route::get('agents/{agent}/edit', [AgentController::class, 'edit'])->whereUuid('agent')->name('umrah.agents.edit');
        Route::put('agents/{agent}', [AgentController::class, 'update'])->whereUuid('agent')->name('umrah.agents.update');
        Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->whereUuid('agent')->name('umrah.agents.destroy');
        Route::get('agents/{agent}', [AgentController::class, 'show'])->whereUuid('agent')->name('umrah.agents.show');

        Route::get('vendors', [VisaVendorController::class, 'index'])->name('umrah.vendors.index');
        Route::post('vendors', [VisaVendorController::class, 'store'])->name('umrah.vendors.store');

        Route::get('groups', [VisaGroupController::class, 'index'])->name('umrah.groups.index');
        Route::get('groups/create', [VisaGroupController::class, 'create'])->name('umrah.groups.create');
        Route::post('groups', [VisaGroupController::class, 'store'])->name('umrah.groups.store');
        Route::get('groups/{group}', [VisaGroupController::class, 'show'])->whereUuid('group')->name('umrah.groups.show');
        Route::post('groups/{group}/passengers', [VisaGroupController::class, 'addPassenger'])->whereUuid('group')->name('umrah.groups.passengers.store');
        Route::put('groups/{group}/passengers/status', [VisaGroupController::class, 'bulkUpdatePassengerStatus'])->whereUuid('group')->name('umrah.groups.passengers.status.bulk');
        Route::put('groups/{group}/passengers/{passenger}/status', [VisaGroupController::class, 'updatePassengerStatus'])->whereUuid('group')->whereUuid('passenger')->name('umrah.groups.passengers.status.update');
        Route::post('groups/{group}/payments', [VisaGroupController::class, 'addPayment'])->whereUuid('group')->name('umrah.groups.payments.store');

        Route::get('vouchers', [VoucherController::class, 'index'])->name('umrah.vouchers.index');
        Route::get('vouchers/create', [VoucherController::class, 'create'])->name('umrah.vouchers.create');
        Route::post('vouchers', [VoucherController::class, 'store'])->name('umrah.vouchers.store');
        Route::get('vouchers/{voucher}', [VoucherController::class, 'show'])->whereUuid('voucher')->name('umrah.vouchers.show');

        Route::get('settings/visa-services', [VisaServiceController::class, 'index'])->name('umrah.visa-services.index');
        Route::post('settings/visa-services', [VisaServiceController::class, 'store'])->name('umrah.visa-services.store');
        Route::put('settings/visa-services/{visaService}', [VisaServiceController::class, 'update'])->whereUuid('visaService')->name('umrah.visa-services.update');
        Route::delete('settings/visa-services/{visaService}', [VisaServiceController::class, 'destroy'])->whereUuid('visaService')->name('umrah.visa-services.destroy');
        Route::get('settings/drivers', [DriverController::class, 'index'])->name('umrah.drivers.index');
        Route::post('settings/drivers', [DriverController::class, 'store'])->name('umrah.drivers.store');
        Route::delete('settings/drivers/{driver}', [DriverController::class, 'destroy'])->whereUuid('driver')->name('umrah.drivers.destroy');
        Route::get('settings/transport-services', [TransportServiceController::class, 'index'])->name('umrah.transport-services.index');
        Route::post('settings/transport-services', [TransportServiceController::class, 'store'])->name('umrah.transport-services.store');
        Route::put('settings/transport-services/{transportService}', [TransportServiceController::class, 'update'])->whereUuid('transportService')->name('umrah.transport-services.update');
        Route::delete('settings/transport-services/{transportService}', [TransportServiceController::class, 'destroy'])->whereUuid('transportService')->name('umrah.transport-services.destroy');

        Route::get('reports/earnings', [ReportController::class, 'earnings'])->name('umrah.reports.earnings');
    });
