<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * Shared by the bill-payment edit tests: a company with an open Sept 2026
 * period, a bank and a cash account, one AP account, one vendor with two
 * unpaid bills, and helpers to record a payment against them the same way
 * BillPaymentController::store() does.
 */
function billPaymentEditFixture(): array
{
    $user = User::factory()->withoutTwoFactor()->create();

    $company = Company::create([
        'name' => 'Bill Payment Edit',
        'slug' => 'bill-payment-edit-'.str()->lower(str()->random(8)),
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
    $augPeriod = AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'August', 'period_number' => 8, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
    $sepPeriod = AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);

    $bank = Account::create(['company_id' => $company->id, 'code' => '1020', 'name' => 'Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $cash = Account::create(['company_id' => $company->id, 'code' => '1050', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $ap = Account::create(['company_id' => $company->id, 'code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'currency' => 'PKR', 'is_active' => true]);

    $vendor = Vendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VEND-0001',
        'name' => 'Fuel Depot',
        'base_currency' => 'PKR',
        'ap_account_id' => $ap->id,
        'is_active' => true,
        'created_by_user_id' => $user->id,
    ]);

    $bill = Bill::create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'BILL-0001',
        'bill_date' => '2026-08-05',
        'due_date' => '2026-09-05',
        'status' => 'received',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'subtotal' => 20000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 20000,
        'paid_amount' => 0,
        'balance' => 20000,
        'base_amount' => 20000,
        'created_by_user_id' => $user->id,
    ]);

    $bill2 = Bill::create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'BILL-0002',
        'bill_date' => '2026-08-06',
        'due_date' => '2026-09-06',
        'status' => 'received',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'subtotal' => 5000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 5000,
        'paid_amount' => 0,
        'balance' => 5000,
        'base_amount' => 5000,
        'created_by_user_id' => $user->id,
    ]);

    return compact('user', 'company', 'bank', 'cash', 'ap', 'vendor', 'bill', 'bill2', 'augPeriod', 'sepPeriod');
}

/**
 * Pays $bill in full (or for $amount if given) from $account, dated
 * 2026-09-01 by default -- the same command BillPaymentController::store()
 * dispatches. Returns the created BillPayment.
 */
function payBillForEditTest(array $f, Bill $bill, ?float $amount = null, string $date = '2026-09-01', ?Account $account = null): \App\Modules\Accounting\Models\BillPayment
{
    $account ??= $f['bank'];
    $amount ??= (float) $bill->balance;

    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.create', [
        'vendor_id' => $f['vendor']->id,
        'payment_date' => $date,
        'amount' => $amount,
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_method' => 'bank_transfer',
        'payment_account_id' => $account->id,
        'allocations' => [['bill_id' => $bill->id, 'amount_allocated' => $amount]],
    ], $f['user'], true));

    return \App\Modules\Accounting\Models\BillPayment::find($result['data']['id']);
}
