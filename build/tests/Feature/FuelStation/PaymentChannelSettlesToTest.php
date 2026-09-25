<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\StationSettings;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';
require_once __DIR__.'/PendingDeliveryFixtures.php';

/**
 * A payment channel's "Settles to" setting decides where its card/fuel-card/mobile-wallet
 * money goes at the daily close, instead of always parking it in clearing until someone
 * settles it later on the Settlements screen (owner's words: "their payment goes straight
 * to that bank or the supplier respectively"). See DailyCloseService::resolvePaymentChannelAccount
 * (settles_to: 'bank') and the channel-supplier-settlement block right after the payment-
 * receipts loop (settles_to: 'supplier'), and StationSettingsController::validatePaymentChannelMappings
 * for the settings-side guard.
 */
test('a card_pos channel that settles to bank debits the bank directly, and expected cash is unchanged versus clearing', function () {
    $f = creditCloseFixture();

    $bank = Account::create([
        'company_id' => $f['company']->id, 'code' => '1010', 'name' => 'POS Settlement Bank',
        'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true,
    ]);

    $settings = StationSettings::where('company_id', $f['company']->id)->first();
    $channels = $settings->payment_channels;
    $channels[0]['settles_to'] = 'bank';
    $channels[0]['bank_account_id'] = $bank->id;
    $settings->update(['payment_channels' => $channels]);

    $posted = creditClosePost($f);
    $close = Transaction::findOrFail($posted['transaction_id']);

    // Same opening/closing cash and same payload as the default 'clearing' behaviour
    // (which nets to zero variance elsewhere in this suite) -- settling to a bank
    // instead of clearing must not move the expected drawer cash at all.
    expect((float) $close->metadata['variance'])->toBe(0.0);

    $bankLine = $close->journalEntries()->where('account_id', $bank->id)->sole();
    expect((float) $bankLine->debit_amount)->toBe(9000.0);

    $clearingLine = $close->journalEntries()->where('account_id', $f['accounts']['1020']->id)->first();
    expect($clearingLine)->toBeNull();
});

test('a fuel_card channel that settles to a supplier pays its open bill from clearing, and clearing nets zero for the day', function () {
    $f = pendingDeliveryFixture();

    $clearingAccount = $f['accounts']['1020'];
    $settings = StationSettings::where('company_id', $f['company']->id)->first();
    $channels = $settings->payment_channels;
    $channels[] = [
        'code' => 'vendor_card', 'label' => 'Vendor Card', 'type' => 'fuel_card', 'enabled' => true,
        'bank_account_id' => null, 'clearing_account_id' => $clearingAccount->id,
        'settles_to' => 'supplier', 'settles_to_vendor_id' => $f['vendor']->id,
    ];
    $settings->update(['payment_channels' => $channels]);

    // An open bill for the vendor, larger than the 8000 in card sales below.
    $bill = Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id,
        'bill_number' => 'BILL-OPEN-1', 'bill_date' => '2026-09-01', 'due_date' => '2026-09-01',
        'status' => 'received', 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 50000, 'tax_amount' => 0, 'discount_amount' => 0,
        'total_amount' => 50000, 'paid_amount' => 0, 'balance' => 50000, 'base_amount' => 50000,
        'created_by_user_id' => $f['user']->id,
    ]);

    // Replace the fixture's card_pos receipt with an 8000 fuel-card receipt: total
    // revenue stays 30000 (100L @ 300), 6000 of it on credit, so cash actually
    // collected is 30000 - 8000 - 6000 = 16000 on top of the 10000 opening float.
    $f['payload']['payment_receipts'] = ['vendor_card' => ['entries' => [['last_four' => '9999', 'amount' => 8000]]]];
    $f['payload']['closing_cash'] = 26000;

    $posted = pendingDeliveryPost($f);
    $close = Transaction::findOrFail($posted['transaction_id']);

    expect((float) $close->metadata['variance'])->toBe(0.0);

    $settlements = $close->metadata['channel_supplier_settlements'] ?? [];
    expect($settlements)->toHaveCount(1);
    expect((float) $settlements[0]['amount_paid'])->toBe(8000.0);
    expect((float) $settlements[0]['applied_to_bills'])->toBe(8000.0);
    expect((float) $settlements[0]['advance_amount'])->toBe(0.0);
    expect($settlements[0]['vendor_id'])->toBe($f['vendor']->id);

    $bill->refresh();
    expect((float) $bill->balance)->toBe(42000.0);
    expect($bill->status)->toBe('partial');

    $payment = BillPayment::where('company_id', $f['company']->id)->where('vendor_id', $f['vendor']->id)->sole();
    expect((float) $payment->amount)->toBe(8000.0);
    expect((string) $payment->payment_date->toDateString())->toBe('2026-09-15');
    $allocation = $payment->allocations()->sole();
    expect((float) $allocation->amount_allocated)->toBe(8000.0);
    expect($allocation->bill_id)->toBe($bill->id);

    // Clearing nets to zero for the day: the close's own debit (card sales landing in
    // clearing) and this settlement payment's credit (paying the vendor from clearing)
    // cancel out.
    $clearingEffect = DB::table('acct.journal_entries as je')
        ->whereIn('je.transaction_id', [$close->id, $payment->transaction_id])
        ->where('je.account_id', $clearingAccount->id)
        ->selectRaw('coalesce(sum(je.debit_amount),0) - coalesce(sum(je.credit_amount),0) as net')
        ->value('net');
    expect((float) $clearingEffect)->toBe(0.0);
});

test('a fuel_card channel settling to a supplier pays the full card total, holding the excess over open bills as an advance', function () {
    $f = pendingDeliveryFixture();

    $clearingAccount = $f['accounts']['1020'];
    $settings = StationSettings::where('company_id', $f['company']->id)->first();
    $channels = $settings->payment_channels;
    $channels[] = [
        'code' => 'vendor_card', 'label' => 'Vendor Card', 'type' => 'fuel_card', 'enabled' => true,
        'bank_account_id' => null, 'clearing_account_id' => $clearingAccount->id,
        'settles_to' => 'supplier', 'settles_to_vendor_id' => $f['vendor']->id,
    ];
    $settings->update(['payment_channels' => $channels]);

    // Open balance (3000) is smaller than the 8000 in card sales below.
    $bill = Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id,
        'bill_number' => 'BILL-OPEN-2', 'bill_date' => '2026-09-01', 'due_date' => '2026-09-01',
        'status' => 'received', 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 3000, 'tax_amount' => 0, 'discount_amount' => 0,
        'total_amount' => 3000, 'paid_amount' => 0, 'balance' => 3000, 'base_amount' => 3000,
        'created_by_user_id' => $f['user']->id,
    ]);

    $f['payload']['payment_receipts'] = ['vendor_card' => ['entries' => [['last_four' => '9999', 'amount' => 8000]]]];
    $f['payload']['closing_cash'] = 26000;

    $posted = pendingDeliveryPost($f);
    $close = Transaction::findOrFail($posted['transaction_id']);

    expect((float) $close->metadata['variance'])->toBe(0.0);

    $settlements = $close->metadata['channel_supplier_settlements'] ?? [];
    expect($settlements)->toHaveCount(1);
    // The FULL card total (8000) leaves clearing, not just what the vendor was owed:
    // 3000 pays down the open bill, and the remaining 5000 is held as an advance with the
    // vendor rather than staying parked in clearing.
    expect((float) $settlements[0]['amount_paid'])->toBe(8000.0);
    expect((float) $settlements[0]['applied_to_bills'])->toBe(3000.0);
    expect((float) $settlements[0]['advance_amount'])->toBe(5000.0);

    $bill->refresh();
    expect((float) $bill->balance)->toBe(0.0);
    expect($bill->status)->toBe('paid');

    $payment = BillPayment::where('company_id', $f['company']->id)->where('vendor_id', $f['vendor']->id)->sole();
    expect((float) $payment->amount)->toBe(8000.0);
    expect($payment->unappliedAmount())->toBe(5000.0);

    // Clearing nets to zero here too: the close's own debit for the full 8000 in card sales
    // and this settlement payment's credit for the same full 8000 cancel out, even though
    // only 3000 of it reached a bill.
    $clearingEffect = DB::table('acct.journal_entries as je')
        ->whereIn('je.transaction_id', [$close->id, $payment->transaction_id])
        ->where('je.account_id', $clearingAccount->id)
        ->selectRaw('coalesce(sum(je.debit_amount),0) - coalesce(sum(je.credit_amount),0) as net')
        ->value('net');
    expect((float) $clearingEffect)->toBe(0.0);
});

test('settings update rejects settles_to supplier without a vendor', function () {
    $f = pendingDeliveryFixture();
    $f['company']->settings = array_merge((array) $f['company']->settings, ['modules' => ['fuel_station' => true]]);
    $f['company']->save();

    // The fixture's stored channel predates these keys; StationAccountMapper::ensureMappings
    // reads the *existing* stored payment_channels before this request's own payload is even
    // applied, so it needs the full shape too, not just what we're about to PUT.
    StationSettings::where('company_id', $f['company']->id)->update(['payment_channels' => [[
        'code' => 'pos', 'label' => 'HBL POS', 'type' => 'card_pos', 'enabled' => true,
        'bank_account_id' => null, 'clearing_account_id' => $f['accounts']['1020']->id,
        'settles_to' => 'clearing', 'settles_to_vendor_id' => null,
    ]]]);

    $response = test()->actingAs($f['user'])->put("/{$f['company']->slug}/fuel/settings", [
        'fuel_vendor' => 'parco',
        'payment_channels' => [[
            'code' => 'pos', 'label' => 'HBL POS', 'type' => 'card_pos', 'enabled' => true,
            'bank_account_id' => $f['accounts']['1020']->id,
            'clearing_account_id' => $f['accounts']['1020']->id,
            'settles_to' => 'supplier',
        ]],
    ]);

    $response->assertSessionHasErrors('payment_channels.0.settles_to_vendor_id');
});
