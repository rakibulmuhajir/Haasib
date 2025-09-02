<?php

use App\Http\Controllers\MeController;
use App\Http\Controllers\UserLookupController;
use App\Http\Controllers\CompanyLookupController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/me/companies', [MeController::class, 'companies']);
    Route::post('/me/companies/switch', [MeController::class, 'switch']);
    Route::get('/me/invitations', [InvitationController::class, 'myInvitations']);

    // Lookups
    Route::get('/users/suggest', [UserLookupController::class, 'suggest']);
    Route::get('/companies', [CompanyLookupController::class, 'index']);
    Route::get('/companies/{company}/users', [CompanyLookupController::class, 'users']);

    // Company create + invitations
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::post('/companies/{company}/invite', [CompanyController::class, 'invite']);

    // Invitations
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);
    Route::post('/invitations/{id}/revoke', [InvitationController::class, 'revoke']);
});
