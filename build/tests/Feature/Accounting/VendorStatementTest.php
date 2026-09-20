<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Services\VendorStatementService;
use Illuminate\Support\Facades\DB;

/**
 * The payables mirror of CustomerStatementTest. A supplier page showed a list of recent
 * bills and a list of recent payments and never a balance, so "what do we owe PSO" had
 * no answer in the app.
 *
 * Sign convention is the payable's own, not the receivable's borrowed: a bill is a CREDIT
 * raising what is owed, a payment or vendor credit is a DEBIT reducing it, and a positive
 * closing balance means money is still owed to the supplier.
 */
function vendorStatementFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Payable Co', 'slug' => 'payable-co-'.str()->random(8), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $ap = Account::create(['company_id' => $company->id, 'code' => '2100', 'name' => 'AP', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'currency' => 'PKR']);
    $vendor = Vendor::create([
        'company_id' => $company->id, 'vendor_number' => 'V-1', 'name' => 'Fuel Supplier',
        'base_currency' => 'PKR', 'ap_account_id' => $ap->id, 'is_active' => true,
    ]);

    return compact('user', 'company', 'vendor');
}

function vendorBill(array $f, string $date, float $amount, string $status = 'received'): Bill
{
    return Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id,
        'bill_number' => 'BILL-'.str()->random(8), 'bill_date' => $date, 'due_date' => $date,
        'status' => $status, 'currency' => 'PKR', 'base_currency' => 'PKR',
        'subtotal' => $amount, 'total_amount' => $amount, 'paid_amount' => 0, 'balance' => $amount,
    ]);
}

test('a bill raises the balance and a payment brings it down', function () {
    $f = vendorStatementFixture();
    vendorBill($f, '2026-09-01', 500000);
    BillPayment::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id,
        'payment_number' => 'BP-1', 'payment_date' => '2026-09-10', 'amount' => 200000,
        'currency' => 'PKR', 'base_currency' => 'PKR', 'payment_method' => 'bank_transfer',
    ]);

    $statement = app(VendorStatementService::class)->statement($f['vendor']->fresh());
    $rows = collect($statement['rows']);

    expect($rows->first()['type'])->toBe('opening_balance')
        ->and($rows->first()['balance'])->toBe(0.0);

    $bill = $rows->firstWhere('type', 'bill');
    expect($bill['credit'])->toBe(500000.0)
        ->and($bill['debit'])->toBe(0.0)
        ->and($bill['balance'])->toBe(500000.0);

    $payment = $rows->firstWhere('type', 'payment');
    expect($payment['debit'])->toBe(200000.0)
        ->and($payment['credit'])->toBe(0.0)
        ->and($payment['balance'])->toBe(300000.0);

    expect($statement['closing_balance'])->toBe(300000.0);
});

test('a vendor credit reduces what is owed', function () {
    $f = vendorStatementFixture();
    vendorBill($f, '2026-09-01', 100000);
    VendorCredit::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id,
        'credit_number' => 'VC-1', 'credit_date' => '2026-09-05', 'amount' => 15000,
        'currency' => 'PKR', 'base_currency' => 'PKR', 'status' => 'received',
    ]);

    $statement = app(VendorStatementService::class)->statement($f['vendor']->fresh());

    expect(collect($statement['rows'])->firstWhere('type', 'vendor_credit')['debit'])->toBe(15000.0)
        ->and($statement['closing_balance'])->toBe(85000.0);
});

test('draft and void documents are not owed and stay off the statement', function () {
    $f = vendorStatementFixture();
    vendorBill($f, '2026-09-01', 100000);
    vendorBill($f, '2026-09-02', 400000, 'draft');
    vendorBill($f, '2026-09-03', 900000, 'void');

    $statement = app(VendorStatementService::class)->statement($f['vendor']->fresh());

    expect(collect($statement['rows'])->where('type', 'bill')->count())->toBe(1)
        ->and($statement['closing_balance'])->toBe(100000.0);
});

test('rows are ordered oldest first so the running balance reads down the page', function () {
    $f = vendorStatementFixture();
    vendorBill($f, '2026-09-20', 30000);
    vendorBill($f, '2026-09-05', 10000);
    BillPayment::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id,
        'payment_number' => 'BP-2', 'payment_date' => '2026-09-10', 'amount' => 4000,
        'currency' => 'PKR', 'base_currency' => 'PKR', 'payment_method' => 'cash',
    ]);

    $balances = collect(app(VendorStatementService::class)->statement($f['vendor']->fresh())['rows'])
        ->pluck('balance')->all();

    expect($balances)->toBe([0.0, 10000.0, 6000.0, 36000.0]);
});

test('a supplier with nothing recorded shows an opening balance of zero', function () {
    $f = vendorStatementFixture();

    $statement = app(VendorStatementService::class)->statement($f['vendor']);

    expect($statement['rows'])->toHaveCount(1)
        ->and($statement['closing_balance'])->toBe(0.0);
});
