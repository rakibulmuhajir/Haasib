<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AuthController;
use Modules\Accounting\Http\Controllers\CompanyController;
// use Modules\Accounting\Http\Controllers\ModuleController; // Disabled - conflicts with main ModuleController

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => ['api']], function () {
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::get('me', [AuthController::class, 'me']);
            Route::put('profile', [AuthController::class, 'updateProfile']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
            Route::post('switch-company/{companyId}', [AuthController::class, 'switchCompany']);

            // API tokens
            Route::get('tokens', [AuthController::class, 'tokens']);
            Route::delete('tokens/{tokenId}', [AuthController::class, 'revokeToken']);

            // Sessions
            Route::get('sessions', [AuthController::class, 'sessions']);
            Route::delete('sessions/revoke-others', [AuthController::class, 'revokeOtherSessions']);
        });
    });

    // Company routes - DISABLED to avoid conflicts with main CompanyController
    /*
    Route::middleware('auth:sanctum')->prefix('companies')->group(function () {
        Route::get('/', [CompanyController::class, 'index']);
        Route::post('/', [CompanyController::class, 'store']);
        Route::get('search', [CompanyController::class, 'search']);

        Route::prefix('{companyId}')->group(function () {
            Route::get('/', [CompanyController::class, 'show']);
            Route::put('/', [CompanyController::class, 'update']);
            Route::post('deactivate', [CompanyController::class, 'deactivate']);
            Route::post('reactivate', [CompanyController::class, 'reactivate']);

            // Company users
            Route::get('users', [CompanyController::class, 'users']);
            Route::post('invite', [CompanyController::class, 'inviteUser']);
            Route::delete('users/{userId}', [CompanyController::class, 'removeUser']);
            Route::put('users/{userId}/role', [CompanyController::class, 'changeUserRole']);
            Route::post('transfer-ownership', [CompanyController::class, 'transferOwnership']);

            // Company settings
            Route::get('settings', [CompanyController::class, 'getSettings']);
            Route::put('settings', [CompanyController::class, 'updateSettings']);

            // Company statistics
            Route::get('statistics', [CompanyController::class, 'statistics']);
        });
    });
    */

    // Module routes - DISABLED to avoid conflicts with main ModuleController
    /*
    Route::middleware('auth:sanctum')->prefix('modules')->group(function () {
        Route::get('/', [ModuleController::class, 'index']);
        Route::get('popular', [ModuleController::class, 'popular']);
        Route::get('company-status', [ModuleController::class, 'companyStatus']);
        Route::get('company-status/{companyId}', [ModuleController::class, 'companyStatus']);
        Route::post('enable-multiple', [ModuleController::class, 'enableMultiple']);
        Route::get('export', [ModuleController::class, 'export']);

        // Super admin only
        Route::middleware('superadmin')->group(function () {
            Route::get('usage-stats', [ModuleController::class, 'usageStats']);
            Route::get('usage-stats/{moduleId}', [ModuleController::class, 'usageStats']);
        });

        Route::prefix('{moduleId}')->group(function () {
            Route::get('/', [ModuleController::class, 'show']);
            Route::post('enable', [ModuleController::class, 'enable']);
            Route::post('disable', [ModuleController::class, 'disable']);
            Route::post('toggle', [ModuleController::class, 'toggle']);
            Route::put('settings', [ModuleController::class, 'updateSettings']);
            Route::get('compatibility', [ModuleController::class, 'checkCompatibility']);
        });
    });
    */

    // Context routes
    Route::middleware('auth:sanctum')->prefix('context')->group(function () {
        Route::get('/', function () {
            $contextService = app(\App\Services\ContextService::class);

            return response()->json([
                'success' => true,
                'data' => $contextService->getAPIContext(),
            ]);
        });
    });
});
