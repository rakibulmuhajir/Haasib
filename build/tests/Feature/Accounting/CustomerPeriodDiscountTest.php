<?php

use App\Modules\Accounting\Actions\CreditNote\ApplyAction;
use App\Modules\Accounting\Actions\CreditNote\CreateAction;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\CustomerDiscountPolicy;
use App\Modules\Accounting\Services\CustomerPeriodDiscountService;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Modules\Accounting\Services\ReceivablesAgingReportService;
use App\Modules\FuelStation\Models\CustomerFuelDiscount;
use App\Modules\FuelStation\Services\CustomerFuelDiscountService;
use App\Modules\FuelStation\Services\FuelSaleService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../FuelStation/CustomerFuelDiscountFixtures.php';

function periodDiscountFixture(): array
{
    $f = discountedCustomerFixture();
    $f['petrol']->update(['fuel_category' => 'petrol']);
    app(CompanyContextService::class)->setContext($f['company']);
    $f['company']->update(['ar_account_id' => $f['accounts']['1100']->id, 'income_account_id' => $f['accounts']['4100']->id]);

    return $f;
}

function periodDiscountInvoice(array $f, float $amount = 100000, string $date = '2026-09-10', array $overrides = []): Invoice
{
    $invoice = Invoice::create(array_merge([
        'company_id' => $f['company']->id, 'customer_id' => $f['customer']->id,
        'invoice_number' => 'PD-'.str()->random(10), 'invoice_date' => $date, 'due_date' => $date,
        'currency' => 'PKR', 'base_currency' => 'PKR', 'subtotal' => $amount, 'total_amount' => $amount,
        'paid_amount' => 0, 'balance' => $amount, 'status' => 'sent',
    ], $overrides));
    $invoice->lineItems()->create(['company_id' => $f['company']->id, 'line_number' => 1,
        'item_id' => $f['petrol']->id, 'description' => 'Petrol', 'quantity' => $amount / 300,
        'unit_price' => 300, 'line_total' => $amount, 'total' => $amount]);

    return $invoice;
}

function periodDiscountData(array $overrides = []): array
{
    return array_merge(['from' => '2026-09-01', 'to' => '2026-09-30', 'credit_date' => '2026-09-30',
        'method' => 'fixed', 'value' => 5000], $overrides);
}

function postPeriodDiscount(array $f, array $data = []): CreditNote
{
    $data = periodDiscountData($data);
    $service = app(CustomerPeriodDiscountService::class);
    $preview = $service->preview($f['company'], $f['customer'], $data);

    return $service->post($f['company'], $f['customer']->id, $data + ['preview_token' => $preview['snapshot']['token']], $f['user']->id);
}

function periodDiscountHttpOwner(array $f): void
{
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    addCompanyMemberRow($f['company'], $f['user'], 'owner');
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($f['user'], 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    test()->actingAs($f['user']);
}

test('company discount timing and customer overrides govern future fuel invoices without repricing history', function () {
    $f = periodDiscountFixture();
    CustomerFuelDiscount::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id,
        'item_id' => $f['diesel']->id, 'discount_type' => 'per_litre', 'value' => 3]);
    $sale = ['sale_type' => 'credit', 'item_id' => $f['diesel']->id, 'quantity' => 100,
        'sale_date' => '2026-09-15', 'customer_id' => $f['customer']->id];
    $first = app(FuelSaleService::class)->createSale($sale);
    $f['company']->update(['settings' => ['customer_discount_timing' => 'period', 'contact_phone' => '123']]);
    $second = app(FuelSaleService::class)->createSale($sale);
    expect((float) $first->fresh()->discount_amount)->toBe(300.0)
        ->and((float) $second->discount_amount)->toBe(0.0)
        ->and(app(CustomerDiscountPolicy::class)->invoiceCustomerIds($f['company']->id))->not->toContain($f['customer']->id);
    $f['customer']->update(['discount_timing' => 'invoice']);
    expect(app(CustomerFuelDiscountService::class)->for($f['company']->id, $f['customer']->id, $f['diesel']->id))->not->toBeNull();
    $f['customer']->update(['discount_timing' => 'period']);
    $f['company']->update(['settings' => ['customer_discount_timing' => 'invoice']]);
    expect(app(CustomerDiscountPolicy::class)->timing($f['customer']))->toBe('period');
});

test('one period credit leaves original sales unchanged and reconciles journal statement and aging', function () {
    $f = periodDiscountFixture();
    $first = periodDiscountInvoice($f);
    $second = periodDiscountInvoice($f, 100000, '2026-09-20');
    $credit = postPeriodDiscount($f);
    expect((float) $first->fresh()->total_amount)->toBe(100000.0)
        ->and((float) $first->fresh()->discount_amount)->toBe(0.0)
        ->and((float) $first->fresh()->balance)->toBe(95000.0)
        ->and((float) $second->fresh()->balance)->toBe(100000.0)
        ->and($credit->applications()->count())->toBe(1)
        ->and($credit->status)->toBe('applied');
    $entries = $credit->transaction->journalEntries;
    expect((float) $entries->sum('debit_amount'))->toBe(5000.0)->and((float) $entries->sum('credit_amount'))->toBe(5000.0);
    $discountAccount = DB::table('acct.accounts')->where('company_id', $f['company']->id)->where('code', '4210')->value('id');
    expect((float) $entries->where('account_id', $discountAccount)->sum('debit_amount'))->toBe(5000.0);
    $statement = app(CustomerStatementService::class)->statement($f['customer'], '2026-09-01', '2026-09-30');
    $summary = app(\App\Modules\FuelStation\Services\CustomerPeriodSummaryService::class)->run($f['company']->id, $f['customer']->id, '2026-09-01', '2026-09-30', $f['company']->slug);
    expect($summary['money']['period_discount'])->toBe(5000.0)->and($summary['money']['other'])->toBe(0.0);
    expect($statement['closing_balance'])->toBe(195000.0)
        ->and(collect($statement['rows'])->where('type', 'credit_note')->count())->toBe(1)
        ->and(app(ReceivablesAgingReportService::class)->run($f['company']->id, '2026-09-30')['totals']['total'])->toBe(195000.0);
});

test('period preview excludes already discounted credited taxed foreign and draft sales', function () {
    $f = periodDiscountFixture();
    periodDiscountInvoice($f);
    periodDiscountInvoice($f, 10000, '2026-09-10', ['discount_amount' => 100, 'total_amount' => 9900, 'balance' => 9900]);
    periodDiscountInvoice($f, 10000, '2026-09-10', ['tax_amount' => 100]);
    periodDiscountInvoice($f, 10000, '2026-09-10', ['status' => 'draft']);
    periodDiscountInvoice($f, 10000, '2026-09-10', ['currency' => 'USD', 'exchange_rate' => 280, 'base_amount' => 2800000]);
    $credited = periodDiscountInvoice($f);
    CreditNote::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'invoice_id' => $credited->id,
        'credit_note_number' => 'CN-PRIOR', 'credit_date' => '2026-09-15', 'amount' => 500, 'currency' => 'PKR',
        'base_currency' => 'PKR', 'base_amount' => 500, 'status' => 'issued', 'reason' => 'Prior adjustment']);
    $preview = app(CustomerPeriodDiscountService::class)->preview($f['company'], $f['customer'], periodDiscountData(['method' => 'percent', 'value' => 5]));
    expect($preview['snapshot']['eligible_amount'])->toBe(100000.0)->and($preview['amount'])->toBe(5000.0)->and($preview['invoice_count'])->toBe(1);
});

test('per litre period discount uses saved fuel quantities and creates a dated credit', function () {
    $f = periodDiscountFixture();
    periodDiscountInvoice($f, 30000);
    periodDiscountInvoice($f, 60000, '2026-09-20');
    $credit = postPeriodDiscount($f, ['method' => 'per_litre', 'value' => 2]);
    expect((float) $credit->amount)->toBe(600.0)->and((float) $credit->period_discount['litres'])->toBe(300.0)
        ->and($credit->credit_date->toDateString())->toBe('2026-09-30');
});

test('overlap stale previews excessive credits and closed dates cannot post partial adjustments', function () {
    $f = periodDiscountFixture();
    $invoice = periodDiscountInvoice($f);
    $service = app(CustomerPeriodDiscountService::class);
    $data = periodDiscountData();
    $preview = $service->preview($f['company'], $f['customer'], $data);
    $invoice->update(['paid_amount' => 100, 'balance' => 99900]);
    expect(fn () => $service->post($f['company'], $f['customer']->id, $data + ['preview_token' => $preview['snapshot']['token']], $f['user']->id))->toThrow(ValidationException::class);
    expect(fn () => postPeriodDiscount($f, ['value' => 100001]))->toThrow(ValidationException::class);
    AccountingPeriod::where('company_id', $f['company']->id)->update(['is_closed' => true]);
    expect(fn () => postPeriodDiscount($f))->toThrow(ValidationException::class);
    expect(CreditNote::where('company_id', $f['company']->id)->count())->toBe(0);
    AccountingPeriod::where('company_id', $f['company']->id)->update(['is_closed' => false]);
    postPeriodDiscount($f);
    expect(fn () => postPeriodDiscount($f))->toThrow(ValidationException::class);
    expect(CreditNote::where('company_id', $f['company']->id)->count())->toBe(1);
    expect(fn () => $invoice->fresh()->update(['total_amount' => 90000]))->toThrow(ValidationException::class);
});

test('unused period credit can settle a later invoice and void restores only its own allocations', function () {
    $f = periodDiscountFixture();
    periodDiscountInvoice($f, 30000, '2026-09-10', ['status' => 'paid', 'paid_amount' => 30000, 'balance' => 0]);
    $credit = postPeriodDiscount($f);
    expect((float) $credit->balance)->toBe(5000.0)
        ->and(app(CustomerStatementService::class)->statement($f['customer'])['available_note_credit'])->toBe(5000.0);
    $next = periodDiscountInvoice($f, 10000, '2026-09-30');
    app(CompanyContextService::class)->setContext($f['company']);
    app(ApplyAction::class)->handle(['credit_note' => $credit->id, 'invoice' => $next->id, 'amount' => 2000, 'applied_at' => '2026-09-30']);
    app(ApplyAction::class)->handle(['credit_note' => $credit->id, 'invoice' => $next->id, 'amount' => 3000, 'applied_at' => '2026-09-30']);
    expect($credit->applications()->count())->toBe(1)->and((float) $next->fresh()->balance)->toBe(5000.0);
    $next->fresh()->update(['paid_amount' => 5100, 'balance' => 4900]);
    app(CustomerPeriodDiscountService::class)->void($credit, 'Correct negotiated discount');
    expect((float) $next->fresh()->balance)->toBe(9900.0)->and((float) $next->fresh()->paid_amount)->toBe(100.0)
        ->and($credit->fresh()->status)->toBe('void')->and($credit->applications()->count())->toBe(0)
        ->and($credit->transaction->fresh()->reversed_by_id)->not->toBeNull();
});

test('base currency credit note creation and draft issue do not require a secondary currency or rate', function () {
    $f = periodDiscountFixture();
    periodDiscountHttpOwner($f);
    $this->post("/{$f['company']->slug}/credit-notes", ['customer_id' => $f['customer']->id, 'amount' => 5000,
        'base_currency' => 'PKR', 'reason' => 'Monthly concession', 'status' => 'issued', 'credit_date' => '2026-09-30'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $credit = CreditNote::where('company_id', $f['company']->id)->sole();
    expect($credit->currency)->toBe('PKR')->and($credit->exchange_rate)->toBeNull()
        ->and((float) $credit->base_amount)->toBe(5000.0)->and($credit->transaction_id)->not->toBeNull();
    $draft = app(CreateAction::class)->handle(['customer' => $f['customer']->id, 'credit_date' => '2026-09-30',
        'reason' => 'Draft concession', 'status' => 'draft', 'line_items' => [['description' => 'Discount', 'quantity' => 1, 'unit_price' => 1000]]]);
    $this->put("/{$f['company']->slug}/credit-notes/{$draft['data']['id']}", ['status' => 'issued'])->assertSessionHasNoErrors()->assertRedirect();
    expect(CreditNote::findOrFail($draft['data']['id'])->transaction_id)->not->toBeNull();
});

test('period discount route posts reviewed figures and exposes saved sources and undo', function () {
    $f = periodDiscountFixture();
    periodDiscountHttpOwner($f);
    $invoice = periodDiscountInvoice($f);
    $data = periodDiscountData();
    $this->get("/{$f['company']->slug}/customers/{$f['customer']->id}/period-discount?".http_build_query($data))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('accounting/customers/PeriodDiscount', false)->where('preview.invoice_count', 1));
    $preview = app(CustomerPeriodDiscountService::class)->preview($f['company'], $f['customer'], $data);
    $this->post("/{$f['company']->slug}/customers/{$f['customer']->id}/period-discount", $data + ['preview_token' => $preview['snapshot']['token']])
        ->assertSessionHasNoErrors()->assertRedirect();
    $credit = CreditNote::where('company_id', $f['company']->id)->sole();
    $this->get("/{$f['company']->slug}/credit-notes/{$credit->id}")
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('periodSources.data.0.id', $invoice->id)->where('canVoid', true));
    $this->post("/{$f['company']->slug}/credit-notes/{$credit->id}/void", ['reason' => 'Replace the period discount'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect((float) $invoice->fresh()->balance)->toBe(100000.0);
});

test('company settings preserve unrelated values and customer timing can be cleared to inherit', function () {
    $f = periodDiscountFixture();
    periodDiscountHttpOwner($f);
    $f['company']->update(['settings' => ['contact_phone' => '123', 'modules' => ['payroll' => false]]]);
    $this->patch("/{$f['company']->slug}/settings", ['customer_discount_timing' => 'period'])->assertSessionHasNoErrors()->assertRedirect();
    expect($f['company']->fresh()->settings)->toMatchArray(['contact_phone' => '123', 'modules' => ['payroll' => false], 'customer_discount_timing' => 'period']);
    $this->patch("/{$f['company']->slug}/customers/{$f['customer']->id}", ['discount_timing' => 'invoice'])->assertSessionHasNoErrors()->assertRedirect();
    expect($f['customer']->fresh()->discount_timing)->toBe('invoice');
    $this->patch("/{$f['company']->slug}/customers/{$f['customer']->id}", ['discount_timing' => null])->assertSessionHasNoErrors()->assertRedirect();
    expect($f['customer']->fresh()->discount_timing)->toBeNull()
        ->and(app(CustomerDiscountPolicy::class)->timing($f['customer']->fresh()))->toBe('period');
});

test('foreign credits require a rate and base credits discard an irrelevant supplied rate', function () {
    $f = periodDiscountFixture();
    app(CompanyContextService::class)->setContext($f['company']);
    \App\Models\CompanyCurrency::create(['company_id' => $f['company']->id, 'currency_code' => 'USD', 'exchange_rate' => 280]);
    $params = ['customer' => $f['customer']->id, 'credit_date' => '2026-09-30', 'reason' => 'Adjustment',
        'status' => 'issued', 'line_items' => [['description' => 'Credit', 'quantity' => 1, 'unit_price' => 100]]];
    expect(fn () => app(CreateAction::class)->handle($params + ['currency' => 'USD']))->toThrow(ValidationException::class);
    $created = app(CreateAction::class)->handle($params + ['currency' => 'USD', 'exchange_rate' => 280]);
    $foreign = CreditNote::findOrFail($created['data']['id']);
    expect($foreign->currency)->toBe('USD')->and((float) $foreign->base_amount)->toBe(28000.0)
        ->and((float) $foreign->transaction->total_debit)->toBe(28000.0);
    $created = app(CreateAction::class)->handle($params + ['currency' => 'PKR', 'exchange_rate' => 999]);
    $base = CreditNote::findOrFail($created['data']['id']);
    expect($base->exchange_rate)->toBeNull()->and((float) $base->base_amount)->toBe(100.0);
});

test('daily close manual sales also leave discounts for period end', function () {
    $f = periodDiscountFixture();
    CustomerFuelDiscount::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id,
        'item_id' => $f['petrol']->id, 'discount_type' => 'per_litre', 'value' => 3]);
    $f['customer']->update(['discount_timing' => 'period']);
    app(\App\Modules\FuelStation\Services\DailyCloseCreditSaleService::class)->prepare($f['company']->id, '2026-09-15', [[
        'customer_id' => $f['customer']->id, 'item_id' => $f['petrol']->id, 'litres' => 100, 'amount' => 30000,
    ]], 30000, 30000, $f['user']);
    $invoice = Invoice::where('company_id', $f['company']->id)->sole();
    expect((float) $invoice->discount_amount)->toBe(0.0)->and((float) $invoice->total_amount)->toBe(30000.0);
});

test('foreign-company customer and unprivileged users cannot use the period discount routes', function () {
    $f = periodDiscountFixture();
    periodDiscountHttpOwner($f);
    $outsider = \App\Models\User::factory()->create();
    addCompanyMemberRow($f['company'], $outsider, 'operations');
    $other = \App\Models\Company::create(['name' => 'Other discount company', 'slug' => 'other-discount-'.str()->random(8), 'base_currency' => 'PKR']);
    enterCompany($other);
    $foreignCustomer = Customer::create(['company_id' => $other->id, 'customer_number' => 'OTHER', 'name' => 'Other customer', 'base_currency' => 'PKR']);
    enterCompany($f['company']);
    $this->get("/{$f['company']->slug}/customers/{$foreignCustomer->id}/period-discount")->assertNotFound();
    $this->actingAs($outsider)->post("/{$f['company']->slug}/customers/{$f['customer']->id}/period-discount", periodDiscountData() + ['preview_token' => str_repeat('a', 64)])->assertForbidden();
    expect(CreditNote::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('period coverage also blocks correction and later issuance of an older draft credit', function () {
    $f = periodDiscountFixture();
    $invoice = periodDiscountInvoice($f);
    $draft = app(CreateAction::class)->handle(['customer' => $f['customer']->id, 'invoice' => $invoice->id,
        'credit_date' => '2026-09-30', 'reason' => 'Draft adjustment', 'status' => 'draft',
        'line_items' => [['description' => 'Discount', 'quantity' => 1, 'unit_price' => 1000]]]);
    postPeriodDiscount($f);
    $other = Customer::create(['company_id' => $f['company']->id, 'customer_number' => 'C-2', 'name' => 'Other buyer', 'base_currency' => 'PKR']);
    expect(fn () => app(\App\Modules\Accounting\Services\CorrectionService::class)->invoiceChangeCustomer($invoice->fresh(), $other->id, 'Change buyer'))
        ->toThrow(ValidationException::class);
    expect(fn () => app(\App\Modules\Accounting\Actions\CreditNote\UpdateAction::class)->handle([
        'id' => $draft['data']['id'], 'customer' => $f['customer']->id, 'invoice' => $invoice->id,
        'credit_date' => '2026-09-30', 'reason' => 'Draft adjustment', 'status' => 'issued',
        'line_items' => [['description' => 'Discount', 'quantity' => 1, 'unit_price' => 1000]],
    ]))->toThrow(ValidationException::class);
    expect(CreditNote::findOrFail($draft['data']['id'])->status)->toBe('draft');
});

test('opening balances are not treated as new sales eligible for period discounts', function () {
    $f = periodDiscountFixture();
    $opening = periodDiscountInvoice($f, 40000);
    periodDiscountInvoice($f, 30000);
    $f['company']->update(['settings' => ['opening_balances' => ['invoice_ids' => [$opening->id]]]]);
    $preview = app(CustomerPeriodDiscountService::class)->preview($f['company'], $f['customer'], periodDiscountData());
    expect($preview['snapshot']['eligible_amount'])->toBe(30000.0)->and($preview['invoice_count'])->toBe(1);
});
