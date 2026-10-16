<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\TaxRate;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/WithholdingFixtures.php';

/** Owner-level access to a company with a vendor, plus a customer. */
function whtSettingsFixture(): array
{
    $f = billPaymentEditFixture();
    $f['customer'] = Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'C-SET', 'name' => 'Settings Buyer',
        'base_currency' => 'PKR', 'is_active' => true,
    ]);

    return $f;
}

test('no withholding rate numbers are seeded', function () {
    $f = whtSettingsFixture();

    expect(TaxRate::where('company_id', $f['company']->id)->where('tax_type', 'withholding')->count())->toBe(0);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/tax/settings")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounting/tax/Settings', false)
            ->where('withholdingRates', [])
            // Suggestions carry a section and a label, never a rate.
            ->where('withholdingSections.0', ['section' => '153(1)(a)', 'label' => 'Goods'])
            ->has('withholdingSections', 5));
});

test('a withholding rate is saved from the Tax page with its section and non-filer rate', function () {
    $f = whtSettingsFixture();

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/tax/rates", [
        'tax_type' => 'withholding', 'section' => '153(1)(b)', 'name' => 'Services',
        'rate' => 5.5, 'rate_non_filer' => 11, 'applies_to' => 'payments_made', 'effective_from' => '2026-07-01',
    ])->assertSessionHasNoErrors();

    $rate = TaxRate::where('company_id', $f['company']->id)->sole();
    expect($rate->tax_type)->toBe('withholding')
        ->and($rate->section)->toBe('153(1)(b)')
        ->and((float) $rate->rate)->toBe(5.5)
        ->and((float) $rate->rate_non_filer)->toBe(11.0)
        ->and($rate->applies_to)->toBe('payments_made')
        ->and($rate->code)->toBe('WHT-153-1-B')
        ->and($rate->is_default)->toBeFalse()
        ->and($rate->jurisdiction_id)->not->toBeNull();

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/tax/settings")
        ->assertInertia(fn (Assert $page) => $page->has('withholdingRates', 1)->where('withholdingRates.0.rate_non_filer', '11.0000'));
});

test('a second rate for the same section gets its own code', function () {
    $f = whtSettingsFixture();
    foreach (['2026-07-01', '2027-07-01'] as $from) {
        $this->actingAs($f['user'])->post("/{$f['company']->slug}/tax/rates", [
            'tax_type' => 'withholding', 'section' => '153(1)(b)', 'name' => 'Services', 'rate' => 5, 'effective_from' => $from,
        ])->assertSessionHasNoErrors();
    }

    expect(TaxRate::where('company_id', $f['company']->id)->pluck('code')->unique())->toHaveCount(2);
});

test('a withholding rate can be changed and stopped; stopped rates stay listed but are not offered', function () {
    $f = whtSettingsFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);

    $this->actingAs($f['user'])->put("/{$f['company']->slug}/tax/rates/{$rate->id}", ['rate' => 6, 'rate_non_filer' => 12])->assertSessionHasNoErrors();
    expect((float) $rate->fresh()->rate)->toBe(6.0)->and((float) $rate->fresh()->rate_non_filer)->toBe(12.0);

    $this->actingAs($f['user'])->put("/{$f['company']->slug}/tax/rates/{$rate->id}", ['is_active' => false])->assertSessionHasNoErrors();

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/tax/settings")
        ->assertInertia(fn (Assert $page) => $page->has('withholdingRates', 1)->where('taxRates', []));
    $this->actingAs($f['user'])->get("/{$f['company']->slug}/bill-payments/create")
        ->assertInertia(fn (Assert $page) => $page->where('whtRates', []));
});

test('an unknown section value or a rate above 100 is refused', function () {
    $f = whtSettingsFixture();

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/tax/rates", [
        'tax_type' => 'withholding', 'name' => 'Bad', 'rate' => 5, 'rate_non_filer' => 120, 'applies_to' => 'sometimes', 'effective_from' => '2026-07-01',
    ])->assertSessionHasErrors(['rate_non_filer', 'applies_to']);
});

test('vendor filer status and usual rate are saved while tax is on', function () {
    $f = whtSettingsFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);

    $this->actingAs($f['user'])->put("/{$f['company']->slug}/vendors/{$f['vendor']->id}", [
        'filer_status' => 'non_filer', 'default_wht_rate_id' => $rate->id,
    ])->assertSessionHasNoErrors();

    expect($f['vendor']->fresh()->filer_status)->toBe('non_filer')->and($f['vendor']->fresh()->default_wht_rate_id)->toBe($rate->id);
});

test('with tax off the vendor form hides the fields and a save leaves them alone', function () {
    $f = whtSettingsFixture();
    whtTax($f['company'], false);
    whtRate($f['company'], 4);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/vendors/{$f['vendor']->id}/edit")
        ->assertInertia(fn (Assert $page) => $page->where('auth.taxEnabled', false)->where('whtRates', []));

    $this->actingAs($f['user'])->put("/{$f['company']->slug}/vendors/{$f['vendor']->id}", ['filer_status' => 'non_filer'])->assertSessionHasNoErrors();
    expect($f['vendor']->fresh()->filer_status)->toBe('unknown');
});

test('the vendor form offers only the rates for payments we make', function () {
    $f = whtSettingsFixture();
    whtTax($f['company'], true);
    $made = whtRate($f['company'], 4, null, 'payments_made', 'WHT-OUT');
    whtRate($f['company'], 4, null, 'payments_received', 'WHT-IN');

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/vendors/{$f['vendor']->id}/edit")
        ->assertInertia(fn (Assert $page) => $page->where('auth.taxEnabled', true)->has('whtRates', 1)->where('whtRates.0.id', $made->id));
});

test('a rate for the wrong side cannot be a vendor default', function () {
    $f = whtSettingsFixture();
    whtTax($f['company'], true);
    $received = whtRate($f['company'], 4, null, 'payments_received');

    $this->actingAs($f['user'])->put("/{$f['company']->slug}/vendors/{$f['vendor']->id}", ['default_wht_rate_id' => $received->id])
        ->assertSessionHasErrors('default_wht_rate_id');
    expect($f['vendor']->fresh()->default_wht_rate_id)->toBeNull();
});

test('customer filer status and usual rate are saved while tax is on, and ignored while it is off', function () {
    $f = whtSettingsFixture();
    $rate = whtRate($f['company'], 4, null, 'payments_received');

    whtTax($f['company'], false);
    $this->actingAs($f['user'])->put("/{$f['company']->slug}/customers/{$f['customer']->id}", ['filer_status' => 'filer', 'default_wht_rate_id' => $rate->id])->assertSessionHasNoErrors();
    expect($f['customer']->fresh()->filer_status)->toBe('unknown')->and($f['customer']->fresh()->default_wht_rate_id)->toBeNull();

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/customers/{$f['customer']->id}/edit")
        ->assertInertia(fn (Assert $page) => $page->where('whtRates', []));

    whtTax($f['company'], true);
    $this->actingAs($f['user'])->put("/{$f['company']->slug}/customers/{$f['customer']->id}", ['filer_status' => 'filer', 'default_wht_rate_id' => $rate->id])->assertSessionHasNoErrors();
    expect($f['customer']->fresh()->filer_status)->toBe('filer')->and($f['customer']->fresh()->default_wht_rate_id)->toBe($rate->id);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/customers/{$f['customer']->id}/edit")
        ->assertInertia(fn (Assert $page) => $page->has('whtRates', 1));
});

test('withholding rates are not offered as a sales or purchase rate on an item', function () {
    $f = whtSettingsFixture();
    whtTax($f['company'], true);
    whtRate($f['company'], 4);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/items/create")
        ->assertInertia(fn (Assert $page) => $page->where('taxRates', []));
});
