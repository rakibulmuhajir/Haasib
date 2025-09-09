<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // API Routes for SPA lookups
    Route::get('/web/users/suggest', [\App\Http\Controllers\UserLookupController::class, 'suggest']);
    Route::get('/web/users/{user}', [\App\Http\Controllers\UserLookupController::class, 'show']);
    Route::get('/web/companies', [\App\Http\Controllers\CompanyLookupController::class, 'index']);
    Route::get('/web/companies/{company}/users', [\App\Http\Controllers\CompanyLookupController::class, 'users']);
    Route::get('/web/companies/{company}', [\App\Http\Controllers\CompanyLookupController::class, 'show']);
    
    // Reference data lookups for pickers
    Route::get('/web/countries/suggest', [\App\Http\Controllers\CountryLookupController::class, 'suggest']);
    Route::get('/web/languages/suggest', [\App\Http\Controllers\LanguageLookupController::class, 'suggest']);
    Route::get('/web/currencies/suggest', [\App\Http\Controllers\CurrencyLookupController::class, 'suggest']);
    Route::get('/web/locales/suggest', [\App\Http\Controllers\LocaleLookupController::class, 'suggest']);
    
    // Admin Routes
    Route::prefix('admin')->name('admin.')->group(function () {
        // Companies Routes
        Route::prefix('companies')->name('companies.')->group(function () {
            Route::get('/', function () {
                return Inertia::render('Admin/Companies/Index');
            })->name('index');
            
            Route::get('/create', function () {
                return Inertia::render('Admin/Companies/Create');
            })->name('create');
            
            Route::get('/{company}', function ($company) {
                return Inertia::render('Admin/Companies/Show', ['company' => $company]);
            })->name('show');
        });
        
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
