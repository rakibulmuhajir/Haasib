<?php

use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Actions\AmanatMovementAction;
use App\Support\Palette\RuleIntrospector;
use Illuminate\Validation\Rule;

function palFields(array $rules): array
{
    $d = (new RuleIntrospector)->describe($rules);

    return collect($d['fields'])->keyBy('name')->all();
}

test('vendor create rules map to typed fields and skip the nested address', function () {
    $rules = (new \App\Modules\Accounting\Actions\Vendor\CreateAction)->rules();
    $f = palFields($rules);

    expect($f['name'])->toMatchArray(['required' => true, 'inputType' => 'text'])
        ->and($f['vendor_type']['inputType'])->toBe('select')
        ->and($f['vendor_type']['enum'])->toBe(array_keys(Vendor::TYPES))
        ->and($f['payment_terms']['inputType'])->toBe('number')
        ->and($f['email']['inputType'])->toBe('email')
        ->and($f['is_active']['enum'])->toBe(['true', 'false'])
        ->and($f['ap_account_id'])->toMatchArray(['inputType' => 'search', 'searchEntity' => 'account'])
        ->and($f['base_currency'])->toMatchArray(['inputType' => 'lookup', 'searchEntity' => 'currency'])
        ->and($f)->not->toHaveKey('address')
        ->and($f)->not->toHaveKey('address.city');
});

test('amanat movement rules map customer to a search and kind to a select', function () {
    $f = palFields(AmanatMovementAction::ruleSet());

    expect($f['customer_id'])->toMatchArray(['required' => true, 'inputType' => 'search', 'searchEntity' => 'customer'])
        ->and($f['kind'])->toMatchArray(['required' => true, 'inputType' => 'select', 'enum' => ['deposit', 'withdraw']])
        ->and($f['business_date']['inputType'])->toBe('date')
        ->and($f['amount']['inputType'])->toBe('number');
});

test('a required array makes the command complex; an optional one is skipped', function () {
    $d = (new RuleIntrospector)->describe(['customer_id' => 'required|uuid', 'line_items' => 'required|array|min:1', 'line_items.*.qty' => 'required|numeric']);
    expect($d['complex'])->toBeTrue()->and(array_column($d['fields'], 'name'))->toBe(['customer_id']);

    $d = (new RuleIntrospector)->describe(['name' => 'required', 'tags' => 'nullable|array']);
    expect($d['complex'])->toBeFalse()->and(array_column($d['fields'], 'name'))->toBe(['name']);
});

test('rule objects and array-form rules are read', function () {
    $f = palFields([
        'status' => ['required', Rule::in(['draft', 'sent'])],
        'due' => ['nullable', 'date_format:Y-m-d'],
        'note' => ['required_with:status', 'string'],
        'vendor_id' => ['required', 'uuid'],
        'credit_note_id' => ['nullable', 'uuid'],
    ]);

    expect($f['status'])->toMatchArray(['required' => true, 'inputType' => 'select', 'enum' => ['draft', 'sent']])
        ->and($f['due']['inputType'])->toBe('date')
        ->and($f['note']['required'])->toBeTrue()
        ->and($f['vendor_id']['searchEntity'])->toBe('vendor')
        ->and($f['credit_note_id']['inputType'])->toBe('text');
});
