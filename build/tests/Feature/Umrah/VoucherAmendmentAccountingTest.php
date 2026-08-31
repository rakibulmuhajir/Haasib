<?php

use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use App\Modules\Umrah\Services\VoucherWorkflowService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TicketingFixtures.php';

/**
 * Approving an amendment must move the group by the difference.
 *
 * It used to add the whole new figure to a group total that had been read
 * before the superseded voucher was reversed, so the old amount came back
 * with it and the group carried both vouchers' hotels at once. QA saw
 * 21,600 reversed to nought and then 57,600 written over it.
 */
function amendmentAccountingFixture(float $originalSale, float $originalCost): object
{
    // The shared fixture opens one accounting period, September 2026, and
    // hotel postings are dated today. Stand inside it.
    Illuminate\Support\Carbon::setTestNow('2026-09-15 09:00:00');

    $f = ticketingCompany([
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
        'base_currency' => 'SAR',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$f->company->id]);

    // Hotel postings need somewhere to land: a receivable and a payable,
    // plus the hotel revenue and cost lines.
    foreach ([
        ['1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit'],
        ['2000', 'Accounts Payable', 'liability', 'accounts_payable', 'credit'],
        ['4120', 'Hotel Revenue', 'revenue', 'revenue', 'credit'],
        ['5120', 'Hotel Cost', 'cogs', 'cogs', 'debit'],
    ] as [$code, $name, $type, $subtype, $normal]) {
        App\Modules\Accounting\Models\Account::firstOrCreate(
            ['company_id' => $f->company->id, 'code' => $code],
            ['name' => $name, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal],
        );
    }

    $agent = Agent::create([
        'company_id' => $f->company->id,
        'agent_number' => 'AGT-'.str()->upper(str()->random(5)),
        'name' => 'Amendment Accounting Agent',
    ]);
    $vendor = VisaVendor::create([
        'company_id' => $f->company->id,
        'vendor_number' => 'VIS-'.str()->upper(str()->random(5)),
        'name' => 'Visa Vendor',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
    ]);
    $hotelVendor = HotelVendor::create([
        'company_id' => $f->company->id,
        'vendor_number' => 'HOT-'.str()->upper(str()->random(5)),
        'name' => 'Hotel Vendor',
    ]);
    $group = VisaGroup::create([
        'company_id' => $f->company->id,
        'agent_id' => $agent->id,
        'vendor_id' => $vendor->id,
        'group_number' => 'UGR-'.str()->upper(str()->random(5)),
        'name' => 'Amendment accounting group',
        'status' => VisaGroup::STATUS_VISA_APPROVED,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'transport_required' => false,
        'travel_date' => now()->addDays(40)->toDateString(),
        'visa_sale_amount' => 0,
        'visa_cost_amount' => 0,
    ]);
    $passenger = Passenger::create([
        'company_id' => $f->company->id,
        'visa_group_id' => $group->id,
        'full_name' => 'Amendment Accounting Passenger',
        'service_type' => Passenger::SERVICE_VISA_TRANSPORT,
    ]);

    $stay = [
        'source' => 'self', 'hotel_id' => null, 'hotel_name' => 'Some Hotel',
        'city' => 'Makkah', 'room_type' => 'quad', 'room_count' => 1,
        'check_in_date' => now()->addDays(41)->toDateString(),
        'check_out_date' => now()->addDays(44)->toDateString(),
        'notes' => null, 'hotel_vendor_id' => null, 'night_count' => 3,
        'unit_retail_amount' => 0, 'unit_cost_amount' => 0,
        'total_retail_amount' => $originalSale, 'total_cost_amount' => $originalCost,
    ];

    $voucher = Voucher::create([
        'company_id' => $f->company->id,
        'visa_group_id' => $group->id,
        'agent_id' => $agent->id,
        'voucher_number' => 'UVR-'.str()->upper(str()->random(5)),
        'title' => 'Original',
        'service_bundle' => Voucher::SERVICE_HOTEL,
        'status' => Voucher::STATUS_DRAFT,
        'hotel_stays' => [$stay],
        'hotel_sale_amount' => $originalSale,
        'hotel_cost_amount' => $originalCost,
    ]);
    VoucherPassenger::create([
        'company_id' => $f->company->id,
        'voucher_id' => $voucher->id,
        'visa_group_id' => $group->id,
        'passenger_id' => $passenger->id,
    ]);

    app(VoucherWorkflowService::class)->approve($voucher);

    return (object) [
        'company' => $f->company,
        'group' => $group,
        'voucher' => $voucher->fresh(),
        'hotelVendor' => $hotelVendor,
    ];
}

test('approving an amendment moves the group by the difference, not the whole amount', function () {
    // QA's figures: 21,600 / 17,000 amended up to 36,000 / 28,400.
    $f = amendmentAccountingFixture(21600, 17000);

    expect((float) $f->group->fresh()->hotel_amount)->toBe(21600.0)
        ->and((float) $f->group->fresh()->hotel_cost_amount)->toBe(17000.0);

    $amendment = app(VoucherWorkflowService::class)
        ->createAmendment($f->voucher, 'UVR-AMEND-ACC', null);

    $amendment->update([
        'hotel_sale_amount' => 36000,
        'hotel_cost_amount' => 28400,
    ]);

    app(VoucherWorkflowService::class)->approve($amendment->fresh());

    $group = $f->group->fresh();

    // The group holds one voucher's hotels, the amended ones -- not both.
    expect((float) $group->hotel_amount)->toBe(36000.0)
        ->and((float) $group->hotel_cost_amount)->toBe(28400.0);
});

test('the superseded voucher is stood down when its amendment is approved', function () {
    $f = amendmentAccountingFixture(21600, 17000);

    $amendment = app(VoucherWorkflowService::class)
        ->createAmendment($f->voucher, 'UVR-AMEND-ACC2', null);
    app(VoucherWorkflowService::class)->approve($amendment->fresh());

    expect($f->voucher->fresh()->superseded_at)->not->toBeNull()
        ->and($f->voucher->fresh()->superseded_by_voucher_id)->toBe($amendment->id);
});

test('an amendment that changes nothing leaves the group where it was', function () {
    $f = amendmentAccountingFixture(21600, 17000);

    $amendment = app(VoucherWorkflowService::class)
        ->createAmendment($f->voucher, 'UVR-AMEND-ACC3', null);
    app(VoucherWorkflowService::class)->approve($amendment->fresh());

    $group = $f->group->fresh();

    expect((float) $group->hotel_amount)->toBe(21600.0)
        ->and((float) $group->hotel_cost_amount)->toBe(17000.0);
});

afterEach(function () {
    Illuminate\Support\Carbon::setTestNow();
});
