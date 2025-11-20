<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Acct Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your module. These routes
| are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group.
|
*/

Route::prefix('/acct')->group(function () {
    Route::get('/', function () {
        return view('acct::index');
    })->name('acct.index');
});
