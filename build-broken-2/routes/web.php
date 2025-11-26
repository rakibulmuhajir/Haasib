<?php

use App\Http\Controllers\CompanyController;
use Modules\Accounting\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Companies routes
Route::middleware(['auth', 'verified'])->prefix('companies')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Companies');
    })->name('companies.index');
    Route::get('/create', function () {
        return Inertia::render('Companies/Create');
    })->name('companies.create');
});


// Accounting Customers routes
Route::middleware(['auth', 'verified', 'company.selected'])->prefix('accounting/customers')->group(function () {
    Route::get('/', [CustomerController::class, 'index'])->name('accounting.customers.index');
    Route::post('/', [CustomerController::class, 'store'])->name('accounting.customers.store');
    Route::get('/search', [CustomerController::class, 'search'])->name('accounting.customers.search');
    Route::get('/{customer}', [CustomerController::class, 'show'])->name('accounting.customers.show');
    Route::put('/{customer}', [CustomerController::class, 'update'])->name('accounting.customers.update');
    Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('accounting.customers.destroy');
});

// Company management routes
Route::middleware(['auth'])->group(function () {
    Route::post('/company/{company}/switch', [CompanyController::class, 'switch'])->name('company.switch');
    Route::post('/company/set-first', [CompanyController::class, 'setFirstCompany'])->name('company.set-first');
    Route::post('/company/clear-context', [CompanyController::class, 'clearContext'])->name('company.clear-context');
    Route::get('/company/status', [CompanyController::class, 'status'])->name('company.status');
});

require __DIR__.'/settings.php';
