<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Umrah\Models\TicketBooking;
use Illuminate\Support\Facades\Bus;

require_once __DIR__.'/TicketingFixtures.php';

/*
 * The first ticket booking on a real company failed with
 *
 *   No active default posting template for TICKET_INVOICE.
 *   Configure posting templates for this company.
 *
 * Twelve ticket test files did not catch it, because every one of them sits on
 * ticketingBookingContext(), which calls ticketingPostingTemplate() and builds
 * by hand the exact rows production was missing. The fixture manufactured the
 * precondition whose absence was the bug.
 *
 * So these tests deliberately do NOT call ticketingPostingTemplate(). They give
 * the company only what a real one has -- the chart of accounts installed by
 * 2026_08_23_000001_add_ticket_accounts_to_existing_companies -- and require
 * that a booking still posts.
 */
function ticketInstallCompany(): object
{
    $f = ticketingCompany();

    // Exactly the accounts the migration puts on every company, and nothing
    // else. No posting templates.
    foreach (['1100', '2000', '2350', '4130', '4140', '4150', '4160', '9900'] as $code) {
        ticketingPostingServiceAccount($f->company, $code);
    }

    $f->company->update([
        'ar_account_id' => Account::where('company_id', $f->company->id)->where('code', '1100')->value('id'),
        'ap_account_id' => Account::where('company_id', $f->company->id)->where('code', '2000')->value('id'),
    ]);

    $customer = ticketingCustomer($f->company);

    return (object) [
        'company' => $f->company->fresh(),
        'user' => $f->user,
        'customer' => $customer,
        'agent' => ticketingAgent($f->company, ['customer_id' => $customer->id]),
        'vendor' => ticketingVendor($f->company),
        'idempotencyKey' => 'install-'.str()->lower(str()->random(12)),
    ];
}

it('posts a booking on a company that has no ticket templates yet', function () {
    $f = ticketInstallCompany();

    expect(PostingTemplate::where('company_id', $f->company->id)->count())->toBe(0);

    $booking = Bus::dispatch(ticketingBookingCommand($f));

    expect($booking)->toBeInstanceOf(TicketBooking::class)
        ->and($booking->invoice->total_amount)->toEqual(96_900.00)
        ->and($booking->bill->total_amount)->toEqual(91_000.00)
        // The clearing account is the whole point of the two-document design:
        // invoice and bill must leave it flat.
        ->and(ticketingAccountBalance($f->company, '2350'))->toBe(0.0);
});

it('installs the four ticket templates as defaults on first posting', function () {
    $f = ticketInstallCompany();

    Bus::dispatch(ticketingBookingCommand($f));

    $installed = PostingTemplate::where('company_id', $f->company->id)
        ->where('is_default', true)
        ->where('is_active', true)
        ->pluck('doc_type')
        ->all();

    expect($installed)->toContain('TICKET_INVOICE')
        ->and($installed)->toContain('TICKET_BILL')
        ->and($installed)->toContain('TICKET_CREDIT_NOTE')
        ->and($installed)->toContain('TICKET_VENDOR_CREDIT');
});

it('maps every role the ticket postings read', function () {
    // TicketPostingService throws on a template that exists but is missing a
    // role, which would turn one confusing error into a different one. The
    // service fee and rounding roles are the easy ones to forget: neither is
    // required by PostingTemplateValidator, and both are read only on some
    // bookings.
    $f = ticketInstallCompany();

    Bus::dispatch(ticketingBookingCommand($f));

    $roles = PostingTemplate::where('company_id', $f->company->id)
        ->where('doc_type', 'TICKET_INVOICE')
        ->where('is_default', true)
        ->firstOrFail()
        ->lines
        ->pluck('role')
        ->all();

    expect($roles)->toContain('AR', 'CLEARING', 'REVENUE', 'SERVICE_FEE', 'DISCOUNT_GIVEN', 'ROUNDING');
});

it('writes no ticket templates for a company with no ticket accounts', function () {
    // A company not running the module has no 2350. Installing a template with
    // no lines for it would replace "no template configured" with the far more
    // confusing "template is missing the CLEARING role mapping".
    $f = ticketingCompany();
    ticketingPostingServiceAccount($f->company, '1100');
    ticketingPostingServiceAccount($f->company, '2000');

    app(\App\Modules\Accounting\Services\PostingTemplateInstaller::class)
        ->ensureDefaults($f->company->fresh());

    $ticketTemplates = PostingTemplate::where('company_id', $f->company->id)
        ->where('doc_type', 'like', 'TICKET_%')
        ->count();

    expect($ticketTemplates)->toBe(0);
});
