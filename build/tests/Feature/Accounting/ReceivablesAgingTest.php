<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\ReceivablesAgingReportService;
use Illuminate\Support\Facades\DB;

/**
 * A total receivable figure says nothing about whether it is collectable: Rs 2m within
 * terms and Rs 2m ninety days overdue are the same number and completely different
 * businesses. Age runs from the DUE date, not the invoice date -- an invoice on 30-day
 * terms is overdue 31 days after it fell due, not 31 days after it was raised.
 */
function agingFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Aging Co', 'slug' => 'aging-co-'.str()->random(8), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $ar = Account::create(['company_id' => $company->id, 'code' => '1100', 'name' => 'AR', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR']);

    $customers = [];
    foreach (['Haulage Ltd', 'City Transport'] as $index => $name) {
        $customers[$name] = Customer::create([
            'company_id' => $company->id, 'customer_number' => 'C-'.($index + 1), 'name' => $name,
            'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'is_active' => true,
        ]);
    }

    return compact('user', 'company', 'customers');
}

function agingInvoice(array $f, string $customer, string $dueDate, float $balance, string $status = 'sent'): Invoice
{
    return Invoice::create([
        'company_id' => $f['company']->id,
        'customer_id' => $f['customers'][$customer]->id,
        'invoice_number' => 'INV-'.str()->random(8),
        'invoice_date' => '2026-06-01',
        'due_date' => $dueDate,
        'status' => $status,
        'currency' => 'PKR', 'base_currency' => 'PKR',
        'subtotal' => $balance, 'total_amount' => $balance,
        'paid_amount' => 0, 'balance' => $balance,
    ]);
}

test('each invoice lands in the bucket its due date earns', function () {
    $f = agingFixture();
    // As at 2026-09-30: not yet due, 15 days over, 45 over, 75 over, 120 over.
    agingInvoice($f, 'Haulage Ltd', '2026-10-15', 1000);
    agingInvoice($f, 'Haulage Ltd', '2026-09-15', 2000);
    agingInvoice($f, 'Haulage Ltd', '2026-08-16', 3000);
    agingInvoice($f, 'Haulage Ltd', '2026-07-17', 4000);
    agingInvoice($f, 'Haulage Ltd', '2026-06-02', 5000);

    $report = app(ReceivablesAgingReportService::class)->run($f['company']->id, '2026-09-30');
    $row = collect($report['rows'])->firstWhere('customer_name', 'Haulage Ltd');

    expect($row['current'])->toBe(1000.0)
        ->and($row['d1_30'])->toBe(2000.0)
        ->and($row['d31_60'])->toBe(3000.0)
        ->and($row['d61_90'])->toBe(4000.0)
        ->and($row['d90_plus'])->toBe(5000.0)
        ->and($row['total'])->toBe(15000.0);
});

test('an invoice due exactly today is not yet overdue', function () {
    $f = agingFixture();
    agingInvoice($f, 'Haulage Ltd', '2026-09-30', 1000);

    $row = collect(app(ReceivablesAgingReportService::class)->run($f['company']->id, '2026-09-30')['rows'])->sole();

    expect($row['current'])->toBe(1000.0)
        ->and($row['d1_30'])->toBe(0.0)
        ->and($row['oldest_days_past_due'])->toBe(0);
});

test('settled, draft and void invoices are not owed and do not appear', function () {
    $f = agingFixture();
    agingInvoice($f, 'Haulage Ltd', '2026-08-01', 5000, 'draft');
    agingInvoice($f, 'Haulage Ltd', '2026-08-01', 6000, 'void');
    $paid = agingInvoice($f, 'Haulage Ltd', '2026-08-01', 7000);
    $paid->update(['paid_amount' => 7000, 'balance' => 0, 'status' => 'paid']);

    $report = app(ReceivablesAgingReportService::class)->run($f['company']->id, '2026-09-30');

    expect($report['rows'])->toBe([])
        ->and($report['totals']['total'])->toBe(0.0)
        ->and($report['customer_count'])->toBe(0);
});

test('the worst debt is listed first', function () {
    $f = agingFixture();
    agingInvoice($f, 'Haulage Ltd', '2026-09-20', 9000);
    agingInvoice($f, 'City Transport', '2026-06-02', 500);

    $rows = app(ReceivablesAgingReportService::class)->run($f['company']->id, '2026-09-30')['rows'];

    // City Transport owes far less but has owed it far longer — that is the row to chase.
    expect($rows[0]['customer_name'])->toBe('City Transport')
        ->and($rows[1]['customer_name'])->toBe('Haulage Ltd');
});

test('totals add up across every buyer and bucket', function () {
    $f = agingFixture();
    agingInvoice($f, 'Haulage Ltd', '2026-10-15', 1000);
    agingInvoice($f, 'Haulage Ltd', '2026-07-17', 4000);
    agingInvoice($f, 'City Transport', '2026-09-15', 2500);

    $report = app(ReceivablesAgingReportService::class)->run($f['company']->id, '2026-09-30');

    expect($report['totals']['current'])->toBe(1000.0)
        ->and($report['totals']['d1_30'])->toBe(2500.0)
        ->and($report['totals']['d61_90'])->toBe(4000.0)
        ->and($report['totals']['total'])->toBe(7500.0)
        ->and($report['customer_count'])->toBe(2);
});

test('an invoice raised after the as-of date is not counted yet', function () {
    $f = agingFixture();
    $future = agingInvoice($f, 'Haulage Ltd', '2026-11-01', 3000);
    $future->update(['invoice_date' => '2026-10-05']);

    $report = app(ReceivablesAgingReportService::class)->run($f['company']->id, '2026-09-30');

    expect($report['totals']['total'])->toBe(0.0);
});
