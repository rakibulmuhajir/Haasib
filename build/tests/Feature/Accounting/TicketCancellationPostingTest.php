<?php

use App\Models\Company;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Services\TicketPostingService;

/**
 * Fixture reuses ticketPostingServiceCompany()/ticketPostingServiceAccount()/
 * ticketPostingTemplate() from TicketPostingServiceTest.php -- top-level Pest
 * helpers are global across the suite.
 */
function ticketCancellationFixture(): array
{
    $company = ticketPostingServiceCompany();

    ticketPostingTemplate($company, 'TICKET_CREDIT_NOTE', [
        'AR' => '1100',
        'CANCELLATION_ADJUSTMENT' => '4160',
    ]);

    ticketPostingTemplate($company, 'TICKET_VENDOR_CREDIT', [
        'AP' => '2000',
        'CANCELLATION_ADJUSTMENT' => '4160',
    ]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'customer_number' => 'CUST-'.strtoupper(str()->random(6)),
        'name' => 'Cancellation Buyer',
        'base_currency' => 'USD',
    ]);

    $vendor = Vendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VEND-'.strtoupper(str()->random(6)),
        'name' => 'Cancellation Supplier',
        'base_currency' => 'USD',
        'is_active' => true,
    ]);

    $note = CreditNote::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'credit_note_number' => 'CN-'.strtoupper(str()->random(6)),
        'credit_date' => '2026-09-10',
        'amount' => 80_000,
        'currency' => 'USD',
        'base_currency' => 'USD',
        'base_amount' => 80_000,
        'reason' => 'Ticket cancellation',
        'status' => 'draft',
    ]);

    $credit = VendorCredit::create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'credit_number' => 'VC-'.strtoupper(str()->random(6)),
        'credit_date' => '2026-09-10',
        'amount' => 85_000,
        'currency' => 'USD',
        'base_currency' => 'USD',
        'base_amount' => 85_000,
        'reason' => 'Ticket cancellation',
        'status' => 'draft',
    ]);

    return [$company, $note->fresh(), $credit->fresh()];
}

/**
 * The buyer leg debits 4160 and the supplier leg credits it, so the net
 * debit left behind is buyer return minus supplier return -- the
 * cancellation cost, falling out of the ledger rather than being
 * computed beside it.
 */
it('leaves the cancellation cost as a net debit in 4160', function () {
    [$company, $note, $credit] = ticketCancellationFixture();

    $service = app(TicketPostingService::class);
    $service->postTicketCreditNote($note, 80_000);        // given back to the buyer
    $service->postTicketVendorCredit($credit, 85_000);    // got back from the supplier

    // Buyer 80,000 debit less supplier 85,000 credit = 5,000 credit,
    // i.e. the cancellation made 5,000 rather than costing anything.
    expect(ticketAccountBalance($company, '4160'))->toBe(-5_000.0);
});

it('shows a real cost when the buyer gets back more than the supplier returned', function () {
    [$company, $note, $credit] = ticketCancellationFixture();

    $service = app(TicketPostingService::class);
    $service->postTicketCreditNote($note, 85_000);
    $service->postTicketVendorCredit($credit, 80_000);

    expect(ticketAccountBalance($company, '4160'))->toBe(5_000.0);
});

it('posts one leg alone when the other returned nothing', function () {
    [$company, $note] = ticketCancellationFixture();

    app(TicketPostingService::class)->postTicketCreditNote($note, 80_000);

    expect(ticketAccountBalance($company, '4160'))->toBe(80_000.0)
        ->and(ticketAccountBalance($company, '1100'))->toBe(-80_000.0);
});

it('refuses to post a zero or negative buyer return', function () {
    [$company, $note] = ticketCancellationFixture();

    expect(fn () => app(TicketPostingService::class)->postTicketCreditNote($note, 0.0))
        ->toThrow(\RuntimeException::class);
});

it('refuses to post a zero or negative supplier return', function () {
    [$company, $note, $credit] = ticketCancellationFixture();

    expect(fn () => app(TicketPostingService::class)->postTicketVendorCredit($credit, -1.0))
        ->toThrow(\RuntimeException::class);
});
