<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\DefaultAccountProvisioner;
use App\Modules\Accounting\Services\OpeningBalanceAccounts;
use App\Modules\FuelStation\Services\OpeningStockLedgerService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/** Stock -> Adjustment says why: stock already owned goes to opening balance, not income. */
function adjReasonFixture(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Adj Station', 'slug' => 'adj-'.str()->lower(str()->random(8)),
        'owner_id' => $owner->id, 'base_currency' => 'PKR', 'industry_code' => 'retail',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $owner->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($owner, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    if (! DB::table('public.currencies')->where('code', 'PKR')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);
    }

    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $stock = Account::create(['company_id' => $company->id, 'code' => '1250', 'name' => 'Shelf stock', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit', 'is_active' => true]);
    $expense = Account::create(['company_id' => $company->id, 'code' => '6100', 'name' => 'Station expense', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'is_active' => true]);
    $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'WH-A', 'name' => 'Shop', 'warehouse_type' => 'standard', 'is_active' => true, 'is_primary' => true]);
    $item = Item::create([
        'company_id' => $company->id, 'sku' => 'SHELF-1', 'name' => 'Biscuits', 'item_type' => 'product', 'unit_of_measure' => 'pack',
        'track_inventory' => true, 'is_purchasable' => true, 'is_sellable' => true, 'cost_price' => 100, 'selling_price' => 130,
        'currency' => 'PKR', 'asset_account_id' => $stock->id, 'is_active' => true,
    ]);

    return compact('owner', 'company', 'stock', 'expense', 'warehouse', 'item');
}

function adjReasonPost($test, array $f, array $extra)
{
    return $test->actingAs($f['owner'])->post("/{$f['company']->slug}/stock/adjustment", array_merge([
        'warehouse_id' => $f['warehouse']->id, 'item_id' => $f['item']->id,
        'quantity' => 10, 'unit_cost' => 100, 'movement_date' => '2026-09-20',
    ], $extra));
}

function adjReasonAccountIds(string $txnId): array
{
    return JournalEntry::where('transaction_id', $txnId)->pluck('account_id')->all();
}

test('stock we already had books as an opening movement and opening journal, no gain', function () {
    $f = adjReasonFixture();

    adjReasonPost($this, $f, ['reason' => 'already_had'])->assertRedirect();

    $movement = StockMovement::where('item_id', $f['item']->id)->firstOrFail();
    $journal = Transaction::where('company_id', $f['company']->id)->where('reference_type', OpeningStockLedgerService::REFERENCE)->whereNull('deleted_at')->firstOrFail();
    $equity = app(OpeningBalanceAccounts::class)->resolve($f['company']->id)['equity'];
    $gain = app(DefaultAccountProvisioner::class)->ensureTransitAccounts($f['company'])['transit_gain_account_id'];

    expect($movement->movement_type)->toBe('opening')
        ->and($movement->reason)->toContain('already had')
        ->and((float) $movement->total_cost)->toBe(1000.0)
        ->and(adjReasonAccountIds($journal->id))->toContain($f['stock']->id, $equity)
        ->and(JournalEntry::where('account_id', $gain)->count())->toBe(0)
        ->and(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'adjustment')->count())->toBe(0);
});

test('a stock gain posts to Transit Gain', function () {
    $f = adjReasonFixture();

    adjReasonPost($this, $f, ['reason' => 'gain'])->assertRedirect();

    $movement = StockMovement::where('item_id', $f['item']->id)->firstOrFail();
    $gain = app(DefaultAccountProvisioner::class)->ensureTransitAccounts($f['company'])['transit_gain_account_id'];

    expect($movement->movement_type)->toBe('adjustment_in')
        ->and($movement->reason)->toBe('Stock gain')
        ->and(adjReasonAccountIds($movement->gl_transaction_id))->toContain($gain, $f['stock']->id);
});

test('own use posts to the chosen expense account and needs one', function () {
    $f = adjReasonFixture();

    adjReasonPost($this, $f, ['reason' => 'own_use', 'quantity' => -2])->assertSessionHasErrors('expense_account_id');
    expect(StockMovement::where('item_id', $f['item']->id)->count())->toBe(0);

    adjReasonPost($this, $f, ['reason' => 'own_use', 'quantity' => -2, 'expense_account_id' => $f['expense']->id])->assertRedirect();

    $movement = StockMovement::where('item_id', $f['item']->id)->firstOrFail();
    $loss = app(DefaultAccountProvisioner::class)->ensureTransitAccounts($f['company'])['transit_loss_account_id'];
    $accounts = adjReasonAccountIds($movement->gl_transaction_id);

    expect($movement->movement_type)->toBe('adjustment_out')
        ->and($accounts)->toContain($f['expense']->id, $f['stock']->id)
        ->and($accounts)->not->toContain($loss);
});

test('lost or damaged posts to Transit Loss', function () {
    $f = adjReasonFixture();

    adjReasonPost($this, $f, ['reason' => 'lost_damaged', 'quantity' => -1])->assertRedirect();

    $movement = StockMovement::where('item_id', $f['item']->id)->firstOrFail();
    $loss = app(DefaultAccountProvisioner::class)->ensureTransitAccounts($f['company'])['transit_loss_account_id'];

    expect(adjReasonAccountIds($movement->gl_transaction_id))->toContain($loss);
});

test('a reason is required and must fit the direction', function () {
    $f = adjReasonFixture();

    adjReasonPost($this, $f, [])->assertSessionHasErrors('reason');
    adjReasonPost($this, $f, ['reason' => 'lost_damaged'])->assertSessionHasErrors('reason');
    adjReasonPost($this, $f, ['reason' => 'already_had', 'quantity' => -1])->assertSessionHasErrors('reason');

    expect(StockMovement::where('item_id', $f['item']->id)->count())->toBe(0);
});
