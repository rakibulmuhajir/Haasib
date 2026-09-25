<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * Shared by the supplier-advance tests: a company with an open Sept 2026 period, a bank
 * account, an AP account, an expense account, and a vendor with NO bills yet -- unlike
 * BillPaymentEditFixtures::billPaymentEditFixture(), which already has two open bills, this
 * one starts empty so a payment recorded against this vendor is a pure advance.
 */
function supplierAdvanceFixture(): array
{
    $user = User::factory()->withoutTwoFactor()->create();

    $company = Company::create([
        'name' => 'Supplier Advance',
        'slug' => 'supplier-advance-'.str()->lower(str()->random(8)),
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
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'August', 'period_number' => 8, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);

    $bank = Account::create(['company_id' => $company->id, 'code' => '1020', 'name' => 'Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $ap = Account::create(['company_id' => $company->id, 'code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'currency' => 'PKR', 'is_active' => true]);
    $expense = Account::create(['company_id' => $company->id, 'code' => '5000', 'name' => 'Fuel Purchases', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'currency' => null, 'is_active' => true]);

    $vendor = Vendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VEND-0001',
        'name' => 'Total Parco',
        'base_currency' => 'PKR',
        'ap_account_id' => $ap->id,
        'is_active' => true,
        'created_by_user_id' => $user->id,
    ]);

    return compact('user', 'company', 'bank', 'ap', 'expense', 'vendor');
}

/**
 * Records a bill payment against $f['vendor'] the same way BillPaymentController::store()
 * does -- $allocations defaults to none, so with no bills yet the whole amount is a pure
 * advance.
 */
function paySupplierAdvance(array $f, float $amount, string $date = '2026-09-01', array $allocations = []): \App\Modules\Accounting\Models\BillPayment
{
    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.create', [
        'vendor_id' => $f['vendor']->id,
        'payment_date' => $date,
        'amount' => $amount,
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_method' => 'bank_transfer',
        'payment_account_id' => $f['bank']->id,
        'allocations' => $allocations,
    ], $f['user'], true));

    return \App\Modules\Accounting\Models\BillPayment::find($result['data']['id']);
}

/**
 * Creates and posts (status: received) a bill for $f['vendor'] for $amount, dispatching
 * bill.create the same way BillController::store() does -- so Bill\CreateAction's own
 * auto-apply-vendor-advance hook runs exactly as it would in production.
 */
function postSupplierBill(array $f, float $amount, string $date = '2026-09-05', ?string $billNumber = null): \App\Modules\Accounting\Models\Bill
{
    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill.create', [
        'vendor_id' => $f['vendor']->id,
        'bill_number' => $billNumber,
        'bill_date' => $date,
        'status' => 'received',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'line_items' => [[
            'description' => 'Fuel delivery',
            'quantity' => 1,
            'unit_price' => $amount,
            'expense_account_id' => $f['expense']->id,
        ]],
    ], $f['user'], true));

    return \App\Modules\Accounting\Models\Bill::find($result['data']['id']);
}
