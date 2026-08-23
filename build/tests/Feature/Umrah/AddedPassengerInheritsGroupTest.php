<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Http\Requests\StorePassengerRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Services\CompanyContextService;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Mockery as m;

/*
 * The group is asked once what it sells. A passenger added to it afterwards
 * -- through the group page rather than the create form -- has to inherit
 * that answer, or the two ways into a group would disagree and the older
 * one would quietly put the group back into the state the group-level
 * choice was introduced to end.
 *
 * These exercise the request rather than the route. Adding a passenger
 * posts to the ledger, which needs a full chart of accounts; the derivation
 * is what is under test here and it happens before any of that.
 */

function inheritTestGroup(bool $includesVisa): VisaGroup
{
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Passenger Inherit Test '.str()->random(8),
        'slug' => 'passenger-inherit-test-'.str()->lower(str()->random(10)),
        'owner_id' => $user->id,
        'base_currency' => 'SAR',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-INH-'.str()->upper(str()->random(5)),
        'name' => 'Passenger Inherit Agent',
    ]);

    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-INH-'.str()->upper(str()->random(5)),
        'name' => 'Passenger Inherit Group',
        'includes_visa' => $includesVisa,
        'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
    ]);

    $context = m::mock(CompanyContextService::class);
    $context->shouldReceive('getCompanyId')->andReturn($company->id);
    app()->instance(CompanyContextService::class, $context);

    return $group;
}

/**
 * Builds the add-passenger request against a group and runs
 * prepareForValidation() the way the framework would.
 */
function preparedPassengerRequest(VisaGroup $group, array $payload): StorePassengerRequest
{
    $uri = "umrah/groups/{$group->id}/passengers";
    $request = StorePassengerRequest::create("/{$uri}", 'POST', $payload);

    $route = new RoutingRoute('POST', 'umrah/groups/{group}/passengers', []);
    $route->bind($request);
    $route->setParameter('group', $group->id);
    $request->setRouteResolver(fn () => $route);

    (fn () => $this->prepareForValidation())->call($request);

    return $request;
}

test('a passenger added to a transport-only group takes no visa', function () {
    $group = inheritTestGroup(false);

    // The payload claims a visa and a fare of its own. Both are ignored.
    $request = preparedPassengerRequest($group, [
        'full_name' => 'Late Arrival',
        'passport_number' => 'INH-1',
        'service_type' => Passenger::SERVICE_VISA_TRANSPORT,
        'transport_charge_amount' => 750,
    ]);

    expect($request->input('service_type'))->toBe(Passenger::SERVICE_TRANSPORT_ONLY)
        ->and((float) $request->input('transport_charge_amount'))->toBe(0.0);
});

test('a passenger added to a visa group takes the visa', function () {
    $group = inheritTestGroup(true);

    $request = preparedPassengerRequest($group, [
        'full_name' => 'Late Arrival',
        'passport_number' => 'INH-2',
        'service_type' => Passenger::SERVICE_TRANSPORT_ONLY,
        'transport_charge_amount' => 750,
    ]);

    expect($request->input('service_type'))->toBe(Passenger::SERVICE_VISA_TRANSPORT)
        ->and((float) $request->input('transport_charge_amount'))->toBe(0.0);
});

test('the derived fields survive into validated()', function () {
    $group = inheritTestGroup(false);

    // Same trap as the group create form: validated() returns only
    // attributes that carry a rule, so a derived field with its rule
    // removed would never reach the controller.
    $rules = preparedPassengerRequest($group, ['full_name' => 'Late Arrival'])->rules();

    expect($rules)->toHaveKeys(['service_type', 'transport_charge_amount']);
});
