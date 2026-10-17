<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Extensions\BillNotes;
use App\Modules\FuelStation\Models\CardCharge;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\CardChargeService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * Card charges on a card set to manual charges: the real charge the bank or card supplier kept,
 * entered from the statement on any date (usually month end) for a period of card sales. It posts
 * once, to card charges, from where that card's money sits; each day's card sales and each
 * supplier bill a vendor card paid show their share at charge / sales.
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
    $f['parcoClearing'] = Account::create(['company_id' => $f['company']->id, 'code' => '1031', 'name' => 'PARCO card clearing',
        'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $f['vendor'] = Vendor::create(['company_id' => $f['company']->id, 'vendor_number' => 'V-1', 'name' => 'PARCO',
        'base_currency' => 'PKR', 'is_active' => true, 'ap_account_id' => $ap->id]);

    StationSettings::where('company_id', $f['company']->id)->update(['payment_channels' => json_encode([
        ['code' => 'pos', 'label' => 'HBL POS', 'type' => 'card_pos', 'enabled' => true, 'charge_mode' => 'manual', 'fee_percent' => 2,
            'clearing_account_id' => $f['accounts']['1020']->id],
        ['code' => 'parco', 'label' => 'PARCO card', 'type' => 'fuel_card', 'enabled' => true, 'charge_mode' => 'manual', 'settles_to' => 'supplier',
            'settles_to_vendor_id' => $f['vendor']->id, 'clearing_account_id' => $f['parcoClearing']->id],
        ['code' => 'ubl', 'label' => 'UBL POS', 'type' => 'card_pos', 'enabled' => true, 'fee_percent' => 1, 'clearing_account_id' => $f['accounts']['1020']->id],
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

test('cards are automatic or manual, and only manual cards can have charges recorded', function () {
    $f = cardChargeFixture();
    $service = app(CardChargeService::class);
    $all = collect($service->channels($f['company']))->keyBy('code');

    expect($all->keys()->all())->toBe(['pos', 'parco', 'ubl'])
        ->and($all['ubl']['charge_mode'])->toBe('automatic')
        ->and($all['pos']['charge_mode'])->toBe('manual')
        ->and(collect($service->channels($f['company'], true))->pluck('code')->all())->toBe(['pos', 'parco']);
    expect(fn () => cardChargeRecord($f, ['channel_code' => 'ubl', 'amount' => 100]))->toThrow(ValidationException::class)
        ->and(fn () => cardChargeRecord($f, ['channel_code' => 'wallet', 'amount' => 100]))->toThrow(ValidationException::class);
});

test('a daily close takes no charge from a manual card, even with an old % still saved on it', function () {
    $f = cardChargeFixture();
    creditClosePost($f);

    $close = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->sole();
    $pos = collect($close->metadata['payment_receipt_postings'])->firstWhere('channel_code', 'pos');
    expect((float) $pos['fee_amount'])->toBe(0.0)
        ->and(cardChargeBalance($f['company']->id, $f['charges']->id))->toBe(0.0);
});

test('a month-end charge posts once from the clearing account and gives each day its share', function () {
    $f = cardChargeFixture();
    creditClosePost($f); // 15 Sep: HBL POS card sales 9,000

    $charge = cardChargeRecord($f, ['channel_code' => 'pos', 'amount' => 180, 'reference' => 'HBL Sep stmt']);
    expect($charge->period_from->toDateString())->toBe('2026-09-01')
        ->and($charge->period_to->toDateString())->toBe('2026-09-30')
        ->and(Transaction::find($charge->transaction_id)->transaction_date->toDateString())->toBe('2026-09-30')
        ->and(cardChargeBalance($f['company']->id, $f['charges']->id))->toBe(180.0);

    $service = app(CardChargeService::class);
    expect($service->cardSales($f['company']->id, 'pos', '2026-09-01', '2026-09-30'))->toBe(9000.0)
        ->and($service->rate($charge))->toBe(2.0);

    $day = $service->forDay($f['company']->id, '2026-09-15');
    expect($day['rates']['pos']['rate'])->toBe(2.0)->and($day['booked'])->toBe([]);
    $monthEnd = $service->forDay($f['company']->id, '2026-09-30');
    expect($monthEnd['booked'][0])->toMatchArray(['channel' => 'HBL POS', 'amount' => 180.0, 'rate' => 2.0]);
    expect($service->forDay($f['company']->id, '2026-10-01')['rates'])->toBe([]);
});

test('undo reverses the charge on its own date and keeps the row as undone', function () {
    $f = cardChargeFixture();
    $charge = cardChargeRecord($f, ['channel_code' => 'pos', 'amount' => 1500]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CardChargeService::class)->undo($f['company'], $charge, $f['user']));

    $reversal = Transaction::where('company_id', $f['company']->id)->where('reversal_of_id', $charge->transaction_id)->sole();
    expect($reversal->transaction_date->toDateString())->toBe('2026-09-30')
        ->and(cardChargeBalance($f['company']->id, $f['charges']->id))->toBe(0.0)
        ->and(cardChargeBalance($f['company']->id, $f['accounts']['1020']->id))->toBe(0.0)
        ->and(CardCharge::withTrashed()->find($charge->id)->trashed())->toBeTrue()
        ->and(app(CardChargeService::class)->forDay($f['company']->id, '2026-09-15')['rates'])->toBe([]);
});

test('a vendor card charge is a bill to the supplier, and each fuel bill the card paid shows its share', function () {
    $f = cardChargeFixture();
    $fuelBill = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill.create', [
        'vendor_id' => $f['vendor']->id, 'bill_date' => '2026-09-10', 'due_date' => '2026-09-10', 'status' => 'received',
        'currency' => 'PKR', 'base_currency' => 'PKR',
        'line_items' => [['description' => 'Fuel', 'quantity' => 1, 'unit_price' => 50000, 'expense_account_id' => $f['charges']->id]],
    ], $f['user'], true));
    // 15 Sep close: 3,000 of PARCO card sales, paid to PARCO against its open bill.
    $f['payload']['payment_receipts']['parco'] = ['entries' => [['amount' => 3000]]];
    $f['payload']['closing_cash'] = 22000;
    creditClosePost($f);

    $charge = cardChargeRecord($f, ['channel_code' => 'parco', 'amount' => 60, 'reference' => 'PARCO Sep']);
    $chargeBill = Bill::where('company_id', $f['company']->id)->findOrFail($charge->bill_id);
    expect($chargeBill->vendor_id)->toBe($f['vendor']->id)
        ->and((float) $chargeBill->total_amount)->toBe(60.0)
        ->and(app(CardChargeService::class)->rate($charge))->toBe(2.0);

    $notes = app(BillNotes::class)->for(Bill::find($fuelBill['data']['id']));
    expect($notes)->toHaveCount(1)
        ->and($notes[0]['label'])->toBe('PARCO card charge')
        ->and($notes[0]['amount'])->toBe(60.0);

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CardChargeService::class)->undo($f['company'], $charge->fresh(), $f['user']));
    expect($chargeBill->fresh()->status)->toBe('void')
        ->and(app(BillNotes::class)->for(Bill::find($fuelBill['data']['id'])))->toBe([]);
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

    $this->post("/{$slug}/fuel/card-charges", ['channel_code' => 'pos', 'charge_date' => '2026-09-30', 'amount' => 1200,
        'period_from' => '2026-09-01', 'period_to' => '2026-09-30'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $charge = CardCharge::where('company_id', $f['company']->id)->sole();
    $this->get("/{$slug}/fuel/vendor-cards/pending")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('canRecordCharge', true)
        ->where('cardCharges.0.id', $charge->id)->where('cardCharges.0.amount', 1200)
        ->where('cardCharges.0.period_from', '2026-09-01')
        ->has('cardChargeChannels', 2));

    $outsider = \App\Models\User::factory()->create();
    addCompanyMemberRow($f['company'], $outsider, 'operations');
    $this->actingAs($outsider)->delete("/{$slug}/fuel/card-charges/{$charge->id}")->assertForbidden();
    expect($charge->fresh())->not->toBeNull();

    $this->actingAs($f['user'])->delete("/{$slug}/fuel/card-charges/{$charge->id}")->assertSessionHasNoErrors()->assertRedirect();
    expect(CardCharge::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('a charge entered as a % is worked out from that card sales over the period', function () {
    $f = cardChargeFixture();
    creditClosePost($f); // 15 Sep: HBL POS card sales 9,000

    $charge = cardChargeRecord($f, ['channel_code' => 'pos', 'percent' => 0.78]);
    expect((float) $charge->amount)->toBe(70.2)
        ->and(cardChargeBalance($f['company']->id, $f['charges']->id))->toBe(70.2)
        ->and(app(CardChargeService::class)->rate($charge))->toBe(0.78);

    // No card sales in the period: a % has nothing to apply to.
    expect(fn () => cardChargeRecord($f, ['channel_code' => 'pos', 'percent' => 0.78, 'charge_date' => '2026-08-31']))
        ->toThrow(ValidationException::class);
});

test('the settlement page gives the dialog a card sales for its period and records a % charge', function () {
    $f = cardChargeFixture();
    $f['company']->enableModule('fuel_station');
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    addCompanyMemberRow($f['company'], $f['user'], 'owner');
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($f['user'], 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    creditClosePost($f);
    $this->actingAs($f['user']);
    $slug = $f['company']->slug;

    $this->get("/{$slug}/fuel/vendor-cards/pending?charge_channel=pos&charge_from=2026-09-01&charge_to=2026-09-30")
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('cardChargeSales', 9000));
    $this->post("/{$slug}/fuel/card-charges", ['channel_code' => 'pos', 'charge_date' => '2026-09-30', 'percent' => 1,
        'period_from' => '2026-09-01', 'period_to' => '2026-09-30'])->assertSessionHasNoErrors()->assertRedirect();
    expect((float) CardCharge::where('company_id', $f['company']->id)->sole()->amount)->toBe(90.0);
    $this->post("/{$slug}/fuel/card-charges", ['channel_code' => 'pos', 'charge_date' => '2026-09-30'])
        ->assertSessionHasErrors(['amount', 'percent']);
});
