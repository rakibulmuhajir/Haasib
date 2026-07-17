<?php

use App\Modules\Umrah\Http\Controllers\AgentController;
use App\Modules\Umrah\Http\Controllers\DashboardController;
use App\Modules\Umrah\Http\Controllers\DriverController;
use App\Modules\Umrah\Http\Controllers\ExpenseController;
use App\Modules\Umrah\Http\Controllers\HotelController;
use App\Modules\Umrah\Http\Controllers\PaymentController;
use App\Modules\Umrah\Http\Controllers\ReportController;
use App\Modules\Umrah\Http\Controllers\TransportServiceController;
use App\Modules\Umrah\Http\Controllers\VisaGroupController;
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
        Route::post('agents/quick-store', [AgentController::class, 'quickStore'])->name('umrah.agents.quick-store');
        Route::get('agents/{agent}/edit', [AgentController::class, 'edit'])->whereUuid('agent')->name('umrah.agents.edit');
        Route::put('agents/{agent}', [AgentController::class, 'update'])->whereUuid('agent')->name('umrah.agents.update');
        Route::put('agents/{agent}/voucher-access', [AgentController::class, 'updateVoucherAccess'])->whereUuid('agent')->name('umrah.agents.voucher-access.update');
        Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->whereUuid('agent')->name('umrah.agents.destroy');
        Route::get('agents/{agent}', [AgentController::class, 'show'])->whereUuid('agent')->name('umrah.agents.show');

        Route::get('vendors', [VisaVendorController::class, 'index'])->name('umrah.vendors.index');
        Route::post('vendors', [VisaVendorController::class, 'store'])->name('umrah.vendors.store');
        Route::post('vendors/quick-store', [VisaVendorController::class, 'quickStore'])->name('umrah.vendors.quick-store');
        Route::get('vendors/{vendor}/statement.pdf', [VisaVendorController::class, 'statementPdf'])->whereUuid('vendor')->name('umrah.vendors.statement.pdf');
        Route::get('vendors/{vendor}', [VisaVendorController::class, 'show'])->whereUuid('vendor')->name('umrah.vendors.show');
        Route::put('vendors/{vendor}', [VisaVendorController::class, 'update'])->whereUuid('vendor')->name('umrah.vendors.update');
        Route::patch('vendors/{vendor}/status', [VisaVendorController::class, 'updateStatus'])->whereUuid('vendor')->name('umrah.vendors.status.update');

        Route::get('groups', [VisaGroupController::class, 'index'])->name('umrah.groups.index');
        Route::get('payments', [PaymentController::class, 'index'])->name('umrah.payments.index');
        Route::get('payments/create', [PaymentController::class, 'create'])->name('umrah.payments.create');
        Route::post('payments', [PaymentController::class, 'store'])->name('umrah.payments.store');
        Route::get('payments/{payment}/pdf', [PaymentController::class, 'pdf'])->whereUuid('payment')->name('umrah.payments.pdf');
        Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->whereUuid('payment')->name('umrah.payments.reverse');
        Route::post('payments/{payment}/allocations', [PaymentController::class, 'allocate'])->whereUuid('payment')->name('umrah.payments.allocations.store');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->whereUuid('payment')->name('umrah.payments.show');
        Route::get('expenses', [ExpenseController::class, 'index'])->name('umrah.expenses.index');
        Route::get('groups/create', [VisaGroupController::class, 'create'])->name('umrah.groups.create');
        Route::post('groups/import-mutamers', [VisaGroupController::class, 'importMutamers'])->name('umrah.groups.import-mutamers');
        Route::post('groups', [VisaGroupController::class, 'store'])->name('umrah.groups.store');
        Route::get('groups/{group}/edit', [VisaGroupController::class, 'edit'])->whereUuid('group')->name('umrah.groups.edit');
        Route::put('groups/{group}', [VisaGroupController::class, 'update'])->whereUuid('group')->name('umrah.groups.update');
        Route::get('groups/{group}', [VisaGroupController::class, 'show'])->whereUuid('group')->name('umrah.groups.show');
        Route::post('groups/{group}/passengers', [VisaGroupController::class, 'addPassenger'])->whereUuid('group')->name('umrah.groups.passengers.store');
        Route::put('groups/{group}/passengers/{passenger}', [VisaGroupController::class, 'updatePassenger'])->whereUuid('group')->whereUuid('passenger')->name('umrah.groups.passengers.update');
        Route::delete('groups/{group}/passengers/{passenger}', [VisaGroupController::class, 'removePassenger'])->whereUuid('group')->whereUuid('passenger')->name('umrah.groups.passengers.destroy');
        Route::put('groups/{group}/passengers/status', [VisaGroupController::class, 'bulkUpdatePassengerStatus'])->whereUuid('group')->name('umrah.groups.passengers.status.bulk');
        Route::put('groups/{group}/passengers/{passenger}/status', [VisaGroupController::class, 'updatePassengerStatus'])->whereUuid('group')->whereUuid('passenger')->name('umrah.groups.passengers.status.update');
        Route::post('groups/{group}/payments', [VisaGroupController::class, 'addPayment'])->whereUuid('group')->name('umrah.groups.payments.store');

        Route::get('vouchers', [VoucherController::class, 'index'])->name('umrah.vouchers.index');
        Route::get('vouchers/create', [VoucherController::class, 'create'])->name('umrah.vouchers.create');
        Route::post('vouchers', [VoucherController::class, 'store'])->name('umrah.vouchers.store');
        Route::get('vouchers/{voucher}/edit', [VoucherController::class, 'edit'])->whereUuid('voucher')->name('umrah.vouchers.edit');
        Route::put('vouchers/{voucher}', [VoucherController::class, 'update'])->whereUuid('voucher')->name('umrah.vouchers.update');
        Route::delete('vouchers/{voucher}', [VoucherController::class, 'destroy'])->whereUuid('voucher')->name('umrah.vouchers.destroy');
        Route::post('vouchers/{voucher}/amend', [VoucherController::class, 'amend'])->whereUuid('voucher')->name('umrah.vouchers.amend');
        Route::post('vouchers/{voucher}/cancel', [VoucherController::class, 'cancel'])->whereUuid('voucher')->name('umrah.vouchers.cancel');
        Route::post('vouchers/{voucher}/passengers/move', [VoucherController::class, 'movePassengers'])->whereUuid('voucher')->name('umrah.vouchers.passengers.move');
        Route::post('vouchers/{voucher}/passengers/separate', [VoucherController::class, 'separatePassengers'])->whereUuid('voucher')->name('umrah.vouchers.passengers.separate');
        Route::get('vouchers/{voucher}/pdf', [VoucherController::class, 'pdf'])->whereUuid('voucher')->name('umrah.vouchers.pdf');
        Route::get('vouchers/{voucher}', [VoucherController::class, 'show'])->whereUuid('voucher')->name('umrah.vouchers.show');
        Route::post('vouchers/{voucher}/approve', [VoucherController::class, 'approve'])->whereUuid('voucher')->name('umrah.vouchers.approve');

        Route::get('settings/drivers', [DriverController::class, 'index'])->name('umrah.drivers.index');
        Route::post('settings/drivers', [DriverController::class, 'store'])->name('umrah.drivers.store');
        Route::put('settings/drivers/{driver}', [DriverController::class, 'update'])->whereUuid('driver')->name('umrah.drivers.update');
        Route::patch('settings/drivers/{driver}/status', [DriverController::class, 'updateStatus'])->whereUuid('driver')->name('umrah.drivers.status.update');
        Route::get('settings/transport-services', [TransportServiceController::class, 'index'])->name('umrah.transport-services.index');
        Route::post('settings/transport-services', [TransportServiceController::class, 'store'])->name('umrah.transport-services.store');
        Route::put('settings/transport-services/{transportService}', [TransportServiceController::class, 'update'])->whereUuid('transportService')->name('umrah.transport-services.update');
        Route::patch('settings/transport-services/{transportService}/status', [TransportServiceController::class, 'updateStatus'])->whereUuid('transportService')->name('umrah.transport-services.status.update');
        Route::post('settings/transport-sectors', [TransportServiceController::class, 'storeSector'])->name('umrah.transport-sectors.store');
        Route::put('settings/transport-sectors/{sector}', [TransportServiceController::class, 'updateSector'])->whereUuid('sector')->name('umrah.transport-sectors.update');
        Route::patch('settings/transport-sectors/{sector}/status', [TransportServiceController::class, 'updateSectorStatus'])->whereUuid('sector')->name('umrah.transport-sectors.status.update');
        Route::post('settings/transport-packages', [TransportServiceController::class, 'storePackage'])->name('umrah.transport-packages.store');
        Route::put('settings/transport-packages/{package}', [TransportServiceController::class, 'updatePackage'])->whereUuid('package')->name('umrah.transport-packages.update');
        Route::patch('settings/transport-packages/{package}/status', [TransportServiceController::class, 'updatePackageStatus'])->whereUuid('package')->name('umrah.transport-packages.status.update');
        Route::post('settings/transport-fares', [TransportServiceController::class, 'storeFare'])->name('umrah.transport-fares.store');
        Route::put('settings/transport-fares/{fare}', [TransportServiceController::class, 'updateFare'])->whereUuid('fare')->name('umrah.transport-fares.update');
        Route::patch('settings/transport-fares/{fare}/status', [TransportServiceController::class, 'updateFareStatus'])->whereUuid('fare')->name('umrah.transport-fares.status.update');
        Route::get('settings/hotels', [HotelController::class, 'index'])->name('umrah.hotels.index');
        Route::get('settings/hotels/create', [HotelController::class, 'create'])->name('umrah.hotels.create');
        Route::post('settings/hotels', [HotelController::class, 'store'])->name('umrah.hotels.store');
        Route::get('settings/hotels/{hotel}/edit', [HotelController::class, 'edit'])->whereUuid('hotel')->name('umrah.hotels.edit');
        Route::put('settings/hotels/{hotel}', [HotelController::class, 'update'])->whereUuid('hotel')->name('umrah.hotels.update');
        Route::patch('settings/hotels/{hotel}/status', [HotelController::class, 'updateStatus'])->whereUuid('hotel')->name('umrah.hotels.status.update');
        Route::get('settings/hotel-vendors/create', [HotelController::class, 'createVendor'])->name('umrah.hotel-vendors.create');
        Route::post('settings/hotel-vendors', [HotelController::class, 'storeVendor'])->name('umrah.hotel-vendors.store');
        Route::get('settings/hotel-vendors/{hotelVendor}/edit', [HotelController::class, 'editVendor'])->whereUuid('hotelVendor')->name('umrah.hotel-vendors.edit');
        Route::put('settings/hotel-vendors/{hotelVendor}', [HotelController::class, 'updateVendor'])->whereUuid('hotelVendor')->name('umrah.hotel-vendors.update');
        Route::patch('settings/hotel-vendors/{hotelVendor}/status', [HotelController::class, 'updateVendorStatus'])->whereUuid('hotelVendor')->name('umrah.hotel-vendors.status.update');

        Route::get('reports/earnings', [ReportController::class, 'earnings'])->name('umrah.reports.earnings');
        Route::get('reports/{report}/pdf', [ReportController::class, 'pdf'])->name('umrah.reports.pdf');
        Route::get('reports/{report}', [ReportController::class, 'show'])->name('umrah.reports.show');
    });
