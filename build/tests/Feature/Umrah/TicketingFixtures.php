<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Ticket;
use App\Modules\Umrah\Models\TicketBooking;
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
