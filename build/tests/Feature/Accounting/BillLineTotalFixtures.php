<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * Shared by the bill line_total tests: a company with an open Sept 2026
 * period, an AP/bank/inventory/expense account each, one vendor, and one
 * tracked item with its tank -- everything a bill priced by total instead of
 * rate, or a received line later revalued, needs. Mirrors
 * BillEditAfterPaymentTest's billEditFixture(), which this doesn't reuse --
 * that helper lives inside a *Test.php file.
 */
function billLineTotalFixture(): array
{
    $user = User::factory()->withoutTwoFactor()->create();

    $company = Company::create([
        'name' => 'Bill Line Total',
        'slug' => 'bill-line-total-'.str()->lower(str()->random(8)),
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
    $period = AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);

    $bank = Account::create(['company_id' => $company->id, 'code' => '1020', 'name' => 'Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $ap = Account::create(['company_id' => $company->id, 'code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'currency' => 'PKR', 'is_active' => true]);
    $inventory = Account::create(['company_id' => $company->id, 'code' => '1200', 'name' => 'Fuel Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit', 'currency' => null, 'is_active' => true]);
    $expense = Account::create(['company_id' => $company->id, 'code' => '5000', 'name' => 'General Purchases', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'currency' => null, 'is_active' => true]);

    $vendor = Vendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VEND-0001',
        'name' => 'Fuel Depot',
        'base_currency' => 'PKR',
        'ap_account_id' => $ap->id,
        'is_active' => true,
        'created_by_user_id' => $user->id,
    ]);

    $item = Item::create([
        'company_id' => $company->id,
        'sku' => 'DIESEL',
        'name' => 'Diesel',
        'item_type' => 'product',
        'unit_of_measure' => 'liter',
        'currency' => 'PKR',
        'cost_price' => 0,
        'track_inventory' => true,
        'asset_account_id' => $inventory->id,
        'is_active' => true,
    ]);

    $tank = Warehouse::create([
        'company_id' => $company->id,
        'code' => 'TANK-1',
        'name' => 'Tank 1',
        'warehouse_type' => 'tank',
        'capacity' => 20000,
        'linked_item_id' => $item->id,
        'is_primary' => true,
        'is_active' => true,
        'created_by_user_id' => $user->id,
    ]);

    return compact('user', 'company', 'bank', 'ap', 'inventory', 'expense', 'vendor', 'item', 'tank', 'period');
}
