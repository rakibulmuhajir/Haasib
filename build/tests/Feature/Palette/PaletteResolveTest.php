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

    paletteResolve($this, $f['owner'], $f['company'], 'customer_category.create', ['name' => ['text' => 'Government']])
        ->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('key', 'customer_category.create')
        ->assertJsonPath('params.name', 'Government')
        ->assertJsonPath('card.what', 'Create a customer category');
});

test('an empty required slot leaves the command pending', function () {
    $f = paletteFixture(false);

    paletteResolve($this, $f['owner'], $f['company'], 'customer_category.create')
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

    paletteResolve($this, $f['other'], $f['company'], 'add vendor', ['name' => ['text' => 'Nope']])->assertNotFound();
});

test('sentences list generic commands for catalog entries no declared command targets', function () {
    $f = paletteFixture(false);

    $sentences = collect($this->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)
        ->getJson('/api/palette/catalog')->assertOk()->json('sentences'));
    $vendor = $sentences->firstWhere('name', 'customer_category.create');

    expect($vendor['generic'])->toBeTrue()
        ->and($vendor['key'])->toBe('customer_category.create')
        ->and($vendor['label'])->toBe('Create a customer category')
        ->and($vendor['aliases'])->toBe(['create a customer category']);
});

test('a generic list or view is not offered and does not resolve', function () {
    $f = paletteFixture(false);

    $sentences = collect($this->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)
        ->getJson('/api/palette/catalog')->assertOk()->json('sentences'));

    expect($sentences->where('generic', true)->where('query', true))->toBeEmpty()
        ->and($sentences->firstWhere('name', 'vendor.list'))->toBeNull();

    paletteResolve($this, $f['owner'], $f['company'], 'vendor.list')->assertNotFound();
});

test('find looks up declared commands by name and builds generics only for a dotted name', function () {
    $f = paletteFixture(false);
    $catalog = app(App\Support\Palette\PaletteCatalog::class);

    expect($catalog->find($f['owner'], $f['company'], 'add vendor')?->name())->toBe('add vendor')
        ->and($catalog->find($f['owner'], $f['company'], 'customer_category.create'))->toBeInstanceOf(App\Support\Palette\Commands\GenericCommand::class)
        ->and($catalog->find($f['owner'], $f['company'], 'create a customer category'))->toBeNull()
        ->and($catalog->find($f['owner'], $f['company'], 'vendor.list'))->toBeNull()
        ->and($catalog->find($f['owner'], null, 'add vendor'))->toBeNull();
});

test('a generic search slot ignores a picked id from another company', function () {
    $b = paletteFixture(false);
    $theirs = App\Modules\Accounting\Models\Vendor::create(['company_id' => $b['company']->id, 'vendor_number' => 'V-B1', 'name' => 'Other Co Vendor', 'base_currency' => 'PKR']);
    $f = paletteFixture(false);
    $ours = App\Modules\Accounting\Models\Vendor::create(['company_id' => $f['company']->id, 'vendor_number' => 'V-A1', 'name' => 'Our Vendor', 'base_currency' => 'PKR']);

    $catalog = app(App\Support\Palette\PaletteCatalog::class);
    $entry = collect($catalog->for($f['owner'], $f['company']))
        ->first(fn ($e) => $catalog->find($f['owner'], $f['company'], $e['key']) instanceof App\Support\Palette\Commands\GenericCommand && collect(array_merge($e['args'], $e['flags']))->contains(fn ($x) => ($x['searchEntity'] ?? null) === 'vendor' && $x['required']));
    if (! $entry) {
        $this->markTestSkipped('No catalog entry requires a vendor search.');
    }
    $field = collect(array_merge($entry['args'], $entry['flags']))->first(fn ($x) => ($x['searchEntity'] ?? null) === 'vendor' && $x['required']);

    $r = paletteResolve($this, $f['owner'], $f['company'], $entry['key'], [$field['name'] => ['value' => $theirs->id]])->assertOk()->json();
    expect($r['status'])->not->toBe('ready')
        ->and($r['slots'][$field['name']]['state'])->toBe('not_found');

    // The typed text is searched in this company instead of trusting the pick.
    $r = paletteResolve($this, $f['owner'], $f['company'], $entry['key'], [$field['name'] => ['text' => 'Other Co Vendor', 'value' => $theirs->id]])->assertOk()->json();
    expect($r['status'])->not->toBe('ready')
        ->and($r['slots'][$field['name']]['state'])->toBe('not_found');

    $r = paletteResolve($this, $f['owner'], $f['company'], $entry['key'], [$field['name'] => ['value' => $ours->id]])->assertOk()->json();
    // Ready carries params, not slots; if other required slots remain, it stays pending with slot state.
    if ($r['status'] === 'ready') {
        expect($r['params'][$field['name']])->toBe($ours->id);
    } else {
        expect($r['slots'][$field['name']])->toBe(['state' => 'resolved', 'value' => $ours->id, 'label' => 'Our Vendor']);
    }
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
    $catalog = app(App\Support\Palette\PaletteCatalog::class);
    $entry = collect($catalog->for($f['owner'], $f['company']))
        ->first(fn ($e) => $catalog->find($f['owner'], $f['company'], $e['key']) instanceof App\Support\Palette\Commands\GenericCommand && collect(array_merge($e['args'], $e['flags']))->contains(fn ($x) => ($x['searchEntity'] ?? null) === 'vendor' && $x['required']));
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

test('a required flag is written as a plain blank in the sentence, an optional one with a question mark', function () {
    $command = new App\Support\Palette\Commands\GenericCommand([
        'key' => 'thing.make', 'entity' => 'thing', 'verb' => 'make', 'description' => 'Make a thing',
        'query' => false, 'form' => false, 'form_url' => null,
        'args' => [['name' => 'name', 'required' => true, 'inputType' => 'text']],
        'flags' => [
            ['name' => 'size', 'required' => true, 'inputType' => 'number'],
            ['name' => 'note', 'required' => false, 'inputType' => 'text'],
        ],
    ]);

    expect($command->sentence())->toBe('Make a thing {name} {size} {note?}');
});
