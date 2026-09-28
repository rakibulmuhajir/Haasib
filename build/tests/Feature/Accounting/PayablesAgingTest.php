<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\PayablesAgingReportService;
use Illuminate\Support\Facades\DB;

test('unpaid bills are aged from their due date per supplier', function () {
    $company = Company::create(['name' => 'AP Co', 'slug' => 'ap-'.str()->lower(str()->random(8)), 'base_currency' => 'PKR']);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $vendor = Vendor::create(['company_id' => $company->id, 'vendor_number' => 'V-1', 'name' => 'Total Parco', 'base_currency' => 'PKR', 'is_active' => true]);

    $bill = fn (string $number, string $date, string $due, float $balance, string $status = 'received') => Bill::create([
        'company_id' => $company->id, 'vendor_id' => $vendor->id, 'bill_number' => $number, 'bill_date' => $date, 'due_date' => $due,
        'status' => $status, 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => $balance, 'total_amount' => $balance, 'balance' => $balance,
    ]);
    $bill('B-1', '2026-09-20', '2026-10-05', 100000);   // not yet due
    $bill('B-2', '2026-08-01', '2026-08-15', 50000);    // 44 days past due on 28 Sep
    $bill('B-3', '2026-09-01', '2026-09-01', 7000, 'draft'); // drafts are not owed

    $report = app(PayablesAgingReportService::class)->run($company->id, '2026-09-28');

    expect($report['vendor_count'])->toBe(1)
        ->and($report['rows'][0]['current'])->toBe(100000.0)
        ->and($report['rows'][0]['d31_60'])->toBe(50000.0)
        ->and($report['totals']['total'])->toBe(150000.0);
});
