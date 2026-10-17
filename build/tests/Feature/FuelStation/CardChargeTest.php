<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Models\CardCharge;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\CardChargeService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * The charge the bank keeps on swipes, or the fuel supplier on its vendor card, entered by hand on
 * any date (usually month end): the card-charges expense, taken from where that card's money sits --
 * its clearing account, its bank, or (a supplier's vendor card) added to what the station owes.
 */
function cardChargeFixture(): array
{
    $f = creditCloseFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    app(CompanyContextService::class)->setContext($f['company']);

    $f['charges'] = Account::create(['company_id' => $f['company']->id, 'code' => '6160', 'name' => 'POS/Bank Charges',
        'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'is_active' => true]);
    $ap = Account::create(['company_id' => $f['company']->id, 'code' => '2000', 'name' => 'Accounts Payable',
        'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_active' => true]);
    $parcoClearing = Account::create(['company_id' => $f['company']->id, 'code' => '1031', 'name' => 'PARCO card clearing',
        'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $f['vendor'] = Vendor::create(['company_id' => $f['company']->id, 'vendor_number' => 'V-1', 'name' => 'PARCO',
        'base_currency' => 'PKR', 'is_active' => true, 'ap_account_id' => $ap->id]);

    StationSettings::where('company_id', $f['company']->id)->update(['payment_channels' => json_encode([
        ['code' => 'pos', 'label' => 'HBL POS', 'type' => 'card_pos', 'enabled' => true, 'clearing_account_id' => $f['accounts']['1020']->id],
        ['code' => 'parco', 'label' => 'PARCO card', 'type' => 'fuel_card', 'enabled' => true, 'settles_to' => 'supplier', 'fee_percent' => 1.5,
            'settles_to_vendor_id' => $f['vendor']->id, 'clearing_account_id' => $parcoClearing->id],
        ['code' => 'wallet', 'label' => 'Wallet', 'type' => 'mobile_wallet', 'enabled' => true, 'bank_account_id' => $f['accounts']['1020']->id],
    ])]);

    return $f;
}

function cardChargeRecord(array $f, array $data): CardCharge
{
    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CardChargeService::class)->record(
        $f['company'], $data + ['charge_date' => '2026-09-30'], $f['user']));
}

function cardChargeBalance(string $companyId, string $accountId): float
{
    return round((float) JournalEntry::where('company_id', $companyId)->where('account_id', $accountId)->sum(DB::raw('debit_amount - credit_amount')), 2);
}

test('only the station card channels are offered, each saying where its charge comes from and any daily %', function () {
    $f = cardChargeFixture();
    $channels = collect(app(CardChargeService::class)->channels($f['company']))->keyBy('code');

    expect($channels->keys()->all())->toBe(['pos', 'parco'])
        ->and($channels['pos']['settles_to'])->toBe('clearing')
        ->and($channels['pos']['account_id'])->toBe($f['accounts']['1020']->id)
        ->and($channels['pos']['daily_percent'])->toBeNull()
        ->and($channels['parco']['daily_percent'])->toBe(1.5)
        ->and($channels['parco']['vendor_name'])->toBe('PARCO');
});

test('a swipe card charge comes out of its clearing account as card charges, dated as entered', function () {
    $f = cardChargeFixture();
    $charge = cardChargeRecord($f, ['channel_code' => 'pos', 'amount' => 1500, 'reference' => 'HBL Sep stmt']);

    $transaction = Transaction::where('company_id', $f['company']->id)->findOrFail($charge->transaction_id);
    expect($transaction->transaction_date->toDateString())->toBe('2026-09-30')
        ->and(cardChargeBalance($f['company']->id, $f['charges']->id))->toBe(1500.0)
        ->and(cardChargeBalance($f['company']->id, $f['accounts']['1020']->id))->toBe(-1500.0);
});

test('undo reverses the charge on its own date and keeps the row as undone', function () {
    $f = cardChargeFixture();
    $charge = cardChargeRecord($f, ['channel_code' => 'pos', 'amount' => 1500]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CardChargeService::class)->undo($f['company'], $charge, $f['user']));

    $reversal = Transaction::where('company_id', $f['company']->id)->where('reversal_of_id', $charge->transaction_id)->sole();
    expect($reversal->transaction_date->toDateString())->toBe('2026-09-30')
        ->and(cardChargeBalance($f['company']->id, $f['charges']->id))->toBe(0.0)
        ->and(cardChargeBalance($f['company']->id, $f['accounts']['1020']->id))->toBe(0.0)
        ->and(CardCharge::withTrashed()->find($charge->id)->trashed())->toBeTrue();
});

test('a supplier vendor card charge is a bill to that supplier, and undo voids it', function () {
    $f = cardChargeFixture();
    $charge = cardChargeRecord($f, ['channel_code' => 'parco', 'amount' => 2400, 'reference' => 'PARCO Sep']);

    $bill = Bill::where('company_id', $f['company']->id)->findOrFail($charge->bill_id);
    expect($bill->vendor_id)->toBe($f['vendor']->id)
        ->and((float) $bill->total_amount)->toBe(2400.0)
        ->and($bill->bill_date->toDateString())->toBe('2026-09-30')
        ->and(cardChargeBalance($f['company']->id, $f['charges']->id))->toBe(2400.0)
        ->and($charge->fresh()->transaction_id)->toBe($bill->transaction_id);

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CardChargeService::class)->undo($f['company'], $charge->fresh(), $f['user']));
    expect($bill->fresh()->status)->toBe('void')
        ->and(cardChargeBalance($f['company']->id, $f['charges']->id))->toBe(0.0);
});

test('a channel that is not a card is refused', function () {
    $f = cardChargeFixture();
    expect(fn () => cardChargeRecord($f, ['channel_code' => 'wallet', 'amount' => 100]))->toThrow(ValidationException::class);
    expect(CardCharge::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('the settlement page records and undoes card charges for someone who may take payments, not for others', function () {
    $f = cardChargeFixture();
    // The route sits behind the module gate, and the fixture company has no modules on.
    $f['company']->enableModule('fuel_station');
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    addCompanyMemberRow($f['company'], $f['user'], 'owner');
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($f['user'], 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    $this->actingAs($f['user']);
    $slug = $f['company']->slug;

    $this->post("/{$slug}/fuel/card-charges", ['channel_code' => 'pos', 'charge_date' => '2026-09-30', 'amount' => 1200])
        ->assertSessionHasNoErrors()->assertRedirect();
    $charge = CardCharge::where('company_id', $f['company']->id)->sole();
    $this->get("/{$slug}/fuel/vendor-cards/pending")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('canRecordCharge', true)
        ->where('cardCharges.0.id', $charge->id)->where('cardCharges.0.amount', 1200)
        ->has('cardChargeChannels', 2));

    $outsider = \App\Models\User::factory()->create();
    addCompanyMemberRow($f['company'], $outsider, 'operations');
    $this->actingAs($outsider)->delete("/{$slug}/fuel/card-charges/{$charge->id}")->assertForbidden();
    expect($charge->fresh())->not->toBeNull();

    $this->actingAs($f['user'])->delete("/{$slug}/fuel/card-charges/{$charge->id}")->assertSessionHasNoErrors()->assertRedirect();
    expect(CardCharge::where('company_id', $f['company']->id)->count())->toBe(0);
});
