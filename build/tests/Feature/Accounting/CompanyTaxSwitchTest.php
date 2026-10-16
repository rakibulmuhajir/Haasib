<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\CompanyTaxSettings;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Jurisdiction;
use App\Modules\Accounting\Models\TaxRate;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\CompanyTax;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/BillLineTotalFixtures.php';

/** The one "Charge tax" switch: effective value, write paths, shared prop. */
function taxSwitchSet(Company $company, bool $on): void
{
    CompanyTaxSettings::where('company_id', $company->id)->delete();
    $row = new CompanyTaxSettings(['company_id' => $company->id]);
    $row->saveDeliberately(['tax_enabled' => $on]);
}

function taxSwitchBillPayload(array $f, int $taxRate): array
{
    return [
        'vendor_id' => $f['vendor']->id, 'bill_date' => '2026-09-10', 'status' => 'received',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'payment_terms' => 0,
        'line_items' => [['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => $taxRate, 'expense_account_id' => $f['expense']->id]],
    ];
}

function taxSwitchInvoiceFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Tax Switch Co', 'slug' => 'tax-switch-'.str()->lower(str()->random(8)), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $ar = Account::create(['company_id' => $company->id, 'code' => '1100', 'name' => 'AR', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR']);
    $customer = Customer::create(['company_id' => $company->id, 'customer_number' => 'C-1', 'name' => 'Buyer', 'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'is_active' => true]);

    return compact('user', 'company', 'customer');
}

function taxSwitchInvoice(array $f, int $taxRate): array
{
    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.create', [
        'customer' => $f['customer']->id, 'currency' => 'PKR', 'date' => '2026-09-10', 'draft' => true,
        'line_items' => [['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => $taxRate]],
    ], $f['user'], true));
}

test('a company that never chose and uses no tax is off', function () {
    $f = billLineTotalFixture();
    expect(app(CompanyTax::class)->enabled($f['company']))->toBeFalse();
    // An untouched row (what opening the Tax page used to create) changes nothing.
    CompanyTaxSettings::forCompany($f['company']);
    expect(app(CompanyTax::class)->enabled($f['company']))->toBeFalse();
});

test('tax is off by default: a tax rate alone does not turn it on, a choice does', function () {
    $f = billLineTotalFixture();
    TaxRate::create([
        'company_id' => $f['company']->id, 'jurisdiction_id' => Jurisdiction::query()->value('id'),
        'code' => 'GST', 'name' => 'GST', 'rate' => 18,
    ]);
    CompanyTaxSettings::forCompany($f['company']); // untouched row, false
    expect(app(CompanyTax::class)->enabled($f['company']))->toBeFalse();

    // Registered for tax at onboarding is a choice: on.
    $f['company']->forceFill(['tax_registered' => true])->save();
    expect(app(CompanyTax::class)->enabled($f['company']))->toBeTrue();

    // The saved switch wins over the onboarding choice.
    taxSwitchSet($f['company'], false);
    expect(app(CompanyTax::class)->enabled($f['company']))->toBeFalse();
});

test('the switch persists from the Tax settings page and is shared to the frontend', function () {
    $f = billLineTotalFixture();

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/bills/create")
        ->assertInertia(fn (Assert $page) => $page->where('auth.taxEnabled', false));

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/tax/settings", ['tax_enabled' => true])
        ->assertSessionHasNoErrors();
    expect(app(CompanyTax::class)->enabled($f['company']))->toBeTrue();
    $this->actingAs($f['user'])->get("/{$f['company']->slug}/bills/create")
        ->assertInertia(fn (Assert $page) => $page->where('auth.taxEnabled', true));

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/tax/settings", ['tax_enabled' => false])
        ->assertSessionHasNoErrors();
    expect(app(CompanyTax::class)->enabled($f['company']))->toBeFalse();
});

test('with tax off a bill ignores a sent tax rate: no tax in totals or journal', function () {
    $f = billLineTotalFixture();
    taxSwitchSet($f['company'], false);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", taxSwitchBillPayload($f, 10))
        ->assertSessionHasNoErrors();

    $bill = Bill::where('company_id', $f['company']->id)->firstOrFail();
    expect((float) $bill->tax_amount)->toBe(0.0)->and((float) $bill->total_amount)->toBe(1000.0);
    $entries = Transaction::findOrFail($bill->transaction_id)->journalEntries;
    expect(round((float) $entries->sum('debit_amount'), 2))->toBe(1000.0);
});

test('with tax on a bill still charges the sent rate', function () {
    $f = billLineTotalFixture();
    taxSwitchSet($f['company'], true);

    // A draft: posting tax needs a purchase-tax account this fixture does not have.
    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", ['status' => 'draft'] + taxSwitchBillPayload($f, 10))
        ->assertSessionHasNoErrors();

    $bill = Bill::where('company_id', $f['company']->id)->firstOrFail();
    expect((float) $bill->tax_amount)->toBe(100.0)->and((float) $bill->total_amount)->toBe(1100.0);
});

test('with tax off an invoice ignores a sent tax rate; with tax on it charges it', function () {
    $f = taxSwitchInvoiceFixture();

    taxSwitchSet($f['company'], false);
    taxSwitchInvoice($f, 10);
    $off = Invoice::where('company_id', $f['company']->id)->firstOrFail();
    expect((float) $off->tax_amount)->toBe(0.0)->and((float) $off->total_amount)->toBe(1000.0);

    taxSwitchSet($f['company'], true);
    taxSwitchInvoice($f, 10);
    $on = Invoice::where('company_id', $f['company']->id)->where('id', '!=', $off->id)->firstOrFail();
    expect((float) $on->tax_amount)->toBe(100.0)->and((float) $on->total_amount)->toBe(1100.0);
});

test('an old taxed invoice keeps its tax after the switch goes off', function () {
    $f = taxSwitchInvoiceFixture();
    taxSwitchSet($f['company'], true);
    taxSwitchInvoice($f, 10);
    $invoice = Invoice::where('company_id', $f['company']->id)->firstOrFail();

    taxSwitchSet($f['company'], false);
    // Editing it still honours its tax; nothing is rewritten.
    expect(app(CompanyTax::class)->allows($f['company'], $invoice->tax_amount))->toBeTrue();
    expect(app(CompanyTax::class)->allows($f['company'], 0))->toBeFalse();

    $invoice->refresh();
    expect((float) $invoice->tax_amount)->toBe(100.0)->and((float) $invoice->total_amount)->toBe(1100.0);
});
