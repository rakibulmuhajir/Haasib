<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\DailyCloseReconciliationService;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

/*
 * The credit-sales close fixture, shared by many FuelStation tests. It lived inside
 * DailyCloseCreditSalesTest.php, so the others only worked when the whole folder ran together.
 */
function creditCloseFixture(): array
{
    $user = User::factory()->create();
    test()->actingAs($user);
    $company = Company::create(['name' => 'Credit close', 'slug' => 'credit-close-'.str()->random(10), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $accounts = [];
    foreach ([['1050','asset','cash','debit'], ['1020','asset','bank','debit'], ['1100','asset','accounts_receivable','debit'], ['1200','asset','inventory','debit'], ['4100','revenue','other_income','credit'], ['5100','cogs','cost_of_goods_sold','debit']] as [$code,$type,$subtype,$normal]) {
        $accounts[$code] = Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal, 'currency' => $type === 'asset' && $code !== '1200' ? 'PKR' : null, 'is_active' => true]);
    }
    StationSettings::create(['company_id' => $company->id, 'payment_channels' => [
        ['code' => 'pos', 'label' => 'HBL POS', 'type' => 'card_pos', 'enabled' => true, 'clearing_account_id' => $accounts['1020']->id],
    ]]);
    $customer = Customer::create(['company_id' => $company->id, 'customer_number' => 'C-1', 'name' => 'Truck owner', 'base_currency' => 'PKR', 'ar_account_id' => $accounts['1100']->id, 'is_active' => true]);
    $item = Item::create(['company_id' => $company->id, 'sku' => 'PETROL', 'name' => 'Petrol', 'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR', 'avg_cost' => 250]);
    $tank = Warehouse::create(['company_id' => $company->id, 'code' => 'T1', 'name' => 'Tank', 'linked_item_id' => $item->id]);
    $pump = Pump::create(['company_id' => $company->id, 'name' => 'Pump', 'tank_id' => $tank->id]);
    $nozzle = Nozzle::create(['company_id' => $company->id, 'pump_id' => $pump->id, 'tank_id' => $tank->id, 'item_id' => $item->id, 'code' => 'N1', 'label' => 'Front']);
    $payload = ['date' => '2026-09-15', 'opening_cash' => 10000, 'closing_cash' => 25000,
        'nozzle_readings' => [['nozzle_id' => $nozzle->id, 'item_id' => $item->id, 'opening_electronic' => 0, 'closing_electronic' => 100, 'liters_sold' => 100, 'sale_rate' => 300]],
        'payment_receipts' => ['pos' => ['entries' => [['last_four' => '1234', 'amount' => 9000]]]],
        'credit_sales' => [['customer_id' => $customer->id, 'amount' => 6000, 'reference' => 'Slip 42']],
    ];
    return compact('user', 'company', 'customer', 'accounts', 'payload');
}

function creditClosePost(array $f): array
{
    return app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
}

