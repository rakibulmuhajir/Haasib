<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\Api\CustomerController;

Route::prefix('customers')->group(function () {
    Route::get('/', [CustomerController::class, 'index']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::get('/{customer}', [CustomerController::class, 'show']);
    Route::put('/{customer}', [CustomerController::class, 'update']);
    Route::delete('/{customer}', [CustomerController::class, 'destroy']);
    Route::post('/{customer}/status', [CustomerController::class, 'changeStatus']);
    
    // Contacts
    Route::get('/{customer}/contacts', [CustomerController::class, 'contactsIndex']);
    Route::post('/{customer}/contacts', [CustomerController::class, 'contactsStore']);
    Route::put('/{customer}/contacts/{contact}', [CustomerController::class, 'contactsUpdate']);
    Route::delete('/{customer}/contacts/{contact}', [CustomerController::class, 'contactsDestroy']);
    
    // Addresses
    Route::get('/{customer}/addresses', [CustomerController::class, 'addressesIndex']);
    Route::post('/{customer}/addresses', [CustomerController::class, 'addressesStore']);
    Route::put('/{customer}/addresses/{address}', [CustomerController::class, 'addressesUpdate']);
    Route::delete('/{customer}/addresses/{address}', [CustomerController::class, 'addressesDestroy']);
    
    // Credit limits
    Route::get('/{customer}/credit-limit', [CustomerController::class, 'creditLimit']);
    Route::post('/{customer}/credit-limit/adjust', [CustomerController::class, 'adjustCreditLimit']);
    Route::get('/{customer}/credit-history', [CustomerController::class, 'creditHistory']);
    
    // Statements
    Route::get('/{customer}/statements', [CustomerController::class, 'statements']);
    Route::post('/{customer}/statements/generate', [CustomerController::class, 'generateStatement']);
    Route::get('/{customer}/statements/{statement}/download', [CustomerController::class, 'downloadStatement']);
    Route::post('/{customer}/statements/{statement}/email', [CustomerController::class, 'emailStatement']);
    Route::delete('/{customer}/statements/{statement}', [CustomerController::class, 'deleteStatement']);
    
    // Aging
    Route::get('/{customer}/aging', [CustomerController::class, 'aging']);
    Route::post('/{customer}/aging/refresh', [CustomerController::class, 'refreshAging']);
    
    // Communications
    Route::get('/{customer}/communications', [CustomerController::class, 'communications']);
    Route::post('/{customer}/communications', [CustomerController::class, 'logCommunication']);
    Route::delete('/{customer}/communications/{communication}', [CustomerController::class, 'deleteCommunication']);
    
    // Import/Export
    Route::post('/import', [CustomerController::class, 'import']);
    Route::post('/export', [CustomerController::class, 'export']);
    Route::get('/exports/history', [CustomerController::class, 'exportHistory']);
});
