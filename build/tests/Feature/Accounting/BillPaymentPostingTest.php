<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

function billPaymentTestFixture(?string $vendorApAccountId, ?string $companyApAccountId): array
{
    $user = User::factory()->create();

    $company = Company::create([
        'name' => 'Bill Payment Posting Test',
        'slug' => 'bill-payment-posting-test-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'USD',
        'ap_account_id' => $companyApAccountId,
    ]);

    if (! DB::table('public.currencies')->where('code', 'USD')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    }

    $fy = FiscalYear::create([
        'company_id' => $company->id,
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);

    AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'name' => 'Aug 2026',
        'period_number' => 8,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
    ]);

    $bankAccount = Account::create([
        'company_id' => $company->id,
        'code' => '1000',
        'name' => 'Checking Account',
        'type' => 'asset',
        'subtype' => 'bank',
        'normal_balance' => 'debit',
        'currency' => 'USD',
    ]);

    $apAccount = null;
    if ($vendorApAccountId !== 'skip') {
        $apAccount = Account::create([
            'company_id' => $company->id,
            'code' => '2000',
            'name' => 'Accounts Payable',
            'type' => 'liability',
            'subtype' => 'accounts_payable',
            'normal_balance' => 'credit',
            'currency' => 'USD',
        ]);
    }

    $vendor = Vendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VEND-0001',
        'name' => 'Acme Supplies',
        'base_currency' => 'USD',
        'is_active' => true,
        'ap_account_id' => $vendorApAccountId === 'skip' ? null : $apAccount->id,
        'created_by_user_id' => $user->id,
    ]);

    $bill = Bill::create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'BILL-0001',
        'bill_date' => '2026-08-10',
        'due_date' => '2026-09-10',
        'status' => 'received',
        'currency' => 'USD',
        'base_currency' => 'USD',
        'exchange_rate' => 1,
        'subtotal' => 500,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 500,
        'paid_amount' => 0,
        'balance' => 500,
        'base_amount' => 500,
        'created_by_user_id' => $user->id,
    ]);

    return compact('company', 'user', 'bankAccount', 'apAccount', 'vendor', 'bill');
}

test('recording a bill payment posts a balanced GL transaction debiting AP and crediting the payment account', function () {
    $fixture = billPaymentTestFixture(null, null);
    $company = $fixture['company'];
    $user = $fixture['user'];
    $bankAccount = $fixture['bankAccount'];
    $apAccount = $fixture['apAccount'];
    $vendor = $fixture['vendor'];
    $bill = $fixture['bill'];

    $result = app(CompanyContextService::class)->withContext($company, function () use ($company, $user, $bankAccount, $vendor, $bill) {
        return app(CommandBus::class)->dispatch('bill_payment.create', [
            'vendor_id' => $vendor->id,
            'payment_date' => '2026-08-15',
            'amount' => 500,
            'currency' => 'USD',
            'base_currency' => 'USD',
            'payment_method' => 'bank_transfer',
            'payment_account_id' => $bankAccount->id,
            'allocations' => [
                ['bill_id' => $bill->id, 'amount_allocated' => 500],
            ],
        ], $user, true);
    });

    $paymentId = $result['data']['id'];
    $payment = BillPayment::find($paymentId);

    expect($payment->transaction_id)->not->toBeNull();

    $transaction = Transaction::find($payment->transaction_id);
    expect($transaction)->not->toBeNull();

    $entries = $transaction->journalEntries;
    $totalDebit = (float) $entries->sum('debit_amount');
    $totalCredit = (float) $entries->sum('credit_amount');
    expect($totalDebit)->toBe($totalCredit)
        ->and($totalDebit)->toBe(500.0);

    $apEntry = $entries->where('account_id', $apAccount->id)->first();
    $bankEntry = $entries->where('account_id', $bankAccount->id)->first();

    expect((float) $apEntry->debit_amount)->toBe(500.0)
        ->and((float) $apEntry->credit_amount)->toBe(0.0)
        ->and((float) $bankEntry->credit_amount)->toBe(500.0)
        ->and((float) $bankEntry->debit_amount)->toBe(0.0);

    $bill->refresh();
    expect($bill->status)->toBe('paid')
        ->and((float) $bill->paid_amount)->toBe(500.0)
        ->and((float) $bill->balance)->toBe(0.0);
});

test('recording a bill payment throws when neither the vendor nor the company has an AP account', function () {
    $fixture = billPaymentTestFixture('skip', null);
    $company = $fixture['company'];
    $user = $fixture['user'];
    $bankAccount = $fixture['bankAccount'];
    $vendor = $fixture['vendor'];
    $bill = $fixture['bill'];

    $call = function () use ($company, $user, $bankAccount, $vendor, $bill) {
        app(CompanyContextService::class)->withContext($company, function () use ($company, $user, $bankAccount, $vendor, $bill) {
            return app(CommandBus::class)->dispatch('bill_payment.create', [
                'vendor_id' => $vendor->id,
                'payment_date' => '2026-08-15',
                'amount' => 500,
                'currency' => 'USD',
                'base_currency' => 'USD',
                'payment_method' => 'bank_transfer',
                'payment_account_id' => $bankAccount->id,
                'allocations' => [
                    ['bill_id' => $bill->id, 'amount_allocated' => 500],
                ],
            ], $user, true);
        });
    };

    expect($call)->toThrow(\RuntimeException::class, 'AP account is required to post the bill payment.');
});
