<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\CompanyTaxSettings;
use App\Modules\Accounting\Models\Jurisdiction;
use App\Modules\Accounting\Models\TaxRate;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;

require_once __DIR__.'/BillPaymentEditFixtures.php';

/** Turn the company-wide "Charge tax" switch on or off, as the Tax page would. */
function whtTax(Company $company, bool $on): void
{
    CompanyTaxSettings::where('company_id', $company->id)->delete();
    $row = new CompanyTaxSettings(['company_id' => $company->id]);
    $row->saveDeliberately(['tax_enabled' => $on]);
}

/** A withholding rate the way the owner would enter it. Test numbers only. */
function whtRate(Company $company, float $filer = 4, ?float $nonFiler = null, string $appliesTo = 'both', string $code = 'WHT-153B'): TaxRate
{
    return TaxRate::create([
        'company_id' => $company->id,
        'jurisdiction_id' => Jurisdiction::query()->value('id'),
        'code' => $code,
        'name' => '153(1)(b) Services',
        'rate' => $filer,
        'rate_non_filer' => $nonFiler,
        'tax_type' => 'withholding',
        'section' => '153(1)(b)',
        'applies_to' => $appliesTo,
        'effective_from' => '2026-07-01',
    ]);
}

function whtBill(array $f, float $total, string $number = 'BILL-WHT-1'): Bill
{
    return Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id, 'bill_number' => $number,
        'bill_date' => '2026-08-05', 'due_date' => '2026-09-05', 'status' => 'received',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => $total, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => $total,
        'paid_amount' => 0, 'balance' => $total, 'base_amount' => $total, 'created_by_user_id' => $f['user']->id,
    ]);
}

function whtPayBill(array $f, Bill $bill, float $amount, ?string $rateId, array $extra = []): BillPayment
{
    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.create', [
        'vendor_id' => $f['vendor']->id, 'payment_date' => '2026-09-01', 'amount' => $amount,
        'currency' => 'PKR', 'base_currency' => 'PKR', 'payment_method' => 'bank_transfer',
        'payment_account_id' => $f['bank']->id,
        'allocations' => [['bill_id' => $bill->id, 'amount_allocated' => $amount]],
        ...($rateId ? ['wht_rate_id' => $rateId] : []),
        ...$extra,
    ], $f['user'], true));

    return BillPayment::find($result['data']['id']);
}

/** What one transaction put on one side of one account. */
function whtLine(string $transactionId, string $accountId, string $side): float
{
    return (float) Transaction::find($transactionId)->journalEntries->where('account_id', $accountId)->sum($side === 'debit' ? 'debit_amount' : 'credit_amount');
}
