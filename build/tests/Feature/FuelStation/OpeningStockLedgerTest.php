<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\OpeningStockLedgerService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Opening stock used to be litres in the tanks only: the books started fuel stock at zero and
 * it went negative as the first days were costed out of it.
 */
test('opening stock is carried into the books and follows later edits', function () {
    $company = Company::create(['name' => 'Opening Co', 'slug' => 'open-'.str()->lower(str()->random(8)), 'base_currency' => 'PKR']);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'August', 'period_number' => 8, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
    $stock = Account::create(['company_id' => $company->id, 'code' => '1200', 'name' => 'Fuel Inventory - Petrol', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit', 'is_active' => true]);
    $item = Item::create(['company_id' => $company->id, 'sku' => 'PMG', 'name' => 'Petrol', 'item_type' => 'product', 'unit_of_measure' => 'liter',
        'currency' => 'PKR', 'fuel_category' => 'petrol', 'asset_account_id' => $stock->id]);
    $tank = Warehouse::create(['company_id' => $company->id, 'code' => 'T1', 'name' => 'Tank', 'linked_item_id' => $item->id]);
    $opening = StockMovement::create(['company_id' => $company->id, 'warehouse_id' => $tank->id, 'item_id' => $item->id, 'movement_date' => '2026-08-31',
        'movement_type' => 'opening', 'quantity' => 2520, 'unit_cost' => 337, 'total_cost' => 849240]);

    $ledger = app(OpeningStockLedgerService::class);
    $first = $ledger->sync($company->id);
    $stockBalance = fn () => (float) JournalEntry::join('acct.transactions as t', 't.id', '=', 'acct.journal_entries.transaction_id')
        ->whereNull('t.deleted_at')->where('acct.journal_entries.account_id', $stock->id)
        ->sum(DB::raw('acct.journal_entries.debit_amount - acct.journal_entries.credit_amount'));

    expect($first->transaction_date->toDateString())->toBe('2026-08-31')
        ->and($stockBalance())->toBe(849240.0);

    // Nothing changed: nothing re-posted.
    expect($ledger->sync($company->id)->id)->toBe($first->id);

    // The opening is corrected: one journal, at the new value.
    $opening->update(['quantity' => 2600, 'total_cost' => 876200]);
    $ledger->sync($company->id);

    expect(Transaction::where('company_id', $company->id)->where('reference_type', OpeningStockLedgerService::REFERENCE)->whereNull('deleted_at')->count())->toBe(1)
        ->and($stockBalance())->toBe(876200.0);
});
