<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\PaymentAllocation;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\TravelReportService;
use App\Modules\Umrah\Services\UmrahCoreService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

function reportFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Travel Reports Test',
        'slug' => 'travel-reports-test',
        'owner_id' => $user->id,
        'base_currency' => 'USD',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    $agent = Agent::create(['company_id' => $company->id, 'agent_number' => 'AGT-RPT', 'name' => 'Reporting Agent']);
    $visaVendor = VisaVendor::create([
        'company_id' => $company->id, 'vendor_number' => 'VIS-RPT', 'name' => 'Visa Supplier',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
    ]);
    $transportVendor = VisaVendor::create([
        'company_id' => $company->id, 'vendor_number' => 'TRN-RPT', 'name' => 'Transport Supplier',
        'service_type' => VisaVendor::SERVICE_TRANSPORT_PROVIDER,
    ]);
    $group = VisaGroup::create([
        'company_id' => $company->id, 'agent_id' => $agent->id, 'vendor_id' => $visaVendor->id,
        'mandatory_transport_vendor_id' => $transportVendor->id, 'group_number' => 'UGR-RPT', 'name' => 'Report Group',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED, 'travel_date' => '2026-06-10', 'passenger_count' => 4,
        'visa_sale_amount' => 1000, 'transport_amount' => 200, 'hotel_amount' => 300, 'discount_amount' => 50,
        'visa_cost_amount' => 600, 'transport_cost_amount' => 100, 'mandatory_transport_cost_amount' => 100,
        'hotel_cost_amount' => 150, 'total_receivable' => 1450, 'total_paid' => 700, 'balance' => 750, 'profit' => 600,
    ]);
    // The financial reports window on when the group was charged, and this
    // fixture writes its group straight to the table rather than going
    // through createGroup(), so nothing has set that date for it. June is
    // the month these tests talk about.
    $group->forceFill(['created_at' => '2026-06-05 09:00:00'])->saveQuietly();

    $payment = GroupPayment::create([
        'company_id' => $company->id, 'agent_id' => $agent->id, 'direction' => GroupPayment::DIRECTION_RECEIVED,
        'payment_number' => 'UPM-RPT', 'payment_date' => '2026-06-12', 'amount' => 700, 'currency' => 'USD',
        'base_currency' => 'USD', 'base_amount' => 700, 'method' => GroupPayment::METHOD_CASH, 'status' => GroupPayment::STATUS_POSTED,
    ]);
    PaymentAllocation::create([
        'company_id' => $company->id, 'group_payment_id' => $payment->id, 'visa_group_id' => $group->id, 'base_amount' => 700,
    ]);

    return compact('user', 'company', 'agent', 'visaVendor', 'transportVendor', 'group');
}

test('group profitability reconciles revenue direct cost allocation and balance', function () {
    $fixture = reportFixture();
    $report = app(TravelReportService::class)->build(
        $fixture['company'],
        $fixture['user'],
        'group-profitability',
        ['start' => '2026-06-01', 'end' => '2026-06-30', 'per_page' => 25],
    );

    $row = $report['rows'][0];
    expect($row['revenue'])->toBe(1450.0)
        ->and($row['cost'])->toBe(850.0)
        ->and($row['gross_contribution'])->toBe(600.0)
        ->and($row['allocated'])->toBe(700.0)
        ->and($row['balance'])->toBe(750.0);
});

test('aging reports include older open balances through the as of date', function () {
    $fixture = reportFixture();
    $service = app(TravelReportService::class);
    $filters = ['start' => '2026-07-01', 'end' => '2026-07-31', 'per_page' => 25];

    $receivables = $service->build($fixture['company'], $fixture['user'], 'receivable-aging', $filters);
    $payables = $service->build($fixture['company'], $fixture['user'], 'vendor-aging', $filters);

    expect($receivables['rows'])->toHaveCount(1)
        ->and($receivables['rows'][0]['balance'])->toBe(750.0)
        ->and(collect($payables['rows'])->where('vendor_type', 'visa')->first()['balance'])->toBe(600.0)
        ->and(collect($payables['rows'])->where('vendor_type', 'transport')->first()['balance'])->toBe(100.0);
});

test('every phase one report builds from the shared report contract', function () {
    $fixture = reportFixture();
    $service = app(TravelReportService::class);
    $filters = ['start' => '2026-01-01', 'end' => '2026-12-31', 'per_page' => 25];

    foreach (array_keys(TravelReportService::REPORTS) as $reportKey) {
        $report = $service->build($fixture['company'], $fixture['user'], $reportKey, $filters);
        expect($report['key'])->toBe($reportKey)
            ->and($report)->toHaveKeys(['summary', 'columns', 'rows', 'pagination', 'date_basis']);
    }
});

test('report pdf uses the same report payload as the screen', function () {
    $fixture = reportFixture();
    $report = app(TravelReportService::class)->build(
        $fixture['company'],
        $fixture['user'],
        'group-profitability',
        ['start' => '2026-06-01', 'end' => '2026-06-30'],
        true,
    );

    $output = Pdf::loadView('umrah::reports.table', [
        'company' => $fixture['company'],
        'report' => $report,
        'logoSource' => null,
    ])->output();

    expect($output)->toStartWith('%PDF');
});

test('agent statement nets available advances against closing receivable as net due', function () {
    $fixture = reportFixture();
    DB::table('umrah.visa_groups')->where('id', $fixture['group']->id)->update(['created_at' => '2026-06-05 00:00:00']);
    GroupPayment::create([
        'company_id' => $fixture['company']->id, 'agent_id' => $fixture['agent']->id, 'direction' => GroupPayment::DIRECTION_RECEIVED,
        'payment_number' => 'UPM-RPT-2', 'payment_date' => '2026-06-15', 'amount' => 300, 'currency' => 'USD',
        'base_currency' => 'USD', 'base_amount' => 300, 'method' => GroupPayment::METHOD_CASH, 'status' => GroupPayment::STATUS_POSTED,
    ]);

    $report = app(TravelReportService::class)->build(
        $fixture['company'], $fixture['user'], 'agent-statement',
        ['start' => '2026-06-01', 'end' => '2026-06-30', 'per_page' => 25],
    );

    $summary = collect($report['summary'])->keyBy('label');

    expect($summary['Closing receivable']['value'])->toBe(750.0)
        ->and($summary['Available advances']['value'])->toBe(300.0)
        ->and($summary['Net due']['value'])->toBe(450.0);
});

test('an advance received after the statement period does not reduce net due', function () {
    $fixture = reportFixture();
    DB::table('umrah.visa_groups')->where('id', $fixture['group']->id)->update(['created_at' => '2026-06-05 00:00:00']);

    // Dated after the statement's end. The closing receivable is a balance as
    // of 30 June and knows nothing about it, so netting it off would subtract
    // money the agent had not yet sent by the date the statement is drawn to.
    GroupPayment::create([
        'company_id' => $fixture['company']->id, 'agent_id' => $fixture['agent']->id, 'direction' => GroupPayment::DIRECTION_RECEIVED,
        'payment_number' => 'UPM-RPT-LATE', 'payment_date' => '2026-07-15', 'amount' => 300, 'currency' => 'USD',
        'base_currency' => 'USD', 'base_amount' => 300, 'method' => GroupPayment::METHOD_CASH, 'status' => GroupPayment::STATUS_POSTED,
    ]);

    $report = app(TravelReportService::class)->build(
        $fixture['company'], $fixture['user'], 'agent-statement',
        ['start' => '2026-06-01', 'end' => '2026-06-30', 'per_page' => 25],
    );

    $summary = collect($report['summary'])->keyBy('label');

    expect($summary['Available advances']['value'])->toBe(0.0)
        ->and($summary['Net due']['value'])->toBe($summary['Closing receivable']['value']);
});

test('agent statement shows a negative net due when advances exceed the receivable', function () {
    $fixture = reportFixture();
    $agent = Agent::create(['company_id' => $fixture['company']->id, 'agent_number' => 'AGT-RPT-2', 'name' => 'Credit Agent']);
    $creditGroup = VisaGroup::create([
        'company_id' => $fixture['company']->id, 'agent_id' => $agent->id, 'vendor_id' => $fixture['visaVendor']->id,
        'mandatory_transport_vendor_id' => $fixture['transportVendor']->id, 'group_number' => 'UGR-RPT-2', 'name' => 'Credit Group',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED, 'travel_date' => '2026-06-10', 'passenger_count' => 1,
        'visa_sale_amount' => 100, 'transport_amount' => 0, 'hotel_amount' => 0, 'discount_amount' => 0,
        'visa_cost_amount' => 0, 'transport_cost_amount' => 0, 'mandatory_transport_cost_amount' => 0,
        'hotel_cost_amount' => 0, 'total_receivable' => 100, 'total_paid' => 0, 'balance' => 100, 'profit' => 100,
    ]);
    DB::table('umrah.visa_groups')->where('id', $creditGroup->id)->update(['created_at' => '2026-06-05 00:00:00']);
    GroupPayment::create([
        'company_id' => $fixture['company']->id, 'agent_id' => $agent->id, 'direction' => GroupPayment::DIRECTION_RECEIVED,
        'payment_number' => 'UPM-RPT-3', 'payment_date' => '2026-06-15', 'amount' => 500, 'currency' => 'USD',
        'base_currency' => 'USD', 'base_amount' => 500, 'method' => GroupPayment::METHOD_CASH, 'status' => GroupPayment::STATUS_POSTED,
    ]);

    $report = app(TravelReportService::class)->build(
        $fixture['company'], $fixture['user'], 'agent-statement',
        ['start' => '2026-06-01', 'end' => '2026-06-30', 'per_page' => 25, 'agent_id' => $agent->id],
    );

    $summary = collect($report['summary'])->keyBy('label');

    expect($summary['Closing receivable']['value'])->toBe(100.0)
        ->and($summary['Available advances']['value'])->toBe(500.0)
        ->and($summary['Net due']['value'])->toBe(-400.0);
});

test('agent statement treats a reversed allocation as an advance, not an allocated receipt', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Refund Reports Test',
        'slug' => 'refund-reports-test',
        'owner_id' => $owner->id,
        'base_currency' => 'SAR',
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    foreach ([
        ['1000', 'Operating Bank Account', 'asset', 'bank', 'debit'],
        ['1001', 'Cash on Hand', 'asset', 'cash', 'debit'],
        ['1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit'],
        ['2200', 'Agent Advances', 'liability', 'other_current_liability', 'credit'],
    ] as [$code, $name, $type, $subtype, $normal]) {
        Account::create([
            'company_id' => $company->id, 'code' => $code, 'name' => $name, 'type' => $type,
            'subtype' => $subtype, 'normal_balance' => $normal, 'currency' => 'SAR', 'is_active' => true,
        ]);
    }
    $company->forceFill([
        'bank_account_id' => Account::where('company_id', $company->id)->where('code', '1000')->value('id'),
        'ar_account_id' => Account::where('company_id', $company->id)->where('code', '1100')->value('id'),
    ])->save();

    $agent = Agent::create(['company_id' => $company->id, 'agent_number' => 'AGT-REF', 'name' => 'Refund Agent']);
    $group = VisaGroup::create([
        'company_id' => $company->id, 'agent_id' => $agent->id, 'group_number' => 'UGR-REF', 'name' => 'Refund Group',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED, 'travel_date' => '2026-08-01',
        'transport_required' => false, 'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'visa_sale_amount' => 900, 'total_receivable' => 900, 'balance' => 900,
    ]);
    DB::table('umrah.visa_groups')->where('id', $group->id)->update(['created_at' => '2026-08-01 00:00:00']);

    $service = app(UmrahCoreService::class);
    $payment = $service->addPayment($company->id, [
        'direction' => GroupPayment::DIRECTION_RECEIVED, 'agent_id' => $agent->id, 'amount' => 900,
        'currency' => 'SAR', 'payment_date' => '2026-08-05', 'payment_number' => null,
        'method' => GroupPayment::METHOD_BANK_TRANSFER,
    ]);
    $allocation = $service->allocatePayment($payment, ['visa_group_id' => $group->id, 'base_amount' => 900]);

    $filters = ['start' => '2026-08-01', 'end' => '2026-08-31', 'per_page' => 25];
    $before = app(TravelReportService::class)->build($company, $owner, 'agent-statement', $filters);
    $beforeRow = collect($before['rows'])->firstWhere('reference', $payment->payment_number);
    $beforeSummary = collect($before['summary'])->keyBy('label');
    expect($beforeRow['receipt'])->toBe(900.0)
        ->and($beforeRow['advance'])->toBe(0.0)
        ->and($beforeSummary['Available advances']['value'])->toBe(0.0);

    $service->reverseAllocation($allocation, 'Refund settlement de-allocation', $owner->id);

    $after = app(TravelReportService::class)->build($company, $owner, 'agent-statement', $filters);
    $afterRow = collect($after['rows'])->firstWhere('reference', $payment->payment_number);
    $afterSummary = collect($after['summary'])->keyBy('label');

    expect($afterRow['receipt'])->toBe(0.0)
        ->and($afterRow['advance'])->toBe(900.0)
        ->and($afterSummary['Available advances']['value'])->toBe(900.0)
        ->and($afterSummary['Closing receivable']['value'])->toBe(900.0)
        ->and($afterSummary['Net due']['value'])->toBe(0.0);
});

/*
 * The contract test above builds passenger-status too, and passed while this
 * was broken: reportFixture() creates no passengers, so the parent collection
 * is empty and Eloquent skips the eager load entirely. The narrowed load has to
 * run against real rows for a missing column to surface.
 *
 * Both party fields here are appended from an extension's parent -- the agent's
 * customer and the umrah vendor's supplier -- so a narrowed load must carry
 * customer_id / vendor_id, or the name comes back null. Selecting the old
 * column name is worse than null: acct holds it now, umrah does not, and
 * Postgres refuses the query.
 */
test('passenger status names the agent and vendor through the parties they extend', function () {
    $fixture = reportFixture();
    Passenger::create([
        'company_id' => $fixture['company']->id,
        'visa_group_id' => $fixture['group']->id,
        'full_name' => 'Reported Passenger',
        'passport_number' => 'AB1234567',
        'imported_age' => 34,
        'service_type' => Passenger::SERVICE_VISA_TRANSPORT,
        'visa_status' => Passenger::STATUS_APPROVED,
    ]);

    $report = app(TravelReportService::class)->build(
        $fixture['company'], $fixture['user'], 'passenger-status',
        ['start' => '2026-01-01', 'end' => '2026-12-31', 'per_page' => 25],
    );

    $row = collect($report['rows'])->firstWhere('passenger', 'Reported Passenger');

    expect($row)->not->toBeNull()
        ->and($row['agent'])->toBe('Reporting Agent')
        ->and($row['vendor'])->toBe('Visa Supplier');
});
