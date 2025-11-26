<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\InvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
| For mobile app: Use Sanctum tokens for authentication.
|
*/

// Public auth routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // User info
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Companies (no company context)
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/companies', [CompanyController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Company-Scoped API Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('identify.company')
        ->prefix('companies/{company}')
        ->group(function () {

            Route::get('/', [CompanyController::class, 'show']);

            // Invoices
            Route::apiResource('invoices', InvoiceController::class);

            Route::post('/invoices/{invoice}/approve', [InvoiceController::class, 'approve']);
            Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void']);

            // Add more API resources...
        });
});
