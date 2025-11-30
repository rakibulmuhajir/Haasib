<?php

use App\Http\Controllers\CommandController;
use App\Http\Controllers\PaletteSuggestionsController;
use Illuminate\Support\Facades\Route;

Route::post('/commands', CommandController::class)
    ->middleware(['web', 'auth', 'identify.company', 'throttle:commands']);

Route::get('/palette/suggestions', [PaletteSuggestionsController::class, 'index'])
    ->middleware(['web', 'auth', 'identify.company', 'throttle:60,1']);
