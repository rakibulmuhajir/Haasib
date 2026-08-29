<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Http\Controllers\VoucherController;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\TravelAccessService;
use Illuminate\Support\Facades\DB;

test('an agent can edit an incomplete draft before any service date is set', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Draft Editing Test',
        'slug' => 'draft-editing-test',
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'agent',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $agent = Agent::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'agent_number' => 'AGT-DRAFT-EDIT',
        'name' => 'Draft Editing Agent',
        'can_edit_voucher' => true,
        'voucher_cutoff_hours' => 6,
    ]);
    $voucher = new Voucher([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'service_bundle' => Voucher::SERVICE_HOTEL,
        'status' => Voucher::STATUS_DRAFT,
        'hotel_stays' => [],
    ]);

    expect(app(TravelAccessService::class)->agentCanModifyVoucherNow($company->id, $user, $voucher))
        ->toBeTrue();
});

test('draft persistence drops untouched hotel placeholders', function () {
    $controller = app(VoucherController::class);
    $method = new ReflectionMethod($controller, 'draftHotelStays');

    $stays = $method->invoke($controller, [
        [
            'source' => 'self',
            'hotel_id' => null,
            'hotel_name' => '',
            'city' => 'Makkah',
            'room_type' => 'double',
            'room_count' => 1,
            'check_in_date' => '',
            'check_out_date' => '',
            'notes' => '',
        ],
        [
            'source' => 'self',
            'hotel_id' => null,
            'hotel_name' => 'Meaningful Hotel',
            'city' => 'Madinah',
            'room_type' => 'double',
            'room_count' => 1,
            'check_in_date' => '',
            'check_out_date' => '',
            'notes' => '',
        ],
    ]);

    expect($stays)->toHaveCount(1)
        ->and($stays[0]['hotel_name'])->toBe('Meaningful Hotel');
});

/**
 * A draft holds no prices on purpose: approval is where hotel rates are
 * taken, and the rooming report reads these stays directly, so an
 * unapproved indicative amount would show up in a money column as though
 * someone had agreed it.
 *
 * Nights are not a price. They follow from the two dates, the rooming
 * list and the voucher PDF both print them, and zeroing them made every
 * draft stay read as nought nights.
 */
test('a draft stay counts its nights but still carries no amounts', function () {
    $controller = app(VoucherController::class);
    $method = new ReflectionMethod($controller, 'draftHotelStays');

    $stays = $method->invoke($controller, [
        [
            'source' => 'company',
            'hotel_id' => '01a04cab-89ee-7241-86c8-5263a130d53c',
            'hotel_name' => 'Al-Haram Madinah',
            'city' => 'Madinah',
            'room_type' => 'quad',
            'room_count' => 1,
            'check_in_date' => '2026-10-04',
            'check_out_date' => '2026-10-06',
            'notes' => null,
        ],
    ]);

    expect($stays)->toHaveCount(1)
        ->and($stays[0]['night_count'])->toBe(2)
        ->and($stays[0]['total_retail_amount'])->toBe(0)
        ->and($stays[0]['total_cost_amount'])->toBe(0)
        ->and($stays[0]['unit_retail_amount'])->toBe(0)
        ->and($stays[0]['unit_cost_amount'])->toBe(0);
});

test('a draft stay half way through being typed counts no nights', function () {
    $controller = app(VoucherController::class);
    $method = new ReflectionMethod($controller, 'draftHotelStays');

    $stays = $method->invoke($controller, [
        [
            'source' => 'self',
            'hotel_id' => null,
            'hotel_name' => 'Somewhere',
            'city' => 'Makkah',
            'room_type' => 'double',
            'room_count' => 1,
            'check_in_date' => '2026-10-06',
            'check_out_date' => null,
            'notes' => null,
        ],
    ]);

    expect($stays[0]['night_count'])->toBe(0);
});
