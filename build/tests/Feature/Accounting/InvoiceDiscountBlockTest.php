<?php

use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\CustomerPeriodDiscountService;
use App\Modules\FuelStation\Models\CustomerFuelDiscount;
use App\Modules\FuelStation\Services\DailyCloseCreditSaleService;
use App\Modules\FuelStation\Services\DailyCloseReopenService;
use App\Modules\FuelStation\Services\FuelSaleService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../FuelStation/CustomerFuelDiscountFixtures.php';

/**
 * "No discount" on one invoice: whoever may give discounts can withhold every discount from a
 * single invoice, with a reason -- period discounts skip it, a stored per-invoice rate is not
 * applied when the sale is recorded with the tick, and nothing can add a discount to it later.
 */
function blockFixture(): array
{
    $f = discountedCustomerFixture();
    $f['petrol']->update(['fuel_category' => 'petrol']);
    app(CompanyContextService::class)->setContext($f['company']);
    $f['company']->update(['ar_account_id' => $f['accounts']['1100']->id, 'income_account_id' => $f['accounts']['4100']->id]);

    return $f;
}

function blockInvoice(array $f, float $amount = 100000, string $date = '2026-09-10', array $overrides = []): Invoice
{
    $invoice = Invoice::create(array_merge([
        'company_id' => $f['company']->id, 'customer_id' => $f['customer']->id,
        'invoice_number' => 'NB-'.str()->random(10), 'invoice_date' => $date, 'due_date' => $date,
        'currency' => 'PKR', 'base_currency' => 'PKR', 'subtotal' => $amount, 'total_amount' => $amount,
        'paid_amount' => 0, 'balance' => $amount, 'status' => 'sent',
    ], $overrides));
    $invoice->lineItems()->create(['company_id' => $f['company']->id, 'line_number' => 1,
        'item_id' => $f['petrol']->id, 'description' => 'Petrol', 'quantity' => $amount / 300,
        'unit_price' => 300, 'line_total' => $amount, 'total' => $amount]);

    return $invoice;
}

function blockPeriod(array $overrides = []): array
{
    return array_merge(['from' => '2026-09-01', 'to' => '2026-09-30', 'credit_date' => '2026-09-30',
        'method' => 'percent', 'value' => 5], $overrides);
}

function blockSet(array $f, Invoice $invoice, bool $blocked, ?string $reason = null): array
{
    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch(
        'invoice.set_discount_block', ['id' => $invoice->id, 'blocked' => $blocked, 'reason' => $reason], $f['user'], true));
}

function blockHttpOwner(array $f): void
{
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    addCompanyMemberRow($f['company'], $f['user'], 'owner');
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($f['user'], 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    test()->actingAs($f['user']);
}

test('a blocked invoice is left out of a period discount and listed with its reason', function () {
    $f = blockFixture();
    $open = blockInvoice($f);
    $blocked = blockInvoice($f, 50000, '2026-09-20');
    blockSet($f, $blocked, true, '  Paid late  ');

    expect($blocked->fresh()->discount_blocked)->toBeTrue()
        ->and($blocked->fresh()->discount_block_reason)->toBe('Paid late')
        ->and($blocked->fresh()->discount_blocked_by_user_id)->toBe($f['user']->id);

    $service = app(CustomerPeriodDiscountService::class);
    $preview = $service->preview($f['company'], $f['customer'], blockPeriod());
    expect($preview['invoice_count'])->toBe(1)
        ->and($preview['snapshot']['invoice_ids'])->toBe([$open->id])
        ->and($preview['amount'])->toBe(5000.0)
        ->and($preview['blocked'])->toHaveCount(1)
        ->and($preview['blocked'][0])->toMatchArray(['id' => $blocked->id, 'reason' => 'Paid late']);

    $credit = $service->post($f['company'], $f['customer']->id, blockPeriod() + ['preview_token' => $preview['snapshot']['token']], $f['user']->id);
    expect($credit->period_discount['invoice_ids'])->toBe([$open->id])
        ->and((float) $blocked->fresh()->balance)->toBe(50000.0);
});

test('allowing the discount again clears the reason and puts the invoice back in the period', function () {
    $f = blockFixture();
    $invoice = blockInvoice($f);
    blockSet($f, $invoice, true, 'Disputed');
    blockSet($f, $invoice, false);

    $fresh = $invoice->fresh();
    expect($fresh->discount_blocked)->toBeFalse()
        ->and($fresh->discount_block_reason)->toBeNull()
        ->and($fresh->discount_blocked_by_user_id)->toBeNull()
        ->and($fresh->discount_blocked_at)->toBeNull()
        ->and(app(CustomerPeriodDiscountService::class)->preview($f['company'], $f['customer'], blockPeriod())['invoice_count'])->toBe(1);
});

test('blocking needs a reason and refuses an invoice already discounted or covered by a period discount', function () {
    $f = blockFixture();
    $invoice = blockInvoice($f);
    expect(fn () => blockSet($f, $invoice, true, ''))->toThrow(ValidationException::class);

    $discounted = blockInvoice($f, 30000, '2026-09-12', ['subtotal' => 30000, 'discount_amount' => 300, 'total_amount' => 29700, 'balance' => 29700]);
    expect(fn () => blockSet($f, $discounted, true, 'No'))->toThrow(ValidationException::class, 'already has a discount');

    $service = app(CustomerPeriodDiscountService::class);
    $preview = $service->preview($f['company'], $f['customer'], blockPeriod());
    $credit = $service->post($f['company'], $f['customer']->id, blockPeriod() + ['preview_token' => $preview['snapshot']['token']], $f['user']->id);
    expect(fn () => blockSet($f, $invoice, true, 'Too late'))->toThrow(ValidationException::class, $credit->credit_note_number)
        ->and($invoice->fresh()->discount_blocked)->toBeFalse();
});

test('nothing can add a discount to a blocked invoice afterwards', function () {
    $f = blockFixture();
    $invoice = blockInvoice($f);
    blockSet($f, $invoice, true, 'Contract price');

    expect(fn () => $invoice->fresh()->forceFill(['discount_amount' => 1000, 'total_amount' => 99000, 'balance' => 99000])->save())
        ->toThrow(ValidationException::class, 'Contract price');
    expect((float) $invoice->fresh()->discount_amount)->toBe(0.0);
});

test('a fuel sale ticked no discount skips the stored rate and is blocked from the start', function () {
    $f = blockFixture();
    CustomerFuelDiscount::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id,
        'item_id' => $f['diesel']->id, 'discount_type' => 'per_litre', 'value' => 3]);
    $sale = ['sale_type' => 'credit', 'item_id' => $f['diesel']->id, 'quantity' => 100,
        'sale_date' => '2026-09-15', 'customer_id' => $f['customer']->id];

    $plain = app(FuelSaleService::class)->createSale($sale);
    $ticked = app(FuelSaleService::class)->createSale($sale + ['no_discount' => true, 'no_discount_reason' => 'Cash price agreed']);

    expect((float) $plain->fresh()->discount_amount)->toBe(300.0)
        ->and((float) $ticked->fresh()->discount_amount)->toBe(0.0)
        ->and((float) $ticked->fresh()->total_amount)->toBe(30000.0)
        ->and($ticked->fresh()->discount_blocked)->toBeTrue()
        ->and($ticked->fresh()->discount_block_reason)->toBe('Cash price agreed');
    expect(fn () => app(FuelSaleService::class)->createSale($sale + ['no_discount' => true, 'no_discount_reason' => 'x', 'discount_percent' => 2]))
        ->toThrow(\InvalidArgumentException::class);
});

test('a daily close credit row ticked no discount skips the stored rate and needs a reason', function () {
    $f = blockFixture();
    CustomerFuelDiscount::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id,
        'item_id' => $f['petrol']->id, 'discount_type' => 'per_litre', 'value' => 3]);
    $service = app(DailyCloseCreditSaleService::class);
    $row = ['customer_id' => $f['customer']->id, 'item_id' => $f['petrol']->id, 'litres' => 100, 'amount' => 30000];

    expect(fn () => $service->prepare($f['company']->id, '2026-09-15', [$row + ['no_discount' => true]], 30000, 30000, $f['user']))
        ->toThrow(ValidationException::class);

    $details = $service->prepare($f['company']->id, '2026-09-15', [$row + ['no_discount' => true, 'no_discount_reason' => 'Fleet rate expired']], 30000, 30000, $f['user']);
    $invoice = Invoice::where('company_id', $f['company']->id)->sole();
    expect($details[0]['discount_amount'])->toBe(0.0)
        ->and((float) $invoice->discount_amount)->toBe(0.0)
        ->and($invoice->discount_blocked)->toBeTrue()
        ->and($invoice->discount_block_reason)->toBe('Fleet rate expired');
});

test('a posted close invoice can be blocked, and reopening the day keeps the tick on its row', function () {
    $f = creditCloseFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    creditClosePost($f);
    $invoice = Invoice::where('company_id', $f['company']->id)->sole();

    blockSet($f, $invoice, true, 'Bounced cheque');
    expect($invoice->fresh()->discount_blocked)->toBeTrue();

    $close = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->sole();
    app(DailyCloseReopenService::class)->reopen($close, $f['user'], 'Fix the cash count.');
    $draft = json_decode(DB::table('fuel.daily_close_drafts')->where('company_id', $f['company']->id)->value('payload'), true);
    expect($draft['credit_sales'][0]['no_discount'])->toBeTrue()
        ->and($draft['credit_sales'][0]['no_discount_reason'])->toBe('Bounced cheque');
});

test('the invoice route blocks with a reason, the page shows it, and others cannot', function () {
    $f = blockFixture();
    blockHttpOwner($f);
    $invoice = blockInvoice($f);
    $url = "/{$f['company']->slug}/invoices/{$invoice->id}/discount-block";

    $this->patch($url, ['blocked' => true, 'reason' => ''])->assertSessionHasErrors('reason');
    $this->patch($url, ['blocked' => true, 'reason' => 'Returned goods'])->assertSessionHasNoErrors()->assertRedirect();
    $this->get("/{$f['company']->slug}/invoices/{$invoice->id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invoice.discount_blocked', true)
            ->where('invoice.discount_block_reason', 'Returned goods')
            ->where('canBlockDiscount', true)->where('discountBlockedBy', $f['user']->name));
    $this->get("/{$f['company']->slug}/customers/{$f['customer']->id}/period-discount?".http_build_query(blockPeriod()))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('preview.blocked.0.id', $invoice->id)->where('preview.blocked.0.reason', 'Returned goods'));

    $outsider = \App\Models\User::factory()->create();
    addCompanyMemberRow($f['company'], $outsider, 'operations');
    $this->actingAs($outsider)->patch($url, ['blocked' => false])->assertForbidden();
    expect($invoice->fresh()->discount_blocked)->toBeTrue();
});
