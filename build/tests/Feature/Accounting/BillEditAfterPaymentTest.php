<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Models\Item;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * The bug this covers: a fuel station entered a delivery bill with the line
 * typed as free text ("Petrol", no item linked) posting straight to 1050 Cash
 * on Hand, then paid it. The owner's rule is that a bill stays editable until
 * its day is locked (or the period is closed) -- paying it does not freeze
 * it. See UpdateAction, BillController and bills/Show.vue + Edit.vue.
 */
function billEditFixture(): array
{
    $user = User::factory()->withoutTwoFactor()->create();

    $company = Company::create([
        'name' => 'Bill Edit After Payment',
        'slug' => 'bill-edit-after-payment-'.str()->lower(str()->random(8)),
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

    $cash = Account::create(['company_id' => $company->id, 'code' => '1050', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $bank = Account::create(['company_id' => $company->id, 'code' => '1020', 'name' => 'Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $ap = Account::create(['company_id' => $company->id, 'code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'currency' => 'PKR', 'is_active' => true]);
    $inventory = Account::create(['company_id' => $company->id, 'code' => '1200', 'name' => 'Fuel Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit', 'currency' => null, 'is_active' => true]);

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
        'sku' => 'PETROL',
        'name' => 'Petrol',
        'item_type' => 'product',
        'unit_of_measure' => 'liter',
        'currency' => 'PKR',
        'cost_price' => 250,
        'track_inventory' => true,
        'asset_account_id' => $inventory->id,
        'is_active' => true,
    ]);

    // Simulate the pre-existing bug: a delivery bill with the line typed as
    // free text (no item linked), posted straight to cash on hand. This has
    // to be built directly -- CreateAction now refuses a cash/bank line
    // account outright, so this shape can only exist as legacy data.
    $bill = Bill::create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'BILL-0001',
        'bill_date' => '2026-09-10',
        'due_date' => '2026-09-10',
        'status' => 'received',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'subtotal' => 25000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 25000,
        'paid_amount' => 0,
        'balance' => 25000,
        'base_amount' => 25000,
        'created_by_user_id' => $user->id,
    ]);

    BillLineItem::create([
        'company_id' => $company->id,
        'bill_id' => $bill->id,
        'line_number' => 1,
        'item_id' => null,
        'description' => 'Petrol',
        'quantity' => 100,
        'quantity_received' => 0,
        'unit_price' => 250,
        'tax_rate' => 0,
        'discount_rate' => 0,
        'line_total' => 25000,
        'tax_amount' => 0,
        'total' => 25000,
        'expense_account_id' => $cash->id,
        'created_by_user_id' => $user->id,
    ]);

    $billTransaction = app(PostingService::class)->postBill($bill->fresh(['vendor', 'lineItems']));
    $bill->update(['transaction_id' => $billTransaction->id]);

    // Pay the bill in full -- this is the state the owner's rule addresses:
    // a paid bill must still be editable, up until its day is locked.
    app(CompanyContextService::class)->withContext($company, fn () => app(CommandBus::class)->dispatch('bill_payment.create', [
        'vendor_id' => $vendor->id,
        'payment_date' => '2026-09-11',
        'amount' => 25000,
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_method' => 'bank_transfer',
        'payment_account_id' => $bank->id,
        'allocations' => [['bill_id' => $bill->id, 'amount_allocated' => 25000]],
    ], $user, true));

    $bill->refresh();
    expect($bill->status)->toBe('paid')->and((float) $bill->balance)->toBe(0.0);

    return compact('user', 'company', 'cash', 'bank', 'ap', 'inventory', 'vendor', 'item', 'bill', 'billTransaction', 'period');
}

test('a paid bill with an unlinked cash line can be edited to link a tracked item, reversing and reposting to inventory', function () {
    $f = billEditFixture();

    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bills/{$f['bill']->id}", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-10',
        'due_date' => '2026-09-10',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'line_items' => [
            [
                'item_id' => $f['item']->id,
                'description' => 'Petrol',
                'quantity' => 100,
                'unit_price' => 250,
                'tax_rate' => 0,
                'discount_rate' => 0,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();
    expect(session('error'))->toBeNull();

    $bill = $f['bill']->fresh(['lineItems']);
    expect($bill->status)->toBe('paid')
        ->and((float) $bill->balance)->toBe(0.0)
        ->and((float) $bill->paid_amount)->toBe(25000.0);

    $line = $bill->lineItems->first();
    expect($line->item_id)->toBe($f['item']->id)
        ->and($line->expense_account_id)->toBe($f['inventory']->id);

    $oldTransaction = Transaction::find($f['billTransaction']->id);
    expect($oldTransaction->reversed_by_id)->not->toBeNull();

    expect($bill->transaction_id)->not->toBe($f['billTransaction']->id);
    $newTransaction = Transaction::find($bill->transaction_id);
    $debitAccounts = $newTransaction->journalEntries->where('debit_amount', '>', 0)->pluck('account_id')->all();
    expect($debitAccounts)->toContain($f['inventory']->id)
        ->and($debitAccounts)->not->toContain($f['cash']->id);
});

test('editing a paid bill to a total below what was already paid is refused', function () {
    $f = billEditFixture();

    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bills/{$f['bill']->id}", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-10',
        'due_date' => '2026-09-10',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'line_items' => [
            [
                'item_id' => $f['item']->id,
                'description' => 'Petrol',
                'quantity' => 10,
                'unit_price' => 250,
                'tax_rate' => 0,
                'discount_rate' => 0,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('line_items');
    expect($f['bill']->fresh()->total_amount)->toEqualWithDelta(25000, 0.01);
});

test('a bill line with quantity already received cannot be replaced', function () {
    $f = billEditFixture();
    $f['bill']->lineItems()->update(['item_id' => $f['item']->id, 'quantity_received' => 50]);

    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bills/{$f['bill']->id}", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-10',
        'due_date' => '2026-09-10',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'line_items' => [
            [
                'item_id' => $f['item']->id,
                'description' => 'Petrol',
                'quantity' => 100,
                'unit_price' => 250,
                'tax_rate' => 0,
                'discount_rate' => 0,
            ],
        ],
    ]);

    expect(session('error'))->toContain('Stock on this bill has already been received');
});

test('a cash account on a bill line is refused when creating a bill', function () {
    $f = billEditFixture();

    $response = $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-12',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'line_items' => [
            [
                'description' => 'Miscellaneous purchase',
                'quantity' => 1,
                'unit_price' => 500,
                'expense_account_id' => $f['cash']->id,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('line_items.0.expense_account_id');
});

test('a bill dated in a closed accounting period cannot be edited', function () {
    $f = billEditFixture();
    $f['period']->update(['is_closed' => true]);

    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bills/{$f['bill']->id}", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-10',
        'due_date' => '2026-09-10',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'notes' => 'Trying to edit inside a closed period',
        'line_items' => [
            [
                'item_id' => $f['item']->id,
                'description' => 'Petrol',
                'quantity' => 100,
                'unit_price' => 250,
                'tax_rate' => 0,
                'discount_rate' => 0,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('date');
});
