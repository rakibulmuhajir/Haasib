<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompaniesPageController;
use App\Http\Controllers\UsersPageController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // Non-scoped company routes (creation and switching)
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::post('/companies/switch', [CompaniesPageController::class, 'switch'])->name('companies.switch');
    Route::get('/companies', [CompaniesPageController::class, 'index'])->name('companies.index');
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');

    // Company-scoped routes
    Route::middleware(['identify.company'])->group(function () {
        Route::get('/{company}', [CompanyController::class, 'show'])->name('company.show');
        Route::put('/{company}', [CompanyController::class, 'update'])->name('company.update');
        Route::get('/{company}/users', [UsersPageController::class, 'index'])->name('users.index');
        Route::post('/{company}/users/invite', [UsersPageController::class, 'invite'])->name('users.invite');
        Route::put('/{company}/users/{user}/role', [UsersPageController::class, 'updateRole'])->name('users.update-role');
        Route::delete('/{company}/users/{user}', [UsersPageController::class, 'remove'])->name('users.remove');
    });
});

require __DIR__.'/settings.php';
