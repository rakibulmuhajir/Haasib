<?php

use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Payroll\Models\Employee;
use Illuminate\Support\Carbon;

/**
 * Dates bound to an edit form's date input must reach the page as YYYY-MM-DD.
 *
 * A bare 'date' cast serializes as "2026-03-01T00:00:00.000000Z". An <input type="date"> cannot
 * show that, so every one of these edit forms opened with the date blank - which reads as "the
 * date was not saved" - and saving sent the blank straight back. Reported on the bank account
 * form's opening balance date; the same cast sat behind seven edit forms.
 *
 * 'date:Y-m-d' changes only what is sent to the page. PHP still gets a Carbon instance.
 */
test('an edit form date reaches the page as a plain date', function (string $model, string $field) {
    $instance = (new $model)->forceFill([$field => '2026-03-01']);

    expect($instance->toArray()[$field])->toBe('2026-03-01')
        ->and($instance->{$field})->toBeInstanceOf(Carbon::class);
})->with([
    [BankAccount::class, 'opening_balance_date'],
    [BankAccount::class, 'last_reconciled_date'],
    [Bill::class, 'bill_date'],
    [Bill::class, 'due_date'],
    [CreditNote::class, 'credit_date'],
    [FiscalYear::class, 'start_date'],
    [FiscalYear::class, 'end_date'],
    [Payment::class, 'payment_date'],
    [VendorCredit::class, 'credit_date'],
    [Employee::class, 'hire_date'],
    [Employee::class, 'termination_date'],
]);
