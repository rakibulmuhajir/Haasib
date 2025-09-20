<?php

use App\Services\InvoiceService;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight flow placeholder. This test documents the expected flow and is
 * skipped under the default sqlite in-memory test DB because migrations rely
 * on PostgreSQL schemas and RLS. Switch DB to pgsql and seed minimal data to
 * enable this test.
 */
it('creates, posts invoice and updates AR (documented flow)', function () {
    test()->markTestSkipped('Requires PostgreSQL test DB and seeded tenant context.');

    // Pseudocode reference for future enablement:
    // 1) actingAs($user) with session(['current_company_id' => $company->id])
    // 2) POST route('invoices.store', payload) and assert redirect
    // 3) POST route('invoices.post', $invoice) and assert success
    // 4) assert DB has accounts_receivable row with amount_due and aging_category
})->group('invoicing');

