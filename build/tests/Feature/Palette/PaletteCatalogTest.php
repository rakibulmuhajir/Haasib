<?php

use App\Models\Company;
use App\Models\User;

test('a general company catalog has core commands and no module commands', function () {
    $f = paletteFixture(false);
    $keys = array_column(paletteCatalog($this, $f['owner'], $f['company']), 'key');

    expect($keys)->toContain('vendor.create', 'invoice.create', 'payment.create');
    foreach ($keys as $k) {
        expect(str_starts_with($k, 'fuel.') || str_starts_with($k, 'calculator.'))->toBeFalse($k);
    }
});

test('a fuel company catalog includes the module commands', function () {
    $f = paletteFixture(true);
    $keys = array_column(paletteCatalog($this, $f['owner'], $f['company']), 'key');

    expect($keys)->toContain('fuel.amanat.movement', 'vendor.create');
});

test('a user without the permission does not see the command and execute still refuses it', function () {
    $f = paletteFixture(false, 'operations');
    $keys = array_column(paletteCatalog($this, $f['other'], $f['company']), 'key');
    expect($keys)->not->toContain('vendor.create');

    $this->actingAs($f["other"])->withHeader("X-Company-Slug", $f["company"]->slug)->postJson("/api/palette/execute", [
        'entity' => 'vendor', 'verb' => 'create', 'params' => ['name' => 'Nope'],
    ])->assertStatus(403);
});

test('entries carry the documented shape and array-input commands are forms', function () {
    $f = paletteFixture(false);
    $commands = collect(paletteCatalog($this, $f['owner'], $f['company']));
    $vendor = $commands->firstWhere('key', 'vendor.create');

    expect(array_keys($vendor))->toBe(['key', 'entity', 'verb', 'description', 'query', 'form', 'form_url', 'args', 'flags'])
        ->and($vendor['entity'])->toBe('vendor')->and($vendor['verb'])->toBe('create')
        ->and($vendor['description'])->toBe('Create a vendor')
        ->and($vendor['args'][0]['name'])->toBe('name')
        ->and($vendor['form'])->toBeFalse()
        ->and($commands->firstWhere('key', 'invoice.create')['form'])->toBeTrue()
        ->and($commands->firstWhere('key', 'vendor.list')['query'])->toBeTrue();
});

test('complex actions open their create page for this company', function () {
    $f = paletteFixture(false);
    $commands = collect(paletteCatalog($this, $f['owner'], $f['company']));
    $invoice = $commands->firstWhere('key', 'invoice.create');

    expect($invoice['form'])->toBeTrue()
        ->and($invoice['form_url'])->toBe('/'.$f['company']->slug.'/invoices/create')
        ->and($commands->firstWhere('key', 'vendor.create')['description'])->toBe('Create a vendor');
});

test('settings blobs and system actions are hidden, fuel ones included', function () {
    $f = paletteFixture(true);
    $keys = array_column(paletteCatalog($this, $f['owner'], $f['company']), 'key');

    expect($keys)->toContain('fuel.amanat.movement')
        ->not->toContain('fuel.settings.update', 'user.preferences.update', 'correction.invoice_split');
});

test('amanat movement takes its account as a search', function () {
    $f = paletteFixture(true);
    $amanat = collect(paletteCatalog($this, $f['owner'], $f['company']))->firstWhere('key', 'fuel.amanat.movement');
    $field = collect(array_merge($amanat['args'], $amanat['flags']))->firstWhere('name', 'payment_account_id');

    expect($amanat['description'])->toBe('Amanat deposit or withdrawal')
        ->and($field['inputType'])->toBe('search')
        ->and($field['searchEntity'])->toBe('account');
});
