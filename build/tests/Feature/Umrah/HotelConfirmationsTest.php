<?php

use App\Facades\CompanyContext;
use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\HotelConfirmations;
use App\Modules\Umrah\Services\OperationalEventTimelineService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function hotelConfirmationFixture(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create(['name' => 'Hotel confirmation tests', 'slug' => 'hotel-'.str()->random(12), 'base_currency' => 'SAR', 'industry_code' => 'umrah', 'settings' => ['modules' => ['umrah' => true]]]);
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert(['company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    CompanyContext::withContext($company, fn () => CompanyContext::assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    CompanyContext::setContext($company);
    $agent = Agent::create(['company_id' => $company->id, 'agent_number' => 'AG-H', 'name' => 'Test Agent']);
    $group = VisaGroup::create(['company_id' => $company->id, 'agent_id' => $agent->id, 'group_number' => 'GR-H', 'name' => 'Hotel test', 'status' => VisaGroup::STATUS_VISA_APPROVED, 'transport_mode' => VisaGroup::TRANSPORT_NONE]);
    $stay = ['source' => 'company', 'hotel_name' => 'Makkah Hotel', 'city' => 'Makkah', 'room_type' => 'double', 'room_count' => 1, 'check_in_date' => '2026-09-15', 'check_out_date' => '2026-09-18'];
    $voucher = Voucher::create(['company_id' => $company->id, 'visa_group_id' => $group->id, 'agent_id' => $agent->id, 'voucher_number' => 'V-H', 'title' => 'Hotel tests', 'status' => Voucher::STATUS_APPROVED, 'service_bundle' => Voucher::SERVICE_HOTEL, 'hotel_stays' => [$stay, [...$stay, 'hotel_name' => 'Madinah Hotel', 'city' => 'Madinah', 'check_in_date' => '2026-09-18', 'check_out_date' => '2026-09-20']]]);

    return compact('company', 'user', 'voucher', 'agent', 'group');
}

function hotelConfirmationPayload(Voucher $voucher, int $index = 0): array
{
    $row = app(HotelConfirmations::class)->rows($voucher->fresh(), true)[$index];

    return ['stay_id' => $row['stay_id'], 'revision' => $row['revision'], 'version' => $row['version'], 'status' => 'confirmed', 'brn' => 'BRN-123', 'confirmation_number' => 'HOTEL-456', 'internal_note' => 'Private supplier note'];
}

function readinessFixture(): array
{
    $f = hotelConfirmationFixture();
    $p = \App\Modules\Umrah\Models\Passenger::create(['company_id' => $f['company']->id, 'visa_group_id' => $f['group']->id, 'full_name' => 'Readiness traveller', 'passport_number' => 'READY123', 'service_type' => 'hotel_only']);
    \App\Modules\Umrah\Models\VoucherPassenger::create(['company_id' => $f['company']->id, 'voucher_id' => $f['voucher']->id, 'visa_group_id' => $f['group']->id, 'passenger_id' => $p->id]);
    $f['voucher']->update(['onward_arrival_at' => '2026-09-15 12:00:00', 'return_departure_at' => '2026-09-20 12:00:00']);

    return $f;
}

test('booking readiness uses the exact 72 hour boundary', function (string $now, string $colour) {
    $f = readinessFixture();
    \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse($now, 'Asia/Riyadh'));
    try {
        $state = app(\App\Modules\Umrah\Services\GroupBookingReadiness::class)->forGroups($f['company']->id, collect([$f['group']]))[$f['group']->id];
        expect($state['colour'])->toBe($colour);
    } finally {
        \Carbon\CarbonImmutable::setTestNow();
    }
})->with([
    'more than 72 hours' => ['2026-09-12 11:59:00', 'orange'],
    'exactly 72 hours' => ['2026-09-12 12:00:00', 'red'],
    'less than 72 hours' => ['2026-09-12 12:01:00', 'red'],
]);

test('all bookings confirmed is green and cancelled hotel needs replacement without changing money', function () {
    $f = readinessFixture();
    \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse('2026-09-14 12:00:00', 'Asia/Riyadh'));
    try {
        $service = app(HotelConfirmations::class);
        foreach ([0, 1] as $i) {
            $service->persist($f['company']->id, $f['voucher']->id, $f['user']->id, hotelConfirmationPayload($f['voucher'], $i));
        }
        $readiness = app(\App\Modules\Umrah\Services\GroupBookingReadiness::class);
        expect($readiness->forGroups($f['company']->id, collect([$f['group']]))[$f['group']->id]['colour'])->toBe('green');
        $before = $f['voucher']->fresh()->only(['hotel_sale_amount', 'hotel_cost_amount', 'hotel_sale_transaction_id', 'hotel_cost_transaction_id', 'hotel_stays', 'status']);
        $payload = [...hotelConfirmationPayload($f['voucher']), 'status' => 'cancelled'];
        $url = "/{$f['company']->slug}/umrah/vouchers/{$f['voucher']->id}/hotel-confirmations";
        $this->actingAs($f['user'])->post($url, $payload)->assertSessionHasErrors(['cancellation_reason', 'supplier_acknowledgement']);
        $this->post($url, [...$payload, 'cancellation_reason' => 'Replacement hotel needed', 'supplier_acknowledgement' => 'Supplier ref CANCEL-1'])->assertSessionHas('success');
        expect($f['voucher']->fresh()->only(array_keys($before)))->toBe($before);
        expect($readiness->forGroups($f['company']->id, collect([$f['group']]))[$f['group']->id]['colour'])->toBe('red');
        expect($service->rows($f['voucher']->fresh(), true)[0]['history'][0]['cancellation_reason'])->toBe('Replacement hotel needed');
    } finally {
        \Carbon\CarbonImmutable::setTestNow();
    }
});

test('readiness follows a passenger into another group travelling voucher', function () {
    $f = readinessFixture();
    $other = VisaGroup::create(['company_id' => $f['company']->id, 'agent_id' => $f['agent']->id, 'group_number' => 'GR-OTHER', 'name' => 'Other party', 'transport_mode' => VisaGroup::TRANSPORT_NONE]);
    $f['voucher']->update(['visa_group_id' => $other->id]);
    \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse('2026-09-14 12:00:00', 'Asia/Riyadh'));
    try {
        $states = app(\App\Modules\Umrah\Services\GroupBookingReadiness::class)->forGroups($f['company']->id, collect([$f['group'], $other]));
        expect($states[$f['group']->id]['colour'])->toBe('red')->and($states[$other->id]['colour'])->toBe('red');
    } finally {
        \Carbon\CarbonImmutable::setTestNow();
    }
});

test('missing arrival and completed journeys are grey rather than green', function () {
    $f = readinessFixture();
    $service = app(\App\Modules\Umrah\Services\GroupBookingReadiness::class);
    $f['voucher']->update(['service_bundle' => Voucher::SERVICE_VISA_HOTEL, 'onward_arrival_at' => null]);
    expect($service->forGroups($f['company']->id, collect([$f['group']]))[$f['group']->id]['label'])->toBe('Arrival date missing');
    $f['voucher']->update(['onward_arrival_at' => '2020-01-01 12:00:00', 'return_departure_at' => '2020-01-10 12:00:00']);
    expect($service->forGroups($f['company']->id, collect([$f['group']]))[$f['group']->id]['label'])->toBe('Journey completed');
});

test('transport confirmation is explicit, versioned, private and does not change charges', function () {
    $f = readinessFixture();
    $vendor = \App\Modules\Umrah\Models\VisaVendor::create(['company_id' => $f['company']->id, 'vendor_number' => 'TR-C', 'name' => 'Transport provider', 'service_type' => 'transport_provider']);
    $f['group']->update(['transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS, 'mandatory_transport_vendor_id' => $vendor->id]);
    $service = app(\App\Modules\Umrah\Services\TransportConfirmations::class);
    $row = $service->rows($f['group']->fresh())[0];
    expect($row['status'])->toBe('pending');
    $payload = ['booking_id' => $row['id'], 'revision' => $row['revision'], 'version' => $row['version'], 'status' => 'confirmed', 'reference' => 'BUS-1', 'internal_note' => 'Private transport note'];
    $before = $f['group']->fresh()->only(['transport_amount', 'transport_cost_amount', 'sale_transaction_id', 'cost_transaction_id']);
    $url = "/{$f['company']->slug}/umrah/groups/{$f['group']->id}/transport-confirmations";
    $this->actingAs($f['user'])->post($url, $payload)->assertSessionHas('success');
    expect($service->rows($f['group']->fresh())[0]['status'])->toBe('confirmed');
    $this->post($url, $payload)->assertSessionHasErrors('status');
    expect($f['group']->fresh()->only(array_keys($before)))->toBe($before);
    expect($f['group']->fresh()->toArray())->not->toHaveKey('transport_confirmations');
    expect($service->rows($f['group']->fresh())[0])->not->toHaveKeys(['internal_note', 'history']);
    $f['group']->refresh()->update(['transport_pax_capacity' => 50]);
    expect($service->rows($f['group']->fresh())[0]['status'])->toBe('reconfirm');
    $f['group']->update(['transport_pax_capacity' => null]);
    expect($service->rows($f['group']->fresh())[0]['status'])->toBe('reconfirm');
});

test('specialized transport items confirm independently and operational changes invalidate only the edited item', function () {
    $f = readinessFixture();
    $vendor = \App\Modules\Umrah\Models\VisaVendor::create(['company_id' => $f['company']->id, 'vendor_number' => 'TR-S', 'name' => 'Specialized provider', 'service_type' => 'transport_provider']);
    $f['group']->update(['transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED]);
    $items = [];
    foreach (['Airport to Makkah', 'Makkah to Madinah'] as $label) {
        $items[] = \App\Modules\Umrah\Models\GroupTransportItem::create(['company_id' => $f['company']->id, 'visa_group_id' => $f['group']->id, 'transport_vendor_id' => $vendor->id, 'description' => $label, 'quantity' => 1, 'passenger_count' => 1]);
    }
    $service = app(\App\Modules\Umrah\Services\TransportConfirmations::class);
    $row = collect($service->rows($f['group']->fresh()))->firstWhere('id', $items[0]->id);
    $service->persist($f['company']->id, $f['group']->id, $f['user'], ['booking_id' => $row['id'], 'revision' => $row['revision'], 'version' => 0, 'status' => 'confirmed']);
    $rows = collect($service->rows($f['group']->fresh()))->keyBy('id');
    expect($rows[$items[0]->id]['status'])->toBe('confirmed')->and($rows[$items[1]->id]['status'])->toBe('pending');
    $items[0]->update(['notes' => 'Different internal dispatch note']);
    expect(collect($service->rows($f['group']->fresh()))->firstWhere('id', $items[0]->id)['status'])->toBe('confirmed');
    $items[0]->update(['scheduled_at' => '2026-09-15 12:00:00']);
    expect(collect($service->rows($f['group']->fresh()))->firstWhere('id', $items[0]->id)['status'])->toBe('reconfirm');
});

test('transport rejects missing supplier and cancellation acknowledgements', function () {
    $f = readinessFixture();
    $f['group']->update(['transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS]);
    $row = app(\App\Modules\Umrah\Services\TransportConfirmations::class)->rows($f['group']->fresh())[0];
    $data = ['booking_id' => $row['id'], 'revision' => $row['revision'], 'version' => 0, 'status' => 'confirmed'];
    $url = "/{$f['company']->slug}/umrah/groups/{$f['group']->id}/transport-confirmations";
    $this->actingAs($f['user'])->post($url, $data)->assertSessionHasErrors('status');
    $this->post($url, [...$data, 'status' => 'cancelled'])->assertSessionHasErrors(['cancellation_reason', 'supplier_acknowledgement']);
    $this->post($url, [...$data, 'status' => 'cancelled', 'cancellation_reason' => 'Not travelling on this bus', 'supplier_acknowledgement' => 'Request was not submitted'])->assertSessionHas('success');
    expect(app(\App\Modules\Umrah\Services\TransportConfirmations::class)->rows($f['group']->fresh())[0]['status'])->toBe('cancelled');
});

test('agent group list omits staff readiness and transport edit controls', function () {
    $f = readinessFixture();
    $login = User::factory()->withoutTwoFactor()->create();
    DB::table('auth.company_user')->insert(['company_id' => $f['company']->id, 'user_id' => $login->id, 'role' => 'agent', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    CompanyContext::withContext($f['company'], fn () => CompanyContext::assignRole($login, 'agent'));
    $f['agent']->update(['user_id' => $login->id]);
    $this->actingAs($login)->get("/{$f['company']->slug}/umrah/groups")->assertOk()->assertInertia(fn ($page) => $page->where('canViewReadiness', false)->missing('groups.data.0.booking_readiness')->missing('groups.data.0.transport_confirmations'));
    $this->get("/{$f['company']->slug}/umrah/groups/{$f['group']->id}")->assertOk()->assertInertia(fn ($page) => $page->where('canManageTransportConfirmations', false));
    $this->post("/{$f['company']->slug}/umrah/groups/{$f['group']->id}/transport-confirmations", [])->assertForbidden();
});

test('owner group list shows readiness independently of payment status', function () {
    $f = readinessFixture();
    $this->actingAs($f['user'])->get("/{$f['company']->slug}/umrah/groups")->assertOk()->assertInertia(fn ($page) => $page->where('canViewReadiness', true)->has('groups.data.0.booking_readiness.colour')->has('groups.data.0.payment_status'));
});

test('hotel confirmations save independently, record history and reject stale updates', function () {
    ['company' => $company, 'user' => $user, 'voucher' => $voucher] = hotelConfirmationFixture();
    $before = $voucher->fresh()->getAttributes();
    $data = hotelConfirmationPayload($voucher);
    $url = "/{$company->slug}/umrah/vouchers/{$voucher->id}/hotel-confirmations";
    $this->actingAs($user)->post($url, $data)->assertRedirect()->assertSessionHas('success');
    $after = $voucher->fresh();
    foreach (['hotel_stays', 'hotel_sale_amount', 'hotel_cost_amount', 'hotel_sale_transaction_id', 'hotel_cost_transaction_id', 'status'] as $field) {
        expect($after->getRawOriginal($field))->toBe($before[$field] ?? null);
    }
    $rows = app(HotelConfirmations::class)->rows($after, true);
    expect($rows[0]['status'])->toBe('confirmed')->and($rows[1]['status'])->toBe('pending')
        ->and($rows[0]['history'][0]['updated_by_user_id'])->toBe($user->id)
        ->and($rows[0]['brn'])->toBe('BRN-123')->and($rows[0]['confirmation_number'])->toBe('HOTEL-456');
    $this->post($url, $data)->assertSessionHasErrors('status');
    $this->post($url, [...hotelConfirmationPayload($after), 'status' => 'pending'])->assertSessionHas('success');
    expect(app(HotelConfirmations::class)->rows($voucher->fresh(), true)[0]['history'])->toHaveCount(2);
    expect($voucher->fresh()->toArray())->not->toHaveKey('hotel_confirmations');
    expect(app(HotelConfirmations::class)->rows($voucher->fresh())[0])->not->toHaveKeys(['history', 'internal_note', 'updated_by_name']);
});

test('hotel identities survive reorder and notes edits but material edits and reversions require reconfirmation', function (array $change) {
    ['company' => $company, 'user' => $user, 'voucher' => $voucher] = hotelConfirmationFixture();
    $service = app(HotelConfirmations::class);
    $service->persist($company->id, $voucher->id, $user->id, hotelConfirmationPayload($voucher));
    $voucher->refresh();
    $original = $voucher->hotel_stays;
    $stays = array_reverse($original);
    $stays[1]['notes'] = 'Different travel note';
    $voucher->update(['hotel_stays' => $stays]);
    expect($service->rows($voucher->fresh())[1]['status'])->toBe('confirmed');
    $stays = $voucher->hotel_stays;
    $stays[1] = [...$stays[1], ...$change];
    $voucher->update(['hotel_stays' => $stays]);
    expect($service->rows($voucher->fresh())[1]['status'])->toBe('reconfirm');
    $voucher->update(['hotel_stays' => $original]);
    expect($service->rows($voucher->fresh())[0]['status'])->toBe('reconfirm');
})->with([
    'rooms' => [['room_count' => 2]],
    'room type' => [['room_type' => 'triple']],
    'beds' => [['beds_per_room' => 3]],
    'hotel name' => [['hotel_name' => 'Different hotel']],
    'hotel identity' => [['hotel_id' => '00000000-0000-4000-8000-000000000001']],
    'supplier' => [['hotel_vendor_id' => '00000000-0000-4000-8000-000000000002']],
    'city' => [['city' => 'Jeddah']],
    'checkin' => [['check_in_date' => '2026-09-16']],
    'checkout' => [['check_out_date' => '2026-09-19']],
]);

test('legacy stays are not recorded without read writes and self arranged stays cannot be confirmed', function () {
    ['company' => $company, 'user' => $user, 'voucher' => $voucher] = hotelConfirmationFixture();
    $stays = array_map(function ($stay) {
        unset($stay['stay_id'], $stay['stay_revision']);

        return $stay;
    }, $voucher->hotel_stays);
    $stays[1]['source'] = 'self';
    DB::table('umrah.vouchers')->where('id', $voucher->id)->update(['hotel_stays' => json_encode($stays)]);
    $voucher->refresh();
    $before = $voucher->getRawOriginal('hotel_stays');
    $rows = app(HotelConfirmations::class)->rows($voucher);
    expect($rows[0]['status'])->toBe('not_recorded')->and($rows[1]['status'])->toBe('agent_arranged');
    expect($voucher->fresh()->getRawOriginal('hotel_stays'))->toBe($before);
    $this->actingAs($user)->post("/{$company->slug}/umrah/vouchers/{$voucher->id}/hotel-confirmations", hotelConfirmationPayload($voucher, 1))->assertSessionHasErrors('status');
    app(HotelConfirmations::class)->persist($company->id, $voucher->id, $user->id, hotelConfirmationPayload($voucher));
    expect(app(HotelConfirmations::class)->rows($voucher->fresh())[0]['status'])->toBe('confirmed');
});

test('hotel confirmation validation rejects invalid data and unavailable stays', function (array $override, string $error) {
    ['company' => $company, 'user' => $user, 'voucher' => $voucher] = hotelConfirmationFixture();
    $this->actingAs($user)->post("/{$company->slug}/umrah/vouchers/{$voucher->id}/hotel-confirmations", [...hotelConfirmationPayload($voucher), ...$override])->assertSessionHasErrors($error);
    expect($voucher->fresh()->hotel_confirmations)->toBe([]);
})->with([
    'unknown stay' => [['stay_id' => '00000000-0000-4000-8000-000000000001'], 'status'],
    'invalid status' => [['status' => 'accepted'], 'status'],
    'long reference' => [['brn' => str_repeat('a', 101)], 'brn'],
    'long note' => [['internal_note' => str_repeat('a', 1001)], 'internal_note'],
    'stale revision' => [['revision' => str_repeat('0', 64)], 'status'],
]);

test('pending checkins are actionable without flagging checkout and confirmation clears the issue', function () {
    ['company' => $company, 'user' => $user, 'voucher' => $voucher] = hotelConfirmationFixture();
    $filters = ['period' => 'custom', 'date' => '2026-09-15', 'start' => '2026-09-15', 'end' => '2026-09-20', 'event_type' => 'all', 'readiness' => 'all'];
    $timeline = app(OperationalEventTimelineService::class);
    $events = collect($timeline->build($company, $user, $filters)['events']);
    $checkin = $events->firstWhere('type', 'hotel_check_in');
    expect($checkin['readiness_issues'])->toContain('Hotel confirmation is pending')
        ->and(collect($checkin['resolution_actions'])->pluck('key')->all())->toContain('hotel_confirmation');
    expect($events->firstWhere('type', 'hotel_check_out')['readiness_issues'])->not->toContain('Hotel confirmation is pending');
    app(HotelConfirmations::class)->persist($company->id, $voucher->id, $user->id, hotelConfirmationPayload($voucher));
    $events = collect($timeline->build($company, $user, $filters)['events']);
    expect($events->firstWhere('type', 'hotel_check_in')['readiness_issues'])->not->toContain('Hotel confirmation is pending');
});

test('cancelled vouchers cannot receive confirmation updates', function () {
    ['company' => $company, 'user' => $user, 'voucher' => $voucher] = hotelConfirmationFixture();
    $voucher->update(['status' => Voucher::STATUS_CANCELLED]);
    expect(fn () => app(HotelConfirmations::class)->persist($company->id, $voucher->id, $user->id, hotelConfirmationPayload($voucher)))->toThrow(ValidationException::class);
});

test('agent sees own references but not private notes or history and cannot write confirmations', function () {
    ['company' => $company, 'user' => $user, 'voucher' => $voucher, 'agent' => $agent] = hotelConfirmationFixture();
    app(HotelConfirmations::class)->persist($company->id, $voucher->id, $user->id, hotelConfirmationPayload($voucher));
    $login = User::factory()->withoutTwoFactor()->create();
    DB::table('auth.company_user')->insert(['company_id' => $company->id, 'user_id' => $login->id, 'role' => 'agent', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    CompanyContext::withContext($company, fn () => CompanyContext::assignRole($login, 'agent'));
    $agent->update(['user_id' => $login->id, 'can_edit_voucher' => true]);
    $this->actingAs($login)->get("/{$company->slug}/umrah/vouchers/{$voucher->id}")
        ->assertOk()->assertInertia(fn ($page) => $page->where('hotelConfirmations.0.brn', 'BRN-123')
        ->where('canManageHotelConfirmations', false)->missing('hotelConfirmations.0.internal_note')
        ->missing('hotelConfirmations.0.history')->missing('voucher.hotel_confirmations'));
    $this->post("/{$company->slug}/umrah/vouchers/{$voucher->id}/hotel-confirmations", hotelConfirmationPayload($voucher))->assertForbidden();
    $agent->update(['user_id' => null]);
    $this->get("/{$company->slug}/umrah/vouchers/{$voucher->id}")->assertNotFound();
});

test('operations staff can update hotel confirmations', function () {
    ['company' => $company, 'voucher' => $voucher] = hotelConfirmationFixture();
    $login = User::factory()->withoutTwoFactor()->create();
    DB::table('auth.company_user')->insert(['company_id' => $company->id, 'user_id' => $login->id, 'role' => 'operations', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    CompanyContext::withContext($company, fn () => CompanyContext::assignRole($login, 'operations'));
    $this->actingAs($login)->post("/{$company->slug}/umrah/vouchers/{$voucher->id}/hotel-confirmations", hotelConfirmationPayload($voucher))->assertSessionHas('success');
    $this->get("/{$company->slug}/umrah/vouchers/{$voucher->id}?tab=details")->assertOk()->assertInertia(fn ($page) => $page->where('canManageHotelConfirmations', true)->where('openDetails', true)->where('hotelConfirmations.0.status', 'confirmed'));
});

test('confirmation endpoint cannot target another company voucher', function () {
    $a = hotelConfirmationFixture();
    $b = hotelConfirmationFixture();
    CompanyContext::setContext($a['company']);
    $data = hotelConfirmationPayload($a['voucher']);
    $this->actingAs($a['user'])->post("/{$a['company']->slug}/umrah/vouchers/{$b['voucher']->id}/hotel-confirmations", $data)->assertSessionHasErrors('status');
    CompanyContext::setContext($b['company']);
    expect($b['voucher']->fresh()->hotel_confirmations)->toBe([]);
});

test('an amendment starts pending and does not copy original hotel confirmations', function () {
    ['company' => $company, 'user' => $user, 'voucher' => $voucher] = hotelConfirmationFixture();
    app(HotelConfirmations::class)->persist($company->id, $voucher->id, $user->id, hotelConfirmationPayload($voucher));
    $amendment = app(\App\Modules\Umrah\Services\VoucherWorkflowService::class)->createAmendment($voucher, 'V-H-A1', $user->id);
    expect($amendment->hotel_confirmations)->toBe([])
        ->and(app(HotelConfirmations::class)->rows($amendment)[0]['status'])->toBe('pending')
        ->and(app(HotelConfirmations::class)->rows($voucher->fresh())[0]['status'])->toBe('confirmed');
});

test('duplicate hotel stays keep separate confirmations when reordered by identity', function () {
    ['company' => $company, 'user' => $user, 'voucher' => $voucher] = hotelConfirmationFixture();
    $stay = $voucher->hotel_stays[0];
    $voucher->update(['hotel_stays' => [$stay, $stay]]);
    expect($voucher->hotel_stays[0]['stay_id'])->not->toBe($voucher->hotel_stays[1]['stay_id']);
    app(HotelConfirmations::class)->persist($company->id, $voucher->id, $user->id, hotelConfirmationPayload($voucher));
    $voucher->refresh()->update(['hotel_stays' => array_reverse($voucher->hotel_stays)]);
    $rows = app(HotelConfirmations::class)->rows($voucher->fresh());
    expect($rows[0]['status'])->toBe('pending')->and($rows[1]['status'])->toBe('confirmed');
});

test('incomplete stays cannot be marked confirmed but can be recorded pending', function () {
    ['company' => $company, 'user' => $user, 'voucher' => $voucher] = hotelConfirmationFixture();
    $stays = $voucher->hotel_stays;
    $stays[0]['room_type'] = null;
    $voucher->update(['hotel_stays' => $stays]);
    $url = "/{$company->slug}/umrah/vouchers/{$voucher->id}/hotel-confirmations";
    $this->actingAs($user)->post($url, hotelConfirmationPayload($voucher))->assertSessionHasErrors('status');
    $this->post($url, [...hotelConfirmationPayload($voucher), 'status' => 'pending'])->assertSessionHas('success');
});

test('unexpected save failures return a recoverable error flash', function () {
    ['company' => $company, 'user' => $user, 'voucher' => $voucher] = hotelConfirmationFixture();
    $data = hotelConfirmationPayload($voucher);
    $this->mock(HotelConfirmations::class)->shouldReceive('persist')->once()->andThrow(new RuntimeException('Simulated storage failure'));
    $this->actingAs($user)->post("/{$company->slug}/umrah/vouchers/{$voucher->id}/hotel-confirmations", $data)->assertRedirect()->assertSessionHas('error');
    expect($voucher->fresh()->hotel_confirmations)->toBe([]);
});

test('legacy blank rows do not shift the visible stay confirmation identity', function () {
    ['company' => $company, 'user' => $user, 'voucher' => $voucher] = hotelConfirmationFixture();
    $stay = $voucher->hotel_stays[0];
    unset($stay['stay_id'], $stay['stay_revision']);
    DB::table('umrah.vouchers')->where('id', $voucher->id)->update(['hotel_stays' => json_encode([['hotel_name' => '', 'city' => ''], $stay])]);
    $data = hotelConfirmationPayload($voucher, 1);
    $this->actingAs($user)->get("/{$company->slug}/umrah/vouchers/{$voucher->id}")->assertOk()
        ->assertInertia(fn ($page) => $page->has('hotelConfirmations', 1)->where('hotelConfirmations.0.stay_id', $data['stay_id']));
    $this->post("/{$company->slug}/umrah/vouchers/{$voucher->id}/hotel-confirmations", $data)->assertSessionHas('success');
});
