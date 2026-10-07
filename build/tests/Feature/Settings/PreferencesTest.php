<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('value trails default on and personal settings work without a company', function () {
    $user = User::factory()->create();

    expect($user->showsValueTrails())->toBeTrue();
    $this->actingAs($user)->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/Appearance')
            ->where('auth.preferences.show_value_trails', true));
});

test('preferences persist on the caller alone and preserve unrelated settings', function () {
    $user = User::factory()->create(['settings' => ['unrelated' => 'keep']]);
    $other = User::factory()->create(['settings' => ['show_value_trails' => true]]);

    $this->actingAs($user)->from(route('appearance.edit'))
        ->patch(route('appearance.update'), ['show_value_trails' => false, 'user_id' => $other->id])
        ->assertRedirect(route('appearance.edit'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Preferences saved.');

    expect($user->fresh()->settings)->toBe(['unrelated' => 'keep', 'show_value_trails' => false])
        ->and($other->fresh()->showsValueTrails())->toBeTrue();

    // A fresh request reflects the stored setting, not a browser-local value.
    $this->actingAs($user->fresh())->get(route('appearance.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.preferences.show_value_trails', false));
});

test('invalid preferences leave the saved value unchanged and can be enabled again', function () {
    $user = User::factory()->create(['settings' => ['show_value_trails' => false]]);
    $this->actingAs($user)->from(route('appearance.edit'))
        ->patch(route('appearance.update'), ['show_value_trails' => 'not-a-boolean'])
        ->assertSessionHasErrors('show_value_trails');
    expect($user->fresh()->showsValueTrails())->toBeFalse();

    $this->patch(route('appearance.update'), ['show_value_trails' => true])->assertSessionHasNoErrors();
    expect($user->fresh()->showsValueTrails())->toBeTrue();
});

test('guests cannot save account preferences', function () {
    $this->patch(route('appearance.update'), ['show_value_trails' => false])->assertRedirect(route('login'));
});
