<?php

use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\RefundService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TicketingFixtures.php';

/**
 * A supplier lowering their price lowers what that trip cost.
 *
 * The ledger already knew: accepting the credit credits the cost account.
 * The group's own figure did not move with it, so the books and the trip's
 * margin told different stories after every supplier credit.
 *
 * Whether the supplier had already been paid changes only which account
 * holds the other half -- payable if not, money owed back if so. It never
 * changes whether the cost comes down.
 */
function vendorCreditFixture(): object
{
    Illuminate\Support\Carbon::setTestNow('2026-09-15 09:00:00');

    $f = ticketingCompany([
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
        'base_currency' => 'SAR',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$f->company->id]);

    foreach ([
        ['1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit'],
        ['1170', 'Refunds Receivable', 'asset', 'other_current_asset', 'debit'],
        ['2000', 'Accounts Payable', 'liability', 'accounts_payable', 'credit'],
        ['2200', 'Agent Advances', 'liability', 'other_current_liability', 'credit'],
        ['2300', 'Refunds Payable', 'liability', 'other_current_liability', 'credit'],
        ['4100', 'Visa Revenue', 'revenue', 'revenue', 'credit'],
        ['5100', 'Visa Cost', 'cogs', 'cogs', 'debit'],
        ['5110', 'Transport Cost', 'cogs', 'cogs', 'debit'],
    ] as [$code, $name, $type, $subtype, $normal]) {
        App\Modules\Accounting\Models\Account::firstOrCreate(
            ['company_id' => $f->company->id, 'code' => $code],
            ['name' => $name, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal],
        );
    }

    $agent = Agent::create([
        'company_id' => $f->company->id,
        'agent_number' => 'AGT-'.str()->upper(str()->random(5)),
        'name' => 'Vendor Credit Agent',
    ]);
    $vendor = VisaVendor::create([
        'company_id' => $f->company->id,
        'vendor_number' => 'VIS-'.str()->upper(str()->random(5)),
        'name' => 'Visa Vendor',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
        // The refund ceiling reads what has been paid to this supplier.
        'total_paid' => 7280,
    ]);
    $group = VisaGroup::create([
        'company_id' => $f->company->id,
        'agent_id' => $agent->id,
        'vendor_id' => $vendor->id,
        'group_number' => 'UGR-'.str()->upper(str()->random(5)),
        'name' => 'Vendor credit group',
        'status' => VisaGroup::STATUS_VISA_APPROVED,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'transport_required' => false,
        'passenger_count' => 14,
        'visa_sale_amount' => 8400, 'visa_cost_amount' => 7280,
        'total_receivable' => 8400,
    ]);

    // A refund is money coming back, so the system will not let one exceed
    // what was actually sent to that supplier. Pay the bill first.
    App\Modules\Umrah\Models\GroupPayment::create([
        'company_id' => $f->company->id,
        'visa_vendor_id' => $vendor->id,
        'direction' => App\Modules\Umrah\Models\GroupPayment::DIRECTION_SENT,
        'payment_number' => 'UPM-'.str()->upper(str()->random(5)),
        'payment_date' => '2026-09-10',
        'amount' => 7280, 'currency' => 'SAR',
        'base_currency' => 'SAR', 'base_amount' => 7280,
        'method' => App\Modules\Umrah\Models\GroupPayment::METHOD_CASH,
        'status' => App\Modules\Umrah\Models\GroupPayment::STATUS_POSTED,
    ]);

    return (object) ['company' => $f->company, 'user' => $f->user, 'agent' => $agent, 'vendor' => $vendor, 'group' => $group];
}

function grantVendorCredit(object $f, array $overrides = []): Refund
{
    $service = app(RefundService::class);

    $refund = $service->request($f->company->id, array_merge([
        'party_type' => Refund::PARTY_VISA_VENDOR,
        'party_id' => $f->vendor->id,
        'visa_group_id' => $f->group->id,
        'service' => Refund::SERVICE_VISA,
        'amount' => 400,
        'currency' => 'SAR',
        'reason' => 'Renegotiated after booking',
    ], $overrides), $f->user->id);

    return $service->approve($refund, ['review_remarks' => 'Agreed'], $f->user->id);
}

test('a supplier credit lowers what that trip cost', function () {
    $f = vendorCreditFixture();

    grantVendorCredit($f);

    // 7,280 less the 400 they gave back.
    expect((float) $f->group->fresh()->visa_cost_amount)->toBe(6880.0);
});

test('the trip margin improves by the credit', function () {
    $f = vendorCreditFixture();

    grantVendorCredit($f);

    // 8,400 charged, 6,880 now cost.
    expect((float) $f->group->fresh()->profit)->toBe(1520.0);
});

test('the agent is charged the same as before', function () {
    // What a supplier charges the company is not the agent's business.
    $f = vendorCreditFixture();

    grantVendorCredit($f);

    expect((float) $f->group->fresh()->total_receivable)->toBe(8400.0)
        ->and((float) $f->group->fresh()->visa_sale_amount)->toBe(8400.0);
});

test('a credit that names no group leaves every trip alone', function () {
    // Damages, or a rebate spread across a season: money arrives, but it is
    // not this trip's purchase price.
    $f = vendorCreditFixture();

    grantVendorCredit($f, ['visa_group_id' => null]);

    expect((float) $f->group->fresh()->visa_cost_amount)->toBe(7280.0);
});

test('cancelling the credit puts the cost back', function () {
    $f = vendorCreditFixture();
    $refund = grantVendorCredit($f);

    app(RefundService::class)->cancel($refund, 'Supplier withdrew it', $f->user->id);

    expect((float) $f->group->fresh()->visa_cost_amount)->toBe(7280.0);
});

test('a supplier credit is not refused for having no agent payments', function () {
    // Naming a group used to run the agent de-allocation, which found no
    // received payments from a supplier and refused the whole thing.
    $f = vendorCreditFixture();

    expect(fn () => grantVendorCredit($f))->not->toThrow(Illuminate\Validation\ValidationException::class);
});

test('a supplier who has not been paid cannot be refunded', function () {
    // Nothing has gone out, so nothing can come back. A price reduction
    // before payment lowers the bill instead, on the group's accounting.
    $f = vendorCreditFixture();
    $f->vendor->update(['total_paid' => 0]);

    expect(fn () => grantVendorCredit($f))->toThrow(Illuminate\Validation\ValidationException::class);
});

afterEach(function () {
    Illuminate\Support\Carbon::setTestNow();
});
