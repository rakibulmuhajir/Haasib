<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Inventory\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reproduces the real Daily Close / concurrent-write race end-to-end, using
 * genuine OS processes (see tests/Fixtures/close-soak-worker.php) rather than
 * a single PHP process pretending to be several. Reuses closeWorkflowFixture()'s
 * shape (company + chart of accounts) plus a supplier, bills, and a fuel
 * customer, as instructed.
 *
 * What this test proves and does not prove is documented at the top of each
 * assertion block below.
 */
function soakFixture(int $bills): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Soak co', 'slug' => 'soak-'.Str::random(12), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    test()->actingAs($user);

    // Worker processes dispatch bill_payment.create / bill.create through the real
    // CommandBus, which enforces permissions against the acting user.
    app(\App\Services\CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert(['company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    app(\App\Services\CompanyContextService::class)->withContext($company, fn () => app(\App\Services\CompanyContextService::class)->assignRole($user, 'owner'));

    $fy = \App\Modules\Accounting\Models\FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    \App\Modules\Accounting\Models\AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);

    $accounts = [];
    foreach ([['1050', 'asset', 'cash', 'debit'], ['1100', 'asset', 'accounts_receivable', 'debit'], ['1200', 'asset', 'inventory', 'debit'],
        ['2100', 'liability', 'accounts_payable', 'credit'], ['4100', 'revenue', 'other_income', 'credit'], ['5100', 'cogs', 'cost_of_goods_sold', 'debit']] as [$code, $type, $subtype, $normal]) {
        $accounts[$code] = Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal, 'currency' => in_array($subtype, ['cash', 'accounts_receivable']) ? 'PKR' : null, 'is_active' => true]);
    }

    $vendor = Vendor::create(['company_id' => $company->id, 'vendor_number' => 'V-1', 'name' => 'Soak Supplier', 'base_currency' => 'PKR', 'is_active' => true, 'ap_account_id' => $accounts['2100']->id, 'created_by_user_id' => $user->id]);
    $customer = Customer::create(['company_id' => $company->id, 'customer_number' => 'C-1', 'name' => 'Soak Buyer', 'base_currency' => 'PKR', 'ar_account_id' => $accounts['1100']->id, 'is_active' => true]);
    $item = Item::create(['company_id' => $company->id, 'sku' => 'PETROL', 'name' => 'Petrol', 'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR', 'avg_cost' => 250, 'asset_account_id' => $accounts['1200']->id]);
    RateChange::create(['company_id' => $company->id, 'item_id' => $item->id, 'effective_date' => '2020-01-01', 'purchase_rate' => 250, 'sale_rate' => 300]);

    $billIds = [];
    for ($i = 0; $i < $bills; $i++) {
        $billIds[] = Bill::create([
            'company_id' => $company->id, 'vendor_id' => $vendor->id, 'bill_number' => 'SOAK-BILL-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'bill_date' => '2026-09-15', 'due_date' => '2026-10-15', 'status' => 'received', 'currency' => 'PKR', 'base_currency' => 'PKR',
            'exchange_rate' => 1, 'subtotal' => 1000, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 1000,
            'paid_amount' => 0, 'balance' => 1000, 'base_amount' => 1000, 'created_by_user_id' => $user->id,
        ])->id;
    }

    // BillPayment\CreateAction::nextNumber() serializes concurrent callers with
    // `SELECT ... FOR UPDATE ORDER BY payment_number DESC`, but that provides no
    // protection at all when there is no existing row yet to lock: this is a genuine,
    // separate pre-existing race (a non-atomic "does this number exist" pre-check
    // throws a plain, non-retryable InvalidArgumentException rather than relying on
    // the retried unique-constraint violation) that this soak test found and is
    // deliberately routing around, since it is not the Daily Close race under test.
    // Seeding one prior payment row here gives every worker's first lockForUpdate()
    // scan something real to lock against.
    DB::table('acct.bill_payments')->insert([
        'id' => Str::uuid(), 'company_id' => $company->id, 'vendor_id' => $vendor->id,
        'payment_number' => 'PMT-00000', 'payment_date' => '2026-09-01', 'amount' => 1,
        'currency' => 'PKR', 'base_currency' => 'PKR', 'base_amount' => 1, 'payment_method' => 'cash',
        'created_by_user_id' => $user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $payload = ['date' => '2026-09-15', 'opening_cash' => 100000, 'closing_cash' => 100000, 'nozzle_readings' => [],
        'zero_sales_confirmed' => true, 'zero_sales_reason' => 'Soak test: no nozzles configured'];

    return compact('user', 'company', 'accounts', 'vendor', 'customer', 'item', 'billIds', 'payload');
}

/** @return array{proc:resource, pipes:array} */
function spawnSoakWorker(array $job): array
{
    $xdebugLogSink = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $proc = proc_open(
        [PHP_BINARY, '-d', "xdebug.log={$xdebugLogSink}", base_path('tests/Fixtures/close-soak-worker.php')],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $pipes
    );
    fwrite($pipes[0], json_encode($job));
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    return ['proc' => $proc, 'pipes' => $pipes];
}

test('the close survives real concurrent bill payments and fuel-sale invoices without double counting or lost work', function () {
    // PROVES: K real OS-process workers create bill payments (and one worker creates
    // fuel-sale invoices) against the real CommandBus/BillPayment\CreateAction and
    // FuelSaleService::createSale, concurrently with the main process posting the real
    // Daily Close, with genuine inter-process DB contention (not simulated). Asserts:
    // no worker ends with an unrecovered exception; the close posts exactly once; and
    // every payment/invoice lands in exactly one of {captured in the posted snapshot,
    // captured as post-close activity} — never both, never neither. Retry counts (SQLSTATE
    // 40001/40P01, retried transparently by AccountingWriteTransaction::run) are counted
    // from the application log by worker PID and reported.
    // DOES NOT PROVE: that a deadlock (specifically 40P01, as opposed to a serialization
    // failure 40001) was necessarily produced on this particular run — that depends on
    // exact OS scheduling. Both codes are retried identically, so the correctness
    // guarantee holds either way; this run's retry counts are reported below.
    $K = 4; // bill-payment workers
    $M = 10; // bills per worker
    $f = soakFixture($K * $M);

    // Commit the RefreshDatabase transaction for real: separate OS processes cannot see
    // this fixture's rows otherwise (see DailyCloseAdvisoryLockingTest for the same trick).
    DB::commit();
    DB::beginTransaction();

    $dbConfig = config('database.connections.pgsql');
    $logPath = storage_path('logs/laravel.log');
    $logSizeBefore = @filesize($logPath) ?: 0;

    $workers = [];
    $billChunks = array_chunk($f['billIds'], $M);
    foreach ($billChunks as $workerIndex => $chunk) {
        $workers[] = spawnSoakWorker([
            'db' => $dbConfig, 'company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'date' => '2026-09-15',
            'mode' => 'bill_payment', 'vendor_id' => $f['vendor']->id, 'payment_account_id' => $f['accounts']['1050']->id,
            'bill_ids' => $chunk, 'worker_tag' => 'SOAK-W'.$workerIndex,
        ]);
    }
    // One worker creating fuel-sale invoices for the same business date.
    $workers[] = spawnSoakWorker([
        'db' => $dbConfig, 'company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'date' => '2026-09-15',
        'mode' => 'invoice', 'customer_id' => $f['customer']->id, 'item_id' => $f['item']->id, 'loops' => 10,
    ]);

    // Post the close at a random point while workers are still running.
    usleep(random_int(200000, 500000));
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $closeId = $posted['transaction_id'];
    // processDailyClose()'s own DB::transaction() is a SAVEPOINT inside RefreshDatabase's
    // wrapping transaction, not a real COMMIT (see DailyCloseAdvisoryLockingTest's docblock):
    // the xact-scoped advisory lock it took would otherwise stay held by this test's own
    // still-open transaction for the rest of the test, starving every worker. Commit for
    // real so the lock actually releases, then reopen so teardown still has one to roll back.
    DB::commit();
    DB::beginTransaction();

    // Drain workers.
    $results = [];
    $pids = [];
    foreach ($workers as $w) {
        $status = proc_get_status($w['proc']);
        $pids[] = $status['pid'];
        $start = microtime(true);
        $buffer = '';
        while (true) {
            $line = fgets($w['pipes'][1]);
            if ($line !== false) {
                $buffer .= $line;
                $decoded = json_decode(trim($line), true);
                if ($decoded) { $results[] = $decoded; }
            }
            $status = proc_get_status($w['proc']);
            if (!$status['running'] && feof($w['pipes'][1])) { break; }
            if (microtime(true) - $start > 60) {
                proc_terminate($w['proc']);
                break;
            }
            usleep(10000);
        }
        $stderr = stream_get_contents($w['pipes'][2]);
        fclose($w['pipes'][1]);
        fclose($w['pipes'][2]);
        $exitCode = proc_close($w['proc']);
        expect($exitCode)->toBe(0, "worker exited {$exitCode}, stderr: {$stderr}");
        expect($stderr)->toBe('', "worker wrote to stderr: {$stderr}");
    }

    // No worker-reported failure (an uncaught exception inside the worker's own try/catch
    // would show up as ok=false; AccountingWriteTransaction retries 40001/40P01 internally,
    // so a result only reaches here as ok=false if retries were exhausted or another error occurred).
    $failures = array_filter($results, fn ($r) => isset($r['ok']) && $r['ok'] === false);
    expect($failures)->toBe([], 'worker(s) reported an unrecovered failure: '.json_encode(array_values($failures)));

    // The close posted exactly once.
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->count())->toBe(1);

    // Every payment/invoice lands in exactly one bucket: captured by the posted snapshot
    // (committed before the close read its sources) or as post-close activity (committed
    // after). Never both, never neither.
    $snapshot = $posted['metadata']['posting_snapshot'];
    $snapshotTxnIds = array_column($snapshot['sources'], 'id');
    $activityTxnIds = DB::table('fuel.daily_close_activity')
        ->where('company_id', $f['company']->id)->where('close_transaction_id', $closeId)
        ->pluck('source_id')->map(fn ($id) => (string) $id)->all();

    $successes = array_values(array_filter($results, fn ($r) => isset($r['ok']) && $r['ok'] === true));
    expect(count($successes))->toBe($K * $M + 10);

    $inSnapshot = 0; $inActivity = 0; $inBoth = 0; $inNeither = 0;
    foreach ($successes as $result) {
        $txnId = $result['transaction_id'];
        $sourceId = $result['source_id'];
        $isInSnapshot = in_array($txnId, $snapshotTxnIds, true);
        $isInActivity = in_array($sourceId, $activityTxnIds, true) || in_array($txnId, $activityTxnIds, true);
        if ($isInSnapshot && $isInActivity) { $inBoth++; }
        elseif ($isInSnapshot) { $inSnapshot++; }
        elseif ($isInActivity) { $inActivity++; }
        else { $inNeither++; }
    }
    expect($inBoth)->toBe(0, 'a payment/invoice was captured both in the snapshot and as post-close activity');
    expect($inNeither)->toBe(0, 'a payment/invoice was captured in neither the snapshot nor post-close activity');
    expect($inSnapshot + $inActivity)->toBe(count($successes));

    // Count retries (SQLSTATE 40001/40P01, retried transparently by AccountingWriteTransaction)
    // by reading only the log bytes appended during this test, filtered to this run's worker PIDs.
    clearstatcache(false, $logPath);
    $logSizeAfter = @filesize($logPath) ?: 0;
    $retryCount = 0;
    if ($logSizeAfter > $logSizeBefore) {
        $handle = fopen($logPath, 'r');
        fseek($handle, $logSizeBefore);
        $appended = stream_get_contents($handle);
        fclose($handle);
        foreach (explode("\n", $appended) as $line) {
            if (!str_contains($line, 'accounting_write_retry')) { continue; }
            foreach ($pids as $pid) {
                if ($pid && str_contains($line, '"pid":'.$pid)) { $retryCount++; break; }
            }
        }
    }

    fwrite(STDERR, sprintf(
        "\n[soak] workers=%d successes=%d in_snapshot=%d in_activity=%d retries_observed=%d\n",
        count($workers), count($successes), $inSnapshot, $inActivity, $retryCount
    ));

    expect(true)->toBeTrue(); // retry count is informational; correctness is asserted above.
})->group('soak');
