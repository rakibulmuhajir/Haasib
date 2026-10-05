<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Services\VendorStatementService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * A supplier credit (e.g. "delivery shortage cash back") must be postable from a draft,
 * editable before and after posting, applicable to a bill, and show on the statement.
 */
function vcFlowFixture(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Vendor Credit Flow',
        'slug' => 'vendor-credit-flow-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    if (! DB::table('public.currencies')->where('code', 'PKR')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);
    }

    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    // Void reverses on the day it is done (today), so that day needs an open period too.
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'Current', 'period_number' => 10, 'start_date' => now()->startOfYear()->max('2026-10-01')->toDateString(), 'end_date' => '2026-12-31']);

    $ap = Account::create(['company_id' => $company->id, 'code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'currency' => 'PKR', 'is_active' => true]);
    $expense = Account::create(['company_id' => $company->id, 'code' => '6000', 'name' => 'General Expense', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'currency' => null, 'is_active' => true]);
    $transit = Account::create(['company_id' => $company->id, 'code' => '8060', 'name' => 'Transit Loss', 'type' => 'other_expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit', 'currency' => null, 'is_active' => true]);
    $fines = Account::create(['company_id' => $company->id, 'code' => '6220', 'name' => 'Fines and Penalties', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'currency' => null, 'is_active' => true]);
    $company->update(['ap_account_id' => $ap->id, 'expense_account_id' => $expense->id]);

    $vendor = Vendor::create([
        'company_id' => $company->id, 'vendor_number' => 'VEND-0001', 'name' => 'Total Parco',
        'base_currency' => 'PKR', 'ap_account_id' => $ap->id, 'is_active' => true, 'created_by_user_id' => $user->id,
    ]);

    return compact('user', 'company', 'ap', 'expense', 'transit', 'fines', 'vendor');
}

function vcFlowPayload(array $f, array $over = []): array
{
    return array_merge([
        'vendor_id' => $f['vendor']->id,
        'credit_date' => '2026-09-30',
        'amount' => 54953,
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'reason' => 'delivery shortage cash back',
        'status' => 'draft',
        'line_items' => [[
            'description' => 'Delivery shortage', 'quantity' => 1, 'unit_price' => 54953,
            'tax_rate' => 0, 'discount_rate' => 0, 'expense_account_id' => $f['transit']->id,
        ]],
    ], $over);
}

function vcFlowCreate($test, array $f, array $over = []): VendorCredit
{
    $test->actingAs($f['user'])->post("/{$f['company']->slug}/vendor-credits", vcFlowPayload($f, $over))->assertSessionHasNoErrors();

    return VendorCredit::where('company_id', $f['company']->id)->latest('created_at')->firstOrFail();
}

function vcFlowBill(array $f, float $amount): Bill
{
    return Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id,
        'bill_number' => 'BILL-'.str()->random(8), 'bill_date' => '2026-09-01', 'due_date' => '2026-09-01',
        'status' => 'received', 'currency' => 'PKR', 'base_currency' => 'PKR',
        'subtotal' => $amount, 'total_amount' => $amount, 'paid_amount' => 0, 'balance' => $amount,
    ]);
}

function vcFlowSide(Transaction $t, string $accountId): array
{
    $rows = $t->journalEntries()->where('account_id', $accountId)->get();

    return [(float) $rows->sum('debit_amount'), (float) $rows->sum('credit_amount')];
}

function vcFlowOnStatement(array $f): bool
{
    return collect(app(VendorStatementService::class)->statement($f['vendor']->fresh())['rows'])->contains('type', 'vendor_credit');
}

test('a draft credit is not on the books or the statement, and posting it creates Dr AP Cr line account', function () {
    $f = vcFlowFixture();
    $credit = vcFlowCreate($this, $f);

    expect($credit->status)->toBe('draft')->and($credit->transaction_id)->toBeNull();
    expect(vcFlowOnStatement($f))->toBeFalse();

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/vendor-credits/{$credit->id}/post")
        ->assertSessionHasNoErrors()->assertRedirect();

    $credit->refresh();
    expect($credit->status)->toBe('received')->and($credit->transaction_id)->not->toBeNull()->and($credit->received_at)->not->toBeNull();

    $t = Transaction::find($credit->transaction_id);
    expect(vcFlowSide($t, $f['ap']->id))->toBe([54953.0, 0.0])
        ->and(vcFlowSide($t, $f['transit']->id))->toBe([0.0, 54953.0])
        ->and(vcFlowSide($t, $f['expense']->id))->toBe([0.0, 0.0]);

    $statement = app(VendorStatementService::class)->statement($f['vendor']->fresh());
    expect(collect($statement['rows'])->firstWhere('type', 'vendor_credit')['debit'])->toBe(54953.0)
        ->and($statement['closing_balance'])->toBe(-54953.0);
});

test('posting a credit that is not a draft is refused', function () {
    $f = vcFlowFixture();
    $credit = vcFlowCreate($this, $f, ['status' => 'received']);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/vendor-credits/{$credit->id}/post")
        ->assertSessionHas('error');
    expect(Transaction::where('reference_id', $credit->id)->count())->toBe(1);
});

test('a draft credit can be edited freely', function () {
    $f = vcFlowFixture();
    $credit = vcFlowCreate($this, $f);

    $this->actingAs($f['user'])->put("/{$f['company']->slug}/vendor-credits/{$credit->id}", vcFlowPayload($f, [
        'amount' => 1000, 'reason' => 'fine for short delivery',
        'line_items' => [['description' => 'Fine', 'quantity' => 1, 'unit_price' => 1000, 'expense_account_id' => $f['fines']->id]],
    ]))->assertSessionHasNoErrors();

    $credit->refresh();
    expect($credit->status)->toBe('draft')->and((float) $credit->amount)->toBe(1000.0)
        ->and($credit->reason)->toBe('fine for short delivery')
        ->and($credit->items()->count())->toBe(1)
        ->and($credit->items()->first()->expense_account_id)->toBe($f['fines']->id)
        ->and($credit->transaction_id)->toBeNull();
});

test('editing a posted credit reverses the old journal and posts the new one', function () {
    $f = vcFlowFixture();
    $credit = vcFlowCreate($this, $f, ['status' => 'received']);
    $oldId = $credit->transaction_id;

    $this->actingAs($f['user'])->put("/{$f['company']->slug}/vendor-credits/{$credit->id}", vcFlowPayload($f, [
        'amount' => 40000,
        'line_items' => [['description' => 'Fine', 'quantity' => 1, 'unit_price' => 40000, 'expense_account_id' => $f['fines']->id]],
    ]))->assertSessionHasNoErrors();

    $credit->refresh();
    expect($credit->transaction_id)->not->toBe($oldId)->and((float) $credit->amount)->toBe(40000.0);
    expect(Transaction::find($oldId)->reversed_by_id)->not->toBeNull();

    $new = Transaction::find($credit->transaction_id);
    expect(vcFlowSide($new, $f['ap']->id))->toBe([40000.0, 0.0])
        ->and(vcFlowSide($new, $f['fines']->id))->toBe([0.0, 40000.0]);
});

test('applying a credit reduces the bill balance', function () {
    $f = vcFlowFixture();
    $credit = vcFlowCreate($this, $f, ['status' => 'received']);
    $bill = vcFlowBill($f, 100000);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/vendor-credits/{$credit->id}/apply", [
        'applications' => [['bill_id' => $bill->id, 'amount_applied' => 54953]],
    ])->assertSessionHasNoErrors();

    $bill->refresh();
    expect((float) $bill->balance)->toBe(45047.0)->and($bill->status)->toBe('partial');
    expect($credit->fresh()->status)->toBe('applied');

    // An applied credit is no longer editable.
    $this->actingAs($f['user'])->put("/{$f['company']->slug}/vendor-credits/{$credit->id}", vcFlowPayload($f))
        ->assertSessionHas('error');
});

test('applying a draft credit posts it first', function () {
    $f = vcFlowFixture();
    $credit = vcFlowCreate($this, $f);
    $bill = vcFlowBill($f, 100000);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/vendor-credits/{$credit->id}/apply", [
        'applications' => [['bill_id' => $bill->id, 'amount_applied' => 20000]],
    ])->assertSessionHasNoErrors();

    $credit->refresh();
    expect($credit->transaction_id)->not->toBeNull()->and($credit->status)->toBe('received');
    expect((float) $bill->fresh()->balance)->toBe(80000.0);
    expect(vcFlowOnStatement($f))->toBeTrue();
});

test('voiding a posted credit reverses its journal and takes it off the statement', function () {
    $f = vcFlowFixture();
    $credit = vcFlowCreate($this, $f, ['status' => 'received']);
    $oldId = $credit->transaction_id;
    expect(vcFlowOnStatement($f))->toBeTrue();

    $this->actingAs($f['user'])->delete("/{$f['company']->slug}/vendor-credits/{$credit->id}")->assertSessionHasNoErrors();

    expect($credit->fresh()->status)->toBe('void')
        ->and(Transaction::find($oldId)->reversed_by_id)->not->toBeNull();
    expect(vcFlowOnStatement($f))->toBeFalse();
});
