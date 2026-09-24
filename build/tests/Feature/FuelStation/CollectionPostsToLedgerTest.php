<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * Fixture for CollectionController's rewrite (Task A): the Collections screen now records
 * through the core payment.create command-bus action instead of writing its own
 * credit_collection transaction with no journal lines. Company owner with real role/
 * permission rows (payment.create is checked for real here - unlike the Daily Close's own
 * inline rows, which dispatch with skipPermission), so the test exercises the actual HTTP
 * route and its permission gate, the same way OpeningBalanceFixtures::openingBalanceHttpFixture
 * does for Accounting.
 */
function collectionFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Fuel Collections Co',
        'slug' => 'fuel-collections-'.str()->lower(str()->random(10)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
        'settings' => ['modules' => ['fuel_station' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($user, 'owner'),
    );
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    enterCompany($company);

    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);

    $mk = fn (string $code, string $name, string $type, string $subtype, string $normal) => Account::create([
        'company_id' => $company->id, 'code' => $code, 'name' => $name, 'type' => $type,
        'subtype' => $subtype, 'normal_balance' => $normal, 'currency' => 'PKR', 'is_active' => true,
    ]);
    $cash = $mk('1050', 'Cash on Hand', 'asset', 'cash', 'debit');
    $bank = $mk('1020', 'Operating Bank', 'asset', 'bank', 'debit');
    $ar = $mk('1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit');

    $customer = Customer::create([
        'company_id' => $company->id, 'customer_number' => 'C-1', 'name' => 'Truck Owner',
        'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'is_active' => true,
    ]);

    $older = Invoice::create([
        'company_id' => $company->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-OLD-'.str()->random(6),
        'invoice_date' => '2026-09-01', 'due_date' => '2026-10-01', 'status' => 'sent',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'subtotal' => 3000, 'total_amount' => 3000,
        'paid_amount' => 0, 'balance' => 3000,
    ]);
    $newer = Invoice::create([
        'company_id' => $company->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-NEW-'.str()->random(6),
        'invoice_date' => '2026-09-10', 'due_date' => '2026-10-10', 'status' => 'sent',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'subtotal' => 4000, 'total_amount' => 4000,
        'paid_amount' => 0, 'balance' => 4000,
    ]);

    return compact('user', 'company', 'customer', 'cash', 'bank', 'ar', 'older', 'newer');
}

test('a cash collection lowers AR and raises cash, applied to the oldest invoice first', function () {
    $f = collectionFixture();
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/collections", [
        'customer_id' => $f['customer']->id,
        'amount' => 5000,
        'payment_method' => 'cash',
        'collection_date' => '2026-09-15',
        'reference' => 'COL-TEST',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $payment = Payment::where('company_id', $f['company']->id)->sole();
    expect((float) $payment->amount)->toBe(5000.0)
        ->and($payment->deposit_account_id)->toBe($f['cash']->id)
        ->and($payment->payment_method)->toBe('cash')
        ->and($payment->customer_id)->toBe($f['customer']->id);

    // Oldest invoice (INV-OLD, 3000) settles first, the remaining 2000 lands on the newer one.
    expect((float) $f['older']->fresh()->balance)->toBe(0.0)
        ->and($f['older']->fresh()->status)->toBe('paid')
        ->and((float) $f['newer']->fresh()->balance)->toBe(2000.0);

    $cashDebits = (float) DB::table('acct.journal_entries')
        ->where('account_id', $f['cash']->id)->sum('debit_amount');
    $arCredits = (float) DB::table('acct.journal_entries')
        ->where('account_id', $f['ar']->id)->sum('credit_amount');
    expect($cashDebits)->toBe(5000.0)->and($arCredits)->toBe(5000.0);
});

test('a bank collection requires a deposit account and posts into that account, not the cash drawer', function () {
    $f = collectionFixture();

    // No deposit_account_id: refused before anything is recorded.
    $missing = test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/collections", [
        'customer_id' => $f['customer']->id,
        'amount' => 1000,
        'payment_method' => 'bank',
        'collection_date' => '2026-09-15',
    ]);
    $missing->assertSessionHasErrors('deposit_account_id');
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/collections", [
        'customer_id' => $f['customer']->id,
        'amount' => 1000,
        'payment_method' => 'bank',
        'deposit_account_id' => $f['bank']->id,
        'collection_date' => '2026-09-15',
    ]);
    $response->assertSessionHasNoErrors();

    $payment = Payment::where('company_id', $f['company']->id)->sole();
    expect($payment->deposit_account_id)->toBe($f['bank']->id)
        ->and($payment->payment_method)->toBe('bank_transfer');

    $cashDebits = (float) DB::table('acct.journal_entries')
        ->where('account_id', $f['cash']->id)->sum('debit_amount');
    $bankDebits = (float) DB::table('acct.journal_entries')
        ->where('account_id', $f['bank']->id)->sum('debit_amount');
    expect($cashDebits)->toBe(0.0)->and($bankDebits)->toBe(1000.0);
});

test('collections index and show list the recorded payment, not the retired credit_collection transaction type', function () {
    $f = collectionFixture();

    test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/collections", [
        'customer_id' => $f['customer']->id,
        'amount' => 2000,
        'payment_method' => 'cash',
        'collection_date' => '2026-09-15',
        'reference' => 'COL-LIST',
    ])->assertSessionHasNoErrors();

    $payment = Payment::where('company_id', $f['company']->id)->sole();

    $index = test()->actingAs($f['user'])->get("/{$f['company']->slug}/fuel/collections?start_date=2026-09-01&end_date=2026-09-30");
    $index->assertOk();
    $index->assertInertia(fn ($page) => $page
        ->component('FuelStation/Collections/Index')
        ->where('collections.0.id', $payment->id)
        ->where('collections.0.amount', 2000.0)
        ->where('collections.0.customer_name', $f['customer']->name));

    $show = test()->actingAs($f['user'])->get("/{$f['company']->slug}/fuel/collections/{$payment->id}");
    $show->assertOk();
    $show->assertInertia(fn ($page) => $page
        ->component('FuelStation/Collections/Show')
        ->where('collection.id', $payment->id)
        ->where('collection.reference', 'COL-LIST'));
});
