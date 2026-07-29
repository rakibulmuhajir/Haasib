<?php

use App\Modules\Umrah\Http\Requests\StoreVisaVendorRequest;
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
        'provides_mandatory_transport',
        'mandatory_transport_vendor_id',
        'adult_retail_amount',
        'adult_cost_amount',
        'child_retail_amount',
        'child_cost_amount',
        'included_bus_cost_amount',
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

it('does not require visa rates for a transport-only vendor', function () {
    $validator = visaVendorRateValidator([
        'vendor_type' => VisaVendor::TYPE_TRANSPORT_PROVIDER,
        'adult_retail_amount' => 0,
        'adult_cost_amount' => 0,
        'child_retail_amount' => 0,
        'child_cost_amount' => 0,
    ]);

    expect($validator->passes())->toBeTrue();
});

it('does not require a transport provider when included bus cost is zero', function () {
    $validator = visaVendorRateValidator([
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
        'provides_mandatory_transport' => false,
        'mandatory_transport_vendor_id' => null,
        'adult_retail_amount' => 500,
        'adult_cost_amount' => 400,
        'child_retail_amount' => 300,
        'child_cost_amount' => 250,
        'included_bus_cost_amount' => 0,
    ]);

    expect($validator->passes())->toBeTrue();
});

it('requires a transport provider when included bus cost is positive', function () {
    $validator = visaVendorRateValidator([
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
        'provides_mandatory_transport' => false,
        'mandatory_transport_vendor_id' => null,
        'adult_retail_amount' => 500,
        'adult_cost_amount' => 400,
        'child_retail_amount' => 300,
        'child_cost_amount' => 250,
        'included_bus_cost_amount' => 50,
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('mandatory_transport_vendor_id'))->toBeTrue();
});
