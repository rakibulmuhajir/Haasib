<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Acct API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your module. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

Route::middleware(['auth:sanctum'])->prefix('/accounting')->name('accounting.api.')->group(function () {
    Route::get('/ping', function () {
        return response()->json([
            'module' => 'Accounting',
            'version' => '1.0.0',
            'status' => 'ok',
        ]);
    })->name('ping');
});
