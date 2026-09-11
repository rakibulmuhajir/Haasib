<?php

use App\Facades\CompanyContext;
use App\Modules\Umrah\Commands\MoveVoucherPassengers;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Modules\Umrah\Services\VoucherWorkflowService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/TicketingFixtures.php';

test('both group pages show current parties without moving purchases and follow the return move', function () {
    $f = crossAgentFixture();
    $pax = $f->A->passengers->first();
    $before = $f->A->group->fresh()->getAttributes();
    $move = '/'.$f->company->slug.'/umrah/vouchers/';
    $groups = '/'.$f->company->slug.'/umrah/groups/';
    $this->actingAs($f->user)->post($move.$f->A->voucher->id.'/passengers/move', ['target_voucher_id' => $f->B->voucher->id, 'passenger_ids' => [$pax->id]])->assertSessionHas('success');
    $this->get($groups.$f->A->group->id)->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->has('group.passengers', 2)->where('travellingParties.assignments.'.$pax->id.'.id', $f->B->voucher->id)
        ->where('travellingParties.assignments.'.$pax->id.'.elsewhere', true)->has('travellingParties.joining', 0));
    $this->get($groups.$f->B->group->id)->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->has('group.passengers', 2)->has('travellingParties.joining', 1)
        ->where('travellingParties.joining.0.id', $pax->id)->where('travellingParties.joining.0.original_group_id', $f->A->group->id)
        ->where('travellingParties.joining.0.voucher.id', $f->B->voucher->id)->missing('travellingParties.joining.0.notes'));
    expect($f->A->group->fresh()->getAttributes())->toBe($before);
    $this->post($move.$f->B->voucher->id.'/passengers/move', ['target_voucher_id' => $f->A->voucher->id, 'passenger_ids' => [$pax->id]])->assertSessionHas('success');
    $this->get($groups.$f->A->group->id)->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->where('travellingParties.assignments.'.$pax->id.'.id', $f->A->voucher->id)->where('travellingParties.assignments.'.$pax->id.'.elsewhere', false));
    $this->get($groups.$f->B->group->id)->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->has('travellingParties.joining', 0));
});

test('group party projection respects both agents privacy', function () {
    $f = crossAgentFixture();
    $pax = $f->A->passengers->first();
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$pax->id]));
    $a = crossAgentLogin($f, 'A');
    $b = crossAgentLogin($f, 'B');
    $groups = '/'.$f->company->slug.'/umrah/groups/';
    $this->actingAs($a)->get($groups.$f->A->group->id)->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->where('travellingParties.assignments.'.$pax->id.'.id', null)
        ->where('travellingParties.assignments.'.$pax->id.'.number', null)
        ->where('travellingParties.assignments.'.$pax->id.'.agent', null)
        ->where('travellingParties.assignments.'.$pax->id.'.elsewhere', true));
    $this->get($groups.$f->B->group->id)->assertNotFound();
    $this->actingAs($b)->get($groups.$f->B->group->id)->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->has('travellingParties.joining', 1)->where('travellingParties.joining.0.id', $pax->id)
        ->where('travellingParties.joining.0.original_group_id', null)->where('travellingParties.joining.0.original_group', null)
        ->where('travellingParties.joining.0.voucher.id', $f->B->voucher->id)
        ->missing('travellingParties.joining.0.notes')->missing('travellingParties.joining.0.transport_charge_amount'));
});

test('group party projection ignores inactive assignments and historical vouchers', function (string $state) {
    $f = crossAgentFixture();
    $pax = $f->A->passengers->first();
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$pax->id]));
    match ($state) {
        'cancelled' => $f->B->voucher->update(['status' => 'cancelled']),
        'superseded' => $f->B->voucher->update(['superseded_by_voucher_id' => $f->A->voucher->id]),
        'amendment' => $f->B->voucher->update(['amends_voucher_id' => $f->A->voucher->id]),
        'deleted' => $f->B->voucher->delete(),
        'released' => VoucherPassenger::where('passenger_id', $pax->id)->delete(),
    };
    $projection = app(\App\Modules\Umrah\Services\GroupTravellingParties::class);
    expect((array) $projection->forGroup($f->A->group, $f->user)['assignments'])->not->toHaveKey($pax->id)
        ->and($projection->forGroup($f->B->group, $f->user)['joining'])->toBe([]);
})->with(['cancelled', 'superseded', 'amendment', 'deleted', 'released']);

test('new payments after a cross agent transfer settle only the purchasing agents group', function () {
    $f = crossAgentFixture();
    foreach ([['1001', 'Cash', 'asset', 'cash', 'debit'], ['1100', 'Receivable', 'asset', 'accounts_receivable', 'debit'], ['2200', 'Agent advances', 'liability', 'other_current_liability', 'credit']] as [$code, $name, $type, $subtype, $normal]) {
        \App\Modules\Accounting\Models\Account::firstOrCreate(['company_id' => $f->company->id, 'code' => $code], ['name' => $name, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal, 'currency' => 'PKR', 'is_active' => true]);
    }
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$f->A->passengers->first()->id]));
    $sourceBefore = $f->A->group->fresh()->getAttributes();
    $url = '/'.$f->company->slug.'/umrah/payments';
    $payload = ['payment_number' => 'AFTER-TRANSFER-B', 'payment_date' => '2026-09-10', 'direction' => 'received', 'agent_id' => $f->B->agent->id, 'amount' => 100, 'currency' => 'PKR', 'method' => 'cash', 'allocations' => [['visa_group_id' => $f->B->group->id, 'base_amount' => 100]]];
    $this->actingAs($f->user)->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    $payment = \App\Modules\Umrah\Models\GroupPayment::where('payment_number', 'AFTER-TRANSFER-B')->sole();
    expect($payment->agent_id)->toBe($f->B->agent->id)
        ->and($payment->allocations()->sole()->visa_group_id)->toBe($f->B->group->id)
        ->and((float) $f->B->group->fresh()->total_paid)->toBe(600.0)
        ->and($f->A->group->fresh()->getAttributes())->toBe($sourceBefore);
    $lines = DB::table('acct.journal_entries')->where('transaction_id', $payment->transaction_id)->get();
    expect((float) $lines->sum('debit_amount'))->toBe(100.0)->and((float) $lines->sum('credit_amount'))->toBe(100.0);
    $before = DB::table('umrah.payment_allocations')->orderBy('id')->get()->toJson();
    $count = DB::table('acct.transactions')->count();
    $this->post($url, $payload)->assertSessionHasErrors('payment_number');
    $this->post($url, [...$payload, 'payment_number' => 'FORGED-CROSS-AGENT', 'allocations' => [['visa_group_id' => $f->A->group->id, 'base_amount' => 100]]])->assertSessionHasErrors();
    expect(DB::table('umrah.payment_allocations')->orderBy('id')->get()->toJson())->toBe($before)
        ->and(DB::table('acct.transactions')->count())->toBe($count)
        ->and(\App\Modules\Umrah\Models\GroupPayment::where('payment_number', 'FORGED-CROSS-AGENT')->exists())->toBeFalse();
});

test('recipient print retains its own contacts footer and agent identity after a cross agent move', function () {
    $f = crossAgentFixture();
    $sourceDetails = ['footer_text' => 'PRIVATE SOURCE FOOTER', 'contacts' => [['name' => 'Source-only Representative', 'responsibility' => 'Local support', 'phone' => '+966500000999', 'city' => 'Makkah']]];
    $targetDetails = ['footer_text' => 'Destination journey instructions', 'contacts' => [
        ['name' => 'Makkah Destination Representative', 'responsibility' => 'Makkah assistance', 'phone' => '+966500000101', 'city' => 'Makkah'],
        ['name' => 'Madinah Destination Representative', 'responsibility' => 'Madinah assistance', 'phone' => '+966500000102', 'city' => 'Madinah'],
    ]];
    $f->A->voucher->update(['status' => 'approved', 'print_details' => $sourceDetails]);
    $f->B->voucher->update(['status' => 'approved', 'print_details' => $targetDetails]);
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$f->A->passengers->first()->id], true, 'Join destination party without replacing its contacts'));
    expect($f->A->voucher->fresh()->print_details)->toEqual($sourceDetails)
        ->and($f->B->voucher->fresh()->print_details)->toEqual($targetDetails);
    $user = crossAgentLogin($f, 'B');
    $this->actingAs($user)->get('/'.$f->company->slug.'/umrah/vouchers/'.$f->B->voucher->id.'/print')->assertOk()
        ->assertSee('Agent B')->assertSee('Destination journey instructions')
        ->assertSee('Makkah Destination Representative')->assertSee('Madinah Destination Representative')
        ->assertDontSee('PRIVATE SOURCE FOOTER')->assertDontSee('Source-only Representative')->assertDontSee('+966500000999');
});

test('passengers in one import batch appear only on their own voucher arrival dates', function () {
    $f = crossAgentFixture();
    $second = $f->A->voucher->replicate();
    $second->voucher_number = 'LATER-COHORT';
    $second->save();
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $second, [$f->A->passengers->last()->id]));
    foreach ([[$f->A->voucher, '2026-10-01'], [$second, '2026-10-15']] as [$voucher, $date]) {
        $voucher->update(['status' => 'approved', 'onward_arrival_at' => $date.' 10:00:00', 'onward_arrival_city' => 'JED', 'onward_departure_city' => 'LHE']);
    }
    foreach (['2026-10-01' => $f->A->passengers->first()->id, '2026-10-15' => $f->A->passengers->last()->id] as $date => $passengerId) {
        $events = app(\App\Modules\Umrah\Services\OperationalEventTimelineService::class)->build($f->company, $f->user, ['period' => 'custom', 'date' => $date, 'start' => $date, 'end' => $date, 'event_type' => 'airport_arrival', 'readiness' => 'all'])['events'];
        expect($events)->toHaveCount(1)->and($events[0]['passenger_count'])->toBe(1)
            ->and(collect($events[0]['passengers'])->pluck('id')->all())->toBe([$passengerId]);
    }
});

test('scheduled pickups use the travelling party dates rather than the whole import batch', function (string $leg) {
    $f = crossAgentFixture();
    $second = $f->A->voucher->replicate();
    $second->voucher_number = 'PICKUP-LATER-COHORT';
    $second->save();
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $second, [$f->A->passengers->last()->id]));
    foreach ([[$f->A->voucher, '2026-10-01'], [$second, '2026-10-15']] as [$voucher, $date]) {
        $voucher->update(['status' => 'approved', 'onward_arrival_at' => $date.' 10:00:00', 'onward_arrival_city' => 'JED', 'onward_departure_city' => 'LHE',
            'return_departure_at' => $date.' 14:00:00', 'return_departure_city' => 'JED',
            'service_bundle' => 'visa_transport_hotel',
            'hotel_stays' => [['city' => 'Makkah', 'hotel_name' => 'First hotel', 'check_in_date' => '2026-09-20', 'check_out_date' => $date], ['city' => 'Madinah', 'hotel_name' => 'Next hotel', 'check_in_date' => $date, 'check_out_date' => '2026-10-20']],
        ]);
    }
    $f->A->group->update(['transport_mode' => 'standard_bus', 'passenger_count' => 2]);
    [$origin, $destination] = match ($leg) {
        'departure' => ['Madinah', 'JED'], 'city' => ['Makkah', 'Madinah'], default => ['JED', 'Makkah'],
    };
    $sector = \App\Modules\Umrah\Models\TransportSector::create(['company_id' => $f->company->id, 'code' => 'COHORT-PICKUP', 'name' => 'Cohort transfer', 'origin' => $origin, 'destination' => $destination]);
    \App\Modules\Umrah\Models\GroupTransportItem::create(['company_id' => $f->company->id, 'visa_group_id' => $f->A->group->id, 'transport_sector_id' => $sector->id, 'description' => 'First travelling party pickup', 'scheduled_at' => '2026-10-01 11:00:00', 'quantity' => 1]);
    $events = collect(app(\App\Modules\Umrah\Services\OperationalEventTimelineService::class)->build($f->company, $f->user, ['period' => 'custom', 'date' => '2026-10-01', 'start' => '2026-10-01', 'end' => '2026-10-01', 'event_type' => 'all', 'readiness' => 'all'])['events'])->where('movement_scope', 'group')->values()->all();
    expect($events)->toHaveCount(1)
        ->and(collect($events[0]['passengers'])->pluck('id')->all())->toBe([$f->A->passengers->first()->id])
        ->and($events[0]['passenger_count'])->toBe(1);
    $duplicate = \App\Modules\Umrah\Models\GroupTransportItem::where('visa_group_id', $f->A->group->id)->sole()->replicate();
    $duplicate->scheduled_at = '2026-10-01 12:00:00';
    $duplicate->save();
    $ambiguous = collect(app(\App\Modules\Umrah\Services\OperationalEventTimelineService::class)->build($f->company, $f->user, ['period' => 'custom', 'date' => '2026-10-01', 'start' => '2026-10-01', 'end' => '2026-10-01', 'event_type' => 'all', 'readiness' => 'all'])['events'])->where('movement_scope', 'group');
    expect($ambiguous)->toHaveCount(2);
    foreach ($ambiguous as $event) {
        expect($event['passengers'])->toBe([])->and($event['readiness_issues'])->toContain('Multiple pickups match this itinerary; confirm passenger allocation');
    }
})->with(['arrival', 'departure', 'city']);

test('issued party transfers retain purchases and record readable history', function (string $sourceStatus, string $targetStatus) {
    $f = crossAgentFixture();
    $pax = $f->A->passengers->first();
    $f->A->voucher->update(['status' => $sourceStatus, 'leader_passenger_id' => $pax->id]);
    $f->B->voucher->update(['status' => $targetStatus, 'leader_passenger_id' => $f->B->passengers->first()->id]);
    $url = '/'.$f->company->slug.'/umrah/vouchers/'.$f->A->voucher->id;
    $payload = ['target_voucher_id' => $f->B->voucher->id, 'passenger_ids' => [$pax->id], 'override_reason' => 'Join the neighbouring family; purchases remain unchanged'];
    $this->actingAs($f->user)->post($url.'/passengers/move', $payload)->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    expect($f->A->voucher->fresh()->status)->toBe($sourceStatus)
        ->and($f->B->voucher->fresh()->status)->toBe($targetStatus)
        ->and($f->A->voucher->fresh()->leader_passenger_id)->toBeNull()
        ->and($f->B->voucher->fresh()->leader_passenger_id)->toBe($f->B->passengers->first()->id);
    $log = \App\Modules\Umrah\Models\ChangeLog::where('entity_id', $f->A->voucher->id)->sole();
    expect($log->metadata['manifest_before']['passengers'])->toHaveCount(2)
        ->and($log->metadata['manifest_after']['passengers'])->toHaveCount(1)
        ->and($log->metadata['manifest_before']['leader_passenger_id'])->toBe($pax->id)
        ->and($log->metadata['purchase_snapshot']['agent_id'])->toBe($f->A->agent->id);
    $this->get($url)->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->has('voucher.passengers', 1));
    $this->get($url.'/print')->assertOk()->assertDontSee($pax->full_name);
    $this->post($url.'/passengers/move', $payload)->assertSessionHasErrors('passenger_ids');
    expect(\App\Modules\Umrah\Models\ChangeLog::where('entity_id', $f->A->voucher->id)->count())->toBe(1);
})->with([['approved', 'draft'], ['draft', 'approved'], ['approved', 'approved']]);

test('issued transfer requires reason and explicit command approval authority', function () {
    $f = crossAgentFixture();
    $f->B->voucher->update(['status' => Voucher::STATUS_APPROVED]);
    $ids = [$f->A->passengers->first()->id];
    expect(fn () => Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, $ids, false, 'A valid transfer reason')))->toThrow(ValidationException::class);
    $this->actingAs($f->user)->post('/'.$f->company->slug.'/umrah/vouchers/'.$f->A->voucher->id.'/passengers/move', ['target_voucher_id' => $f->B->voucher->id, 'passenger_ids' => $ids])->assertSessionHasErrors('override_reason');
    expect($f->A->voucher->passengers()->count())->toBe(2);
});

test('empty issued source retains its booking but no longer generates passenger movements', function () {
    $f = crossAgentFixture();
    $f->A->voucher->update([
        'status' => Voucher::STATUS_APPROVED, 'service_bundle' => Voucher::SERVICE_VISA_TRANSPORT_HOTEL,
        'onward_arrival_at' => '2026-11-01 10:00:00', 'onward_arrival_city' => 'JED',
        'hotel_stays' => [['source' => 'external', 'hotel_name' => 'Retained hotel', 'city' => 'Makkah', 'room_count' => 1, 'check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-04']],
    ]);
    $retainedStays = $f->A->voucher->fresh()->hotel_stays;
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, $f->A->passengers->pluck('id')->all(), true, 'Everyone joins the other travelling party'));
    expect($f->A->voucher->fresh()->status)->toBe(Voucher::STATUS_APPROVED)
        ->and($f->A->voucher->fresh()->version_number)->toBe(2)
        ->and($f->A->voucher->fresh()->hotel_stays)->toBe($retainedStays)
        ->and($f->A->voucher->passengers()->count())->toBe(0);
    $events = app(\App\Modules\Umrah\Services\OperationalEventTimelineService::class)->build($f->company, $f->user, [
        'period' => 'custom', 'date' => '2026-11-01', 'start' => '2026-11-01', 'end' => '2026-11-30', 'event_type' => 'all', 'readiness' => 'all',
    ])['events'];
    expect(collect($events)->where('type', 'airport_arrival'))->toHaveCount(0)
        ->and(collect($events)->where('type', 'hotel_check_in'))->toHaveCount(1);
});

test('invalid original purchase group rolls back an issued destination transfer', function () {
    $f = crossAgentFixture();
    $f->A->group->update(['status' => VisaGroup::STATUS_CANCELLED]);
    $f->B->voucher->update(['status' => Voucher::STATUS_APPROVED]);
    expect(fn () => Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$f->A->passengers->first()->id], true, 'Try an invalid original purchase')))->toThrow(ValidationException::class);
    expect($f->A->voucher->passengers()->count())->toBe(2)
        ->and($f->B->voucher->passengers()->count())->toBe(2)
        ->and($f->B->voucher->fresh()->version_number)->toBe(1);
});

test('malformed destination returns validation rather than a database error', function () {
    $f = crossAgentFixture();
    $this->actingAs($f->user)->post('/'.$f->company->slug.'/umrah/vouchers/'.$f->A->voucher->id.'/passengers/move', ['target_voucher_id' => 'not-a-uuid', 'passenger_ids' => [$f->A->passengers->first()->id]])->assertSessionHasErrors('target_voucher_id');
});

test('issued transfer refuses pending amendments cancelled vouchers and superseded copies', function (string $scenario) {
    $f = crossAgentFixture();
    $f->B->voucher->update(['status' => Voucher::STATUS_APPROVED]);
    if ($scenario === 'pending') {
        app(VoucherWorkflowService::class)->createAmendment($f->B->voucher, 'PENDING-TRANSFER', $f->user->id);
    } elseif ($scenario === 'cancelled') {
        $f->B->voucher->update(['status' => Voucher::STATUS_CANCELLED]);
    } else {
        $f->B->voucher->update(['superseded_at' => now()]);
    }
    expect(fn () => Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$f->A->passengers->first()->id], true, 'Try a stale destination')))->toThrow(ValidationException::class);
    expect($f->A->voucher->passengers()->count())->toBe(2);
})->with(['pending', 'cancelled', 'superseded']);

test('agent cannot modify an issued party even with an approval capability', function () {
    $f = crossAgentFixture();
    $user = crossAgentLogin($f, 'A');
    $f->A->voucher->update(['status' => Voucher::STATUS_APPROVED]);
    $f->A->agent->update(['can_approve_voucher' => true]);
    $this->actingAs($user)->post('/'.$f->company->slug.'/umrah/vouchers/'.$f->A->voucher->id.'/passengers/move', ['target_voucher_id' => $f->B->voucher->id, 'passenger_ids' => [$f->A->passengers->first()->id], 'override_reason' => 'Agent transfer attempt'])->assertForbidden();
    expect($f->A->voucher->passengers()->count())->toBe(2);
});

test('operations staff cannot approve a transfer or read its commercial snapshot', function () {
    $f = crossAgentFixture();
    $f->A->voucher->update(['status' => Voucher::STATUS_APPROVED]);
    $url = '/'.$f->company->slug.'/umrah/vouchers/'.$f->A->voucher->id;
    $payload = ['target_voucher_id' => $f->B->voucher->id, 'passenger_ids' => [$f->A->passengers->first()->id], 'override_reason' => 'Join another party without moving the purchases'];
    $user = \App\Models\User::factory()->withoutTwoFactor()->create();
    DB::table('auth.company_user')->insert(['company_id' => $f->company->id, 'user_id' => $user->id, 'role' => 'operations', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    CompanyContext::withContext($f->company, fn () => CompanyContext::assignRole($user, 'operations'));
    $this->actingAs($user)->post($url.'/passengers/move', $payload)->assertSessionHasErrors('override_reason');
    expect($f->A->voucher->passengers()->count())->toBe(2);
    $this->actingAs($f->user)->post($url.'/passengers/move', $payload)->assertSessionHasNoErrors();
    $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->where('agentCapabilities.can_move_passengers', false)
        ->has('changeLogs.0.metadata.manifest_before')->missing('changeLogs.0.metadata.purchase_snapshot'));
});

function crossAgentFixture(): object
{
    $f = ticketingCompany(['industry_code' => 'umrah', 'settings' => ['modules' => ['umrah' => true]]]);
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$f->user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f->company);
    DB::table('auth.company_user')->insert(['company_id' => $f->company->id, 'user_id' => $f->user->id, 'role' => 'owner', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    CompanyContext::withContext($f->company, fn () => CompanyContext::assignRole($f->user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    CompanyContext::setContext($f->company);
    $vendor = VisaVendor::create(['company_id' => $f->company->id, 'vendor_number' => 'CROSS-VENDOR', 'name' => 'Original Provider', 'service_type' => VisaVendor::SERVICE_VISA_PROVIDER]);
    $parties = [];
    foreach (['A', 'B'] as $name) {
        $agent = Agent::create(['company_id' => $f->company->id, 'agent_number' => 'CROSS-'.$name, 'name' => 'Agent '.$name]);
        $group = VisaGroup::create(['company_id' => $f->company->id, 'agent_id' => $agent->id, 'vendor_id' => $vendor->id, 'group_number' => 'GROUP-'.$name, 'name' => 'Group '.$name, 'travel_date' => now()->addDays(40)->toDateString(), 'visa_sale_amount' => 1000, 'visa_cost_amount' => 700, 'transport_amount' => 200, 'transport_cost_amount' => 100, 'total_receivable' => 1200, 'total_paid' => 500, 'balance' => 700]);
        $voucher = Voucher::create(['company_id' => $f->company->id, 'agent_id' => $agent->id, 'visa_group_id' => $group->id, 'voucher_number' => 'CROSS-'.$name, 'title' => 'Party '.$name, 'status' => Voucher::STATUS_DRAFT, 'service_bundle' => Voucher::SERVICE_VISA_TRANSPORT, 'hotel_stays' => []]);
        $passengers = collect();
        foreach ([1, 2] as $number) {
            $pax = Passenger::create(['company_id' => $f->company->id, 'visa_group_id' => $group->id, 'full_name' => $name.' Traveller '.$number, 'passport_number' => $name.$number, 'notes' => 'Private purchase note', 'transport_charge_amount' => 100]);
            VoucherPassenger::create(['company_id' => $f->company->id, 'voucher_id' => $voucher->id, 'visa_group_id' => $group->id, 'passenger_id' => $pax->id]);
            $passengers->push($pax);
        }
        $payment = \App\Modules\Umrah\Models\GroupPayment::create([
            'company_id' => $f->company->id, 'agent_id' => $agent->id,
            'payment_number' => 'PAY-'.$name, 'payment_date' => '2026-09-09',
            'direction' => 'received', 'amount' => 500, 'currency' => 'PKR',
            'base_currency' => 'PKR', 'base_amount' => 500, 'method' => 'cash',
        ]);
        \App\Modules\Umrah\Models\PaymentAllocation::create([
            'company_id' => $f->company->id, 'group_payment_id' => $payment->id,
            'visa_group_id' => $group->id, 'base_amount' => 500,
        ]);
        $parties[$name] = (object) compact('agent', 'group', 'voucher', 'passengers');
    }

    return (object) [...(array) $f, ...$parties];
}

test('cross agent move preserves passenger provenance and all financial records', function () {
    $f = crossAgentFixture();
    $before = [];
    foreach (['visa_groups', 'agents', 'visa_vendors', 'group_payments', 'payment_allocations'] as $table) {
        $before[$table] = DB::table('umrah.'.$table)->orderBy('id')->get()->toJson();
    }
    $journals = DB::table('acct.transactions')->orderBy('id')->get()->toJson();
    $pax = $f->A->passengers->first()->fresh();
    $result = Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$pax->id]));
    expect($result['source_after'])->toHaveCount(1)->and($result['target_after'])->toHaveCount(3);
    expect($pax->fresh()->getAttributes())->toBe($pax->getAttributes());
    $assignment = VoucherPassenger::where('passenger_id', $pax->id)->sole();
    expect($assignment->voucher_id)->toBe($f->B->voucher->id)->and($assignment->visa_group_id)->toBe($f->A->group->id);
    foreach ($before as $table => $rows) {
        expect(DB::table('umrah.'.$table)->orderBy('id')->get()->toJson())->toBe($rows);
    }
    expect(DB::table('acct.transactions')->orderBy('id')->get()->toJson())->toBe($journals);
});

test('repeated cross agent move cannot duplicate the passenger', function () {
    $f = crossAgentFixture();
    $command = new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$f->A->passengers->first()->id]);
    Bus::dispatch($command);
    expect(fn () => Bus::dispatch($command))->toThrow(ValidationException::class);
    expect($f->B->voucher->passengers()->count())->toBe(3);
});

test('cross group moves reject posted or amendment and shared billing drafts', function (string $field) {
    $f = crossAgentFixture();
    $value = $field === 'status' ? Voucher::STATUS_APPROVED : $f->A->voucher->id;
    $f->B->voucher->update([$field => $value]);
    expect(fn () => Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$f->A->passengers->first()->id])))
        ->toThrow(ValidationException::class);
    expect($f->A->voucher->passengers()->count())->toBe(2)->and($f->B->voucher->passengers()->count())->toBe(2);
})->with(['status', 'amends_voucher_id', 'billing_voucher_id']);

test('unknown passengers and same destination are rejected atomically', function (string $scenario) {
    $f = crossAgentFixture();
    $target = $scenario === 'same' ? $f->A->voucher : $f->B->voucher;
    $ids = match ($scenario) {
        'unknown' => [$f->B->passengers->first()->id],
        'all' => $f->A->passengers->pluck('id')->all(),
        default => [$f->A->passengers->first()->id],
    };
    expect(fn () => Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $target, $ids)))->toThrow(ValidationException::class);
    expect($f->A->voucher->passengers()->count())->toBe(2)->and($f->B->voucher->passengers()->count())->toBe(2);
})->with(['unknown', 'same']);

test('all passengers may transfer across agents without deleting their original draft or purchases', function () {
    $f = crossAgentFixture();
    $before = $f->A->group->fresh()->getAttributes();
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, $f->A->passengers->pluck('id')->all()));
    expect($f->A->voucher->fresh()->status)->toBe(Voucher::STATUS_DRAFT)
        ->and($f->A->voucher->passengers()->count())->toBe(0)
        ->and($f->B->voucher->passengers()->count())->toBe(4)
        ->and($f->A->group->fresh()->getAttributes())->toBe($before);
    expect(fn () => app(VoucherWorkflowService::class)->approve($f->A->voucher))->toThrow(ValidationException::class);
});

test('mixed source approval with no new hotel services leaves accounting unchanged', function () {
    $f = crossAgentFixture();
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$f->A->passengers->first()->id]));
    $before = DB::table('acct.transactions')->count();
    app(VoucherWorkflowService::class)->approve($f->B->voucher);
    expect($f->B->voucher->fresh()->status)->toBe(Voucher::STATUS_APPROVED)->and(DB::table('acct.transactions')->count())->toBe($before);
});

test('a transferred passenger cannot be deleted from the original purchase', function () {
    $f = crossAgentFixture();
    $pax = $f->A->passengers->first();
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$pax->id]));
    expect(fn () => app(UmrahCoreService::class)->removePassenger($f->A->group, $pax, 'Remove test passenger'))->toThrow(ValidationException::class);
    expect($pax->fresh()->deleted_at)->toBeNull()->and($f->B->voucher->passengers()->count())->toBe(3);
});

test('mixed source print and pdf render original provider attribution without financial data', function (string $format) {
    $f = crossAgentFixture();
    $secondVisa = VisaVendor::create(['company_id' => $f->company->id, 'vendor_number' => 'SECOND-VISA', 'name' => 'Destination Visa Provider', 'service_type' => VisaVendor::SERVICE_VISA_PROVIDER]);
    $f->B->group->update(['vendor_id' => $secondVisa->id]);
    foreach (['A', 'B'] as $party) {
        $transport = VisaVendor::create(['company_id' => $f->company->id, 'vendor_number' => 'PRINT-BUS-'.$party, 'name' => 'Transport Company '.$party, 'service_type' => 'transport_provider']);
        $f->{$party}->group->update(['mandatory_transport_vendor_id' => $transport->id, 'transport_mode' => 'standard_bus']);
    }
    $f->B->voucher->update([
        'onward_airline' => 'SV', 'onward_flight_number' => '700', 'onward_departure_city' => 'LHE', 'onward_arrival_city' => 'JED', 'onward_departure_at' => '2026-10-01 04:00:00', 'onward_arrival_at' => '2026-10-01 08:00:00',
        'return_airline' => 'SV', 'return_flight_number' => '701', 'return_departure_city' => 'JED', 'return_arrival_city' => 'LHE', 'return_departure_at' => '2026-10-10 10:00:00', 'return_arrival_at' => '2026-10-10 16:00:00',
        'hotel_stays' => [
            ['source' => 'self', 'hotel_name' => 'Makkah Central Hotel', 'city' => 'Makkah', 'room_type' => 'quad', 'room_count' => 1, 'check_in_date' => '2026-10-01', 'check_out_date' => '2026-10-05', 'night_count' => 4],
            ['source' => 'self', 'hotel_name' => 'Madinah Central Hotel', 'city' => 'Madinah', 'room_type' => 'quad', 'room_count' => 1, 'check_in_date' => '2026-10-05', 'check_out_date' => '2026-10-08', 'night_count' => 3],
            ['source' => 'self', 'hotel_name' => 'Makkah Central Hotel', 'city' => 'Makkah', 'room_type' => 'quad', 'room_count' => 1, 'check_in_date' => '2026-10-08', 'check_out_date' => '2026-10-10', 'night_count' => 2],
        ],
    ]);
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$f->A->passengers->first()->id]));
    $response = $this->actingAs($f->user)->get('/'.$f->company->slug.'/umrah/vouchers/'.$f->B->voucher->id.'/'.$format)->assertOk();
    if ($format === 'print') {
        $response->assertSee('Passenger services')->assertSee('Original Provider')->assertSee('Destination Visa Provider')
            ->assertSee('Transport Company A')->assertSee('Transport Company B')->assertDontSee('Private purchase note');
    } else {
        $response->assertHeader('content-type', 'application/pdf');
        if (getenv('UMRAH_TRANSFER_PDF_QA')) {
            file_put_contents(storage_path('app/mixed-agent-voucher-qa.pdf'), $response->getContent());
        }
    }
})->with(['print', 'pdf']);

test('staff transfer endpoint records both agent identities in audit history', function () {
    $f = crossAgentFixture();
    $url = '/'.$f->company->slug.'/umrah/vouchers/'.$f->A->voucher->id.'/passengers/move';
    $this->actingAs($f->user)->post($url, ['target_voucher_id' => $f->B->voucher->id, 'passenger_ids' => [$f->A->passengers->first()->id]])
        ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    $logs = \App\Modules\Umrah\Models\ChangeLog::whereIn('action', ['passengers_moved_in', 'passengers_moved_out'])->get();
    expect($logs)->toHaveCount(2);
    foreach ($logs as $log) {
        expect($log->metadata['source_agent_id'])->toBe($f->A->agent->id)
            ->and($log->metadata['target_agent_id'])->toBe($f->B->agent->id)
            ->and($log->metadata['original_purchases_preserved'])->toBeTrue();
    }
});

function crossAgentLogin(object $f, string $party): \App\Models\User
{
    $user = \App\Models\User::factory()->withoutTwoFactor()->create();
    DB::table('auth.company_user')->insert(['company_id' => $f->company->id, 'user_id' => $user->id, 'role' => 'agent', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    CompanyContext::withContext($f->company, fn () => CompanyContext::assignRole($user, 'agent'));
    $f->{$party}->agent->update(['user_id' => $user->id, 'can_edit_voucher' => true]);

    return $user;
}

test('agent cannot move passengers to another agents voucher or discover it in targets', function () {
    $f = crossAgentFixture();
    $user = crossAgentLogin($f, 'A');
    $url = '/'.$f->company->slug.'/umrah/vouchers/'.$f->A->voucher->id;
    $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->has('moveTargets', 0));
    $this->post($url.'/passengers/move', ['target_voucher_id' => $f->B->voucher->id, 'passenger_ids' => [$f->A->passengers->first()->id]])->assertNotFound();
    expect($f->A->voucher->passengers()->count())->toBe(2)->and($f->B->voucher->passengers()->count())->toBe(2);
});

test('destination agent sees transferred identity but not original passenger price or private notes', function () {
    $f = crossAgentFixture();
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$f->A->passengers->first()->id]));
    $user = crossAgentLogin($f, 'B');
    $this->actingAs($user)->get('/'.$f->company->slug.'/umrah/vouchers/'.$f->B->voucher->id)->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('hasMixedPassengerSources', true)->has('voucher.passengers', 3)
            ->missing('voucher.passengers.0.transport_charge_amount')->missing('voucher.passengers.0.notes')
            ->missing('voucher.passengers.1.transport_charge_amount')->missing('voucher.passengers.1.notes')
            ->missing('voucher.passengers.2.transport_charge_amount')->missing('voucher.passengers.2.notes'));
    $this->get('/'.$f->company->slug.'/umrah/vouchers/'.$f->A->voucher->id)->assertNotFound();
});

test('cross company destination is rejected without changing either manifest', function () {
    $f = crossAgentFixture();
    $other = crossAgentFixture();
    CompanyContext::setContext($f->company);
    expect(fn () => Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $other->B->voucher, [$f->A->passengers->first()->id])))
        ->toThrow(ValidationException::class);
    expect($f->A->voucher->passengers()->count())->toBe(2);
    CompanyContext::setContext($other->company);
    expect($other->B->voucher->passengers()->count())->toBe(2);
});

test('amendment keeps each passengers original purchase group', function () {
    $f = crossAgentFixture();
    $pax = $f->A->passengers->first();
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$pax->id]));
    // Exercise the amendment mechanism independently of hotel pricing.
    $f->B->voucher->update(['status' => Voucher::STATUS_APPROVED]);
    $amended = app(VoucherWorkflowService::class)->createAmendment($f->B->voucher, 'CROSS-AMENDED', $f->user->id);
    $assignment = VoucherPassenger::where('voucher_id', $amended->id)->where('passenger_id', $pax->id)->sole();
    expect($assignment->visa_group_id)->toBe($f->A->group->id);
});

test('empty selection is rejected by the transfer command itself', function () {
    $f = crossAgentFixture();
    expect(fn () => Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [])))->toThrow(ValidationException::class);
});

test('mixed party hotel approval uses destination agent rate and cancellation leaves original purchases untouched', function () {
    $f = crossAgentFixture();
    foreach ([['1100', 'Receivable', 'asset', 'accounts_receivable', 'debit'], ['2000', 'Payable', 'liability', 'accounts_payable', 'credit'], ['4120', 'Hotels', 'revenue', 'revenue', 'credit'], ['5120', 'Hotel cost', 'cogs', 'cogs', 'debit']] as [$code, $name, $type, $subtype, $normal]) {
        \App\Modules\Accounting\Models\Account::firstOrCreate(['company_id' => $f->company->id, 'code' => $code], ['name' => $name, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal]);
    }
    $supplier = \App\Modules\Umrah\Models\HotelVendor::create(['company_id' => $f->company->id, 'vendor_number' => 'DEST-HOTEL', 'name' => 'Destination Hotel Supplier']);
    $hotel = \App\Modules\Umrah\Models\Hotel::create(['company_id' => $f->company->id, 'hotel_vendor_id' => $supplier->id, 'name' => 'Party Hotel', 'city' => 'Makkah', 'is_active' => true]);
    $room = \App\Modules\Umrah\Models\HotelRoomRate::create(['company_id' => $f->company->id, 'hotel_id' => $hotel->id, 'room_type' => 'double', 'retail_amount' => 100, 'cost_amount' => 60, 'is_active' => true]);
    foreach (['A' => 50, 'B' => 80] as $party => $rate) {
        \App\Modules\Umrah\Models\CommercialRate::create(['company_id' => $f->company->id, 'service_type' => 'hotel_room', 'hotel_room_rate_id' => $room->id, 'scope_type' => 'agent', 'agent_id' => $f->{$party}->agent->id, 'calculation_type' => 'set_price', 'amount' => $rate, 'currency' => 'PKR', 'effective_from' => '2026-01-01', 'effective_until' => '2026-12-31', 'is_active' => true]);
    }
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$f->A->passengers->first()->id]));
    $f->B->voucher->update(['service_bundle' => Voucher::SERVICE_HOTEL, 'hotel_stays' => [['source' => 'company', 'hotel_id' => $hotel->id, 'hotel_name' => $hotel->name, 'city' => 'Makkah', 'room_type' => 'double', 'room_count' => 1, 'check_in_date' => '2026-10-01', 'check_out_date' => '2026-10-03']]]);
    $original = $f->A->group->fresh()->getAttributes();
    $payments = DB::table('umrah.payment_allocations')->orderBy('id')->get()->toJson();
    $url = '/'.$f->company->slug.'/umrah/vouchers/'.$f->B->voucher->id;
    $this->actingAs($f->user)->post($url.'/approve')->assertRedirect()->assertSessionHasNoErrors();
    $approved = $f->B->voucher->fresh();
    expect((float) $approved->hotel_sale_amount)->toBe(320.0)->and((float) $approved->hotel_cost_amount)->toBe(240.0)
        ->and((float) $f->B->group->fresh()->hotel_amount)->toBe(320.0)
        ->and((float) $supplier->fresh()->balance)->toBe(240.0)
        ->and($f->A->group->fresh()->getAttributes())->toBe($original);
    $transaction = DB::table('acct.transactions')->where('id', $approved->hotel_sale_transaction_id)->first();
    expect(json_decode($transaction->metadata, true)['agent_id'])->toBe($f->B->agent->id);
    // Issued membership changes must not enter amendment accounting, even
    // when the receiving party belongs to a different purchasing agent.
    $financialBefore = [];
    foreach (['umrah.visa_groups', 'umrah.agents', 'umrah.hotel_vendors', 'umrah.group_payments', 'umrah.payment_allocations', 'acct.transactions'] as $table) {
        $financialBefore[$table] = DB::table($table)->orderBy('id')->get()->toJson();
    }
    $movedPax = $f->B->passengers->first()->id;
    $this->post($url.'/passengers/move', ['target_voucher_id' => $f->A->voucher->id, 'passenger_ids' => [$movedPax], 'override_reason' => 'Travelling with another family; keep the purchased rooms'])
        ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    $this->post('/'.$f->company->slug.'/umrah/vouchers/'.$f->A->voucher->id.'/passengers/move', ['target_voucher_id' => $f->B->voucher->id, 'passenger_ids' => [$movedPax], 'override_reason' => 'Return to the original travelling party'])
        ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    foreach ($financialBefore as $table => $snapshot) {
        expect(DB::table($table)->orderBy('id')->get()->toJson())->toBe($snapshot);
    }
    expect($approved->fresh()->hotel_sale_transaction_id)->toBe($approved->hotel_sale_transaction_id)
        ->and($approved->fresh()->hotel_cost_transaction_id)->toBe($approved->hotel_cost_transaction_id)
        ->and($approved->fresh()->hotel_stays)->toBe($approved->hotel_stays);
    $this->post($url.'/approve')->assertRedirect()->assertSessionHasNoErrors();
    expect((float) $f->B->group->fresh()->hotel_amount)->toBe(320.0);
    $amendment = app(VoucherWorkflowService::class)->createAmendment($approved, 'CROSS-HOTEL-AMENDED', $f->user->id);
    $stays = $amendment->hotel_stays;
    $stays[0]['room_count'] = 2;
    $amendment->update(['hotel_stays' => $stays]);
    $this->post('/'.$f->company->slug.'/umrah/vouchers/'.$amendment->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();
    expect((float) $f->B->group->fresh()->hotel_amount)->toBe(640.0)
        ->and((float) $supplier->fresh()->balance)->toBe(480.0)
        ->and($f->A->group->fresh()->getAttributes())->toBe($original);
    app(VoucherWorkflowService::class)->cancel($amendment->fresh(), 'Cancel the newly purchased hotel stay', $f->user->id);
    expect((float) $f->B->group->fresh()->hotel_amount)->toBe(0.0)
        ->and((float) $supplier->fresh()->balance)->toBe(0.0)
        ->and($f->A->group->fresh()->getAttributes())->toBe($original)
        ->and(DB::table('umrah.payment_allocations')->orderBy('id')->get()->toJson())->toBe($payments);
});

test('mixed party operations counts people once and keeps original transport passenger manifests', function () {
    $f = crossAgentFixture();
    $sector = \App\Modules\Umrah\Models\TransportSector::create(['company_id' => $f->company->id, 'code' => 'CROSS-ARRIVAL', 'name' => 'Jeddah Airport to Makkah', 'origin' => 'JED', 'destination' => 'Makkah']);
    foreach (['A', 'B'] as $party) {
        $provider = VisaVendor::create(['company_id' => $f->company->id, 'vendor_number' => 'BUS-'.$party, 'name' => 'Bus Provider '.$party, 'service_type' => 'transport_provider']);
        $f->{$party}->group->update(['status' => VisaGroup::STATUS_VISA_APPROVED, 'mandatory_transport_vendor_id' => $provider->id, 'transport_mode' => 'standard_bus', 'passenger_count' => 2]);
        \App\Modules\Umrah\Models\GroupTransportItem::create(['company_id' => $f->company->id, 'visa_group_id' => $f->{$party}->group->id, 'transport_vendor_id' => $provider->id, 'transport_sector_id' => $sector->id, 'description' => 'Original pickup '.$party, 'scheduled_at' => '2026-10-01 10:30:00', 'passenger_count' => 2, 'quantity' => 1]);
        $f->{$party}->voucher->update(['onward_arrival_at' => '2026-10-01 10:00:00', 'onward_arrival_city' => 'JED', 'onward_departure_city' => 'LHE', 'service_bundle' => Voucher::SERVICE_VISA_TRANSPORT_HOTEL, 'hotel_stays' => [['hotel_name' => 'Party hotel', 'city' => 'Makkah', 'room_count' => 1, 'check_in_date' => '2026-10-01', 'check_out_date' => '2026-10-03']]]);
    }
    $moved = $f->A->passengers->first();
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$moved->id]));
    foreach (['A', 'B'] as $party) {
        $f->{$party}->voucher->update(['status' => Voucher::STATUS_APPROVED]);
    }
    $filters = ['period' => 'custom', 'date' => '2026-10-01', 'start' => '2026-10-01', 'end' => '2026-10-03', 'event_type' => 'all', 'readiness' => 'all'];
    $result = app(\App\Modules\Umrah\Services\OperationalEventTimelineService::class)->build($f->company, $f->user, $filters);
    $events = collect($result['events']);
    expect($events->where('type', 'airport_arrival')->sum('passenger_count'))->toBe(4)
        ->and($events->where('type', 'hotel_check_in')->sum('room_count'))->toBe(2);
    $arrivals = $events->where('type', 'airport_arrival')->filter(fn ($event) => $event['voucher']['id'] === $f->B->voucher->id);
    expect($arrivals)->toHaveCount(2);
    $movedArrival = $arrivals->first(fn ($event) => collect($event['passengers'])->contains('id', $moved->id));
    expect($movedArrival['transport']['provider'])->toBe('Bus Provider A');
    $pickupA = $events->where('type', 'transport_pickup')->first(fn ($event) => $event['group']['id'] === $f->A->group->id);
    $pickupB = $events->where('type', 'transport_pickup')->first(fn ($event) => $event['group']['id'] === $f->B->group->id);
    expect(collect($pickupA['passengers'])->pluck('id')->all())->toContain($moved->id)
        ->and(collect($pickupB['passengers'])->pluck('id')->all())->not->toContain($moved->id);
    $agentUser = crossAgentLogin($f, 'B');
    $agentResult = app(\App\Modules\Umrah\Services\OperationalEventTimelineService::class)->build($f->company, $agentUser, $filters);
    $agentArrivals = collect($agentResult['events'])->where('type', 'airport_arrival');
    expect($agentArrivals->sum('passenger_count'))->toBe(3);
    $borrowed = $agentArrivals->first(fn ($event) => collect($event['passengers'])->contains('id', $moved->id));
    expect($borrowed['group'])->toBeNull()->and($borrowed['transport']['provider'])->toBe('Bus Provider A');
    config(['umrah.operations.presentation_by_role.owner' => 'summary']);
    $summary = app(\App\Modules\Umrah\Services\OperationalEventTimelineService::class)->build($f->company, $f->user, $filters);
    expect($summary['events'])->toBe([])
        ->and(collect($summary['summary'])->firstWhere('key', 'moving_in')['value'])->toBe(4);
});

test('mixed voucher cannot approve when an original purchase group is cancelled', function () {
    $f = crossAgentFixture();
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$f->A->passengers->first()->id]));
    $f->A->group->update(['status' => VisaGroup::STATUS_CANCELLED]);
    expect(fn () => app(VoucherWorkflowService::class)->approve($f->B->voucher))->toThrow(ValidationException::class);
    expect($f->B->voucher->fresh()->status)->toBe(Voucher::STATUS_DRAFT);
});

test('voucher draft saves an explicit leader and prints that person rather than the first passenger', function () {
    $f = crossAgentFixture();
    $leader = $f->B->passengers->last();
    $f->B->group->update(['transport_mode' => 'none']);
    $url = '/'.$f->company->slug.'/umrah/vouchers/'.$f->B->voucher->id;
    $data = ['title' => 'Leader test', 'service_bundle' => 'hotel', 'leader_passenger_id' => $leader->id,
        'hotel_stays' => [['source' => 'self', 'hotel_name' => 'Test hotel', 'city' => 'Makkah', 'room_type' => 'quad', 'room_count' => 1]]];
    $this->actingAs($f->user)->put($url, $data)->assertSessionHasNoErrors()->assertRedirect();
    expect($f->B->voucher->fresh()->leader_passenger_id)->toBe($leader->id);
    $this->get($url.'/print')->assertOk()->assertSee('Group leader: '.$leader->full_name);
    $this->put($url, [...$data, 'leader_passenger_id' => $f->A->passengers->first()->id])->assertSessionHasErrors('leader_passenger_id');
    expect($f->B->voucher->fresh()->leader_passenger_id)->toBe($leader->id);
    $this->put($url, [...$data, 'leader_passenger_id' => null])->assertSessionHasNoErrors();
    $this->get($url.'/print')->assertSee('Group leader: Not selected');
});

test('new voucher accepts only one leader among its selected passengers', function () {
    $f = crossAgentFixture();
    $f->A->voucher->voucherPassengers()->delete();
    $pax = $f->A->passengers;
    $data = ['visa_group_id' => $f->A->group->id, 'title' => 'Explicit leader creation', 'service_bundle' => 'visa_transport', 'status' => 'draft',
        'passenger_ids' => $pax->pluck('id')->all(), 'passenger_services' => $pax->mapWithKeys(fn ($p) => [$p->id => 'visa_transport'])->all(),
        'hotel_stays' => [['source' => 'self', 'city' => 'Makkah', 'room_type' => 'quad', 'room_count' => 1]]];
    $url = '/'.$f->company->slug.'/umrah/vouchers';
    $this->actingAs($f->user);
    foreach ([$f->B->passengers->first()->id, [$pax->first()->id, $pax->last()->id], 'not-a-uuid'] as $invalid) {
        $this->post($url, [...$data, 'leader_passenger_id' => $invalid])->assertSessionHasErrors('leader_passenger_id');
    }
    $this->post($url, [...$data, 'passenger_ids' => [$pax->first()->id], 'leader_passenger_id' => $pax->last()->id])->assertSessionHasErrors('leader_passenger_id');
    $this->post($url, [...$data, 'leader_passenger_id' => $pax->last()->id])->assertSessionHasNoErrors()->assertRedirect();
    expect(Voucher::where('title', 'Explicit leader creation')->sole()->leader_passenger_id)->toBe($pax->last()->id);
});

test('moving a leader clears only the source selection and never replaces the destination leader', function () {
    $f = crossAgentFixture();
    $sourceLeader = $f->A->passengers->first();
    $targetLeader = $f->B->passengers->last();
    $f->A->voucher->update(['leader_passenger_id' => $sourceLeader->id]);
    $f->B->voucher->update(['leader_passenger_id' => $targetLeader->id]);
    Bus::dispatch(new MoveVoucherPassengers($f->A->voucher, $f->B->voucher, [$sourceLeader->id]));
    expect($f->A->voucher->fresh()->leader_passenger_id)->toBeNull()
        ->and($f->B->voucher->fresh()->leader_passenger_id)->toBe($targetLeader->id);
    $f->B->voucher->update(['status' => Voucher::STATUS_APPROVED]);
    $amended = app(VoucherWorkflowService::class)->createAmendment($f->B->voucher, 'LEADER-AMENDMENT', $f->user->id);
    expect($amended->leader_passenger_id)->toBe($targetLeader->id);
});

test('separated copies only retain the chosen leader when that passenger belongs to the copy', function () {
    $f = crossAgentFixture();
    $leader = $f->A->passengers->last();
    $f->A->voucher->update(['leader_passenger_id' => $leader->id]);
    $result = app(\App\Modules\Umrah\Services\VoucherPassengerAssignmentService::class)->separate($f->A->voucher, $f->A->passengers->pluck('id')->all(), $f->user->id);
    foreach ($result['created'] as $copy) {
        expect($copy->leader_passenger_id)->toBe($copy->passengers()->whereKey($leader->id)->exists() ? $leader->id : null);
    }
});
