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

Route::prefix('/acct')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'module' => 'Acct',
            'version' => '1.0.0',
        ]);
    });
});
