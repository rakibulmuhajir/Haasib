<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\InvoiceLineItem;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\TicketPostingService;
use App\Modules\Accounting\Services\TicketSaleAmounts;
use Illuminate\Support\Facades\DB;

/**
 * Fixture style follows billPaymentTestFixture() in BillPaymentPostingTest.php:
 * a bare Company::create() (Company::factory() does not exist), a FiscalYear
 * and an open AccountingPeriod covering the test dates (posting is refused
 * outside an open period), and accounts built by hand since a bare company
 * gets no chart of accounts.
 */
function ticketPostingServiceAccount(Company $company, string $code): Account
{
    return Account::where('company_id', $company->id)->where('code', $code)->first()
        ?? match ($code) {
            '1100' => Account::create([
                'company_id' => $company->id,
                'code' => '1100',
                'name' => 'Accounts Receivable',
                'type' => 'asset',
                'subtype' => 'accounts_receivable',
                'normal_balance' => 'debit',
            ]),
            '2000' => Account::create([
                'company_id' => $company->id,
                'code' => '2000',
                'name' => 'Accounts Payable',
                'type' => 'liability',
                'subtype' => 'accounts_payable',
                'normal_balance' => 'credit',
            ]),
            '2350' => Account::create([
                'company_id' => $company->id,
                'code' => '2350',
                'name' => 'Ticket Supplier Clearing',
                'type' => 'liability',
                'subtype' => 'other_current_liability',
                'normal_balance' => 'credit',
                'currency' => $company->base_currency,
            ]),
            '4130' => Account::create([
                'company_id' => $company->id,
                'code' => '4130',
                'name' => 'Ticket Commission Revenue',
                'type' => 'revenue',
                'subtype' => 'revenue',
                'normal_balance' => 'credit',
            ]),
            '4140' => Account::create([
                'company_id' => $company->id,
                'code' => '4140',
                'name' => 'Ticket Service Fee Revenue',
                'type' => 'revenue',
                'subtype' => 'revenue',
                'normal_balance' => 'credit',
            ]),
            '4150' => Account::create([
                'company_id' => $company->id,
                'code' => '4150',
                'name' => 'Ticket Discount',
                'type' => 'revenue',
                'subtype' => 'revenue',
                'normal_balance' => 'debit',
                'is_contra' => true,
            ]),
            '9900' => Account::create([
                'company_id' => $company->id,
                'code' => '9900',
                'name' => 'Rounding Differences',
                'type' => 'expense',
                'subtype' => 'expense',
                'normal_balance' => 'debit',
            ]),
            default => throw new \RuntimeException("no fixture for account {$code}"),
        };
}

function ticketPostingServiceCompany(): Company
{
    $user = User::factory()->create();

    $company = Company::create([
        'name' => 'Ticket Posting Service Co '.str()->random(8),
        'slug' => 'ticket-posting-service-'.str()->lower(str()->random(10)),
        'owner_id' => $user->id,
        'base_currency' => 'USD',
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
        'name' => 'Sep 2026',
        'period_number' => 9,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]);

    return $company;
}

function ticketPostingTemplate(Company $company, string $docType, array $roles): PostingTemplate
{
    $existing = PostingTemplate::where('company_id', $company->id)->where('doc_type', $docType)->first();
    if ($existing) {
        return $existing;
    }

    $template = PostingTemplate::create([
        'company_id' => $company->id,
        'doc_type' => $docType,
        'name' => $docType,
        'is_active' => true,
        'is_default' => true,
        'effective_from' => '2026-01-01',
        'version' => 1,
    ]);

    foreach ($roles as $role => $code) {
        PostingTemplateLine::create([
            'template_id' => $template->id,
            'role' => $role,
            'account_id' => ticketPostingServiceAccount($company, $code)->id,
        ]);
    }

    return $template->fresh(['lines']);
}

function ticketInvoiceFixture(float $saleTotal, float $discount): array
{
    $company = ticketPostingServiceCompany();

    ticketPostingTemplate($company, 'TICKET_INVOICE', [
        'AR' => '1100',
        'CLEARING' => '2350',
        'REVENUE' => '4130',
        'SERVICE_FEE' => '4140',
        'DISCOUNT_GIVEN' => '4150',
        'ROUNDING' => '9900',
    ]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'customer_number' => 'CUST-'.strtoupper(str()->random(6)),
        'name' => 'Test Buyer',
        'base_currency' => 'USD',
    ]);

    $lineTotal = round($saleTotal + $discount, 2);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'TICK-'.strtoupper(str()->random(6)),
        'invoice_date' => '2026-09-05',
        'due_date' => '2026-09-20',
        'status' => 'draft',
        'currency' => 'USD',
        'base_currency' => 'USD',
        'exchange_rate' => 1,
        'subtotal' => $lineTotal,
        'tax_amount' => 0,
        'discount_amount' => $discount,
        'total_amount' => $saleTotal,
        'paid_amount' => 0,
        'balance' => $saleTotal,
        'base_amount' => $saleTotal,
    ]);

    InvoiceLineItem::create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'line_number' => 1,
        'description' => 'Air ticket',
        'quantity' => 1,
        'unit_price' => $lineTotal,
        'tax_rate' => 0,
        'discount_rate' => 0,
        'line_total' => $lineTotal,
        'tax_amount' => 0,
        'total' => $lineTotal,
    ]);

    return [$company, $invoice->fresh()];
}

function ticketBillFixture(Company $company, float $supplierCostBase): Bill
{
    ticketPostingTemplate($company, 'TICKET_BILL', [
        'AP' => '2000',
        'CLEARING' => '2350',
    ]);

    $vendor = Vendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VEND-'.strtoupper(str()->random(6)),
        'name' => 'Test Supplier',
        'base_currency' => 'USD',
        'is_active' => true,
    ]);

    $bill = Bill::create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'TICKBILL-'.strtoupper(str()->random(6)),
        'bill_date' => '2026-09-05',
        'due_date' => '2026-09-20',
        'status' => 'received',
        'currency' => 'USD',
        'base_currency' => 'USD',
        'exchange_rate' => 1,
        'subtotal' => $supplierCostBase,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => $supplierCostBase,
        'paid_amount' => 0,
        'balance' => $supplierCostBase,
        'base_amount' => $supplierCostBase,
    ]);

    BillLineItem::create([
        'company_id' => $company->id,
        'bill_id' => $bill->id,
        'line_number' => 1,
        'description' => 'Supplier cost',
        'quantity' => 1,
        'unit_price' => $supplierCostBase,
        'tax_rate' => 0,
        'discount_rate' => 0,
        'line_total' => $supplierCostBase,
        'tax_amount' => 0,
        'total' => $supplierCostBase,
    ]);

    return $bill->fresh();
}

function ticketInvoiceAndBillFixture(): array
{
    [$company, $invoice] = ticketInvoiceFixture(saleTotal: 96_900, discount: 2_000);
    $bill = ticketBillFixture($company, 91_000);

    return [$company, $invoice, $bill];
}

/**
 * Debit minus credit for an account across one transaction's journal lines.
 * A credit balance reads negative.
 */
function ticketBalance(Transaction $transaction, string $code): float
{
    $account = Account::where('company_id', $transaction->company_id)->where('code', $code)->first();
    if (! $account) {
        return 0.0;
    }

    $entries = $transaction->journalEntries()->where('account_id', $account->id)->get();

    return round((float) $entries->sum('debit_amount') - (float) $entries->sum('credit_amount'), 2);
}

/**
 * Debit minus credit for an account across every transaction for the company.
 */
function ticketAccountBalance(Company $company, string $code): float
{
    $account = Account::where('company_id', $company->id)->where('code', $code)->first();
    if (! $account) {
        return 0.0;
    }

    $debit = (float) DB::table('acct.journal_entries')->where('account_id', $account->id)->sum('debit_amount');
    $credit = (float) DB::table('acct.journal_entries')->where('account_id', $account->id)->sum('credit_amount');

    return round($debit - $credit, 2);
}

/**
 * The worked example from the spec: gross fare 85,000, taxes 12,400,
 * supplier cost 91,000, discount 2,000, service fee 1,500. The buyer
 * pays 96,900 and the company keeps 5,900.
 */
it('posts the spec worked example and leaves clearing at zero', function () {
    [$company, $invoice] = ticketInvoiceFixture(saleTotal: 96_900, discount: 2_000);

    $transaction = app(TicketPostingService::class)->postTicketInvoice(
        $invoice,
        new TicketSaleAmounts(
            supplierCostBase: 91_000,
            commissionBase: 6_400,
            serviceFeeBase: 1_500,
            discountBase: 2_000,
        ),
    );

    expect(ticketBalance($transaction, '1100'))->toBe(96_900.0)   // Dr AR
        ->and(ticketBalance($transaction, '4150'))->toBe(2_000.0)  // Dr discount
        ->and(ticketBalance($transaction, '2350'))->toBe(-91_000.0) // Cr clearing
        ->and(ticketBalance($transaction, '4130'))->toBe(-6_400.0)  // Cr commission
        ->and(ticketBalance($transaction, '4140'))->toBe(-1_500.0); // Cr fee
});

it('returns clearing to exactly zero once the bill posts', function () {
    [$company, $invoice, $bill] = ticketInvoiceAndBillFixture();

    $service = app(TicketPostingService::class);
    $service->postTicketInvoice($invoice, new TicketSaleAmounts(91_000, 6_400, 1_500, 2_000));
    $service->postTicketBill($bill, 91_000);

    expect(ticketAccountBalance($company, '2350'))->toBe(0.0);
});

it('posts no discount entry when nothing was given away', function () {
    [$company, $invoice] = ticketInvoiceFixture(saleTotal: 98_900, discount: 0);

    $transaction = app(TicketPostingService::class)->postTicketInvoice(
        $invoice,
        new TicketSaleAmounts(91_000, 6_400, 1_500, 0),
    );

    expect(ticketBalance($transaction, '4150'))->toBe(0.0);
});

it('refuses to post when the amounts do not balance', function () {
    [$company, $invoice] = ticketInvoiceFixture(saleTotal: 96_900, discount: 2_000);

    // Commission is wrong by 100, so the entry cannot balance.
    expect(fn () => app(TicketPostingService::class)->postTicketInvoice(
        $invoice,
        new TicketSaleAmounts(91_000, 6_500, 1_500, 2_000),
    ))->toThrow(\RuntimeException::class);
});
