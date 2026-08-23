<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use App\Modules\Accounting\Services\PostingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function creditNoteCustomer(Company $company): Customer
{
    return Customer::create([
        'company_id' => $company->id,
        'customer_number' => 'CUST-'.strtoupper(str()->random(6)),
        'name' => 'Credit Note Buyer',
        'base_currency' => $company->base_currency,
    ]);
}

function creditNoteCompany(string $baseCurrency = 'PKR'): Company
{
    $user = User::factory()->create();

    return Company::create([
        'name' => 'Credit Note Currency Co '.str()->random(8),
        'slug' => 'credit-note-currency-'.str()->lower(str()->random(10)),
        'owner_id' => $user->id,
        'base_currency' => $baseCurrency,
    ]);
}

it('records a credit note in a foreign currency with its rate', function () {
    $company = creditNoteCompany('PKR');

    $note = CreditNote::create([
        'company_id' => $company->id,
        'customer_id' => creditNoteCustomer($company)->id,
        'credit_note_number' => 'CN-TEST-1',
        'credit_date' => '2026-09-01',
        'amount' => 325.00,
        'currency' => 'USD',
        'exchange_rate' => 280.00000000,
        'base_currency' => 'PKR',
        'base_amount' => 91_000.00,
        'reason' => 'Ticket cancellation',
        'status' => 'draft',
    ]);

    expect((float) $note->fresh()->base_amount)->toEqual(91_000.00);
});

it('refuses a base-amount that does not match the rate', function () {
    $company = creditNoteCompany('PKR');

    expect(fn () => CreditNote::create([
        'company_id' => $company->id,
        'customer_id' => creditNoteCustomer($company)->id,
        'credit_note_number' => 'CN-TEST-2',
        'credit_date' => '2026-09-01',
        'amount' => 325.00,
        'currency' => 'USD',
        'exchange_rate' => 280.00000000,
        'base_currency' => 'PKR',
        'base_amount' => 1.00,            // wrong on purpose
        'reason' => 'Ticket cancellation',
        'status' => 'draft',
    ]))->toThrow(QueryException::class);
});

/**
 * A bare Company::create() seeds no chart of accounts, fiscal year, or
 * accounting period, and a posting outside an open period is refused --
 * same setup shape as ticketPostingServiceCompany() in
 * TicketPostingServiceTest.php, but building an AR_CREDIT_NOTE template
 * for PostingService (not TicketPostingService) instead.
 */
function creditNotePostingCompany(): Company
{
    $company = creditNoteCompany('PKR');

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

    $ar = Account::create([
        'company_id' => $company->id,
        'code' => '1100',
        'name' => 'Accounts Receivable',
        'type' => 'asset',
        'subtype' => 'accounts_receivable',
        'normal_balance' => 'debit',
    ]);

    $revenue = Account::create([
        'company_id' => $company->id,
        'code' => '4000',
        'name' => 'Sales Revenue',
        'type' => 'revenue',
        'subtype' => 'revenue',
        'normal_balance' => 'credit',
    ]);

    $template = PostingTemplate::create([
        'company_id' => $company->id,
        'doc_type' => 'AR_CREDIT_NOTE',
        'name' => 'AR_CREDIT_NOTE',
        'is_active' => true,
        'is_default' => true,
        'effective_from' => '2026-01-01',
        'version' => 1,
    ]);

    PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'AR', 'account_id' => $ar->id]);
    PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'REVENUE', 'account_id' => $revenue->id]);

    return $company;
}

it('posts a foreign-currency credit note with its own currency, rate, and base amount', function () {
    $company = creditNotePostingCompany();

    $note = CreditNote::create([
        'company_id' => $company->id,
        'customer_id' => creditNoteCustomer($company)->id,
        'credit_note_number' => 'CN-TEST-FX',
        'credit_date' => '2026-09-10',
        'amount' => 100.00,
        'currency' => 'USD',
        'exchange_rate' => 280.00000000,
        'base_currency' => 'PKR',
        'base_amount' => 28_000.00,
        'reason' => 'Ticket cancellation',
        'status' => 'draft',
    ]);

    $transaction = app(PostingService::class)->postCreditNote($note->fresh());

    expect($transaction->currency)->toBe('USD')
        ->and($transaction->base_currency)->toBe('PKR')
        ->and((float) $transaction->exchange_rate)->toBe(280.0)
        ->and((float) $transaction->total_debit)->toBe(28_000.0)
        ->and((float) $transaction->total_credit)->toBe(28_000.0);

    $entries = DB::table('acct.journal_entries')->where('transaction_id', $transaction->id)->get();

    $arEntry = $entries->firstWhere('credit_amount', '>', 0);
    expect((float) $arEntry->credit_amount)->toBe(28_000.0)
        ->and((float) $arEntry->currency_credit)->toBe(100.0);

    $revenueEntry = $entries->firstWhere('debit_amount', '>', 0);
    expect((float) $revenueEntry->debit_amount)->toBe(28_000.0)
        ->and((float) $revenueEntry->currency_debit)->toBe(100.0);
});

it('keeps existing base-currency credit notes valid', function () {
    $company = creditNoteCompany('PKR');

    $note = CreditNote::create([
        'company_id' => $company->id,
        'customer_id' => creditNoteCustomer($company)->id,
        'credit_note_number' => 'CN-TEST-3',
        'credit_date' => '2026-09-01',
        'amount' => 5_000.00,
        'currency' => 'PKR',
        'exchange_rate' => null,
        'base_currency' => 'PKR',
        'base_amount' => 5_000.00,
        'reason' => 'Goodwill',
        'status' => 'draft',
    ]);

    expect($note->fresh()->exchange_rate)->toBeNull();
});
