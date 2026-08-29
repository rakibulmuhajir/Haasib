<?php

use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TicketingFixtures.php';

/**
 * The group page used to work out "paid" in the browser, from
 * group.balance. An agent is not sent a balance, so the field arrived
 * undefined, Number(undefined || 0) came out as zero, and a group with
 * one small payment against it read Paid.
 *
 * The server has always known better -- payment_status is appended on
 * every VisaGroup -- so what these guard is that the answer keeps
 * reaching the page whose money the agent is not allowed to see. Hiding
 * an amount must not be able to change a status.
 */
function agentStatusFixture(float $receivable, float $paid): object
{
    $f = ticketingCompany([
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
        'base_currency' => 'SAR',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$f->user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f->company);
    ticketingAddCompanyMember($f->company, $f->user, 'owner');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$f->company->id]);

    $agentUser = User::factory()->withoutTwoFactor()->create();
    ticketingAddCompanyMember($f->company, $agentUser, 'agent');

    $agent = Agent::create([
        'company_id' => $f->company->id,
        'user_id' => $agentUser->id,
        'agent_number' => 'AGT-'.str()->upper(str()->random(5)),
        'name' => 'Status Agent',
    ]);
    $vendor = VisaVendor::create([
        'company_id' => $f->company->id,
        'vendor_number' => 'VIS-'.str()->upper(str()->random(5)),
        'name' => 'Visa Vendor',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
    ]);
    $group = VisaGroup::create([
        'company_id' => $f->company->id,
        'agent_id' => $agent->id,
        'vendor_id' => $vendor->id,
        'group_number' => 'UGR-'.str()->upper(str()->random(5)),
        'name' => 'Part paid group',
        'status' => VisaGroup::STATUS_VISA_APPROVED,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'transport_required' => false,
        'visa_sale_amount' => $receivable,
        'visa_cost_amount' => round($receivable * 0.8, 2),
        // These are plain columns the service maintains, not generated
        // ones, so a fixture that writes the group directly has to set
        // them or every group looks settled.
        'total_receivable' => $receivable,
        'total_paid' => $paid,
        'balance' => round($receivable - $paid, 2),
    ]);

    return (object) ['company' => $f->company, 'agentUser' => $agentUser, 'group' => $group];
}

test('a group with one small payment does not read as paid to its agent', function () {
    // Luna's Group C exactly: 400 paid against 8,460 receivable.
    $f = agentStatusFixture(8460, 400);

    $this->actingAs($f->agentUser)
        ->get("/{$f->company->slug}/umrah/groups/{$f->group->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Umrah/Groups/Show')
            ->where('group.payment_status', 'partially_paid'));
});

test('the agent is still not sent the balance the status is derived from', function () {
    $f = agentStatusFixture(8460, 400);

    // If balance ever starts coming through, the page would render
    // correctly for the wrong reason and the regression would hide.
    $this->actingAs($f->agentUser)
        ->get("/{$f->company->slug}/umrah/groups/{$f->group->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('group.balance')
            ->missing('group.profit'));
});

test('a fully settled group still reads as paid', function () {
    $f = agentStatusFixture(3600, 3600);

    $this->actingAs($f->agentUser)
        ->get("/{$f->company->slug}/umrah/groups/{$f->group->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('group.payment_status', 'paid'));
});

test('a group nobody has paid reads as unpaid', function () {
    $f = agentStatusFixture(8400, 0);

    $this->actingAs($f->agentUser)
        ->get("/{$f->company->slug}/umrah/groups/{$f->group->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('group.payment_status', 'unpaid'));
});
