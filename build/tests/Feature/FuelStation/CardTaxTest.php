<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\CompanyTaxSettings;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\WithholdingRegisterService;
use App\Modules\Accounting\Services\WithholdingTax;
use App\Modules\FuelStation\Models\CardTaxDeduction;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\CardTaxService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * Tax a card company deducted from card sales, recorded by hand on any date (usually month end):
 * advance income tax, taken from where that card's money sits -- its clearing account, its bank,
 * or (a supplier's vendor card) added to what the station owes that supplier.
 */
function cardTaxFixture(bool $taxOn = true): array
{
    $f = creditCloseFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    app(CompanyContextService::class)->setContext($f['company']);
    CompanyTaxSettings::where('company_id', $f['company']->id)->delete();
    (new CompanyTaxSettings(['company_id' => $f['company']->id]))->saveDeliberately(['tax_enabled' => $taxOn]);

    $ap = Account::create(['company_id' => $f['company']->id, 'code' => '2000', 'name' => 'Accounts Payable',
        'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_active' => true]);
    $parcoClearing = Account::create(['company_id' => $f['company']->id, 'code' => '1031', 'name' => 'PARCO card clearing',
        'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $f['vendor'] = Vendor::create(['company_id' => $f['company']->id, 'vendor_number' => 'V-1', 'name' => 'PARCO',
        'base_currency' => 'PKR', 'is_active' => true, 'ap_account_id' => $ap->id]);

    StationSettings::where('company_id', $f['company']->id)->update(['payment_channels' => json_encode([
        ['code' => 'pos', 'label' => 'HBL POS', 'type' => 'card_pos', 'enabled' => true, 'clearing_account_id' => $f['accounts']['1020']->id],
        ['code' => 'parco', 'label' => 'PARCO card', 'type' => 'fuel_card', 'enabled' => true, 'settles_to' => 'supplier',
            'settles_to_vendor_id' => $f['vendor']->id, 'clearing_account_id' => $parcoClearing->id],
        ['code' => 'wallet', 'label' => 'Wallet', 'type' => 'mobile_wallet', 'enabled' => true, 'bank_account_id' => $f['accounts']['1020']->id],
    ])]);

    return $f;
}

function cardTaxRecord(array $f, array $data): CardTaxDeduction
{
    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CardTaxService::class)->record(
        $f['company'], $data + ['deduction_date' => '2026-09-30'], $f['user']));
}

function cardTaxBalance(string $companyId, string $accountId): float
{
    return round((float) JournalEntry::where('company_id', $companyId)->where('account_id', $accountId)->sum(DB::raw('debit_amount - credit_amount')), 2);
}

test('only the station card channels are offered, each saying where its tax comes from', function () {
    $f = cardTaxFixture();
    $channels = collect(app(CardTaxService::class)->channels($f['company']))->keyBy('code');

    expect($channels->keys()->all())->toBe(['pos', 'parco'])
        ->and($channels['pos']['settles_to'])->toBe('clearing')
        ->and($channels['pos']['account_id'])->toBe($f['accounts']['1020']->id)
        ->and($channels['parco']['vendor_name'])->toBe('PARCO');
});

test('tax on a swipe card comes out of its clearing account into advance tax, dated as entered', function () {
    $f = cardTaxFixture();
    $deduction = cardTaxRecord($f, ['channel_code' => 'pos', 'amount' => 1500, 'section' => '236', 'reference' => 'HBL Sep stmt']);

    $advance = app(WithholdingTax::class)->advanceTaxAccount($f['company']);
    $transaction = Transaction::where('company_id', $f['company']->id)->findOrFail($deduction->transaction_id);
    expect($transaction->transaction_date->toDateString())->toBe('2026-09-30')
        ->and(cardTaxBalance($f['company']->id, $advance))->toBe(1500.0)
        ->and(cardTaxBalance($f['company']->id, $f['accounts']['1020']->id))->toBe(-1500.0);

    $register = app(WithholdingRegisterService::class)->run($f['company']->id, $f['company']->slug, '2026-09-01', '2026-09-30', 'received');
    expect($register['rows'])->toHaveCount(1)
        ->and($register['rows'][0])->toMatchArray(['party' => 'HBL POS', 'section' => '236', 'wht' => 1500.0, 'kind' => 'deduction'])
        ->and($register['totals']['wht'])->toBe(1500.0)
        ->and(app(WithholdingRegisterService::class)->run($f['company']->id, $f['company']->slug, '2026-09-01', '2026-09-30', 'made')['rows'])->toBe([]);
});

test('undo reverses the posting, keeps the row as undone and shows a reversal on the register', function () {
    $f = cardTaxFixture();
    $deduction = cardTaxRecord($f, ['channel_code' => 'pos', 'amount' => 1500]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CardTaxService::class)->undo($f['company'], $deduction, $f['user']));

    $advance = app(WithholdingTax::class)->advanceTaxAccount($f['company']);
    expect(cardTaxBalance($f['company']->id, $advance))->toBe(0.0)
        ->and(cardTaxBalance($f['company']->id, $f['accounts']['1020']->id))->toBe(0.0)
        ->and(CardTaxDeduction::withTrashed()->find($deduction->id)->trashed())->toBeTrue();

    $undoneOn = CardTaxDeduction::withTrashed()->find($deduction->id)->deleted_at->toDateString();
    $register = app(WithholdingRegisterService::class)->run($f['company']->id, $f['company']->slug, '2026-09-01', max('2026-09-30', $undoneOn), 'received');
    expect(collect($register['rows'])->pluck('kind')->sort()->values()->all())->toBe(['deduction', 'reversal'])
        ->and($register['totals']['wht'])->toBe(0.0);
});

test('tax on a supplier vendor card is a bill to that supplier, and undo voids it', function () {
    $f = cardTaxFixture();
    $deduction = cardTaxRecord($f, ['channel_code' => 'parco', 'amount' => 2400, 'reference' => 'PARCO Sep']);

    $bill = Bill::where('company_id', $f['company']->id)->findOrFail($deduction->bill_id);
    $advance = app(WithholdingTax::class)->advanceTaxAccount($f['company']);
    expect($bill->vendor_id)->toBe($f['vendor']->id)
        ->and((float) $bill->total_amount)->toBe(2400.0)
        ->and($bill->bill_date->toDateString())->toBe('2026-09-30')
        ->and(cardTaxBalance($f['company']->id, $advance))->toBe(2400.0)
        ->and($deduction->fresh()->transaction_id)->toBe($bill->transaction_id);

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CardTaxService::class)->undo($f['company'], $deduction->fresh(), $f['user']));
    expect($bill->fresh()->status)->toBe('void')
        ->and(cardTaxBalance($f['company']->id, $advance))->toBe(0.0);
});

test('nothing is recorded while tax is switched off, or for a channel that is not a card', function () {
    $off = cardTaxFixture(false);
    expect(fn () => cardTaxRecord($off, ['channel_code' => 'pos', 'amount' => 100]))->toThrow(ValidationException::class);
    expect(CardTaxDeduction::where('company_id', $off['company']->id)->count())->toBe(0);
});

test('a channel that is not a card is refused', function () {
    $f = cardTaxFixture();
    expect(fn () => cardTaxRecord($f, ['channel_code' => 'wallet', 'amount' => 100]))->toThrow(ValidationException::class);
});

test('the settlement page records and undoes card tax for someone who may take payments, not for others', function () {
    $f = cardTaxFixture();
    // The route sits behind the module gate, and the fixture company has no modules on.
    $f['company']->enableModule('fuel_station');
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    addCompanyMemberRow($f['company'], $f['user'], 'owner');
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($f['user'], 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    $this->actingAs($f['user']);
    $slug = $f['company']->slug;

    $this->post("/{$slug}/fuel/card-tax", ['channel_code' => 'pos', 'deduction_date' => '2026-09-30', 'amount' => 1200])
        ->assertSessionHasNoErrors()->assertRedirect();
    $deduction = CardTaxDeduction::where('company_id', $f['company']->id)->sole();
    $this->get("/{$slug}/fuel/vendor-cards/pending")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('taxEnabled', true)->where('canRecordTax', true)
        ->where('cardTaxes.0.id', $deduction->id)->where('cardTaxes.0.amount', 1200)
        ->has('cardTaxChannels', 2));

    $outsider = \App\Models\User::factory()->create();
    addCompanyMemberRow($f['company'], $outsider, 'operations');
    $this->actingAs($outsider)->delete("/{$slug}/fuel/card-tax/{$deduction->id}")->assertForbidden();
    expect($deduction->fresh())->not->toBeNull();

    $this->actingAs($f['user'])->delete("/{$slug}/fuel/card-tax/{$deduction->id}")->assertSessionHasNoErrors()->assertRedirect();
    expect(CardTaxDeduction::where('company_id', $f['company']->id)->count())->toBe(0);
});
