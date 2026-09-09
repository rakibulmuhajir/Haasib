<?php

use App\Facades\CompanyContext;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use App\Modules\Umrah\Services\VoucherPrintProfiles;
use App\Modules\Umrah\Services\VoucherWorkflowService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/TicketingFixtures.php';

test('voucher passenger table prints age without birth date nationality or visa status', function (?string $birth, ?int $imported, string $expected) {
    $f = printProfileFixture();
    $f->passenger->update(['date_of_birth' => $birth, 'imported_age' => $imported]);
    $f->voucher->update(['onward_departure_at' => '2026-10-01 10:00:00', 'hotel_stays' => [['hotel_name' => 'Age Test Hotel', 'night_count' => 2]]]);
    $this->actingAs($f->user)->get('/'.$f->company->slug.'/umrah/vouchers/'.$f->voucher->id.'/print')
        ->assertOk()->assertSee('>Age</th>', false)->assertSee('<td>'.$expected.'</td>', false)
        ->assertDontSee('>Nationality</th>', false)->assertDontSee('>Visa status</th>', false)
        ->assertDontSee('Date of birth')->assertSee('>Location</th>', false)->assertDontSee('>Map</th>', false);
})->with([
    'birthday reached' => ['2000-10-01', null, '26'],
    'birthday tomorrow' => ['2000-10-02', null, '25'],
    'imported age' => [null, 43, '43'],
    'infant' => [null, 0, '0'],
    'unknown' => [null, null, '—'],
    'future birth' => ['2027-01-01', null, '—'],
]);

function printProfileContact(string $name = 'Makkah Representative'): array
{
    return ['name' => $name, 'responsibility' => 'Makkah assistance', 'organization' => 'Local Supplier', 'city' => 'Makkah', 'phone' => '+966 500 000 000', 'whatsapp' => ''];
}

function printProfileFixture(): object
{
    $f = ticketingCompany(['industry_code' => 'umrah', 'settings' => ['modules' => ['umrah' => true], 'contact_phone' => 'KEEP-ME'], 'base_currency' => 'SAR']);
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$f->user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f->company);
    DB::table('auth.company_user')->insert(['company_id' => $f->company->id, 'user_id' => $f->user->id, 'role' => 'owner', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    CompanyContext::withContext($f->company, fn () => CompanyContext::assignRole($f->user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    CompanyContext::setContext($f->company);
    $agent = Agent::create(['company_id' => $f->company->id, 'agent_number' => 'PRINT-AGENT', 'name' => 'Print Agent']);
    $vendor = VisaVendor::create(['company_id' => $f->company->id, 'vendor_number' => 'PRINT-VENDOR', 'name' => 'Print Provider', 'service_type' => VisaVendor::SERVICE_VISA_PROVIDER]);
    $group = VisaGroup::create(['company_id' => $f->company->id, 'agent_id' => $agent->id, 'vendor_id' => $vendor->id, 'group_number' => 'PRINT-GROUP', 'name' => 'Print Group', 'transport_mode' => VisaGroup::TRANSPORT_NONE, 'travel_date' => now()->addDays(50)->toDateString()]);
    $voucher = Voucher::create(['company_id' => $f->company->id, 'agent_id' => $agent->id, 'visa_group_id' => $group->id, 'voucher_number' => 'PRINT-001', 'title' => 'Print voucher', 'service_bundle' => Voucher::SERVICE_HOTEL, 'status' => Voucher::STATUS_DRAFT, 'hotel_stays' => []]);
    $passenger = Passenger::create(['company_id' => $f->company->id, 'visa_group_id' => $group->id, 'full_name' => 'Synthetic Passenger', 'service_type' => Passenger::SERVICE_HOTEL_ONLY]);
    VoucherPassenger::create(['company_id' => $f->company->id, 'visa_group_id' => $group->id, 'voucher_id' => $voucher->id, 'passenger_id' => $passenger->id]);

    return (object) [...(array) $f, ...compact('agent', 'vendor', 'group', 'voucher', 'passenger')];
}

function printProfileUpdateData(array $details): array
{
    return ['title' => 'Print voucher', 'service_bundle' => 'hotel', 'hotel_stays' => [['source' => 'self', 'hotel_name' => 'Test hotel', 'city' => 'Makkah', 'room_type' => 'double', 'room_count' => 1]], 'print_details' => $details];
}

test('party logo upload redirects with its URL and preserves the old saved file', function () {
    $f = printProfileFixture();
    \Illuminate\Support\Facades\Storage::fake('public');
    $old = 'party-logos/'.$f->company->id.'/old.png';
    \Illuminate\Support\Facades\Storage::disk('public')->put($old, 'existing');
    $response = $this->actingAs($f->user)->from('/'.$f->company->slug.'/umrah/agents')
        ->post('/'.$f->company->slug.'/umrah/logos', [
            'logo' => \Illuminate\Http\UploadedFile::fake()->image('logo.png')->size(650),
            'replacing' => '/storage/'.$old,
        ]);
    $response->assertRedirect('/'.$f->company->slug.'/umrah/agents')->assertSessionHasNoErrors()->assertSessionHas('uploaded_logo_url');
    $url = session('uploaded_logo_url');
    expect($url)->toStartWith('/storage/party-logos/'.$f->company->id.'/');
    \Illuminate\Support\Facades\Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));
    \Illuminate\Support\Facades\Storage::disk('public')->assertExists($old);
    $this->put('/'.$f->company->slug.'/umrah/agents/'.$f->agent->id, ['name' => $f->agent->name, 'agent_number' => $f->agent->agent_number, 'logo_url' => $url])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($f->agent->fresh()->logo_url)->toBe($url);
});

test('party logo validation rejects foreign storage and unsafe paths', function () {
    $f = printProfileFixture();
    app(\App\Services\CurrentCompany::class)->set($f->company);
    $rule = new \App\Modules\Umrah\Rules\PartyLogoUrl;
    foreach (['/storage/party-logos/foreign/'.str()->uuid().'.png', '/storage/party-logos/'.$f->company->id.'/../secret.png', 'javascript:alert(1)', '//example.com/logo.png'] as $url) {
        expect(\Illuminate\Support\Facades\Validator::make(['logo_url' => $url], ['logo_url' => [$rule]])->fails())->toBeTrue();
    }
    expect(\Illuminate\Support\Facades\Validator::make(['logo_url' => 'https://example.com/logo.png'], ['logo_url' => [$rule]])->passes())->toBeTrue();
});

test('owner can save each print profile without replacing unrelated company settings', function (string $type) {
    $f = printProfileFixture();
    $target = match ($type) {
        'agent' => 'agent:'.$f->agent->id, 'vendor' => 'vendor:'.$f->vendor->id, default => 'company'
    };
    $details = ['footer_text' => 'Keep your voucher available.', 'contacts' => [printProfileContact()]];
    $this->actingAs($f->user)->put('/'.$f->company->slug.'/umrah/settings/voucher', compact('target', 'details'))->assertSessionHasNoErrors()->assertRedirect();
    $saved = match ($type) {
        'agent' => $f->agent->fresh()->voucher_settings, 'vendor' => $f->vendor->fresh()->voucher_settings, default => $f->company->fresh()->settings['umrah_voucher']
    };
    expect($saved['contacts'][0]['name'])->toBe('Makkah Representative')->and($saved['footer_text'])->toBe($details['footer_text'])->and($f->company->fresh()->settings['contact_phone'])->toBe('KEEP-ME');
})->with(['company', 'agent', 'vendor']);

test('invalid contact and footer data is rejected', function (array $details, string $error) {
    $f = printProfileFixture();
    $this->actingAs($f->user)->put('/'.$f->company->slug.'/umrah/settings/voucher', ['target' => 'company', 'details' => $details])->assertSessionHasErrors($error);
    expect($f->company->fresh()->settings)->not->toHaveKey('umrah_voucher');
})->with([
    [['contacts' => [array_replace(printProfileContact(), ['name' => ''])]], 'details.contacts.0.name'],
    [['contacts' => [array_replace(printProfileContact(), ['phone' => ''])]], 'details.contacts.0.phone'],
    [['contacts' => [array_replace(printProfileContact(), ['responsibility' => ''])]], 'details.contacts.0.responsibility'],
    [['contacts' => [], 'footer_text' => str_repeat('x', 2001)], 'details.footer_text'],
    [['contacts' => array_fill(0, 13, printProfileContact())], 'details.contacts'],
    [['footer_text' => 'Missing array'], 'details'],
    [['contacts' => [], 'arbitrary_html' => '<script>bad</script>'], 'details'],
]);

test('agent defaults override footer while supplier contacts remain explicit choices', function () {
    $f = printProfileFixture();
    $f->company->update(['settings' => [...$f->company->settings, 'umrah_voucher' => ['footer_text' => 'Company footer', 'contacts' => [printProfileContact('Company rep')]]]]);
    $f->agent->update(['voucher_settings' => ['footer_text' => 'Agent footer', 'contacts' => [printProfileContact('Agent rep')]]]);
    $f->vendor->update(['voucher_settings' => ['footer_text' => 'Vendor footer', 'contacts' => [printProfileContact('Vendor rep')]]]);
    $defaults = app(VoucherPrintProfiles::class)->defaults($f->company->fresh(), $f->agent->id);
    expect($defaults['footer_text'])->toBe('Agent footer')->and(array_column($defaults['contacts'], 'name'))->toBe(['Company rep', 'Agent rep']);
    $f->agent->update(['voucher_settings' => ['footer_text' => '', 'contacts' => []]]);
    expect(app(VoucherPrintProfiles::class)->defaults($f->company->fresh(), $f->agent->id)['footer_text'])->toBe('Company footer');
});

test('draft snapshot can be edited and deliberately cleared without reapplying defaults', function () {
    $f = printProfileFixture();
    $url = '/'.$f->company->slug.'/umrah/vouchers/'.$f->voucher->id;
    $this->actingAs($f->user)->put($url, printProfileUpdateData(['footer_text' => 'Saved footer', 'contacts' => [printProfileContact()]]))->assertSessionHasNoErrors();
    expect($f->voucher->fresh()->print_details['contacts'][0]['name'])->toBe('Makkah Representative');
    $this->put($url, printProfileUpdateData(['footer_text' => '', 'contacts' => []]))->assertSessionHasNoErrors();
    expect($f->voucher->fresh()->print_details['contacts'])->toBe([])->and($f->voucher->fresh()->print_details['footer_text'])->toBeNull();
});

test('amendment copies contacts and later profile changes do not change issued print', function () {
    $f = printProfileFixture();
    $f->voucher->update(['status' => Voucher::STATUS_APPROVED, 'print_details' => ['footer_text' => 'Original footer', 'contacts' => [printProfileContact('Original rep')]]]);
    $amendment = app(VoucherWorkflowService::class)->createAmendment($f->voucher, 'PRINT-AMEND', $f->user->id);
    expect($amendment->print_details)->toEqual($f->voucher->print_details);
    $f->agent->update(['voucher_settings' => ['footer_text' => 'NEW DEFAULT', 'contacts' => [printProfileContact('NEW REP')]]]);
    $this->actingAs($f->user)->get('/'.$f->company->slug.'/umrah/vouchers/'.$f->voucher->id.'/print')->assertOk()->assertSee('Original rep')->assertSee('Original footer')->assertDontSee('NEW REP')->assertDontSee('NEW DEFAULT');
    $this->put('/'.$f->company->slug.'/umrah/vouchers/'.$f->voucher->id, printProfileUpdateData(['footer_text' => 'MUTATION', 'contacts' => []]))->assertForbidden();
});

test('print uses escaped footer and exact map QR with nights in last column', function () {
    $f = printProfileFixture();
    $f->voucher->update(['print_details' => ['footer_text' => '<script>alert(1)</script>', 'contacts' => []], 'hotel_stays' => [['hotel_name' => 'Synthetic Hotel', 'city' => 'Makkah', 'night_count' => 4, 'map_url' => 'https://maps.app.goo.gl/SyntheticLink']]]);
    $response = $this->actingAs($f->user)->get('/'.$f->company->slug.'/umrah/vouchers/'.$f->voucher->id.'/print')->assertOk();
    $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('data:image/svg+xml;base64,', false)->assertSee('Total nights')->assertSee('<th class="nights">Nights</th>', false)->assertDontSee('Outbound flight');
    $this->get('/'.$f->company->slug.'/umrah/vouchers/'.$f->voucher->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('voucher rejects non map links', function (string $url) {
    $f = printProfileFixture();
    $data = printProfileUpdateData(['contacts' => []]);
    $data['hotel_stays'][0]['map_url'] = $url;
    $this->actingAs($f->user)->put('/'.$f->company->slug.'/umrah/vouchers/'.$f->voucher->id, $data)->assertSessionHasErrors('hotel_stays.0.map_url');
})->with(['javascript:alert(1)', 'https://example.com/', 'https://maps.app.goo.gl.evil.example/path', 'http://maps.google.com/']);

test('another company cannot update an agent profile or print a voucher', function () {
    $first = printProfileFixture();
    $second = printProfileFixture();
    $this->actingAs($second->user)->put('/'.$second->company->slug.'/umrah/settings/voucher', ['target' => 'agent:'.$first->agent->id, 'details' => ['contacts' => []]])->assertSessionHasErrors('target');
    $this->get('/'.$second->company->slug.'/umrah/vouchers/'.$first->voucher->id.'/print')->assertNotFound();
});

test('setup catalog is available to owner and denied to unaffiliated user', function () {
    $f = printProfileFixture();
    $url = '/'.$f->company->slug.'/umrah/settings/voucher';
    $this->actingAs($f->user)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Umrah/Settings/Voucher')->has('profiles', 3));
    $this->actingAs(User::factory()->create())->get($url)->assertRedirect();
});

test('acceptance print handles complete and visa transport packages with four logo slots', function (string $bundle) {
    $f = printProfileFixture();
    $f->group->update(['mandatory_transport_vendor_id' => $f->vendor->id, 'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS]);
    $logo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aXioAAAAASUVORK5CYII=';
    $f->company->update(['logo_url' => $logo]);
    $f->agent->customer->update(['logo_url' => $logo]);
    $f->vendor->vendor->update(['logo_url' => $logo]);
    $f->voucher->update([
        'service_bundle' => $bundle,
        'onward_airline' => 'SV', 'onward_flight_number' => '701',
        'onward_departure_city' => 'KHI', 'onward_arrival_city' => 'JED',
        'onward_departure_at' => '2026-10-01 10:00:00', 'onward_arrival_at' => '2026-10-01 13:00:00',
        'return_airline' => 'SV', 'return_flight_number' => '700',
        'return_departure_city' => 'JED', 'return_arrival_city' => 'KHI',
        'return_departure_at' => '2026-10-15 15:00:00', 'return_arrival_at' => '2026-10-15 20:00:00',
        'hotel_stays' => $bundle === 'visa_transport_hotel' ? [[
            'hotel_name' => 'Acceptance Hotel', 'city' => 'Makkah', 'room_type' => 'quad', 'room_count' => 2,
            'check_in_date' => '2026-10-01', 'check_out_date' => '2026-10-15', 'night_count' => 14,
            'meal_plan' => 'Breakfast', 'map_url' => 'https://www.google.com/maps/',
        ]] : [],
        'print_details' => ['footer_text' => 'Acceptance terms', 'contacts' => [printProfileContact('Makkah rep'), array_replace(printProfileContact('Madinah rep'), ['city' => 'Madinah', 'responsibility' => 'Madinah assistance'])]],
    ]);
    $url = '/'.$f->company->slug.'/umrah/vouchers/'.$f->voucher->id;
    $response = $this->actingAs($f->user)->get($url.'/print')->assertOk()
        ->assertSee('Outbound flight')->assertSee('Return flight')->assertSee('Makkah rep')->assertSee('Madinah rep')
        ->assertSee('10:00')->assertSee('13:00')->assertSee('Acceptance terms');
    foreach (['Company', 'Agent', 'Visa provider', 'Transport provider'] as $role) {
        $response->assertSee('alt="'.$role.' logo"', false);
    }
    if ($bundle === 'visa_transport_hotel') {
        $response->assertSee('Acceptance Hotel')->assertSee('Total nights')->assertSee('data:image/svg+xml;base64,', false);
        $response->assertDontSee('>Meals</th>', false);
        expect(strpos($response->getContent(), '>Location</th>'))->toBeLessThan(strpos($response->getContent(), '>Rooms</th>'));
        expect(strpos($response->getContent(), '>Accommodation<'))->toBeLessThan(strpos($response->getContent(), '>Outbound flight<'));
    } else {
        $response->assertDontSee('>Accommodation<', false)->assertDontSee('Total nights');
    }
    $this->get($url.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
})->with(['visa_transport_hotel', 'visa_transport']);

test('setup permissions distinguish manager from desk and agent roles', function (string $role) {
    $f = printProfileFixture();
    $user = User::factory()->withoutTwoFactor()->create();
    DB::table('auth.company_user')->insert(['company_id' => $f->company->id, 'user_id' => $user->id, 'role' => $role, 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    CompanyContext::withContext($f->company, fn () => CompanyContext::assignRole($user, $role));
    $url = '/'.$f->company->slug.'/umrah/settings/voucher';
    $this->actingAs($user);
    if ($role === 'manager') {
        $this->get($url)->assertOk();
        $this->put($url, ['target' => 'company', 'details' => ['contacts' => [], 'footer_text' => 'Manager saved']])->assertSessionHasNoErrors();
        expect($f->company->fresh()->settings['umrah_voucher']['footer_text'])->toBe('Manager saved');
    } else {
        $this->get($url)->assertForbidden();
        $this->put($url, ['target' => 'company', 'details' => ['contacts' => []]])->assertForbidden();
        expect($f->company->fresh()->settings)->not->toHaveKey('umrah_voucher');
    }
})->with(['manager', 'operations', 'accountant', 'agent']);

test('new voucher stores supplied snapshot or company defaults exactly once', function (bool $explicit) {
    $f = printProfileFixture();
    $f->company->update(['settings' => [...$f->company->settings, 'umrah_voucher' => ['footer_text' => 'Creation default', 'contacts' => [printProfileContact()]]]]);
    $passenger = Passenger::create(['company_id' => $f->company->id, 'visa_group_id' => $f->group->id, 'full_name' => 'New snapshot passenger', 'service_type' => Passenger::SERVICE_HOTEL_ONLY]);
    $data = [
        'visa_group_id' => $f->group->id, 'title' => 'Snapshot create', 'service_bundle' => 'hotel', 'status' => 'draft',
        'passenger_ids' => [$passenger->id], 'passenger_services' => [$passenger->id => Passenger::SERVICE_HOTEL_ONLY],
        'hotel_stays' => [['source' => 'self', 'city' => 'Makkah', 'room_type' => 'double', 'room_count' => 1]],
    ];
    if ($explicit) {
        $data['print_details'] = ['footer_text' => 'My own footer', 'contacts' => []];
    }
    $this->actingAs($f->user)->post('/'.$f->company->slug.'/umrah/vouchers', $data)->assertSessionHasNoErrors()->assertRedirect();
    $saved = Voucher::where('company_id', $f->company->id)->where('title', 'Snapshot create')->firstOrFail();
    expect($saved->print_details['footer_text'])->toBe($explicit ? 'My own footer' : 'Creation default')->and($saved->print_details['contacts'])->toHaveCount($explicit ? 0 : 1);
})->with([false, true]);
