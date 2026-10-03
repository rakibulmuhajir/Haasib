<?php

use Inertia\Testing\AssertableInertia as Assert;

/** Settings live in the Settings menu; the old Settings home only forwards to company settings. */
test('the old settings home forwards to company settings', function () {
    $f = fuelPricesPageFixture();

    test()->actingAs($f['user'])->get("/{$f['company']->slug}/setup")
        ->assertRedirect("/{$f['company']->slug}/settings");
});

test('the settings menu lists each group with what is inside it', function () {
    $f = fuelPricesPageFixture();
    $slug = $f['company']->slug;

    test()->actingAs($f['user'])->get("/{$slug}/fuel/rates")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.settingsMenu.0.title', 'Company')
            ->where('auth.settingsMenu.0.href', "/{$slug}/settings")
            ->where('auth.settingsMenu.0.items.0.title', 'General')
            ->where('auth.settingsMenu.1.title', 'Station settings')
            ->where('auth.settingsMenu.1.items.1.href', "/{$slug}/fuel/settings#monthly-profit"));
});
