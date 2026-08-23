<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\VisaGroup;
use Illuminate\Support\Facades\DB;

function makeVisaGroupCompany(string $suffix): array
{
    $user = User::factory()->create();
    $company = Company::create([
        'name' => "Group Service Choice Test {$suffix}",
        'slug' => 'group-service-choice-test-'.$suffix,
        'owner_id' => $user->id,
        'base_currency' => 'SAR',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => "AGT-GSC-{$suffix}",
        'name' => 'Group Service Choice Agent',
    ]);

    return [$company, $agent];
}

it('defaults an existing group to including a visa', function () {
    [$company, $agent] = makeVisaGroupCompany('a');

    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-GSC-A',
        'name' => 'Visa default group',
        'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
    ]);

    expect($group->fresh()->includes_visa)->toBeTrue();
});

it('offers no visa statuses to a transport-only group', function () {
    expect(array_keys(VisaGroup::statusesAvailable(false)))
        ->toBe(['draft', 'delivered', 'closed', 'cancelled']);

    expect(array_keys(VisaGroup::statusesAvailable(true)))
        ->toBe(array_keys(VisaGroup::STATUSES));
});

it('refuses a group that is neither visa nor transport', function () {
    [$company, $agent] = makeVisaGroupCompany('b');

    expect(fn () => VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-GSC-B',
        'name' => 'Neither visa nor transport group',
        'includes_visa' => false,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('refuses to park a transport-only group in a visa status', function () {
    [$company, $agent] = makeVisaGroupCompany('c');

    // statusesAvailable() decides what the UI offers; this is the half that
    // decides what the record may hold. Without it a transport-only group
    // could still be written straight to 'submitted' by any path that
    // bypasses the picker -- an import, a console command, a later feature.
    expect(fn () => VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-GSC-C',
        'name' => 'Transport-only group in a visa status',
        'includes_visa' => false,
        'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
        'status' => VisaGroup::STATUS_SUBMITTED,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
