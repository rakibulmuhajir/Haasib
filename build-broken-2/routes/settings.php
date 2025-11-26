<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\CurrencySettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/currencies', [CurrencySettingsController::class, 'index'])->name('currency.index');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});

Route::middleware('auth')->group(function () {
    Route::apiResource('api/currencies', CurrencySettingsController::class);
    Route::post('api/currencies/{companyCurrency}/set-base', [CurrencySettingsController::class, 'setBaseCurrency'])
        ->name('currencies.set-base');
    Route::post('api/currencies/{companyCurrency}/exchange-rate', [CurrencySettingsController::class, 'updateExchangeRate'])
        ->name('currencies.update-exchange-rate');
    Route::post('api/settings/currencies/toggle-multi-currency', [CurrencySettingsController::class, 'toggleMultiCurrency'])
        ->name('currencies.toggle-multi-currency');
});
