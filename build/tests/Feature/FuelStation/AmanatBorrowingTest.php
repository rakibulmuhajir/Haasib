<?php

use App\Modules\FuelStation\Models\CustomerProfile;

require_once __DIR__.'/PendingDeliveryFixtures.php';

/*
 * "Allow borrowing" on an amanat holder: their balance may go below zero. Every other holder
 * is still refused beyond what they deposited (AmanatService::withdraw/applyToFuelPurchase and
 * the Daily Close amanat withdrawal all ask CustomerProfile::canDrawAmanat).
 */
test('a holder can draw only their balance unless borrowing is allowed', function () {
    $profile = new CustomerProfile(['amanat_balance' => 5000, 'allow_amanat_borrowing' => false]);
    expect($profile->canDrawAmanat(5000))->toBeTrue()
        ->and($profile->canDrawAmanat(5001))->toBeFalse();

    $profile->allow_amanat_borrowing = true;
    expect($profile->canDrawAmanat(50000))->toBeTrue();
});

test('the Allow borrowing tick saves on the holder', function () {
    $f = pendingDeliveryFixture();
    CustomerProfile::getOrCreateForCustomer($f['company']->id, $f['customer']->id)->update(['is_amanat_holder' => true]);

    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/amanat/{$f['customer']->id}/borrowing", ['allow_amanat_borrowing' => true])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(CustomerProfile::where('customer_id', $f['customer']->id)->sole()->allow_amanat_borrowing)->toBeTrue();
});
