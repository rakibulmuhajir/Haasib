<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\Api\JournalBatchController;
use Modules\Accounting\Http\Controllers\Api\JournalEntryController;
use Modules\Accounting\Http\Controllers\Api\JournalTemplateController;
use Modules\Accounting\Http\Controllers\Api\TrialBalanceController;

/*
|--------------------------------------------------------------------------
| API Routes - Journal Entries
|--------------------------------------------------------------------------
|
| These routes define the API endpoints for journal entry management,
| including manual entries, batches, recurring templates, and reporting.
|
*/

Route::middleware(['auth:sanctum', 'api'])->group(function () {

    // Journal Entry Routes
    Route::prefix('ledger/journal-entries')->group(function () {
        Route::get('/', [JournalEntryController::class, 'index'])
            ->name('journal-entries.index');

        Route::post('/', [JournalEntryController::class, 'store'])
            ->name('journal-entries.store');

        Route::get('{journalEntryId}', [JournalEntryController::class, 'show'])
            ->name('journal-entries.show');

        Route::put('{journalEntryId}', [JournalEntryController::class, 'update'])
            ->name('journal-entries.update');

        Route::post('{journalEntryId}/submit', [JournalEntryController::class, 'submit'])
            ->name('journal-entries.submit');

        Route::post('{journalEntryId}/approve', [JournalEntryController::class, 'approve'])
            ->name('journal-entries.approve');

        Route::post('{journalEntryId}/post', [JournalEntryController::class, 'post'])
            ->name('journal-entries.post');

        Route::post('{journalEntryId}/void', [JournalEntryController::class, 'void'])
            ->name('journal-entries.void');

        Route::post('{journalEntryId}/reverse', [JournalEntryController::class, 'reverse'])
            ->name('journal-entries.reverse');

        Route::get('{journalEntryId}/audit', [JournalEntryController::class, 'audit'])
            ->name('journal-entries.audit');

        Route::get('{journalEntryId}/summary', [JournalEntryController::class, 'summary'])
            ->name('journal-entries.summary');
    });

    // Journal Batch Routes
    Route::prefix('ledger/journal-batches')->group(function () {
        Route::get('/', [JournalBatchController::class, 'index'])
            ->name('journal-batches.index');

        Route::post('/', [JournalBatchController::class, 'store'])
            ->name('journal-batches.store');

        Route::get('{batchId}', [JournalBatchController::class, 'show'])
            ->name('journal-batches.show');

        Route::put('{batchId}', [JournalBatchController::class, 'update'])
            ->name('journal-batches.update');

        Route::delete('{batchId}', [JournalBatchController::class, 'destroy'])
            ->name('journal-batches.destroy');

        Route::post('{batchId}/approve', [JournalBatchController::class, 'approve'])
            ->name('journal-batches.approve');

        Route::post('{batchId}/post', [JournalBatchController::class, 'post'])
            ->name('journal-batches.post');

        Route::post('{batchId}/schedule', [JournalBatchController::class, 'schedule'])
            ->name('journal-batches.schedule');

        Route::post('{batchId}/add-entries', [JournalBatchController::class, 'addEntries'])
            ->name('journal-batches.add-entries');

        Route::post('{batchId}/remove-entries', [JournalBatchController::class, 'removeEntries'])
            ->name('journal-batches.remove-entries');

        Route::get('statistics', [JournalBatchController::class, 'statistics'])
            ->name('journal-batches.statistics');
    });

    // Recurring Template Routes
    Route::prefix('ledger/journal-templates')->group(function () {
        Route::get('/', [JournalTemplateController::class, 'index'])
            ->name('journal-templates.index');

        Route::post('/', [JournalTemplateController::class, 'store'])
            ->name('journal-templates.store');

        Route::get('{templateId}', [JournalTemplateController::class, 'show'])
            ->name('journal-templates.show');

        Route::put('{templateId}', [JournalTemplateController::class, 'update'])
            ->name('journal-templates.update');

        Route::delete('{templateId}', [JournalTemplateController::class, 'destroy'])
            ->name('journal-templates.destroy');

        Route::post('{templateId}/activate', [JournalTemplateController::class, 'activate'])
            ->name('journal-templates.activate');

        Route::post('{templateId}/deactivate', [JournalTemplateController::class, 'deactivate'])
            ->name('journal-templates.deactivate');

        Route::post('{templateId}/preview', [JournalTemplateController::class, 'preview'])
            ->name('journal-templates.preview');

        Route::post('{templateId}/generate', [JournalTemplateController::class, 'generate'])
            ->name('journal-templates.generate');
    });

    // Trial Balance Routes
    Route::prefix('ledger')->group(function () {
        Route::get('trial-balance', [TrialBalanceController::class, 'index'])
            ->name('trial-balance.index');

        Route::post('trial-balance/generate', [TrialBalanceController::class, 'generate'])
            ->name('trial-balance.generate');

        Route::get('ledger', [TrialBalanceController::class, 'ledger'])
            ->name('ledger.index');
    });
});
