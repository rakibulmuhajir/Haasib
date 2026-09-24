<?php

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Services\DailyCloseLockService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * Paying a bill doesn't freeze it -- only its day being locked does. A daily
 * close is one of the things that can lock a date, via the DocumentDateLock
 * resolver FuelStationServiceProvider registers (fuel.daily_close_...
 * -> Transaction where transaction_type = 'fuel_daily_close', is_locked =
 * true). This proves the module-lock side end to end: it never touches
 * accounting periods, only the fuel close lock.
 *
 * Relies on creditCloseFixture() from DailyCloseCreditSalesTest.php, which
 * Pest loads when the FuelStation directory is run together (module scope),
 * per project convention (see DailyCloseUnlockAuditTest.php).
 */
test('a bill dated on a locked daily close day cannot be edited', function () {
    $f = creditCloseFixture();
    $posted = creditClosePost($f);
    $close = Transaction::findOrFail($posted['transaction_id']);

    app(DailyCloseLockService::class)->lockTransaction($close, $f['user'], 'month_end');
    expect($close->fresh()->is_locked)->toBeTrue();

    $vendor = Vendor::create([
        'company_id' => $f['company']->id,
        'vendor_number' => 'V-1',
        'name' => 'Fuel Supplier',
        'base_currency' => 'PKR',
        'is_active' => true,
    ]);

    $bill = Bill::create([
        'company_id' => $f['company']->id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'BILL-0001',
        // Same business date the daily close above was posted and locked for.
        'bill_date' => $f['payload']['date'],
        'due_date' => $f['payload']['date'],
        'status' => 'draft',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'subtotal' => 1000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 1000,
        'paid_amount' => 0,
        'balance' => 1000,
        'base_amount' => 1000,
        'created_by_user_id' => $f['user']->id,
    ]);

    $call = fn () => app(CompanyContextService::class)->withContext($f['company'], fn () =>
        app(CommandBus::class)->dispatch('bill.update', [
            'id' => $bill->id,
            'notes' => 'Trying to edit a locked day',
        ], $f['user'], true)
    );

    expect($call)->toThrow(ValidationException::class);

    try {
        $call();
        test()->fail('Expected a ValidationException.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('date');
        expect($e->errors()['date'][0])->toContain('is locked by its daily close');
    }

    expect($bill->fresh()->notes)->toBeNull();
});
