<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Umrah\Commands\CreateTicketBooking;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Ticket;
use App\Modules\Umrah\Models\TicketBooking;
use App\Modules\Umrah\Models\TicketCancellation;
use Illuminate\Support\Facades\DB;

/**
 * Shared fixture builders for the ticketing (umrah) domain tests. Every
 * name here is prefixed `ticketing` because Pest top-level helpers are
 * global across the whole suite -- an unprefixed name would collide with
 * an existing accounting-domain helper rather than shadow it.
 *
 * Copies the shape of billPaymentTestFixture() in
 * tests/Feature/Accounting/BillPaymentPostingTest.php: company creation
 * seeds nothing, so every fixture that will post must also open a fiscal
 * year and an accounting period covering the dates this plan uses
 * (September 2026).
 */
function ticketingCompany(array $overrides = []): object
{
    $user = User::factory()->create();

    $company = Company::create(array_merge([
        'name' => 'Ticketing Test',
        'slug' => 'ticketing-test-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ], $overrides));

    if (! DB::table('public.currencies')->where('code', $company->base_currency)->exists()) {
        DB::table('public.currencies')->insert([
            'code' => $company->base_currency,
            'name' => 'Pakistan Rupee',
            'symbol' => 'Rs',
        ]);
    }

    // Postings are refused outside an open period. Every test in this
    // plan posts, so every fixture needs both of these.
    $fiscalYear = FiscalYear::create([
        'company_id' => $company->id,
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);

    AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'name' => 'Sep 2026',
        'period_number' => 9,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]);

    return (object) [
        'company' => $company,
        'user' => $user,
        'fiscalYear' => $fiscalYear,
    ];
}

function ticketingCustomer(Company $company, array $overrides = []): Customer
{
    return Customer::create(array_merge([
        'company_id' => $company->id,
        'customer_number' => 'CUST-'.str()->upper(str()->random(8)),
        'name' => 'Test Customer',
        'base_currency' => $company->base_currency,
    ], $overrides));
}

function ticketingAgent(Company $company, array $overrides = []): Agent
{
    return Agent::create(array_merge([
        'company_id' => $company->id,
        'agent_number' => 'AGT-'.str()->upper(str()->random(8)),
        'name' => 'Test Agent',
    ], $overrides));
}

function ticketingVendor(Company $company, array $overrides = []): Vendor
{
    return Vendor::create(array_merge([
        'company_id' => $company->id,
        'vendor_number' => 'VEND-'.str()->upper(str()->random(8)),
        'name' => 'Test Airline',
        'base_currency' => $company->base_currency,
        'is_active' => true,
    ], $overrides));
}

/**
 * A bare, unposted invoice -- just enough to satisfy the booking
 * table's foreign key and its own NOT NULL columns. Tasks 5 and 6
 * post real amounts through TicketPostingService; here we only need
 * a row that exists.
 */
function ticketingInvoice(Company $company, Customer $customer, array $overrides = []): Invoice
{
    return Invoice::create(array_merge([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'TICK-'.str()->upper(str()->random(8)),
        'invoice_date' => '2026-09-01',
        'due_date' => '2026-09-15',
        'status' => 'draft',
        'currency' => $company->base_currency,
        'base_currency' => $company->base_currency,
        'exchange_rate' => 1,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
        'balance' => 0,
        'base_amount' => 0,
    ], $overrides));
}

/**
 * A bare, unposted bill -- see ticketingInvoice().
 */
function ticketingBill(Company $company, Vendor $vendor, array $overrides = []): Bill
{
    return Bill::create(array_merge([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'TICKBILL-'.str()->upper(str()->random(8)),
        'bill_date' => '2026-09-01',
        'due_date' => '2026-09-15',
        'status' => 'received',
        'currency' => $company->base_currency,
        'base_currency' => $company->base_currency,
        'exchange_rate' => 1,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
        'balance' => 0,
        'base_amount' => 0,
    ], $overrides));
}

/**
 * Everything TicketBookingTableTest needs: a company with an open
 * period, a buyer, an agent linked to that buyer, a supplier vendor,
 * and two independent invoice/bill pairs so tests can prove the
 * unique-per-document constraints without reusing a row that is
 * already claimed.
 */
function ticketingBookingFixture(array $overrides = []): object
{
    $f = ticketingCompany();
    $customer = ticketingCustomer($f->company);
    $agent = ticketingAgent($f->company, ['customer_id' => $customer->id]);
    $vendor = ticketingVendor($f->company);

    return (object) array_merge([
        'company' => $f->company,
        'user' => $f->user,
        'customer' => $customer,
        'agent' => $agent,
        'vendor' => $vendor,
        'invoice' => ticketingInvoice($f->company, $customer),
        'bill' => ticketingBill($f->company, $vendor),
        'secondInvoice' => ticketingInvoice($f->company, $customer),
        'secondBill' => ticketingBill($f->company, $vendor),
    ], $overrides);
}

/**
 * The full attribute array for TicketBooking::create(), so each test
 * only needs to override the one column it is exercising.
 */
function ticketingBookingAttributes(object $f, array $overrides = []): array
{
    return array_merge([
        'company_id' => $f->company->id,
        'customer_id' => $f->customer->id,
        'agent_id' => $f->agent->id,
        'supplier_vendor_id' => $f->vendor->id,
        'invoice_id' => $f->invoice->id,
        'bill_id' => $f->bill->id,
        'booking_reference' => 'TB-00001',
        'pnr' => 'X4K9QZ',
        'booking_date' => '2026-09-01',
        'status' => 'confirmed',
        'idempotency_key' => 'test-key-1',
    ], $overrides);
}

/**
 * A fresh company + booking pair, created new on every call. Pass the
 * same context object to two ticketingTicket() calls when a test wants
 * both tickets to collide on something scoped to that company/booking
 * (e.g. the airline-ticket-number uniqueness tests); pass none, or two
 * distinct contexts, when a test wants isolation instead. Nothing here
 * is shared implicitly -- the call site says what it wants.
 */
function ticketingTicketContext(): object
{
    $f = ticketingBookingFixture();
    $booking = TicketBooking::create(ticketingBookingAttributes($f));

    return (object) ['company' => $f->company, 'booking' => $booking];
}

/**
 * A single ticket. Given no context, builds a fresh company + booking
 * for this ticket alone. Given a context (from ticketingTicketContext()
 * or a previous call's return), attaches to that company + booking
 * instead -- the caller decides whether tickets share a company, not an
 * accident of static state.
 */
function ticketingTicket(?object $context = null, array $overrides = []): Ticket
{
    $context ??= ticketingTicketContext();

    return Ticket::create(array_merge([
        'company_id' => $context->company->id,
        'ticket_booking_id' => $context->booking->id,
        'ticket_number' => 'TKT-'.str()->upper(str()->random(10)),
        'airline_ticket_number' => '214-'.random_int(1000000000, 9999999999),
        'passenger_name' => 'Test Passenger',
        'airline' => 'PIA',
        'route' => 'KHI-JED',
        'travel_date' => '2026-09-10',
        'sale_currency' => $context->company->base_currency,
        'gross_fare' => 85_000,
        'taxes' => 0,
        'discount' => 0,
        'service_fee' => 0,
        'gross_fare_base' => 85_000,
        'taxes_base' => 0,
        'discount_base' => 0,
        'service_fee_base' => 0,
        'supplier_currency' => $context->company->base_currency,
        'supplier_cost' => 91_000,
        'supplier_cost_base' => 91_000,
        'base_currency' => $context->company->base_currency,
        'status' => 'issued',
    ], $overrides));
}

/**
 * A cancellation of its own fresh ticket (its own company and booking,
 * via ticketingTicket()), with a buyer credit note and a supplier
 * vendor credit already raised. Override 'ticket_id' to collide two
 * cancellations against the same ticket deliberately; override
 * 'buyer_credit_note_id' or 'supplier_vendor_credit_id' to null out a
 * leg the fixture created but the test does not want counted.
 */
function ticketingCancellation(array $overrides = []): TicketCancellation
{
    $ticket = ticketingTicket();
    $company = Company::find($ticket->company_id);
    $booking = TicketBooking::find($ticket->ticket_booking_id);

    $creditNote = CreditNote::create([
        'company_id' => $company->id,
        'customer_id' => $booking->customer_id,
        'credit_note_number' => 'CN-'.str()->upper(str()->random(8)),
        'credit_date' => '2026-09-05',
        'amount' => 85_000,
        'currency' => $company->base_currency,
        'base_currency' => $company->base_currency,
        'base_amount' => 85_000,
        'reason' => 'Ticket cancellation',
        'status' => 'draft',
    ]);

    $vendorCredit = VendorCredit::create([
        'company_id' => $company->id,
        'vendor_id' => $booking->supplier_vendor_id,
        'credit_number' => 'VC-'.str()->upper(str()->random(8)),
        'credit_date' => '2026-09-05',
        'amount' => 80_000,
        'currency' => $company->base_currency,
        'base_currency' => $company->base_currency,
        'base_amount' => 80_000,
        'reason' => 'Ticket cancellation',
        'status' => 'draft',
    ]);

    return TicketCancellation::create(array_merge([
        'company_id' => $company->id,
        'ticket_id' => $ticket->id,
        'cancellation_date' => '2026-09-05',
        'supplier_returns_currency' => $company->base_currency,
        'supplier_returns_exchange_rate' => null,
        'supplier_returns_amount' => 80_000,
        'supplier_returns_base' => 80_000,
        'buyer_returns_currency' => $company->base_currency,
        'buyer_returns_exchange_rate' => null,
        'buyer_returns_amount' => 85_000,
        'buyer_returns_base' => 85_000,
        'base_currency' => $company->base_currency,
        'buyer_credit_note_id' => $creditNote->id,
        'supplier_vendor_credit_id' => $vendorCredit->id,
        'idempotency_key' => 'cancel-'.str()->lower(str()->random(10)),
        'reason' => 'Passenger withdrew',
    ], $overrides));
}

/**
 * The ticket accounts a booking posts to, built by hand because a bare
 * company gets no chart of accounts. Mirrors
 * ticketPostingServiceAccount() in TicketPostingServiceTest.php.
 */
function ticketingPostingServiceAccount(Company $company, string $code): Account
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
            '4160' => Account::create([
                'company_id' => $company->id,
                'code' => '4160',
                'name' => 'Ticket Cancellation Adjustments',
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

/**
 * A posting template with role => account-code mappings, built once per
 * (company, doc type). Mirrors ticketPostingTemplate() in
 * TicketPostingServiceTest.php.
 */
function ticketingPostingTemplate(Company $company, string $docType, array $roles): PostingTemplate
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
            'account_id' => ticketingPostingServiceAccount($company, $code)->id,
        ]);
    }

    return $template->fresh(['lines']);
}

/**
 * Everything CreateTicketBookingTest needs to actually post: a company
 * with an open period and the ticket accounts, TICKET_INVOICE and
 * TICKET_BILL posting templates, a buyer, an agent linked to that
 * buyer, and an active supplier vendor. `idempotencyKey` is fixed on
 * the context object so two calls to ticketingBookingCommand() against
 * the same context replay the same key.
 */
function ticketingBookingContext(): object
{
    $f = ticketingCompany();

    ticketingPostingTemplate($f->company, 'TICKET_INVOICE', [
        'AR' => '1100',
        'CLEARING' => '2350',
        'REVENUE' => '4130',
        'SERVICE_FEE' => '4140',
        'DISCOUNT_GIVEN' => '4150',
        'ROUNDING' => '9900',
    ]);

    ticketingPostingTemplate($f->company, 'TICKET_BILL', [
        'AP' => '2000',
        'CLEARING' => '2350',
    ]);

    $customer = ticketingCustomer($f->company);
    $agent = ticketingAgent($f->company, ['customer_id' => $customer->id]);
    $vendor = ticketingVendor($f->company);

    return (object) [
        'company' => $f->company,
        'user' => $f->user,
        'customer' => $customer,
        'agent' => $agent,
        'vendor' => $vendor,
        'idempotencyKey' => 'booking-'.str()->lower(str()->random(12)),
    ];
}

/**
 * The spec's worked example as a single command-ready ticket array:
 * gross fare 85,000, taxes 12,400, supplier cost 91,000, discount
 * 2,000, service fee 1,500 -- all in the company base currency, so
 * every *_exchange_rate is null.
 */
function ticketingWorkedExampleTicket(array $overrides = []): array
{
    return array_merge([
        'passenger_name' => 'Test Passenger',
        'airline' => 'PIA',
        'route' => 'KHI-JED',
        'travel_date' => '2026-09-10',
        'gross_fare' => 85_000,
        'taxes' => 12_400,
        'discount' => 2_000,
        'service_fee' => 1_500,
        'sale_currency' => 'PKR',
        'sale_exchange_rate' => null,
        'supplier_cost' => 91_000,
        'supplier_currency' => 'PKR',
        'supplier_exchange_rate' => null,
    ], $overrides);
}

/**
 * The same ticket, but billed to the supplier in USD at 280 -- 325.00
 * USD converts to exactly 91,000.00 base, so a test built on this
 * ticket can still lean on the worked example's numbers.
 */
function ticketingUsdSupplierTicket(array $overrides = []): array
{
    return ticketingWorkedExampleTicket(array_merge([
        'supplier_cost' => 325.00,
        'supplier_currency' => 'USD',
        'supplier_exchange_rate' => 280.00000000,
    ], $overrides));
}

/**
 * A ready-to-dispatch CreateTicketBooking built from a
 * ticketingBookingContext(). Every field can be overridden by (command
 * constructor) argument name, camelCase, matching the command's own
 * parameter names rather than the database's snake_case columns.
 */
function ticketingBookingCommand(object $f, array $overrides = []): CreateTicketBooking
{
    $args = array_merge([
        'companyId' => $f->company->id,
        'customerId' => $f->customer->id,
        'agentId' => $f->agent->id,
        'supplierVendorId' => $f->vendor->id,
        'bookingDate' => '2026-09-01',
        'pnr' => 'X4K9QZ',
        'tickets' => [ticketingWorkedExampleTicket()],
        'idempotencyKey' => $f->idempotencyKey,
    ], $overrides);

    return new CreateTicketBooking(...$args);
}

/**
 * Deactivates the context's supplier vendor, so a booking against it
 * cannot raise a bill.
 */
function ticketingBreakTheSupplierVendor(object $f): void
{
    $f->vendor->update(['is_active' => false]);
}

/**
 * A fully wired-up, already-sold ticket: a booking context carrying
 * every posting template a sale and a cancellation both need
 * (TICKET_INVOICE, TICKET_BILL, TICKET_CREDIT_NOTE, TICKET_VENDOR_CREDIT),
 * with the worked-example booking already dispatched. Returns the
 * company, the booking, its one ticket, and its invoice and bill.
 */
function ticketingSoldTicket(): object
{
    $f = ticketingBookingContext();

    ticketingPostingTemplate($f->company, 'TICKET_CREDIT_NOTE', [
        'AR' => '1100',
        'CANCELLATION_ADJUSTMENT' => '4160',
    ]);

    ticketingPostingTemplate($f->company, 'TICKET_VENDOR_CREDIT', [
        'AP' => '2000',
        'CANCELLATION_ADJUSTMENT' => '4160',
    ]);

    $booking = \Illuminate\Support\Facades\Bus::dispatch(ticketingBookingCommand($f));

    return (object) [
        'company' => $f->company,
        'booking' => $booking,
        'ticket' => $booking->tickets->first(),
        'invoice' => $booking->invoice,
        'bill' => $booking->bill,
    ];
}

/**
 * A sold ticket exactly like ticketingSoldTicket(), except the
 * TICKET_VENDOR_CREDIT posting template is deliberately left
 * unconfigured -- so a cancellation with a supplier return fails partway
 * through, after the buyer credit note has already posted, exercising
 * the all-or-nothing rollback.
 */
function ticketingSoldTicketWithoutVendorCreditTemplate(): object
{
    $f = ticketingBookingContext();

    ticketingPostingTemplate($f->company, 'TICKET_CREDIT_NOTE', [
        'AR' => '1100',
        'CANCELLATION_ADJUSTMENT' => '4160',
    ]);

    $booking = \Illuminate\Support\Facades\Bus::dispatch(ticketingBookingCommand($f));

    return (object) [
        'company' => $f->company,
        'booking' => $booking,
        'ticket' => $booking->tickets->first(),
        'invoice' => $booking->invoice,
        'bill' => $booking->bill,
    ];
}

/**
 * Marks an invoice fully paid without posting a real payment
 * transaction -- CancelTicketTest only needs the invoice's own balance
 * to read zero, not a balanced payment journal entry alongside it.
 */
function ticketingPayInFull(Invoice $invoice): void
{
    $invoice->update([
        'paid_amount' => $invoice->total_amount,
        'balance' => 0,
        'status' => 'paid',
        'paid_at' => now(),
    ]);
}

/**
 * Marks an invoice partly paid, the same shortcut as ticketingPayInFull().
 */
function ticketingPayPartial(Invoice $invoice, float $amountPaid): void
{
    $balance = round((float) $invoice->total_amount - $amountPaid, 6);

    $invoice->update([
        'paid_amount' => $amountPaid,
        'balance' => max(0, $balance),
        'status' => $balance <= 0 ? 'paid' : 'partial',
    ]);
}

/**
 * Debit minus credit for an account across every transaction for the
 * company. Named `ticketing` (not `ticketAccountBalance`, already
 * taken by TicketPostingServiceTest.php) per the plan's global naming
 * rule.
 */
function ticketingAccountBalance(Company $company, string $code): float
{
    $account = Account::where('company_id', $company->id)->where('code', $code)->first();
    if (! $account) {
        return 0.0;
    }

    $debit = (float) DB::table('acct.journal_entries')->where('account_id', $account->id)->sum('debit_amount');
    $credit = (float) DB::table('acct.journal_entries')->where('account_id', $account->id)->sum('credit_amount');

    return round($debit - $credit, 2);
}
