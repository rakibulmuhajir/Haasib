<?php

use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Models\Voucher;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TicketingFixtures.php';

/**
 * What a refund may say it paid back.
 */
test('an agent can be refunded any part of their package', function () {
    // A group is built from visas already approved, which makes returning
    // one rare -- but a list that omits the rare case forces whoever meets
    // it to file the thing as Other, which loses more than it saves.
    expect(array_keys(Refund::servicesFor(Refund::PARTY_AGENT)))
        ->toContain(Refund::SERVICE_VISA)
        ->toContain(Refund::SERVICE_TRANSPORT)
        ->toContain(Refund::SERVICE_HOTEL)
        ->toContain(Refund::SERVICE_TICKET);
});

test('a visa vendor can still refund a visa fee to the company', function () {
    // The other direction: a supplier giving money back, which a visa desk
    // does in the ordinary course of business.
    expect(array_keys(Refund::servicesFor(Refund::PARTY_VISA_VENDOR)))
        ->toContain(Refund::SERVICE_VISA);
});

test('a refund can name the hotel, the ticket or the seat', function () {
    // One person out of a group not travelling is the ordinary case, and
    // these are the three parts of their package that can come back.
    expect(array_keys(Refund::servicesFor(Refund::PARTY_AGENT)))
        ->toContain(Refund::SERVICE_HOTEL)
        ->toContain(Refund::SERVICE_TICKET)
        ->toContain(Refund::SERVICE_TRANSPORT);
});

test('a refund already recorded against a visa can still be read back', function () {
    // The rule changed; what was recorded under the old one did not stop
    // being true, and the statement still has to be able to label it.
    expect(Refund::SERVICES)->toHaveKey(Refund::SERVICE_VISA)
        ->and(Refund::SERVICES)->toHaveKey(Refund::SERVICE_TICKET);
});

test('the database accepts every service the model can label', function () {
    $allowed = collect(DB::select("select pg_get_constraintdef(oid) as def from pg_constraint where conrelid = 'umrah.refunds'::regclass and conname = 'umrah_refunds_service_check'"))
        ->first()?->def ?? '';

    foreach (array_keys(Refund::SERVICES) as $service) {
        expect($allowed)->toContain("'{$service}'");
    }
});

/**
 * The voucher cutoff, which an agent could walk straight past by leaving
 * the dates empty.
 */
function cutoffFixture(string $travelDate): object
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
        'name' => 'Cutoff Agent',
        'can_create_voucher' => true,
        'can_edit_voucher' => true,
        'voucher_cutoff_hours' => 24,
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
        'name' => 'Cutoff group',
        'status' => VisaGroup::STATUS_VISA_APPROVED,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'transport_required' => false,
        'travel_date' => $travelDate,
        'visa_sale_amount' => 900,
        'visa_cost_amount' => 750,
        'total_receivable' => 900,
    ]);
    $passenger = Passenger::create([
        'company_id' => $f->company->id,
        'visa_group_id' => $group->id,
        'full_name' => 'Cutoff Passenger',
        'service_type' => Passenger::SERVICE_VISA_TRANSPORT,
    ]);

    return (object) [
        'company' => $f->company,
        'agentUser' => $agentUser,
        'group' => $group,
        'passenger' => $passenger,
    ];
}

function cutoffPayload(object $f): array
{
    // A hotel-bundle draft whose only stay row is still blank -- exactly
    // the shape QA used to open a voucher after the group had flown. The
    // row survives validation and is then dropped as a placeholder, which
    // is why the saved voucher carried no stays at all.
    return [
        'visa_group_id' => $f->group->id,
        'title' => 'Cutoff draft',
        'service_bundle' => Voucher::SERVICE_HOTEL,
        'status' => Voucher::STATUS_DRAFT,
        'passenger_ids' => [$f->passenger->id],
        'passenger_services' => [$f->passenger->id => 'visa_transport'],
        'hotel_stays' => [[
            'source' => 'self',
            'hotel_id' => null,
            'hotel_name' => null,
            'city' => null,
            'room_type' => null,
            'room_count' => 1,
            'check_in_date' => null,
            'check_out_date' => null,
            'notes' => null,
        ]],
    ];
}

test('an agent cannot open a dateless voucher on a group that has already travelled', function () {
    $f = cutoffFixture(now()->subDays(3)->toDateString());

    $this->actingAs($f->agentUser)
        ->post("/{$f->company->slug}/umrah/vouchers", cutoffPayload($f))
        ->assertSessionHasErrors('visa_group_id');

    expect(Voucher::where('company_id', $f->company->id)->count())->toBe(0);
});

test('an agent cannot open one inside the cutoff either', function () {
    // Twelve hours out, against a twenty-four hour cutoff.
    $f = cutoffFixture(now()->addHours(12)->toDateString());

    $this->actingAs($f->agentUser)
        ->post("/{$f->company->slug}/umrah/vouchers", cutoffPayload($f))
        ->assertSessionHasErrors('visa_group_id');

    expect(Voucher::where('company_id', $f->company->id)->count())->toBe(0);
});

test('an agent can still open one in good time', function () {
    $f = cutoffFixture(now()->addDays(30)->toDateString());

    $response = $this->actingAs($f->agentUser)
        ->post("/{$f->company->slug}/umrah/vouchers", cutoffPayload($f));

    // assertSessionHasNoErrors passes on a 500, so the redirect is the
    // half that proves it actually saved.
    $response->assertSessionHasNoErrors()->assertRedirect();

    expect(Voucher::where('company_id', $f->company->id)->count())->toBe(1);
});
