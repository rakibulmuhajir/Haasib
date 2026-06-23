<?php

use App\Modules\Umrah\Http\Controllers\AgentController;
use App\Modules\Umrah\Http\Controllers\DashboardController;
use App\Modules\Umrah\Http\Controllers\ReportController;
use App\Modules\Umrah\Http\Controllers\TransportServiceController;
use App\Modules\Umrah\Http\Controllers\VehicleTypeController;
use App\Modules\Umrah\Http\Controllers\VisaGroupController;
use App\Modules\Umrah\Http\Controllers\VisaServiceController;
use App\Modules\Umrah\Http\Controllers\VisaVendorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'identify.company', 'require.module:umrah'])
    ->prefix('{company}/umrah')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('umrah.dashboard');

        Route::get('agents', [AgentController::class, 'index'])->name('umrah.agents.index');
        Route::get('agents/create', [AgentController::class, 'create'])->name('umrah.agents.create');
        Route::post('agents', [AgentController::class, 'store'])->name('umrah.agents.store');
        Route::get('agents/{agent}', [AgentController::class, 'show'])->whereUuid('agent')->name('umrah.agents.show');

        Route::get('vendors', [VisaVendorController::class, 'index'])->name('umrah.vendors.index');
        Route::post('vendors', [VisaVendorController::class, 'store'])->name('umrah.vendors.store');

        Route::get('groups', [VisaGroupController::class, 'index'])->name('umrah.groups.index');
        Route::get('groups/create', [VisaGroupController::class, 'create'])->name('umrah.groups.create');
        Route::post('groups', [VisaGroupController::class, 'store'])->name('umrah.groups.store');
        Route::get('groups/{group}', [VisaGroupController::class, 'show'])->whereUuid('group')->name('umrah.groups.show');
        Route::post('groups/{group}/passengers', [VisaGroupController::class, 'addPassenger'])->whereUuid('group')->name('umrah.groups.passengers.store');
        Route::post('groups/{group}/payments', [VisaGroupController::class, 'addPayment'])->whereUuid('group')->name('umrah.groups.payments.store');

        Route::get('settings/vehicle-types', [VehicleTypeController::class, 'index'])->name('umrah.vehicle-types.index');
        Route::post('settings/vehicle-types', [VehicleTypeController::class, 'store'])->name('umrah.vehicle-types.store');
        Route::get('settings/visa-services', [VisaServiceController::class, 'index'])->name('umrah.visa-services.index');
        Route::post('settings/visa-services', [VisaServiceController::class, 'store'])->name('umrah.visa-services.store');
        Route::get('settings/transport-services', [TransportServiceController::class, 'index'])->name('umrah.transport-services.index');
        Route::post('settings/transport-services', [TransportServiceController::class, 'store'])->name('umrah.transport-services.store');

        Route::get('reports/earnings', [ReportController::class, 'earnings'])->name('umrah.reports.earnings');
    });
