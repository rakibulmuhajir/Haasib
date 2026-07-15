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
