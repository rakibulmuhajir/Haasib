<?php

use App\Http\Controllers\Api\CurrencyApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\InvoiceApiController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\InlineEditController;
use Illuminate\Support\Facades\Route;

// API Routes for Invoicing System
// All routes require authentication and company context

// Invoice Routes
Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('/', [InvoiceApiController::class, 'index'])->name('index');
    Route::post('/', [InvoiceApiController::class, 'store'])->name('store')->middleware('idempotent');
    Route::get('/{id}', [InvoiceApiController::class, 'show'])->whereUuid('id')->name('show');
    Route::put('/{id}', [InvoiceApiController::class, 'update'])->whereUuid('id')->name('update')->middleware('idempotent');
    Route::delete('/{id}', [InvoiceApiController::class, 'destroy'])->whereUuid('id')->name('destroy')->middleware('idempotent');

    // Invoice Actions
    Route::post('/{id}/send', [InvoiceApiController::class, 'markAsSent'])->whereUuid('id')->name('send')->middleware('idempotent');
    Route::post('/{id}/post', [InvoiceApiController::class, 'markAsPosted'])->whereUuid('id')->name('post')->middleware('idempotent');
    Route::post('/{id}/cancel', [InvoiceApiController::class, 'cancel'])->whereUuid('id')->name('cancel')->middleware('idempotent');
    Route::post('/{id}/generate-pdf', [InvoiceApiController::class, 'generatePdf'])->whereUuid('id')->name('generate-pdf')->middleware('idempotent');
    Route::get('/{id}/pdf-exists', [InvoiceApiController::class, 'pdfExists'])->whereUuid('id')->name('pdf-exists');
    Route::post('/{id}/send-email', [InvoiceApiController::class, 'sendEmail'])->whereUuid('id')->name('send-email')->middleware('idempotent');
    Route::post('/{id}/duplicate', [InvoiceApiController::class, 'duplicate'])->whereUuid('id')->name('duplicate')->middleware('idempotent');

    // Invoice Statistics
    Route::get('/statistics', [InvoiceApiController::class, 'statistics'])->name('statistics');

    // Bulk Operations
    Route::post('/bulk', [InvoiceApiController::class, 'bulk'])->name('bulk')->middleware('idempotent');
});

// Payment Routes
Route::prefix('payments')->name('payments.')->group(function () {
    Route::get('/', [PaymentApiController::class, 'index'])->name('index');
    Route::post('/', [PaymentApiController::class, 'store'])->name('store')->middleware('idempotent');
    Route::get('/{id}', [PaymentApiController::class, 'show'])->whereUuid('id')->name('show');
    Route::put('/{id}', [PaymentApiController::class, 'update'])->whereUuid('id')->name('update')->middleware('idempotent');
    Route::delete('/{id}', [PaymentApiController::class, 'destroy'])->whereUuid('id')->name('destroy')->middleware('idempotent');

    // Payment Actions
    Route::post('/{id}/allocate', [PaymentApiController::class, 'allocate'])->whereUuid('id')->name('allocate')->middleware('idempotent');
    Route::post('/{id}/auto-allocate', [PaymentApiController::class, 'autoAllocate'])->whereUuid('id')->name('auto-allocate')->middleware('idempotent');
    // Payment Allocation explicit endpoints
    Route::get('/{id}/allocations', [PaymentApiController::class, 'allocations'])->whereUuid('id')->name('allocations');
    Route::post('/{id}/allocations', [PaymentApiController::class, 'allocate'])->whereUuid('id')->name('allocations.store')->middleware('idempotent');
    Route::post('/{paymentId}/allocations/{allocationId}/void', [PaymentApiController::class, 'voidAllocation'])->whereUuid('paymentId')->whereUuid('allocationId')->name('allocations.void')->middleware('idempotent');
    Route::post('/{paymentId}/allocations/{allocationId}/refund', [PaymentApiController::class, 'refundAllocation'])->whereUuid('paymentId')->whereUuid('allocationId')->name('allocations.refund')->middleware('idempotent');
    Route::post('/{id}/void', [PaymentApiController::class, 'void'])->whereUuid('id')->name('void')->middleware('idempotent');
    Route::post('/{id}/refund', [PaymentApiController::class, 'refund'])->whereUuid('id')->name('refund')->middleware('idempotent');

    // Payment Statistics
    Route::get('/statistics', [PaymentApiController::class, 'statistics'])->name('statistics');
    Route::get('/{customerId}/summary', [PaymentApiController::class, 'customerSummary'])->whereUuid('customerId')->name('customer-summary');
    Route::get('/{id}/allocation-suggestions', [PaymentApiController::class, 'allocationSuggestions'])->whereUuid('id')->name('allocation-suggestions');

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
    Route::get('/{id}', [CustomerApiController::class, 'show'])->whereUuid('id')->name('show');
    Route::put('/{id}', [CustomerApiController::class, 'update'])->whereUuid('id')->name('update')->middleware('idempotent');
    Route::delete('/{id}', [CustomerApiController::class, 'destroy'])->whereUuid('id')->name('destroy')->middleware('idempotent');

    // Customer Relations
    Route::get('/{id}/invoices', [CustomerApiController::class, 'invoices'])->whereUuid('id')->name('invoices');
    Route::get('/{id}/payments', [CustomerApiController::class, 'payments'])->whereUuid('id')->name('payments');
    Route::get('/{id}/statement', [CustomerApiController::class, 'statement'])->whereUuid('id')->name('statement');
    Route::get('/{id}/statistics', [CustomerApiController::class, 'statistics'])->whereUuid('id')->name('statistics');

    // Customer Search
    Route::get('/search', [CustomerApiController::class, 'search'])->name('search');

    // Bulk Operations
    Route::post('/bulk', [CustomerApiController::class, 'bulk'])->name('bulk')->middleware('idempotent');
});

// Universal inline edit endpoint
Route::patch('/inline-edit', [InlineEditController::class, 'patch'])->middleware('web');

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()->toISOString(),
        'storage_symlink' => file_exists(public_path('storage')),
    ]);
})->name('health');
