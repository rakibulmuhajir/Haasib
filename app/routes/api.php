<?php

use App\Http\Controllers\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/me/companies', [MeController::class, 'companies']);
    Route::post('/me/companies/switch', [MeController::class, 'switch']);
});
