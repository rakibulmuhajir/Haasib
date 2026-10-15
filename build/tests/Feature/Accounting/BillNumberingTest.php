<?php

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Services\BillNumberService;

require_once __DIR__.'/BillLineTotalFixtures.php';

function numberingBill(array $f, string $number): Bill
{
    return Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id,
        'bill_number' => $number, 'bill_date' => '2026-09-10', 'due_date' => '2026-09-10',
        'status' => 'draft', 'currency' => 'PKR', 'base_currency' => 'PKR',
        'subtotal' => 100, 'total_amount' => 100, 'balance' => 100,
    ]);
}

test('the first bill honours the prefix and start number saved in onboarding', function () {
    $f = billLineTotalFixture();
    $f['company']->update(['bill_prefix' => 'PUR-', 'bill_start_number' => 1001]);

    expect(app(BillNumberService::class)->next($f['company']->id))->toBe('PUR-01001');
});

test('later bills carry on from the highest number and existing bills keep theirs', function () {
    $f = billLineTotalFixture();
    numberingBill($f, 'BILL-00005');
    $f['company']->update(['bill_prefix' => 'BILL-', 'bill_start_number' => 1001]);

    expect(app(BillNumberService::class)->next($f['company']->id))->toBe('BILL-00006')
        ->and(Bill::where('bill_number', 'BILL-00005')->exists())->toBeTrue();
});
