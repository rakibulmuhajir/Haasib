<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Services\DailyCloseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * These tests exercise the advisory-lock design from review finding #4:
 * - fuel.capture_post_close_activity takes a SHARED pg_advisory_xact_lock on
 *   (company, business date) instead of `FOR UPDATE` on auth.companies.
 * - DailyCloseService::processDailyClose takes the matching EXCLUSIVE lock as
 *   the first statement of its transaction.
 *
 * A single PHP test process cannot literally block on its own connection, so
 * where the task allows a fallback we (a) prove exclusivity with
 * pg_try_advisory_xact_lock against a second, real PDO connection that holds
 * the lock open across an uncommitted transaction, and (b) prove the full
 * ordering by committing that second connection and observing the outcome
 * through the real service. See each test's docblock for exactly what it
 * proves and does not prove.
 */
function advisoryLockFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Advisory locks', 'slug' => 'adv-'.Str::random(12), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    test()->actingAs($user);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    $period = AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $accounts = [];
    foreach ([['1050', 'asset', 'cash', 'debit'], ['1200', 'asset', 'inventory', 'debit'], ['4100', 'revenue', 'other_income', 'credit'], ['5100', 'cogs', 'cost_of_goods_sold', 'debit'], ['6180', 'expense', 'operating_expense', 'debit']] as [$code, $type, $subtype, $normal]) {
        $accounts[$code] = Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal, 'currency' => $code === '1050' ? 'PKR' : null, 'is_active' => true]);
    }
    $payload = ['date' => '2026-09-15', 'opening_cash' => 420000, 'closing_cash' => 420000, 'nozzle_readings' => [],
        'zero_sales_confirmed' => true, 'zero_sales_reason' => 'Advisory locking test fixture'];

    return compact('user', 'company', 'fy', 'period', 'accounts', 'payload');
}

function apVendorAndBill(array $f, string $billNumber = 'BILL-0001'): array
{
    $apAccount = Account::create([
        'company_id' => $f['company']->id, 'code' => '2100', 'name' => 'Accounts Payable',
        'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_active' => true,
    ]);
    $vendor = Vendor::create([
        'company_id' => $f['company']->id, 'vendor_number' => 'VEND-0001', 'name' => 'Acme Fuel Supplies',
        'base_currency' => 'PKR', 'is_active' => true, 'ap_account_id' => $apAccount->id, 'created_by_user_id' => $f['user']->id,
    ]);
    $bill = Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $vendor->id, 'bill_number' => $billNumber,
        'bill_date' => '2026-09-01', 'due_date' => '2026-09-30', 'status' => 'received', 'currency' => 'PKR',
        'base_currency' => 'PKR', 'exchange_rate' => 1, 'subtotal' => 5000, 'tax_amount' => 0, 'discount_amount' => 0,
        'total_amount' => 5000, 'paid_amount' => 0, 'balance' => 5000, 'base_amount' => 5000,
        'created_by_user_id' => $f['user']->id,
    ]);

    return compact('vendor', 'bill');
}

function secondPdoConnection(): PDO
{
    $cfg = config('database.connections.pgsql');
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $cfg['host'], $cfg['port'], $cfg['database']);
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

function rawInsertBillPayment(PDO $pdo, array $f, string $vendorId, ?string $paymentAccountId, string $date, string $number): string
{
    $id = (string) Str::uuid();
    $stmt = $pdo->prepare('insert into acct.bill_payments
        (id, company_id, vendor_id, payment_number, payment_date, amount, currency, base_currency, base_amount, payment_method, payment_account_id, created_by_user_id)
        values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$id, $f['company']->id, $vendorId, $number, $date, 5000, 'PKR', 'PKR', 5000, 'cash', $paymentAccountId, $f['user']->id]);

    return $id;
}

test('a shared advisory lock held by an uncommitted write excludes the close key, and the close observes the write once it commits', function () {
    // PROVES: (1) the audit trigger's SHARED advisory lock on (company, date) is
    // visible to pg_try_advisory_xact_lock as excluding an EXCLUSIVE attempt on the
    // same key while the writer's transaction is open; (2) once that writer commits,
    // the key is free again; (3) the real DailyCloseService, using the identical key
    // computation, picks up the now-committed bill payment as part of the close
    // (transaction_id gets stamped), demonstrating correct visibility ordering.
    // DOES NOT PROVE: that processDailyClose() itself would physically block while
    // the writer's transaction is still open (that needs a second OS process; see
    // the task's own documented fallback for why this suffices here).
    $f = advisoryLockFixture();
    ['vendor' => $vendor] = apVendorAndBill($f);
    // Checkpoint the fixture: RefreshDatabase wraps this test in a transaction on
    // Laravel's own connection, so a genuinely separate PDO session (below) cannot
    // see the company/vendor/bill rows until that transaction actually commits.
    // Commit it for real, then immediately reopen one so the test harness's own
    // teardown (which expects one open transaction to roll back) still works.
    DB::commit();
    DB::beginTransaction();

    $conn = secondPdoConnection();
    $conn->beginTransaction();
    $paymentId = rawInsertBillPayment($conn, $f, $vendor->id, $f['accounts']['1050']->id, '2026-09-15', 'PAY-0001');

    $locked = DB::selectOne('select pg_try_advisory_xact_lock(hashtext(?), hashtext(?)) as ok', [$f['company']->id, '2026-09-15']);
    expect((bool) $locked->ok)->toBeFalse();

    $conn->commit();

    $locked = DB::selectOne('select pg_try_advisory_xact_lock(hashtext(?), hashtext(?)) as ok', [$f['company']->id, '2026-09-15']);
    expect((bool) $locked->ok)->toBeTrue();

    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    expect(BillPayment::find($paymentId)->transaction_id)->toBe($result['transaction_id']);
});

test('a writer blocked by the close’s exclusive lock fails fast, then succeeds and is captured once the close releases it', function () {
    // PROVES: (1) while a transaction holds the EXCLUSIVE advisory lock on
    // (company, date) -- simulating processDailyClose's own first statement -- a
    // concurrent writer whose trigger needs the matching SHARED lock is blocked and,
    // with a short statement_timeout, fails with SQLSTATE 40001 (lock not obtained
    // without waiting) rather than succeeding or deadlocking; (2) once the holder commits,
    // the same writer's retry succeeds; (3) because a posting_snapshot already
    // exists for that date (from a real close posted beforehand), the retried
    // insert is captured by fuel.daily_close_activity.
    // DOES NOT PROVE: that a second, independent close attempt is what is holding
    // the lock -- it is genuinely processDailyClose()'s own exclusive advisory
    // lock, still held in this session: Laravel's test-per-request transaction
    // wrapper means DailyCloseService's own DB::transaction() is a SAVEPOINT, not
    // a real COMMIT, so the xact-scoped advisory lock it took is not released when
    // the method returns -- only a genuine top-level COMMIT releases it (see
    // below). That is a test-harness artifact, not production behaviour, but it
    // lets us hold the exact lock the real close took, rather than a stand-in.
    $f = advisoryLockFixture();
    ['vendor' => $vendor] = apVendorAndBill($f);

    $otherCompanyUser = User::factory()->create();
    $otherCompany = Company::create(['name' => 'Other co', 'slug' => 'other-'.Str::random(12), 'owner_id' => $otherCompanyUser->id, 'base_currency' => 'PKR']);
    $otherVendor = Vendor::create(['company_id' => $otherCompany->id, 'vendor_number' => 'VEND-0001', 'name' => 'Other vendor', 'base_currency' => 'PKR', 'is_active' => true, 'created_by_user_id' => $otherCompanyUser->id]);

    // Checkpoint so the second, genuinely separate PDO connection below can see
    // all of the above rows (see the comment in the previous test).
    DB::commit();
    DB::beginTransaction();

    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $closeId = $posted['transaction_id'];

    $conn = secondPdoConnection();
    $conn->exec('SET statement_timeout = 2000');
    $blocked = null;
    try {
        rawInsertBillPayment($conn, $f, $vendor->id, $f['accounts']['1050']->id, '2026-09-15', 'PAY-0002');
    } catch (\PDOException $e) {
        $blocked = $e;
    }
    expect($blocked)->not->toBeNull();
    expect($blocked->errorInfo[0] ?? null)->toBe('40001');

    // While the exclusive lock is still held, a DIFFERENT date and a DIFFERENT
    // company are not blocked at all (shared locks on unrelated keys never wait).
    $otherDateId = rawInsertBillPayment($conn, $f, $vendor->id, $f['accounts']['1050']->id, '2026-09-16', 'PAY-0003');
    expect($otherDateId)->not->toBeNull();

    $otherPaymentId = rawInsertBillPayment($conn, ['company' => $otherCompany, 'user' => $otherCompanyUser], $otherVendor->id, null, '2026-09-15', 'PAY-0004');
    expect($otherPaymentId)->not->toBeNull();

    // This is a genuine top-level COMMIT (see the docblock above): it actually ends
    // the underlying Postgres transaction and releases the exclusive advisory lock
    // processDailyClose took, exactly as a real close committing would.
    DB::commit();
    DB::beginTransaction();

    $conn->exec('SET statement_timeout = 0');
    $retryId = rawInsertBillPayment($conn, $f, $vendor->id, $f['accounts']['1050']->id, '2026-09-15', 'PAY-0005');

    $captured = DB::table('fuel.daily_close_activity')
        ->where('company_id', $f['company']->id)
        ->where('close_transaction_id', $closeId)
        ->where('source_table', 'acct.bill_payments')
        ->where('source_id', $retryId)
        ->first();
    expect($captured)->not->toBeNull();
});


test('a row owner never waits on the close lock and retries after the other process commits', function () {
    $f = advisoryLockFixture();
    ['vendor' => $vendor] = apVendorAndBill($f);
    $id = rawInsertBillPayment(DB::connection()->getPdo(), $f, $vendor->id, $f['accounts']['1050']->id, '2026-09-15', 'RACE-1');
    DB::commit(); DB::beginTransaction();
    $pdo = DB::connection()->getPdo();
    $pdo->exec('SAVEPOINT race_owner');
    DB::table('acct.bill_payments')->where('id', $id)->lockForUpdate()->first();
    // The worker inherits the host PHP configuration.  On this Windows setup
    // Xdebug points at an unavailable WAMP log path and writes its startup
    // warning to stderr, which would contaminate the protocol channel below.
    // Keep the subprocess protocol deterministic without disabling Xdebug for
    // the parent test process.
    $xdebugLogSink = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $worker = proc_open(
        [PHP_BINARY, '-d', "xdebug.log={$xdebugLogSink}", base_path('tests/Fixtures/close-lock-worker.php')],
        [['pipe','r'],['pipe','w'],['pipe','w']],
        $pipes,
    );
    fwrite($pipes[0], json_encode(['config' => config('database.connections.pgsql'), 'company' => $f['company']->id, 'payment' => $id]));
    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 10);
    try {
        expect(trim(fgets($pipes[1])))->toBe('LOCKED');
        $started = microtime(true); $state = null;
        try { DB::table('acct.bill_payments')->where('id', $id)->update(['notes' => 'Retry-safe edit']); }
        catch (\Illuminate\Database\QueryException $e) { $state = $e->errorInfo[0]; }
        expect($state)->toBe('40001');
        expect(microtime(true) - $started)->toBeLessThan(2.0);
        $pdo->exec('ROLLBACK TO SAVEPOINT race_owner'); // releases the document row; worker can finish
        expect(trim(fgets($pipes[1])))->toBe('COMMITTED');
        expect(stream_get_contents($pipes[2]))->toBe('');
        fclose($pipes[1]); fclose($pipes[2]);
        expect(proc_close($worker))->toBe(0); $worker = null;
        DB::table('acct.bill_payments')->where('id', $id)->update(['notes' => 'Retry-safe edit']);
        expect(DB::table('acct.bill_payments')->where('id', $id)->value('notes'))->toBe('Retry-safe edit');
    } finally {
        if (is_resource($worker)) { proc_terminate($worker); proc_close($worker); }
    }
});

test('the scoped accounting retry rolls back the entire failed unit of work', function () {
    DB::commit();
    DB::statement('CREATE TEMP TABLE retry_probe (value integer)');
    $attempts = 0;
    try {
        \App\Services\AccountingWriteTransaction::run(function () use (&$attempts) {
            $attempts++;
            DB::table('retry_probe')->insert(['value' => $attempts]);
            if ($attempts === 1) { DB::unprepared("DO $$ BEGIN RAISE EXCEPTION 'retry' USING ERRCODE='40001'; END $$"); }
        });
        expect($attempts)->toBe(2);
        expect(DB::table('retry_probe')->pluck('value')->all())->toBe([2]);
    } finally { DB::statement('DROP TABLE retry_probe'); DB::beginTransaction(); }
});
