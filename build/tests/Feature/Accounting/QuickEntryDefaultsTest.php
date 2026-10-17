<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/CashSaleFixtures.php';

function quickEntryUrl(array $f): string
{
    return "/{$f['company']->slug}/accounting/quick-entry";
}

function savedQuickEntry(array $f): array
{
    return Company::find($f['company']->id)->quickEntryDefaults();
}

test('the owner opens the page and sees only cash and bank accounts', function () {
    $f = cashSaleFixture();

    $this->actingAs($f['owner'])->get(quickEntryUrl($f))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('accounting/settings/QuickEntry', false)
        ->where('defaults.receive_account_id', null)
        ->where('defaults.pay_account_id', null)
        ->has('accounts', 3)
        ->where('accounts.0.code', '1050'));
});

test('the two accounts are saved on the company and cleared again', function () {
    $f = cashSaleFixture();

    $this->actingAs($f['owner'])->patch(quickEntryUrl($f), [
        'receive_account_id' => $f['bank']->id, 'pay_account_id' => $f['cash']->id,
    ])->assertSessionHasNoErrors();
    expect(savedQuickEntry($f))->toBe(['receive_account_id' => $f['bank']->id, 'pay_account_id' => $f['cash']->id]);

    $this->actingAs($f['owner'])->get(quickEntryUrl($f))->assertInertia(fn (Assert $page) => $page
        ->where('defaults.receive_account_id', $f['bank']->id)
        ->where('defaults.pay_account_id', $f['cash']->id));

    $this->actingAs($f['owner'])->patch(quickEntryUrl($f), ['receive_account_id' => null, 'pay_account_id' => null])
        ->assertSessionHasNoErrors();
    expect(savedQuickEntry($f))->toBe(['receive_account_id' => null, 'pay_account_id' => null]);
});

test('saving keeps the rest of the company settings', function () {
    $f = cashSaleFixture();
    $f['company']->setAutoCreateFiscalYear(false);

    $this->actingAs($f['owner'])->patch(quickEntryUrl($f), ['receive_account_id' => $f['bank']->id]);

    expect(Company::find($f['company']->id)->getAutoCreateFiscalYear())->toBeFalse();
});

test('only a cash or bank account of this company is accepted', function () {
    $f = cashSaleFixture();
    $other = cashSaleFixture();
    enterCompany($f['company']);

    foreach ([$f['tea']->id, $f['ar']->id, $other['bank']->id, 'not-a-uuid'] as $bad) {
        $this->actingAs($f['owner'])->patch(quickEntryUrl($f), ['receive_account_id' => $bad])
            ->assertSessionHasErrors('receive_account_id');
        $this->actingAs($f['owner'])->patch(quickEntryUrl($f), ['pay_account_id' => $bad])
            ->assertSessionHasErrors('pay_account_id');
    }

    Account::where('id', $f['bank2']->id)->update(['is_active' => false]);
    $this->actingAs($f['owner'])->patch(quickEntryUrl($f), ['pay_account_id' => $f['bank2']->id])
        ->assertSessionHasErrors('pay_account_id');
});

test('someone without the account permission can neither open nor save it', function () {
    $f = cashSaleFixture();

    $this->actingAs($f['limited'])->get(quickEntryUrl($f))->assertForbidden();
    $this->actingAs($f['limited'])->patch(quickEntryUrl($f), ['receive_account_id' => $f['bank']->id])->assertForbidden();
    expect(savedQuickEntry($f)['receive_account_id'])->toBeNull();
});

test('the palette defaults hand the two accounts to the quick-entry grammar', function () {
    $f = cashSaleFixture();
    $f['company']->setQuickEntryDefaults($f['bank2']->id, $f['cash']->id);

    $res = $this->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)->getJson('/api/palette/defaults')->assertOk();

    expect($res->json('receive_account_id'))->toBe($f['bank2']->id);
    expect($res->json('pay_account_id'))->toBe($f['cash']->id);
});

test('the palette defaults leave out an account that was closed or is not cash or bank', function () {
    $f = cashSaleFixture();
    $f['company']->setQuickEntryDefaults($f['bank2']->id, $f['ar']->id);
    Account::where('id', $f['bank2']->id)->update(['is_active' => false]);

    $res = $this->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)->getJson('/api/palette/defaults')->assertOk();

    expect($res->json('receive_account_id'))->toBeNull();
    expect($res->json('pay_account_id'))->toBeNull();
});

test('nothing set means nothing sent', function () {
    $f = cashSaleFixture();

    $res = $this->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)->getJson('/api/palette/defaults')->assertOk();

    expect($res->json())->toHaveKeys(['receive_account_id', 'pay_account_id']);
    expect($res->json('receive_account_id'))->toBeNull();
});
