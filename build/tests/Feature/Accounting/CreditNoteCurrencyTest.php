<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Customer;
use Illuminate\Database\QueryException;

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
