<?php

use App\Models\Company;
use App\Models\User;
use App\Support\Palette\PaletteSearchSources;

function paletteResolve($test, User $user, Company $company, string $command, array $slots = [])
{
    return $test->actingAs($user)->withHeader('X-Company-Slug', $company->slug)
        ->postJson('/api/palette/resolve', ['command' => $command, 'slots' => $slots]);
}

test('a generic command with its required slot filled is ready', function () {
    $f = paletteFixture(false);

    paletteResolve($this, $f['owner'], $f['company'], 'vendor.create', ['name' => ['text' => 'Ali Traders']])
        ->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('key', 'vendor.create')
        ->assertJsonPath('params.name', 'Ali Traders')
        ->assertJsonPath('card.what', 'Create a vendor');
});

test('an empty required slot leaves the command pending', function () {
    $f = paletteFixture(false);

    paletteResolve($this, $f['owner'], $f['company'], 'vendor.create')
        ->assertOk()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('slots.name.state', 'missing');
});

test('an unknown command is a 404', function () {
    $f = paletteFixture(false);

    paletteResolve($this, $f['owner'], $f['company'], 'nothing.here')
        ->assertNotFound()
        ->assertJson(['status' => 'error', 'message' => 'Unknown command']);
});

test('a fuel command on a general company is a 404', function () {
    $f = paletteFixture(false);

    paletteResolve($this, $f['owner'], $f['company'], 'fuel.amanat.movement')->assertNotFound();
});

test('a command the user may not run is a 404', function () {
    $f = paletteFixture(false, 'operations');

    paletteResolve($this, $f['other'], $f['company'], 'vendor.create', ['name' => ['text' => 'Nope']])->assertNotFound();
});

test('sentences list generic commands for catalog entries no declared command targets', function () {
    $f = paletteFixture(false);

    $sentences = collect($this->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)
        ->getJson('/api/palette/catalog')->assertOk()->json('sentences'));
    $vendor = $sentences->firstWhere('name', 'vendor.create');

    expect($vendor['generic'])->toBeTrue()
        ->and($vendor['key'])->toBe('vendor.create')
        ->and($vendor['label'])->toBe('Create a vendor');
});

test('resolve needs a command name', function () {
    $f = paletteFixture(false);

    $this->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)
        ->postJson('/api/palette/resolve', ['slots' => []])->assertUnprocessable();
});

test('a search slot with several hits gives choices and none gives not found', function () {
    $f = paletteFixture(false);
    PaletteSearchSources::register('vendor', fn ($companyId, $q) => $q === 'ali'
        ? [['value' => 'a', 'label' => 'Ali Traders'], ['value' => 'b', 'label' => 'Ali Mills']]
        : []);

    // vendor.update-style entries are not guaranteed; assert through any generic command with a vendor search slot.
    $entry = collect(app(App\Support\Palette\PaletteCatalog::class)->for($f['owner'], $f['company']))
        ->first(fn ($e) => collect(array_merge($e['args'], $e['flags']))->contains(fn ($x) => ($x['searchEntity'] ?? null) === 'vendor' && $x['required']));
    if (! $entry) {
        $this->markTestSkipped('No catalog entry requires a vendor search.');
    }
    $field = collect(array_merge($entry['args'], $entry['flags']))->first(fn ($x) => ($x['searchEntity'] ?? null) === 'vendor' && $x['required']);

    paletteResolve($this, $f['owner'], $f['company'], $entry['key'], [$field['name'] => ['text' => 'ali']])
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath("slots.{$field['name']}.state", 'choices')
        ->assertJsonCount(2, "slots.{$field['name']}.choices");

    paletteResolve($this, $f['owner'], $f['company'], $entry['key'], [$field['name'] => ['text' => 'zzz']])
        ->assertJsonPath("slots.{$field['name']}.state", 'not_found');
});
