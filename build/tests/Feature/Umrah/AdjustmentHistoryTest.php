<?php

use App\Modules\Umrah\Services\GroupAccountingService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TicketingFixtures.php';
require_once __DIR__.'/GroupCostAdjustmentTest.php';

/**
 * Making an adjustment starts from the trip, because it is always about
 * one trip. This is the other direction: at month end somebody wants every
 * correction and renegotiation at once, which until now could only be
 * assembled by opening groups one at a time.
 */
function adjustmentHistoryRows(object $f): array
{
    $rows = [];

    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f->company);
    ticketingAddCompanyMember($f->company, $f->user, 'owner');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    test()->actingAs($f->user)
        ->get("/{$f->company->slug}/umrah/adjustments")
        ->assertOk()
        ->assertInertia(function ($page) use (&$rows) {
            $rows = $page->toArray()['props']['adjustments'] ?? [];

            return $page;
        });

    return $rows;
}

test('an adjustment appears in the history with what it moved and why', function () {
    $f = costAdjustmentFixture();

    app(GroupAccountingService::class)->update($f->group->fresh(), [
        'vendor_id' => $f->visaVendor->id,
        'mandatory_transport_vendor_id' => $f->busVendor->id,
        'visa_sale_amount' => 1500,
        'transport_amount' => 160,
        'discount_amount' => 0,
        'visa_cost_amount' => 1040,
        'transport_cost_amount' => 100,
        'reason' => 'Rate corrected after booking',
        'reason_category' => 'error',
    ]);

    $rows = collect(adjustmentHistoryRows($f));
    $charge = $rows->firstWhere('side', 'charge');

    // 1,200 charged became 1,500.
    expect($charge)->not->toBeNull()
        ->and((float) $charge['amount'])->toBe(300.0)
        ->and($charge['group'])->toBe($f->group->group_number)
        ->and($charge['reason'])->toBe('Rate corrected after booking')
        ->and($charge['reason_category'])->toBe('error');
});

test('a supplier cost change is listed as its own kind', function () {
    $f = costAdjustmentFixture();

    app(GroupAccountingService::class)->update($f->group->fresh(), [
        'vendor_id' => $f->visaVendor->id,
        'mandatory_transport_vendor_id' => $f->busVendor->id,
        'visa_sale_amount' => 1200,
        'transport_amount' => 160,
        'discount_amount' => 0,
        'visa_cost_amount' => 960,
        'transport_cost_amount' => 100,
        'reason' => 'Supplier dropped their price',
        'reason_category' => 'renegotiated',
    ]);

    $cost = collect(adjustmentHistoryRows($f))->firstWhere('side', 'cost');

    // 1,040 fell to 960, so the cost moved down by eighty.
    expect($cost)->not->toBeNull()
        ->and((float) $cost['amount'])->toBe(-80.0)
        ->and($cost['reason_category'])->toBe('renegotiated');
});

test('a group nobody has adjusted lists nothing', function () {
    $f = costAdjustmentFixture();

    expect(adjustmentHistoryRows($f))->toBe([]);
});
