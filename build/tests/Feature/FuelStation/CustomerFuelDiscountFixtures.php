<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CurrentCompany;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * Shared fixture for a customer negotiated fuel discount (Rs/L or percent, per fuel item):
 * used by both CustomerFuelDiscountTest.php (pricing it on a fresh sale/close row) and
 * PostCloseDiscountTest.php (applying one after the close has already posted). Lived inside
 * CustomerFuelDiscountTest.php until PostCloseDiscountTest.php needed it too -- per project
 * convention, a fixture used by more than one *Test.php file lives in its own *Fixtures.php
 * file, never depended on from inside another Test.php.
 */
function discountedCustomerFixture(): array
{
    $f = creditCloseFixture();
    app(CurrentCompany::class)->set($f['company']);

    $petrol = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();

    $diesel = Item::create([
        'company_id' => $f['company']->id, 'sku' => 'DIESEL', 'name' => 'Diesel',
        'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR',
        'avg_cost' => 250, 'fuel_category' => 'diesel',
    ]);
    RateChange::create([
        'company_id' => $f['company']->id, 'item_id' => $diesel->id,
        'effective_date' => '2026-09-01', 'purchase_rate' => 250, 'sale_rate' => 300,
    ]);
    $dieselTank = Warehouse::create(['company_id' => $f['company']->id, 'code' => 'T2', 'name' => 'Diesel Tank', 'linked_item_id' => $diesel->id]);
    $dieselPump = Pump::create(['company_id' => $f['company']->id, 'name' => 'Pump2', 'tank_id' => $dieselTank->id]);
    $dieselNozzle = Nozzle::create(['company_id' => $f['company']->id, 'pump_id' => $dieselPump->id, 'tank_id' => $dieselTank->id, 'item_id' => $diesel->id, 'code' => 'N2', 'label' => 'Diesel']);

    // A close carrying any variance needs somewhere to post the balancing line.
    $f['accounts']['6180'] = Account::create([
        'company_id' => $f['company']->id, 'code' => '6180', 'name' => 'Cash Short/Over',
        'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_active' => true,
    ]);

    $f['petrol'] = $petrol;
    $f['diesel'] = $diesel;
    $f['dieselNozzle'] = $dieselNozzle;

    return $f;
}

function fuelDiscountsAccount(string $companyId): ?Account
{
    return Account::where('company_id', $companyId)->where('code', '4210')->first();
}
