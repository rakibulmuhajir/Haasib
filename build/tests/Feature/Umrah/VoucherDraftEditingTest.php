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
