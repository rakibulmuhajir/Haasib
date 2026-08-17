<?php

use App\Modules\Umrah\Http\Requests\StoreVisaVendorRequest;
use App\Modules\Umrah\Http\Requests\StoreTransportProviderRequest;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyContextService;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

function visaVendorRateValidator(array $data): Illuminate\Contracts\Validation\Validator
{
    $companyContext = Mockery::mock(CompanyContextService::class);
    $companyContext->shouldReceive('getCompanyId')->andReturn(null);
    app()->instance(CompanyContextService::class, $companyContext);

    $request = StoreVisaVendorRequest::create('/travel/umrah/vendors', 'POST', $data);
    $allRules = $request->rules();
    $rules = array_intersect_key($allRules, array_flip([
        'vendor_type',
        'adult_retail_amount',
        'adult_cost_amount',
        'child_retail_amount',
        'child_cost_amount',
    ]));
    $factory = new Factory(new Translator(new ArrayLoader, 'en'));

    return $factory->make($data, $rules);
}

it('requires positive adult and child rates for a visa vendor', function () {
    $validator = visaVendorRateValidator([
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
        'adult_retail_amount' => 0,
        'adult_cost_amount' => 0,
        'child_retail_amount' => 0,
        'child_cost_amount' => 0,
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('adult_retail_amount'))->toBeTrue()
        ->and($validator->errors()->has('adult_cost_amount'))->toBeTrue()
        ->and($validator->errors()->has('child_retail_amount'))->toBeTrue()
        ->and($validator->errors()->has('child_cost_amount'))->toBeTrue();
});

it('accepts positive adult and child rates for a visa vendor', function () {
    $validator = visaVendorRateValidator([
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
        'adult_retail_amount' => 500,
        'adult_cost_amount' => 400,
        'child_retail_amount' => 300,
        'child_cost_amount' => 250,
    ]);

    expect($validator->passes())->toBeTrue();
});

it('rejects transport providers from the visa vendor CRUD', function () {
    $validator = visaVendorRateValidator([
        'vendor_type' => VisaVendor::TYPE_TRANSPORT_PROVIDER,
        'adult_retail_amount' => 0,
        'adult_cost_amount' => 0,
        'child_retail_amount' => 0,
        'child_cost_amount' => 0,
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('vendor_type'))->toBeTrue();
});

it('validates independent transport rates and the child fare checkbox', function () {
    $request = StoreTransportProviderRequest::create('/travel/umrah/transport-providers', 'POST', [
        'standard_bus_retail_amount' => 100,
        'standard_bus_cost_amount' => 80,
        'charge_child_fare' => false,
    ]);
    $rules = array_intersect_key($request->rules(), array_flip([
        'standard_bus_retail_amount',
        'standard_bus_cost_amount',
        'charge_child_fare',
    ]));
    $factory = new Factory(new Translator(new ArrayLoader, 'en'));
    $validator = $factory->make($request->all(), $rules);

    expect($validator->passes())->toBeTrue();
});

it('requires the child fare choice for a transport provider', function () {
    $request = StoreTransportProviderRequest::create('/travel/umrah/transport-providers', 'POST', [
        'standard_bus_retail_amount' => 100,
        'standard_bus_cost_amount' => 80,
    ]);
    $rules = array_intersect_key($request->rules(), array_flip([
        'standard_bus_retail_amount',
        'standard_bus_cost_amount',
        'charge_child_fare',
    ]));
    $factory = new Factory(new Translator(new ArrayLoader, 'en'));
    $validator = $factory->make($request->all(), $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('charge_child_fare'))->toBeTrue();
});
