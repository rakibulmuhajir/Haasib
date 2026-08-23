<?php

use App\Modules\Umrah\Http\Requests\StoreVisaGroupRequest;
use App\Services\CompanyContextService;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

function transportItemsRules(string $mode): array
{
    $companyContext = Mockery::mock(CompanyContextService::class);
    $companyContext->shouldReceive('getCompanyId')->andReturn(null);
    app()->instance(CompanyContextService::class, $companyContext);

    $request = StoreVisaGroupRequest::create('/travel/umrah/groups', 'POST', [
        'transport_mode' => $mode,
        'transport_items' => [],
    ]);

    return ['transport_items' => $request->rules()['transport_items']];
}

function noTransportRules(): array
{
    $companyContext = Mockery::mock(CompanyContextService::class);
    $companyContext->shouldReceive('getCompanyId')->andReturn(null);
    app()->instance(CompanyContextService::class, $companyContext);

    $request = StoreVisaGroupRequest::create('/travel/umrah/groups', 'POST', ['transport_mode' => 'none']);
    $rules = $request->rules();

    return collect($rules)->only([
        'transport_required',
        'transport_items',
    ])->all();
}

/**
 * Builds the request and runs prepareForValidation() the way the framework
 * would, so the assertions see exactly what the validator would be handed.
 */
function preparedGroupRequest(array $payload): StoreVisaGroupRequest
{
    $companyContext = Mockery::mock(CompanyContextService::class);
    $companyContext->shouldReceive('getCompanyId')->andReturn(null);
    app()->instance(CompanyContextService::class, $companyContext);

    $request = StoreVisaGroupRequest::create('/travel/umrah/groups', 'POST', $payload);

    (fn () => $this->prepareForValidation())->call($request);

    return $request;
}

function transportValidator(array $data, array $rules): Illuminate\Contracts\Validation\Validator
{
    $factory = new Factory(new Translator(new ArrayLoader(), 'en'));

    return $factory->make($data, $rules);
}

it('allows a standard bus group without specialized fare items', function () {
    $data = ['transport_items' => []];

    expect(transportValidator($data, transportItemsRules('standard_bus'))->passes())->toBeTrue();
});

it('requires a fare item for specialized transport', function () {
    $data = ['transport_items' => []];

    expect(transportValidator($data, transportItemsRules('specialized'))->errors()->has('transport_items'))->toBeTrue();
});

it('allows visa-only passengers without transport details', function () {
    $data = [
        'transport_required' => false,
        'transport_items' => [],
    ];

    expect(transportValidator($data, noTransportRules())->passes())->toBeTrue();
});

it('derives passenger service type from the group rather than the payload', function () {
    $request = preparedGroupRequest([
        'includes_visa' => '0',
        'transport_mode' => 'specialized',
        'passengers' => [
            ['full_name' => 'Ayesha Khan', 'service_type' => 'visa_transport', 'transport_charge_amount' => 900],
        ],
    ]);

    $passengers = $request->input('passengers');

    expect($passengers[0]['service_type'])->toBe('transport_only')
        ->and((float) $passengers[0]['transport_charge_amount'])->toBe(0.0)
        ->and($passengers[0]['full_name'])->toBe('Ayesha Khan');
});

it('marks every passenger of a visa group as taking the visa', function () {
    $request = preparedGroupRequest([
        'includes_visa' => '1',
        'transport_mode' => 'none',
        'passengers' => [
            ['full_name' => 'Bilal Ahmed', 'service_type' => 'transport_only', 'transport_charge_amount' => 500],
            ['full_name' => 'Sana Iqbal'],
        ],
    ]);

    expect(collect($request->input('passengers'))->pluck('service_type')->all())
        ->toBe(['visa_transport', 'visa_transport']);
});

it('keeps the derived fields reachable through validated()', function () {
    // The regression this guards: deriving in passedValidation() mutates the
    // request but not the validator's own copy of the data, so validated()
    // -- which is what the controller reads -- would never see either field.
    $rules = collect(preparedGroupRequest(['includes_visa' => '1'])->rules())
        ->only(['passengers.*.service_type', 'passengers.*.transport_charge_amount'])
        ->all();

    expect($rules)->toHaveKeys(['passengers.*.service_type', 'passengers.*.transport_charge_amount']);
});

it('rejects a group that sells neither visa nor transport', function () {
    $request = preparedGroupRequest([
        'includes_visa' => '0',
        'transport_mode' => 'none',
    ]);

    $rules = ['transport_mode' => $request->rules()['transport_mode']];

    expect(transportValidator(['transport_mode' => 'none'], $rules)->errors()->has('transport_mode'))->toBeTrue();
});

it('accepts a visa group that sells no transport', function () {
    $request = preparedGroupRequest([
        'includes_visa' => '1',
        'transport_mode' => 'none',
    ]);

    $rules = ['transport_mode' => $request->rules()['transport_mode']];

    expect(transportValidator(['transport_mode' => 'none'], $rules)->passes())->toBeTrue();
});
