<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\Api\PaymentController;
use Modules\Accounting\Http\Controllers\Api\PaymentAuditController;

Route::middleware(['auth:sanctum', 'tenant.context'])->prefix('api/accounting')->group(function () {
    
    // Payment endpoints
    Route::prefix('payments')->group(function () {
        Route::post('/', [PaymentController::class, 'store'])->name('accounting.payments.create');
        Route::get('{paymentId}', [PaymentController::class, 'show'])->name('accounting.payments.view');
        Route::post('{paymentId}/allocate', [PaymentController::class, 'allocate'])->name('accounting.payments.allocate');
        Route::post('{paymentId}/auto-allocate', [PaymentController::class, 'autoAllocate'])->name('accounting.payments.auto-allocate');
        Route::get('{paymentId}/allocations', [PaymentController::class, 'allocations'])->name('accounting.payments.allocations');
        Route::get('{paymentId}/receipt', [PaymentController::class, 'receipt'])->name('accounting.payments.receipt');
        Route::post('{paymentId}/reverse', [PaymentController::class, 'reverse'])->name('accounting.payments.reverse');
        Route::post('{paymentId}/allocations/{allocationId}/reverse', [PaymentController::class, 'reverseAllocation'])->name('accounting.payments.allocations.reverse');
    });
    
    // Payment batch endpoints
    Route::prefix('payment-batches')->group(function () {
        Route::post('/', [PaymentController::class, 'createBatch'])->name('accounting.payment-batches.create');
        Route::get('/', [PaymentController::class, 'listBatches'])->name('accounting.payment-batches.list');
        Route::get('{batchId}', [PaymentController::class, 'getBatch'])->name('accounting.payment-batches.show');
    });
    
    // Audit endpoints
    Route::prefix('payments/audit')->group(function () {
        Route::get('/', [PaymentAuditController::class, 'index'])->name('accounting.payments.audit.list');
        Route::get('{paymentId}', [PaymentAuditController::class, 'show'])->name('accounting.payments.audit.show');
        Route::get('reconciliation', [PaymentAuditController::class, 'reconciliation'])->name('accounting.payments.audit.reconciliation');
        Route::get('metrics', [PaymentAuditController::class, 'metrics'])->name('accounting.payments.audit.metrics');
    });
});