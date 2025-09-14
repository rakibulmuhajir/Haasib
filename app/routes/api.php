<?php

use App\Http\Controllers\Api\CurrencyApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\InvoiceApiController;
use App\Http\Controllers\Api\PaymentApiController;
use Illuminate\Support\Facades\Route;

// API Routes for Invoicing System
// All routes require authentication and company context

// Invoice Routes
Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('/', [InvoiceApiController::class, 'index'])->name('index');
    Route::post('/', [InvoiceApiController::class, 'store'])->name('store')->middleware('idempotent');
    Route::get('/{id}', [InvoiceApiController::class, 'show'])->name('show');
    Route::put('/{id}', [InvoiceApiController::class, 'update'])->name('update')->middleware('idempotent');
    Route::delete('/{id}', [InvoiceApiController::class, 'destroy'])->name('destroy')->middleware('idempotent');

    // Invoice Actions
    Route::post('/{id}/send', [InvoiceApiController::class, 'markAsSent'])->name('send')->middleware('idempotent');
    Route::post('/{id}/post', [InvoiceApiController::class, 'markAsPosted'])->name('post')->middleware('idempotent');
    Route::post('/{id}/cancel', [InvoiceApiController::class, 'cancel'])->name('cancel')->middleware('idempotent');
    Route::post('/{id}/generate-pdf', [InvoiceApiController::class, 'generatePdf'])->name('generate-pdf')->middleware('idempotent');
    Route::post('/{id}/send-email', [InvoiceApiController::class, 'sendEmail'])->name('send-email')->middleware('idempotent');
    Route::post('/{id}/duplicate', [InvoiceApiController::class, 'duplicate'])->name('duplicate')->middleware('idempotent');

    // Invoice Statistics
    Route::get('/statistics', [InvoiceApiController::class, 'statistics'])->name('statistics');

    // Bulk Operations
    Route::post('/bulk', [InvoiceApiController::class, 'bulk'])->name('bulk')->middleware('idempotent');
});

// Payment Routes
Route::prefix('payments')->name('payments.')->group(function () {
    Route::get('/', [PaymentApiController::class, 'index'])->name('index');
    Route::post('/', [PaymentApiController::class, 'store'])->name('store')->middleware('idempotent');
    Route::get('/{id}', [PaymentApiController::class, 'show'])->name('show');
    Route::put('/{id}', [PaymentApiController::class, 'update'])->name('update')->middleware('idempotent');
    Route::delete('/{id}', [PaymentApiController::class, 'destroy'])->name('destroy')->middleware('idempotent');

    // Payment Actions
    Route::post('/{id}/allocate', [PaymentApiController::class, 'allocate'])->name('allocate')->middleware('idempotent');
    Route::post('/{id}/auto-allocate', [PaymentApiController::class, 'autoAllocate'])->name('auto-allocate')->middleware('idempotent');
    Route::post('/{id}/void', [PaymentApiController::class, 'void'])->name('void')->middleware('idempotent');
    Route::post('/{id}/refund', [PaymentApiController::class, 'refund'])->name('refund')->middleware('idempotent');

    // Payment Statistics
    Route::get('/statistics', [PaymentApiController::class, 'statistics'])->name('statistics');
    Route::get('/{customerId}/summary', [PaymentApiController::class, 'customerSummary'])->name('customer-summary');
    Route::get('/{id}/allocation-suggestions', [PaymentApiController::class, 'allocationSuggestions'])->name('allocation-suggestions');

    // Bulk Operations
    Route::post('/bulk', [PaymentApiController::class, 'bulk'])->name('bulk')->middleware('idempotent');
});

// Currency Routes
Route::prefix('currencies')->name('currencies.')->group(function () {
    Route::get('/company', [CurrencyApiController::class, 'companyCurrencies'])->name('company');
    Route::get('/available', [CurrencyApiController::class, 'availableCurrencies'])->name('available');
    Route::get('/exchange-rate', [CurrencyApiController::class, 'exchangeRate'])->name('exchange-rate');
    Route::post('/convert', [CurrencyApiController::class, 'convert'])->name('convert');
    Route::post('/exchange-rate', [CurrencyApiController::class, 'updateExchangeRate'])->name('update-exchange-rate')->middleware('idempotent');
    Route::post('/enable', [CurrencyApiController::class, 'enableCurrency'])->name('enable')->middleware('idempotent');
    Route::post('/disable', [CurrencyApiController::class, 'disableCurrency'])->name('disable')->middleware('idempotent');
    Route::get('/exchange-rate-history', [CurrencyApiController::class, 'exchangeRateHistory'])->name('exchange-rate-history');
    Route::get('/latest-exchange-rates', [CurrencyApiController::class, 'latestExchangeRates'])->name('latest-exchange-rates');
    Route::get('/balances', [CurrencyApiController::class, 'currencyBalances'])->name('balances');
    Route::post('/currency-impact', [CurrencyApiController::class, 'currencyImpact'])->name('currency-impact');
    Route::post('/sync-exchange-rates', [CurrencyApiController::class, 'syncExchangeRates'])->name('sync-exchange-rates')->middleware('idempotent');
    Route::get('/symbol', [CurrencyApiController::class, 'currencySymbol'])->name('symbol');
    Route::post('/format-money', [CurrencyApiController::class, 'formatMoney'])->name('format-money');
});

// Customer Routes
Route::prefix('customers')->name('customers.')->group(function () {
    Route::get('/', [CustomerApiController::class, 'index'])->name('index');
    Route::post('/', [CustomerApiController::class, 'store'])->name('store')->middleware('idempotent');
    Route::get('/{id}', [CustomerApiController::class, 'show'])->name('show');
    Route::put('/{id}', [CustomerApiController::class, 'update'])->name('update')->middleware('idempotent');
    Route::delete('/{id}', [CustomerApiController::class, 'destroy'])->name('destroy')->middleware('idempotent');

    // Customer Relations
    Route::get('/{id}/invoices', [CustomerApiController::class, 'invoices'])->name('invoices');
    Route::get('/{id}/payments', [CustomerApiController::class, 'payments'])->name('payments');
    Route::get('/{id}/statement', [CustomerApiController::class, 'statement'])->name('statement');
    Route::get('/{id}/statistics', [CustomerApiController::class, 'statistics'])->name('statistics');

    // Customer Search
    Route::get('/search', [CustomerApiController::class, 'search'])->name('search');

    // Bulk Operations
    Route::post('/bulk', [CustomerApiController::class, 'bulk'])->name('bulk')->middleware('idempotent');
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()->toISOString(),
    ]);
})->name('health');
