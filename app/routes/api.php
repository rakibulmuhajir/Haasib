<?php

use App\Http\Controllers\MeController;
use App\Http\Controllers\UserLookupController;
use App\Http\Controllers\CompanyLookupController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/me/companies', [MeController::class, 'companies']);
    Route::post('/me/companies/switch', [MeController::class, 'switch']);

    // Lookups
    Route::get('/users/suggest', [UserLookupController::class, 'suggest']);
    Route::get('/companies', [CompanyLookupController::class, 'index']);
    Route::get('/companies/{company}/users', [CompanyLookupController::class, 'users']);
});

