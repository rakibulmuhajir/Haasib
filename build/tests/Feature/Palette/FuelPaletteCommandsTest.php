<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Models\StationSettings;
use Carbon\Carbon;

/*
 * The FuelStation module's own palette commands: they exist only for a fuel company, and
 * card charge reads the same card channels the card-charge page offers.
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 12:00:00', 'UTC'));
});

function fuelPaletteSeed(): array
{
    $f = paletteFixture(true);
    $clearing = Account::create([
        'company_id' => $f['company']->id, 'code' => '1031', 'name' => 'Card clearing', 'type' => 'asset', 'subtype' => 'bank',
        'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true,
    ]);
    StationSettings::create(['company_id' => $f['company']->id, 'payment_channels' => [
        ['code' => 'pos', 'label' => 'HBL POS', 'type' => 'card_pos', 'enabled' => true, 'charge_mode' => 'manual', 'clearing_account_id' => $clearing->id],
        ['code' => 'ubl', 'label' => 'UBL POS', 'type' => 'card_pos', 'enabled' => true, 'charge_mode' => 'automatic', 'clearing_account_id' => $clearing->id],
        ['code' => 'hbl2', 'label' => 'HBL Fuel card', 'type' => 'fuel_card', 'enabled' => true, 'charge_mode' => 'manual', 'clearing_account_id' => $clearing->id],
    ]]);

    return $f;
}

function fuelPaletteResolve($test, array $f, array $slots): array
{
    $body = [];
    foreach ($slots as $name => $slot) {
        $body[$name] = is_array($slot) ? $slot : ['text' => $slot];
    }

    return $test->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)
        ->postJson('/api/palette/resolve', ['command' => 'card charge', 'slots' => $body])->assertOk()->json();
}

test('a general company has no card charge or close day; a fuel company does', function () {
    $general = paletteFixture(false);
    $fuel = paletteFixture(true);

    $names = fn (array $f) => array_column($this->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)
        ->getJson('/api/palette/catalog')->assertOk()->json('sentences'), 'name');

    expect($names($general))->not->toContain('card charge')->not->toContain('close day')
        ->and($names($fuel))->toContain('card charge')->toContain('close day');
});

test('close day opens the daily close form', function () {
    $f = paletteFixture(true);

    $sentences = collect($this->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)
        ->getJson('/api/palette/catalog')->assertOk()->json('sentences'))->keyBy('name');

    expect($sentences['close day']['key'])->toBe('fuel.daily_close.save')
        ->and($sentences['close day']['form_url'])->toBe("/{$f['company']->slug}/fuel/daily-close")
        ->and($sentences['card charge']['key'])->toBe('fuel.card_charge.record')
        ->and($sentences['card charge']['undo'])->toBe('fuel.card_charge.undo');
});

test('card charge with an amount sends amount and the day', function () {
    $f = fuelPaletteSeed();

    $r = fuelPaletteResolve($this, $f, ['channel' => 'hbl pos', 'amount' => '12,500']);

    expect($r['status'])->toBe('ready')
        ->and($r['key'])->toBe('fuel.card_charge.record')
        ->and($r['params'])->toBe(['channel_code' => 'pos', 'charge_date' => '2026-10-16', 'amount' => 12500])
        ->and($r['card']['what'])->toBe('Card charge')
        ->and($r['card']['from'])->toBe('HBL POS');
});

test('card charge with 2% sends percent and no amount', function () {
    $f = fuelPaletteSeed();

    $r = fuelPaletteResolve($this, $f, ['channel' => ['text' => 'pos', 'value' => 'pos'], 'amount' => '2%', 'date' => '30 sep']);

    expect($r['status'])->toBe('ready')
        ->and($r['params']['percent'])->toEqual(2)
        ->and($r['params'])->not->toHaveKey('amount')
        ->and($r['params']['channel_code'])->toBe('pos');
});

test('card charge offers only cards set to manual charges, and asks when the name fits two', function () {
    $f = fuelPaletteSeed();

    $none = fuelPaletteResolve($this, $f, ['amount' => '100']);
    expect($none['status'])->toBe('pending')
        ->and($none['slots']['channel']['state'])->toBe('choices')
        ->and(array_column($none['slots']['channel']['choices'], 'value'))->toBe(['pos', 'hbl2']);

    $two = fuelPaletteResolve($this, $f, ['channel' => 'hbl', 'amount' => '100']);
    expect($two['status'])->toBe('pending')->and($two['slots']['channel']['state'])->toBe('choices');

    $automatic = fuelPaletteResolve($this, $f, ['channel' => 'ubl', 'amount' => '100']);
    expect($automatic['slots']['channel']['state'])->toBe('not_found');
});

test('card charge refuses an amount it cannot read', function () {
    $f = fuelPaletteSeed();

    $r = fuelPaletteResolve($this, $f, ['channel' => 'pos', 'amount' => 'lots']);

    expect($r['status'])->toBe('pending')->and($r['slots']['amount'])->toBe(['state' => 'not_found', 'message' => 'Amount needed']);
});
