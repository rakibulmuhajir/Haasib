<?php

/**
 * Real Laravel worker for the Daily Close concurrency soak test
 * (tests/Feature/FuelStation/DailyCloseConcurrencySoakTest.php).
 *
 * Boots the actual application (same as artisan) in a separate OS process so
 * BillPayment\CreateAction / FuelSaleService::createSale run through the real
 * CommandBus, real GL posting, and real advisory-lock / audit-trigger paths —
 * not a stand-in. Reads a JSON job description from STDIN, writes one JSON
 * result line per unit of work to STDOUT, and refuses to run against
 * anything but a `_test`/`_testing` database, exactly like close-lock-worker.php.
 *
 * STDIN shape:
 * {
 *   "db": {...config('database.connections.pgsql')...},
 *   "company_id": "...", "user_id": "...", "date": "2026-09-15",
 *   "mode": "bill_payment" | "invoice",
 *   // mode=bill_payment
 *   "vendor_id": "...", "payment_account_id": "...", "bill_ids": ["...", ...],
 *   // mode=invoice
 *   "customer_id": "...", "item_id": "...",
 * }
 *
 * STDOUT: one JSON object per line: {"ok":true,"id":"...","transaction_id":"..."}
 * or {"ok":false,"error":"..."}. A final line {"done":true,"count":N} closes the stream.
 */

require __DIR__.'/../../vendor/autoload.php';

$input = json_decode(stream_get_contents(STDIN), true);
if (!$input || empty($input['db']['database']) || !preg_match('/_(test|testing)$/', $input['db']['database'])) {
    fwrite(STDERR, "refusing: not a test database\n");
    exit(9);
}

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Force the exact test connection the parent process resolved, regardless of
// whatever this subprocess's own .env would otherwise pick.
config(['database.connections.pgsql' => $input['db']]);
\Illuminate\Support\Facades\DB::purge('pgsql');
\Illuminate\Support\Facades\DB::reconnect('pgsql');
\Illuminate\Support\Facades\DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$input['company_id']]);

$company = \App\Models\Company::findOrFail($input['company_id']);
$user = \App\Models\User::findOrFail($input['user_id']);

\Illuminate\Support\Facades\Auth::login($user);
app(\App\Services\CurrentCompany::class)->set($company);
app(\App\Services\CompanyContextService::class)->setContext($company);

$count = 0;

if ($input['mode'] === 'bill_payment') {
    foreach ($input['bill_ids'] as $index => $billId) {
        // A little jitter between a single cashier's own successive submissions,
        // like real data entry, so K workers don't all hammer the same company's
        // sequential-numbering row lock in perfect lockstep every iteration.
        usleep(random_int(30000, 90000));
        try {
            $bill = \App\Modules\Accounting\Models\Bill::where('company_id', $company->id)->findOrFail($billId);
            $result = app(\App\Services\CommandBus::class)->dispatch('bill_payment.create', [
                // BillPayment\CreateAction::nextNumber()'s `SELECT ... FOR UPDATE ORDER BY
                // payment_number DESC` is a "pick the current max, then insert max+1" scheme:
                // it locks only the row(s) that already exist, not the not-yet-existing next
                // number, so it cannot serialize concurrent callers (a real, separate,
                // pre-existing gap unrelated to the Daily Close lock this test exercises).
                // Supplying an explicit, worker-unique payment_number sidesteps that gap so
                // this test can isolate the race it is actually reproducing.
                'payment_number' => $input['worker_tag'].'-'.$index,
                'vendor_id' => $input['vendor_id'],
                'payment_date' => $input['date'],
                'amount' => (float) $bill->balance,
                'currency' => $bill->currency,
                'base_currency' => $bill->base_currency,
                'payment_method' => 'cash',
                'payment_account_id' => $input['payment_account_id'],
                'allocations' => [['bill_id' => $bill->id, 'amount_allocated' => (float) $bill->balance]],
            ], $user);
            $paymentId = $result['data']['id'];
            $txnId = \App\Modules\Accounting\Models\BillPayment::find($paymentId)->transaction_id;
            echo json_encode(['ok' => true, 'id' => $paymentId, 'source_id' => $paymentId, 'transaction_id' => $txnId]), "\n";
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'bill_id' => $billId]), "\n";
        }
        $count++;
        flush();
    }
} elseif ($input['mode'] === 'invoice') {
    foreach ($input['sale_dates'] ?? array_fill(0, $input['loops'] ?? 10, $input['date']) as $saleDate) {
        usleep(random_int(30000, 90000));
        try {
            $invoice = app(\App\Modules\FuelStation\Services\FuelSaleService::class)->createSale([
                'sale_type' => \App\Modules\FuelStation\Models\SaleMetadata::TYPE_CREDIT,
                'customer_id' => $input['customer_id'],
                'item_id' => $input['item_id'],
                'quantity' => 1,
                'sale_date' => $saleDate,
            ]);
            echo json_encode(['ok' => true, 'id' => $invoice->id, 'source_id' => $invoice->id, 'transaction_id' => $invoice->transaction_id]), "\n";
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]), "\n";
        }
        $count++;
        flush();
    }
}

echo json_encode(['done' => true, 'count' => $count]), "\n";
