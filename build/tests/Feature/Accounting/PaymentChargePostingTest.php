<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

test('a received payment charge reduces bank movement while leaving AR gross', function () {
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Payment Charge AR Test',
        'slug' => 'payment-charge-ar-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

    enterCompany($company);

    foreach ([['PKR', 'Pakistani Rupee', '₨']] as [$code, $name, $symbol]) {
        DB::table('public.currencies')->insertOrIgnore(['code' => $code, 'name' => $name, 'symbol' => $symbol]);
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
        'name' => 'September 2026',
        'period_number' => 9,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]);

    $ar = Account::create([
        'company_id' => $company->id,
        'code' => '1100',
        'name' => 'Accounts Receivable',
        'type' => 'asset',
        'subtype' => 'accounts_receivable',
        'normal_balance' => 'debit',
        'currency' => 'PKR',
    ]);
    $bank = Account::create([
        'company_id' => $company->id,
        'code' => '1000',
        'name' => 'Operating Bank',
        'type' => 'asset',
        'subtype' => 'bank',
        'normal_balance' => 'debit',
        'currency' => 'PKR',
    ]);
    $expense = Account::create([
        'company_id' => $company->id,
        'code' => '6500',
        'name' => 'Transaction Charges',
        'type' => 'expense',
        'subtype' => 'expense',
        'normal_balance' => 'debit',
        'currency' => null,
    ]);
    $company->update([
        'ar_account_id' => $ar->id,
        'expense_account_id' => $expense->id,
    ]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'customer_number' => 'CUST-'.str()->upper(str()->random(6)),
        'name' => 'Charge Test Customer',
        'base_currency' => 'PKR',
        'ar_account_id' => $ar->id,
        'is_active' => true,
    ]);
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-CHARGE-0001',
        'invoice_date' => '2026-09-10',
        'due_date' => '2026-09-30',
        'status' => 'sent',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'subtotal' => 500,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 500,
        'paid_amount' => 0,
        'balance' => 500,
        'base_amount' => 500,
    ]);

    $result = app(CompanyContextService::class)->withContext($company, function () use ($invoice, $bank, $user) {
        return app(CommandBus::class)->dispatch('payment.create', [
            'invoice' => $invoice->id,
            'amount' => 500,
            'transaction_charge' => 25,
            'method' => 'bank_transfer',
            'currency' => 'PKR',
            'date' => '2026-09-15',
            'deposit_account_id' => $bank->id,
        ], $user, true);
    });

    $payment = Payment::find($result['data']['id']);
    $entries = Transaction::find($payment->transaction_id)->journalEntries;

    expect((float) $entries->sum('debit_amount'))->toBe(500.0)
        ->and((float) $entries->sum('credit_amount'))->toBe(500.0)
        ->and((float) $entries->where('account_id', $bank->id)->sum('debit_amount'))->toBe(475.0)
        ->and((float) $entries->where('account_id', $expense->id)->sum('debit_amount'))->toBe(25.0)
        ->and((float) $entries->where('account_id', $ar->id)->sum('credit_amount'))->toBe(500.0)
        ->and((float) $payment->transaction_charge)->toBe(25.0)
        ->and((float) $payment->base_transaction_charge)->toBe(25.0)
        ->and((float) $invoice->fresh()->balance)->toBe(0.0);
});
