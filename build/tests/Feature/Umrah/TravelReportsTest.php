<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\PaymentAllocation;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\TravelReportService;
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
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
    ]);
    $transportVendor = VisaVendor::create([
        'company_id' => $company->id, 'vendor_number' => 'TRN-RPT', 'name' => 'Transport Supplier',
        'vendor_type' => VisaVendor::TYPE_TRANSPORT_PROVIDER,
    ]);
    $group = VisaGroup::create([
        'company_id' => $company->id, 'agent_id' => $agent->id, 'vendor_id' => $visaVendor->id,
        'mandatory_transport_vendor_id' => $transportVendor->id, 'group_number' => 'UGR-RPT', 'name' => 'Report Group',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED, 'travel_date' => '2026-06-10', 'passenger_count' => 4,
        'visa_sale_amount' => 1000, 'transport_amount' => 200, 'hotel_amount' => 300, 'discount_amount' => 50,
        'visa_cost_amount' => 600, 'transport_cost_amount' => 100, 'mandatory_transport_cost_amount' => 100,
        'hotel_cost_amount' => 150, 'total_receivable' => 1450, 'total_paid' => 700, 'balance' => 750, 'profit' => 600,
    ]);
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
