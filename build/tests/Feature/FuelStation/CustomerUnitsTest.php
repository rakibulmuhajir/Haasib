<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\CustomerUnit;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\ConsolidatedInvoiceService;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Modules\FuelStation\Services\CustomerPeriodSummaryService;
use App\Modules\FuelStation\Services\FuelSaleService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';
require_once __DIR__.'/CustomerFuelDiscountFixtures.php';

/**
 * A fuel customer's vehicles (customer units): picked on a sale, stored as the invoice's unit_id,
 * and shown wherever the customer's purchases are -- the invoice, the statement, a consolidated
 * invoice and the period summary.
 */
function vehicleFuelFixture(): array
{
    $f = discountedCustomerFixture();
    $unitA = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'name' => 'GAL-1804']);
    $unitB = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'name' => 'TLF-866']);

    $sell = fn (array $row) => app(FuelSaleService::class)->createSale($row + [
        'sale_type' => 'credit', 'customer_id' => $f['customer']->id, 'item_id' => $f['diesel']->id,
    ]);
    // Diesel is 300 a litre, no discount.
    $sell(['quantity' => 100, 'sale_date' => '2026-09-12', 'unit_id' => $unitA->id]);
    $sell(['quantity' => 50, 'sale_date' => '2026-09-14', 'unit_id' => $unitA->id]);
    $sell(['quantity' => 20, 'sale_date' => '2026-09-15', 'unit_id' => $unitB->id]);
    $sell(['quantity' => 10, 'sale_date' => '2026-09-16']);

    return $f + ['unitA' => $unitA, 'unitB' => $unitB];
}

test('a fuel sale outside the close stores the picked unit on the invoice, and refuses another customer unit', function () {
    $f = vehicleFuelFixture();

    $invoice = Invoice::where('company_id', $f['company']->id)->where('unit_id', $f['unitB']->id)->sole();
    // The vehicle is its own field; the reference is left for the slip number.
    expect($invoice->reference)->toBeNull()
        ->and(Invoice::where('company_id', $f['company']->id)->whereNull('unit_id')->count())->toBe(1);

    $other = Customer::create(['company_id' => $f['company']->id, 'customer_number' => 'C-2', 'name' => 'Other', 'base_currency' => 'PKR', 'ar_account_id' => $f['accounts']['1100']->id, 'is_active' => true]);
    expect(fn () => app(FuelSaleService::class)->createSale([
        'sale_type' => 'credit', 'customer_id' => $other->id, 'item_id' => $f['diesel']->id,
        'quantity' => 5, 'sale_date' => '2026-09-17', 'unit_id' => $f['unitA']->id,
    ]))->toThrow(\InvalidArgumentException::class);
});

test('a daily close credit sale with a unit stores unit_id on its invoice', function () {
    $f = creditCloseFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    $unit = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'name' => 'GAL-1804']);
    $f['payload']['credit_sales'][0]['unit_id'] = $unit->id;

    creditClosePost($f);

    expect(Invoice::where('company_id', $f['company']->id)->sole()->unit_id)->toBe($unit->id);
});

test('the invoice page props include the vehicle', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create(['name' => 'Vehicle Show Co', 'slug' => 'vehicle-show-'.str()->lower(str()->random(8)), 'owner_id' => $owner->id, 'base_currency' => 'PKR']);
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
    $ar = Account::create(['company_id' => $company->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $customer = Customer::create(['company_id' => $company->id, 'customer_number' => 'CUST-0001', 'name' => 'Shrimp Farm', 'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'is_active' => true, 'created_by_user_id' => $owner->id]);
    $unit = CustomerUnit::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'name' => 'GAL-1804']);

    $result = app(CompanyContextService::class)->withContext($company, fn () => app(CommandBus::class)->dispatch('invoice.create', [
        'customer' => $customer->id, 'currency' => 'PKR', 'date' => '2026-09-25', 'draft' => true, 'unit_id' => $unit->id,
        'line_items' => [['description' => 'Fuel', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0]],
    ], $owner, true));

    $props = $this->actingAs($owner)->get("/{$company->slug}/invoices/{$result['data']['id']}")->assertOk()->viewData('page')['props'];

    expect($props['invoice']['unit']['name'])->toBe('GAL-1804');
});

test('statement invoice rows carry the vehicle', function () {
    $f = vehicleFuelFixture();

    $rows = collect(app(CustomerStatementService::class)->statement($f['customer'])['rows'])->where('type', 'invoice');

    expect($rows->pluck('vehicle')->values()->all())->toBe(['GAL-1804', 'GAL-1804', 'TLF-866', null]);
});

test('a consolidated invoice shows each line vehicle and a subtotal per vehicle', function () {
    $f = vehicleFuelFixture();
    $service = app(ConsolidatedInvoiceService::class);

    $rows = $service->rowsFor($f['company']->id, $f['customer']->id, '2026-09-01', '2026-09-30');
    expect(collect($rows)->pluck('vehicle')->all())->toBe(['GAL-1804', 'GAL-1804', 'TLF-866', null]);

    $id = $service->create($f['company'], [
        'customer_id' => $f['customer']->id, 'from' => '2026-09-01', 'to' => '2026-09-30',
        'keys' => array_column($rows, 'key'),
    ], $f['user']->id);
    $doc = $service->document($f['company'], $id);

    expect($doc['show_vehicle'])->toBeTrue();
    $subtotals = collect($doc['lines'])->where('is_subtotal', true)->keyBy('unit');
    expect($subtotals['GAL-1804']['quantity'])->toBe(150.0)
        ->and($subtotals['GAL-1804']['amount'])->toBe(45000.0)
        ->and($subtotals['TLF-866']['quantity'])->toBe(20.0)
        ->and($subtotals['TLF-866']['amount'])->toBe(6000.0)
        ->and(collect($doc['lines'])->where('vehicle', 'GAL-1804')->count())->toBe(2);
});

test('the period summary splits by vehicle, with No vehicle last, only for a customer who has units', function () {
    $f = vehicleFuelFixture();
    $company = $f['company'];

    $summary = app(CustomerPeriodSummaryService::class)->run($company->id, $f['customer']->id, '2026-09-01', '2026-09-30', $company->slug);

    expect(array_column($summary['vehicles'], 'name'))->toBe(['GAL-1804', 'TLF-866', 'No vehicle'])
        ->and($summary['vehicles'][0])->toMatchArray(['quantity' => 150.0, 'gross' => 45000.0, 'discount' => 0.0, 'net' => 45000.0])
        ->and($summary['vehicles'][2])->toMatchArray(['unit_id' => null, 'quantity' => 10.0, 'net' => 3000.0])
        ->and(array_sum(array_column($summary['vehicles'], 'net')))->toBe($summary['totals']['net']);

    $plain = Customer::create(['company_id' => $company->id, 'customer_number' => 'C-3', 'name' => 'No units', 'base_currency' => 'PKR', 'ar_account_id' => $f['accounts']['1100']->id, 'is_active' => true]);
    expect(app(CustomerPeriodSummaryService::class)->run($company->id, $plain->id, '2026-09-01', '2026-09-30', $company->slug)['vehicles'])->toBe([]);
});

test('the vehicle can be named on a posted close invoice afterwards, but only one of that customer', function () {
    $f = creditCloseFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    creditClosePost($f);
    $invoice = Invoice::where('company_id', $f['company']->id)->sole();
    $unit = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'name' => 'GBE-930']);

    // The close's invoice guard lets the vehicle through: a label, no money.
    app(\App\Services\CompanyContextService::class)->withContext($f['company'], fn () => app(\App\Services\CommandBus::class)->dispatch('invoice.set_unit', ['id' => $invoice->id, 'unit_id' => $unit->id], $f['user'], true));
    expect($invoice->fresh()->unit_id)->toBe($unit->id);

    $other = Customer::create(['company_id' => $f['company']->id, 'customer_number' => 'C-9', 'name' => 'Other', 'base_currency' => 'PKR', 'ar_account_id' => $f['accounts']['1100']->id, 'is_active' => true]);
    $foreign = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $other->id, 'name' => 'X-1']);
    expect(fn () => app(\App\Services\CompanyContextService::class)->withContext($f['company'], fn () => app(\App\Services\CommandBus::class)->dispatch('invoice.set_unit', ['id' => $invoice->id, 'unit_id' => $foreign->id], $f['user'], true)))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    // Money on it is still guarded.
    expect(fn () => \Illuminate\Support\Facades\DB::table('acct.invoices')->where('id', $invoice->id)->update(['subtotal' => 1]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('a fuel sale keeps the slip number as its reference, next to the vehicle', function () {
    $f = vehicleFuelFixture();
    app(FuelSaleService::class)->createSale([
        'sale_type' => 'credit', 'customer_id' => $f['customer']->id, 'item_id' => $f['diesel']->id,
        'quantity' => 70, 'sale_date' => '2026-09-18', 'unit_id' => $f['unitA']->id, 'reference' => '110',
    ]);

    $invoice = Invoice::where('company_id', $f['company']->id)->whereDate('invoice_date', '2026-09-18')->sole();
    expect($invoice->reference)->toBe('110')->and($invoice->unit_id)->toBe($f['unitA']->id);
});

test('a consolidated line with no vehicle is not grouped by its slip number when the customer has vehicles', function () {
    $f = vehicleFuelFixture();
    app(FuelSaleService::class)->createSale([
        'sale_type' => 'credit', 'customer_id' => $f['customer']->id, 'item_id' => $f['diesel']->id,
        'quantity' => 5, 'sale_date' => '2026-09-18', 'reference' => '306',
    ]);

    $row = collect(app(ConsolidatedInvoiceService::class)->rowsFor($f['company']->id, $f['customer']->id, '2026-09-01', '2026-09-30'))
        ->firstWhere('reference', '306');
    expect($row['unit'])->toBeNull()->and($row['vehicle'])->toBeNull();
});

test('reopening a close keeps a vehicle named on its invoice after posting', function () {
    $f = creditCloseFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    creditClosePost($f);
    $invoice = Invoice::where('company_id', $f['company']->id)->sole();
    $unit = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'name' => 'GENERATOR']);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.set_unit', ['id' => $invoice->id, 'unit_id' => $unit->id], $f['user'], true));

    $close = \App\Modules\Accounting\Models\Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->sole();
    app(\App\Modules\FuelStation\Services\DailyCloseReopenService::class)->reopen($close, $f['user'], 'Split the credit sale by vehicle.');

    $draft = json_decode(DB::table('fuel.daily_close_drafts')->where('company_id', $f['company']->id)->value('payload'), true);
    expect($draft['credit_sales'][0]['unit_id'])->toBe($unit->id);
});

test('a posted close invoice splits by vehicle into invoices of their own, the day stays as posted and cannot be reopened', function () {
    $f = creditCloseFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    creditClosePost($f);
    $invoice = Invoice::where('company_id', $f['company']->id)->sole();
    $total = round((float) $invoice->total_amount, 2);
    $gen = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'name' => 'GENERATOR']);
    $truck = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'name' => 'GBK-339']);
    $close = \App\Modules\Accounting\Models\Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->sole();
    $journal = fn () => DB::table('acct.journal_entries')->where('transaction_id', $close->id)->orderBy('id')->get(['account_id', 'debit_amount', 'credit_amount'])->toArray();
    $before = $journal();

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('correction.invoice_split', [
        'invoice_id' => $invoice->id,
        'reason' => 'One sale was two vehicles.',
        'shares' => [
            ['customer_id' => $f['customer']->id, 'amount' => 1000, 'unit_id' => $truck->id, 'quantity' => 4, 'reference' => '110'],
            ['customer_id' => $f['customer']->id, 'amount' => round($total - 1000, 2), 'unit_id' => $gen->id],
        ],
    ], $f['user'], true));

    $shares = Invoice::where('company_id', $f['company']->id)->whereKeyNot($invoice->id)->with('lineItems')->orderBy('invoice_number')->get();
    expect($shares)->toHaveCount(2)
        ->and($shares[0]->unit_id)->toBe($truck->id)
        ->and($shares[0]->reference)->toBe('110')
        ->and((float) $shares[0]->lineItems[0]->quantity)->toBe(4.0)
        ->and((float) $shares[0]->lineItems[0]->unit_price)->toBe(250.0)
        ->and($shares[1]->unit_id)->toBe($gen->id)
        ->and(round((float) $shares->sum('total_amount'), 2))->toBe($total)
        ->and((float) $invoice->fresh()->balance)->toBe(0.0)
        ->and($journal())->toEqual($before);

    // Litres and money are counted once: the shares, not the credited original as well.
    $summary = app(CustomerPeriodSummaryService::class)->run($f['company']->id, $f['customer']->id, '2026-09-01', '2026-09-30', $f['company']->slug);
    expect(round(array_sum(array_column($summary['vehicles'], 'gross')), 2))->toBe($total);

    expect(fn () => app(\App\Modules\FuelStation\Services\DailyCloseReopenService::class)->reopen($close->fresh(), $f['user'], 'Try to edit the day.'))
        ->toThrow(\RuntimeException::class, 'split after posting');
});

test('a vehicle share must be one of that customer vehicles', function () {
    $f = creditCloseFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    creditClosePost($f);
    $invoice = Invoice::where('company_id', $f['company']->id)->sole();
    $other = Customer::create(['company_id' => $f['company']->id, 'customer_number' => 'C-9', 'name' => 'Other', 'base_currency' => 'PKR', 'ar_account_id' => $f['accounts']['1100']->id, 'is_active' => true]);
    $foreign = CustomerUnit::create(['company_id' => $f['company']->id, 'customer_id' => $other->id, 'name' => 'X-1']);
    $total = round((float) $invoice->total_amount, 2);

    expect(fn () => app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('correction.invoice_split', [
        'invoice_id' => $invoice->id, 'reason' => 'Wrong vehicle.',
        'shares' => [
            ['customer_id' => $f['customer']->id, 'amount' => 1000, 'unit_id' => $foreign->id],
            ['customer_id' => $f['customer']->id, 'amount' => round($total - 1000, 2)],
        ],
    ], $f['user'], true)))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('the slip number can be written on a posted close invoice afterwards, its money still guarded', function () {
    $f = creditCloseFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    creditClosePost($f);
    $invoice = Invoice::where('company_id', $f['company']->id)->sole();

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.set_reference', ['id' => $invoice->id, 'reference' => ' 422 '], $f['user'], true));
    expect($invoice->fresh()->reference)->toBe('422');

    expect(fn () => DB::table('acct.invoices')->where('id', $invoice->id)->update(['subtotal' => 1]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
