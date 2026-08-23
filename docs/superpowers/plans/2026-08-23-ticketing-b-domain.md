# Ticketing B — The Ticketing Domain Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sell an air ticket and cancel it — three `umrah` tables and two atomic commands that raise the buyer invoice and supplier bill together and post them through Plan A's service.

**Architecture:** A booking is a container. It holds tickets, one buyer invoice and one supplier bill, all created inside a single transaction so the supplier-clearing account can never be left holding a residual. Cancellation is a second command that raises a credit note to the buyer and a vendor credit to the supplier, either of which may be absent when its side returned nothing. Both commands are idempotent on a caller-supplied key.

**Tech Stack:** Laravel 12, PostgreSQL (schema `umrah`), Pest.

**Spec:** `docs/superpowers/specs/2026-08-22-umrah-ticketing-design.md`

**Depends on:** `docs/superpowers/plans/2026-08-23-ticketing-a-accounting-foundations.md` — all six tasks merged. This plan calls `TicketPostingService` and stores currency on credit notes; neither exists until Plan A lands.

## Global Constraints

- Run everything from `D:\projects\haasib\build`.
- **The PHP namespace is `App\Modules\Umrah\...` even though the path is `modules/Umrah/...`.**
- **Umrah migrations live in `modules/Umrah/Database/Migrations/`.** Umrah routes in `modules/Umrah/Routes/umrah.php`. **Umrah tests live in `build/tests/Feature/Umrah/`, not in the module.**
- Copy the migration pattern from `modules/Umrah/Database/Migrations/2026_08_21_000001_create_umrah_refunds.php` exactly: UUID default, `char(3)` currency with an FK to `public.currencies`, CHECK constraints via `DB::statement`, RLS enabled + forced + a company-isolation policy.
- Money columns: `decimal(18, 6)` for transaction amounts, `decimal(15, 2)` for base amounts, `decimal(18, 8)` for rates. `docs/contracts/multicurrency-rules.md` is **LOCKED**.
- Never instantiate a service directly. Commands go through `Bus::dispatch()`.
- **The app's own models have no factories.** `Company::factory()`,
  `Customer::factory()`, `Vendor::factory()`, `Agent::factory()` do not exist and
  will fatal. `User::factory()` **does** exist — it is the Laravel default. Every
  non-`User` `::factory()` call written in this plan is a mistake in the plan;
  replace it with `Model::create([...])`. Task 1's instruction to "write a factory
  if none exists" is withdrawn — put fixture builders in `TicketingFixtures.php`,
  where Tasks 2-6 already expect them.

- **Company creation seeds nothing.** No chart of accounts, no fiscal year, no
  currency row. A posting test must arrange all of it. Copy
  `billPaymentTestFixture()` in `tests/Feature/Accounting/BillPaymentPostingTest.php`
  — it is the working reference for this whole plan. The shape:

```php
$user = User::factory()->create();

$company = Company::create([
    'name' => 'Ticketing Test',
    'slug' => 'ticketing-test-'.str()->lower(str()->random(8)),
    'owner_id' => $user->id,
    'base_currency' => 'PKR',
]);

// public.currencies is not seeded in tests -- insert what you use.
if (! DB::table('public.currencies')->where('code', 'PKR')->exists()) {
    DB::table('public.currencies')->insert(['code' => 'PKR', 'name' => 'Pakistan Rupee', 'symbol' => 'Rs']);
}

// Postings are refused outside an open period. Every test in this plan
// posts, so every fixture needs both of these.
$fy = FiscalYear::create([
    'company_id' => $company->id, 'name' => 'FY 2026',
    'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open',
]);

AccountingPeriod::create([
    'company_id' => $company->id, 'fiscal_year_id' => $fy->id,
    'name' => 'Sep 2026', 'period_number' => 9,
    'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
]);
```

  Then create the accounts the test posts to with `Account::create([...])`, or
  run the ticket-account backfill migration the way
  `tests/Feature/Accounting/TicketAccountsTest.php` does. **All the plan's dates
  sit in September 2026 — keep the period covering them, or every posting test
  fails on a locked period rather than on the thing it is testing.**
- Never use `$request->validate()`. Use a FormRequest.
- Pest helper functions declared at file top-level are **global across the whole suite** — redefining a name collides rather than shadows. Prefix every helper in this plan with `ticketing`.
- **Test baseline is 185 passed / 937 assertions.** Run `php artisan test` before every commit; never let it drop.
- Commit after every task. Never `--no-verify`.

---

### Task 1: Link an agent to a customer

Every buyer is an `acct.customer`. An agent is a customer who also has an agent record, so one statement covers everything the agent owes — tickets, Umrah packages, anything else.

**Files:**
- Create: `modules/Umrah/Database/Migrations/2026_08_24_000001_add_customer_id_to_umrah_agents.php`
- Modify: `modules/Umrah/Models/Agent.php`
- Test: `tests/Feature/Umrah/AgentCustomerLinkTest.php`

**Interfaces:**
- Produces: `umrah.agents.customer_id` (uuid, nullable, FK to `acct.customers`), and `Agent::customer()` returning a `BelongsTo`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Umrah\Models\Agent;

it('links an agent to the customer who carries the balance', function () {
    $f = ticketingCompany();
    $customer = ticketingCustomer($f->company, ['name' => 'Al-Noor Travels']);
    $agent = ticketingAgent($f->company, ['customer_id' => $customer->id]);

    expect($agent->customer->name)->toBe('Al-Noor Travels');
});

it('leaves existing agents unlinked rather than inventing a customer', function () {
    $f = ticketingCompany();

    // An agent that predates this column has no customer record to point
    // at, and inventing one would put a duplicate party on the books.
    expect(ticketingAgent($f->company)->customer_id)->toBeNull();
});

it('reaches the customer through the relation', function () {
    $f = ticketingCompany();
    $customer = ticketingCustomer($f->company);
    $agent = ticketingAgent($f->company, ['customer_id' => $customer->id]);

    expect($agent->customer)->toBeInstanceOf(\App\Modules\Accounting\Models\Customer::class)
        ->and($agent->customer->id)->toBe($customer->id);
});
```

Write `ticketingCompany()`, `ticketingCustomer()` and `ticketingAgent()` in
`tests/Feature/Umrah/TicketingFixtures.php` now, to the recipe in Global
Constraints — Tasks 2 through 6 all build on them, so getting them right here
saves five repetitions.

There is deliberately **no** test that the database refuses a customer from
another company. RLS and foreign-key evaluation interact in ways that would make
such a test assert the wrong mechanism. The cross-company guard that matters is
in the booking command, and Task 5 tests it there.

- [ ] **Step 2: Run it and watch it fail**

```bash
php artisan test tests/Feature/Umrah/AgentCustomerLinkTest.php
```

Expected: FAIL, unknown column `customer_id`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable, not backfilled. An existing agent has no customer record to
 * point at, and inventing one would put a duplicate party on the books.
 * The booking command requires the link and says so when it is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->uuid('customer_id')->nullable()->after('company_id');
            $table->foreign('customer_id')
                ->references('id')->on('acct.customers')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['company_id', 'customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
```

- [ ] **Step 4: Add the relation**

In `modules/Umrah/Models/Agent.php`, add `customer_id` to `$fillable` and:

```php
public function customer(): BelongsTo
{
    return $this->belongsTo(\App\Modules\Accounting\Models\Customer::class);
}
```

- [ ] **Step 5: Run the tests**

```bash
php artisan migrate
php artisan test tests/Feature/Umrah/AgentCustomerLinkTest.php
```

Expected: PASS, 3 tests.

- [ ] **Step 6: Full suite and commit**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -5
git add modules/Umrah/ tests/
git commit -m "Point an agent at the customer who carries the balance"
```

---

### Task 2: The bookings table

**Files:**
- Create: `modules/Umrah/Database/Migrations/2026_08_24_000002_create_umrah_ticket_bookings.php`
- Create: `modules/Umrah/Models/TicketBooking.php`
- Test: `tests/Feature/Umrah/TicketBookingTableTest.php`

**Interfaces:**
- Produces: `umrah.ticket_bookings` and `App\Modules\Umrah\Models\TicketBooking`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Company;
use App\Modules\Umrah\Models\TicketBooking;
use Illuminate\Database\QueryException;

it('stores a booking with both its documents', function () {
    $f = ticketingBookingFixture();

    $booking = TicketBooking::create([
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
    ]);

    expect($booking->fresh()->pnr)->toBe('X4K9QZ');
});

it('refuses a booking with no supplier bill', function () {
    $f = ticketingBookingFixture();

    // A bill raised later would be converted at a different rate and
    // leave a residual in the clearing account that nothing closes.
    expect(fn () => TicketBooking::create(ticketingBookingAttributes($f, ['bill_id' => null])))
        ->toThrow(QueryException::class);
});

it('refuses a booking with no buyer', function () {
    $f = ticketingBookingFixture();

    expect(fn () => TicketBooking::create(ticketingBookingAttributes($f, ['customer_id' => null])))
        ->toThrow(QueryException::class);
});

it('refuses a second booking on the same idempotency key', function () {
    $f = ticketingBookingFixture();
    TicketBooking::create(ticketingBookingAttributes($f));

    expect(fn () => TicketBooking::create(ticketingBookingAttributes($f, [
        'booking_reference' => 'TB-00002',
        'invoice_id' => $f->secondInvoice->id,
        'bill_id' => $f->secondBill->id,
    ])))->toThrow(QueryException::class);
});

it('refuses two bookings against one invoice', function () {
    $f = ticketingBookingFixture();
    TicketBooking::create(ticketingBookingAttributes($f));

    expect(fn () => TicketBooking::create(ticketingBookingAttributes($f, [
        'booking_reference' => 'TB-00003',
        'idempotency_key' => 'test-key-2',
        'bill_id' => $f->secondBill->id,
    ])))->toThrow(QueryException::class);
});
```

Write `ticketingBookingFixture()` returning an object with `company`, `customer`,
`agent`, `vendor`, `invoice`, `bill`, `secondInvoice`, `secondBill`; and
`ticketingBookingAttributes($f, array $overrides = [])` returning the full
attribute array with `array_merge`. Put both in
`tests/Feature/Umrah/TicketingFixtures.php` and require it — Tasks 3 through 6
reuse them.

- [ ] **Step 2: Run it and watch it fail**

```bash
php artisan test tests/Feature/Umrah/TicketBookingTableTest.php
```

Expected: FAIL, relation `umrah.ticket_bookings` does not exist.

- [ ] **Step 3: Write the migration**

```php
Schema::create('umrah.ticket_bookings', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
    $table->uuid('company_id');
    $table->uuid('customer_id');                       // the buyer, always
    $table->uuid('agent_id')->nullable();              // set when the buyer is an agent
    $table->uuid('supplier_vendor_id');
    $table->uuid('invoice_id');
    $table->uuid('bill_id');
    $table->string('booking_reference', 32);
    $table->string('pnr', 16)->nullable();
    $table->date('booking_date');
    $table->string('status', 20)->default('confirmed');
    $table->string('idempotency_key', 64);
    $table->uuid('created_by_user_id')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
    $table->foreign('customer_id')->references('id')->on('acct.customers')->restrictOnDelete()->cascadeOnUpdate();
    $table->foreign('agent_id')->references('id')->on('umrah.agents')->nullOnDelete()->cascadeOnUpdate();
    $table->foreign('supplier_vendor_id')->references('id')->on('acct.vendors')->restrictOnDelete()->cascadeOnUpdate();
    $table->foreign('invoice_id')->references('id')->on('acct.invoices')->restrictOnDelete()->cascadeOnUpdate();
    $table->foreign('bill_id')->references('id')->on('acct.bills')->restrictOnDelete()->cascadeOnUpdate();

    $table->unique(['company_id', 'booking_reference']);
    $table->unique(['company_id', 'idempotency_key']);
    $table->unique('invoice_id');
    $table->unique('bill_id');
    $table->index(['company_id', 'customer_id']);
    $table->index(['company_id', 'booking_date']);
    $table->index(['company_id', 'status']);
});

DB::statement("ALTER TABLE umrah.ticket_bookings ADD CONSTRAINT umrah_ticket_bookings_status_check CHECK (status IN ('confirmed','cancelled'))");
```

Then the four RLS statements, copied verbatim from the refunds migration with
`ticket_bookings` substituted, and a policy named
`ticket_bookings_company_isolation`. `down()` drops the policy then the table.

The `acct.vendors` table is unchanged — no rename, no ticketing profile table.
An airline is an ordinary vendor.

- [ ] **Step 4: Write the model**

`modules/Umrah/Models/TicketBooking.php`, namespace `App\Modules\Umrah\Models`.
Set `protected $table = 'umrah.ticket_bookings';`, `$keyType = 'string'`,
`$incrementing = false`, `SoftDeletes`, `$fillable` covering every column above,
`'booking_date' => 'date'` in `$casts`, and relations: `customer()`, `agent()`,
`supplierVendor()`, `invoice()`, `bill()`, `tickets()` (hasMany, added in Task 3).
Copy the class-header conventions from `modules/Umrah/Models/Agent.php`.

- [ ] **Step 5: Run the tests**

```bash
php artisan migrate
php artisan test tests/Feature/Umrah/TicketBookingTableTest.php
```

Expected: PASS, 5 tests.

- [ ] **Step 6: Full suite and commit**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -5
git add modules/Umrah/ tests/
git commit -m "Hold a booking's tickets and both its documents"
```

---

### Task 3: The tickets table

**Files:**
- Create: `modules/Umrah/Database/Migrations/2026_08_24_000003_create_umrah_tickets.php`
- Create: `modules/Umrah/Models/Ticket.php`
- Modify: `modules/Umrah/Models/TicketBooking.php` (add `tickets()`)
- Test: `tests/Feature/Umrah/TicketTableTest.php`

**Interfaces:**
- Produces: `umrah.tickets`, `App\Modules\Umrah\Models\Ticket`, and a derived
  accessor `Ticket::commissionBase(): float`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Modules\Umrah\Models\Ticket;

it('derives commission from fare and cost rather than storing it', function () {
    $ticket = ticketingTicket([
        'gross_fare_base' => 85_000,
        'taxes_base' => 12_400,
        'supplier_cost_base' => 91_000,
    ]);

    // (85,000 + 12,400) - 91,000
    expect($ticket->commissionBase())->toBe(6_400.0);
});

it('allows a negative commission when the fare undercuts the cost', function () {
    $ticket = ticketingTicket([
        'gross_fare_base' => 80_000,
        'taxes_base' => 5_000,
        'supplier_cost_base' => 91_000,
    ]);

    expect($ticket->commissionBase())->toBe(-6_000.0);
});

it('keeps the passenger name even when the passenger record goes', function () {
    $ticket = ticketingTicket(['passenger_id' => null, 'passenger_name' => 'Fatima Bibi']);

    expect($ticket->passenger_id)->toBeNull()
        ->and($ticket->passenger_name)->toBe('Fatima Bibi');
});

it('refuses two tickets on one airline ticket number', function () {
    ticketingTicket(['airline_ticket_number' => '214-1234567890']);

    expect(fn () => ticketingTicket(['airline_ticket_number' => '214-1234567890']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows many tickets with no airline number yet', function () {
    ticketingTicket(['airline_ticket_number' => null]);
    ticketingTicket(['airline_ticket_number' => null]);

    expect(Ticket::whereNull('airline_ticket_number')->count())->toBe(2);
});
```

Add `ticketingTicket(array $overrides = [])` to `TicketingFixtures.php`.

- [ ] **Step 2: Run it and watch it fail**

```bash
php artisan test tests/Feature/Umrah/TicketTableTest.php
```

Expected: FAIL, relation `umrah.tickets` does not exist.

- [ ] **Step 3: Write the migration**

```php
Schema::create('umrah.tickets', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
    $table->uuid('company_id');
    $table->uuid('ticket_booking_id');
    $table->string('ticket_number', 32);                       // ours
    $table->string('airline_ticket_number', 32)->nullable();   // the airline's
    $table->uuid('passenger_id')->nullable();
    $table->string('passenger_name', 120);                     // snapshot, survives deletion
    $table->string('passport_number', 32)->nullable();
    $table->string('airline', 80)->nullable();
    $table->string('route', 120)->nullable();
    $table->date('travel_date')->nullable();

    // Sale side: the buyer's currency.
    $table->char('sale_currency', 3);
    $table->decimal('sale_exchange_rate', 18, 8)->nullable();
    $table->decimal('gross_fare', 18, 6);
    $table->decimal('taxes', 18, 6)->default(0);
    $table->decimal('discount', 18, 6)->default(0);
    $table->decimal('service_fee', 18, 6)->default(0);
    $table->decimal('gross_fare_base', 15, 2);
    $table->decimal('taxes_base', 15, 2)->default(0);
    $table->decimal('discount_base', 15, 2)->default(0);
    $table->decimal('service_fee_base', 15, 2)->default(0);

    // Supply side: the supplier's currency, a different rate.
    $table->char('supplier_currency', 3);
    $table->decimal('supplier_exchange_rate', 18, 8)->nullable();
    $table->decimal('supplier_cost', 18, 6);
    $table->decimal('supplier_cost_base', 15, 2);

    $table->char('base_currency', 3);
    $table->string('status', 20)->default('issued');
    $table->timestamps();
    $table->softDeletes();
    // ... FKs as in Task 2, plus passenger_id -> umrah.pilgrims nullOnDelete
    $table->unique(['company_id', 'ticket_number']);
    $table->unique(['company_id', 'airline_ticket_number']);   // NULLs do not collide in Postgres
    $table->index(['company_id', 'ticket_booking_id']);
    $table->index(['company_id', 'travel_date']);
});
```

Check what the passenger table is actually called before writing that FK —
`umrah.pilgrims` is the expected name but confirm it against the module's
migrations, and drop the FK if no such table exists rather than guessing.

There is no `date_of_birth` column. This is an accounting scope; a passenger's
date of birth belongs on the passenger record, not on a revenue document.

Add the dual-recording CHECK for both currency pairs, mirroring the refunds
migration:

```php
DB::statement('ALTER TABLE umrah.tickets ADD CONSTRAINT umrah_tickets_sale_rate_check CHECK ((sale_currency = base_currency AND sale_exchange_rate IS NULL) OR (sale_currency <> base_currency AND sale_exchange_rate > 0))');
DB::statement('ALTER TABLE umrah.tickets ADD CONSTRAINT umrah_tickets_supplier_rate_check CHECK ((supplier_currency = base_currency AND supplier_exchange_rate IS NULL) OR (supplier_currency <> base_currency AND supplier_exchange_rate > 0))');
DB::statement("ALTER TABLE umrah.tickets ADD CONSTRAINT umrah_tickets_status_check CHECK (status IN ('issued','cancelled'))");
```

Then RLS, policy `tickets_company_isolation`.

- [ ] **Step 4: Write the model**

`modules/Umrah/Models/Ticket.php`. Cast every money column to `decimal`, the
dates to `date`. Add:

```php
/**
 * Commission is what is left of the buyer's fare and taxes after the
 * supplier is paid. Deriving it means a corrected supplier cost cannot
 * leave a stale commission behind.
 */
public function commissionBase(): float
{
    return (float) $this->gross_fare_base
        + (float) $this->taxes_base
        - (float) $this->supplier_cost_base;
}
```

Add `tickets()` to `TicketBooking`.

- [ ] **Step 5: Run the tests**

```bash
php artisan migrate
php artisan test tests/Feature/Umrah/TicketTableTest.php
```

Expected: PASS, 5 tests.

- [ ] **Step 6: Full suite and commit**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -5
git add modules/Umrah/ tests/
git commit -m "Record a ticket's two currencies and derive its commission"
```

---

### Task 4: The cancellations table

**Files:**
- Create: `modules/Umrah/Database/Migrations/2026_08_24_000004_create_umrah_ticket_cancellations.php`
- Create: `modules/Umrah/Models/TicketCancellation.php`
- Test: `tests/Feature/Umrah/TicketCancellationTableTest.php`

**Interfaces:**
- Produces: `umrah.ticket_cancellations` and its model, with
  `TicketCancellation::costBase(): float`.

- [ ] **Step 1: Write the failing test**

```php
<?php

it('costs the difference between what the buyer got back and what the supplier returned', function () {
    $cancellation = ticketingCancellation([
        'buyer_returns_base' => 85_000,
        'supplier_returns_base' => 80_000,
    ]);

    // The company gave back 5,000 more than it got back.
    expect($cancellation->costBase())->toBe(5_000.0);
});

it('shows a negative cost when the cancellation made money', function () {
    $cancellation = ticketingCancellation([
        'buyer_returns_base' => 80_000,
        'supplier_returns_base' => 85_000,
    ]);

    expect($cancellation->costBase())->toBe(-5_000.0);
});

it('cancels a ticket only once', function () {
    $cancellation = ticketingCancellation();

    expect(fn () => ticketingCancellation(['ticket_id' => $cancellation->ticket_id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows a cancellation with no credit note when the buyer got nothing back', function () {
    $cancellation = ticketingCancellation([
        'buyer_returns_base' => 0,
        'buyer_credit_note_id' => null,
    ]);

    expect($cancellation->buyer_credit_note_id)->toBeNull();
});
```

- [ ] **Step 2: Run it and watch it fail**

- [ ] **Step 3: Write the migration**

Columns: `id`, `company_id`, `ticket_id`, `cancellation_date`,
`supplier_returns_amount` + `_base`, `buyer_returns_amount` + `_base`,
`currency`/`base_currency`/`exchange_rate` for each side,
`buyer_credit_note_id` (nullable), `supplier_vendor_credit_id` (nullable),
`buyer_refund_id` (nullable), `supplier_refund_receipt_id` (nullable),
`idempotency_key`, `reason`, timestamps, soft deletes.

Constraints:

```php
$table->unique('ticket_id');                       // a ticket cancels once
$table->unique(['company_id', 'idempotency_key']);
$table->unique('buyer_credit_note_id');            // NULLs do not collide
$table->unique('supplier_vendor_credit_id');
```

`buyer_refund_id` and `supplier_refund_receipt_id` are columns with **no foreign
key yet** — the tables they will point at (`acct.customer_refunds`,
`acct.vendor_refund_receipts`) are a deferred follow-on. Leave them nullable and
unconstrained, with a comment saying so. Do not point them at `umrah.refunds`,
which knows only agents and Umrah vendors and would be the wrong party.

Then RLS, policy `ticket_cancellations_company_isolation`.

- [ ] **Step 4: Write the model**

```php
/**
 * Positive is a cost. The buyer leg debits 4160 and the supplier leg
 * credits it, so this figure is the net debit the ledger is left
 * holding -- computed the same way in both places on purpose.
 */
public function costBase(): float
{
    return (float) $this->buyer_returns_base - (float) $this->supplier_returns_base;
}
```

- [ ] **Step 5: Run the tests**

Expected: PASS, 4 tests.

- [ ] **Step 6: Full suite and commit**

```bash
git add modules/Umrah/ tests/
git commit -m "Record a cancellation and what each side gave back"
```

---

### Task 5: The booking command

The load-bearing task. One transaction, one idempotency key, both documents or
neither.

**Files:**
- Create: `modules/Umrah/Commands/CreateTicketBooking.php`
- Create: `modules/Umrah/Handlers/CreateTicketBookingHandler.php`
- Create: `modules/Umrah/Http/Requests/StoreTicketBookingRequest.php`
- Test: `tests/Feature/Umrah/CreateTicketBookingTest.php`

**Interfaces:**
- Consumes: `TicketPostingService::postTicketInvoice()` and `postTicketBill()`
  and `TicketSaleAmounts` from Plan A Task 4; the three tables above.
- Produces:

```php
Bus::dispatch(new CreateTicketBooking(
    companyId: string,
    customerId: string,
    supplierVendorId: string,
    bookingDate: string,       // Y-m-d
    pnr: ?string,
    tickets: array,            // each: passenger_name, gross_fare, taxes,
                               // discount, service_fee, supplier_cost,
                               // sale_currency, sale_exchange_rate,
                               // supplier_currency, supplier_exchange_rate
    idempotencyKey: string,
)): TicketBooking
```

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Modules\Umrah\Commands\CreateTicketBooking;
use App\Modules\Umrah\Models\TicketBooking;
use Illuminate\Support\Facades\Bus;

it('raises both documents and leaves clearing at zero', function () {
    $f = ticketingBookingContext();

    $booking = Bus::dispatch(ticketingBookingCommand($f));

    expect($booking->invoice->total_amount)->toEqual(96_900.00)
        ->and($booking->bill->total_amount)->toEqual(91_000.00)
        ->and(ticketingAccountBalance($f->company, '2350'))->toBe(0.0);
});

it('puts one line on the buyer invoice and no supplier cost anywhere on it', function () {
    $f = ticketingBookingContext();

    $booking = Bus::dispatch(ticketingBookingCommand($f));
    $invoice = $booking->invoice;

    expect($invoice->lineItems)->toHaveCount(1)
        ->and($invoice->lineItems->first()->line_total)->toEqual(98_900.00)
        ->and($invoice->discount_amount)->toEqual(2_000.00);

    // The supplier's price must not be reconstructable from the document
    // the agent receives.
    $printed = $invoice->lineItems->pluck('description')->join(' ')
        . ' ' . $invoice->lineItems->pluck('line_total')->join(' ');
    expect($printed)->not->toContain('91000')
        ->and($printed)->not->toContain('91,000');
});

it('returns the same booking for a repeated key rather than a second one', function () {
    $f = ticketingBookingContext();
    $command = ticketingBookingCommand($f);

    $first = Bus::dispatch($command);
    $second = Bus::dispatch(ticketingBookingCommand($f));   // same key

    expect($second->id)->toBe($first->id)
        ->and(TicketBooking::count())->toBe(1);
});

it('writes nothing at all when the bill cannot be raised', function () {
    $f = ticketingBookingContext();
    ticketingBreakTheSupplierVendor($f);   // deactivate it

    expect(fn () => Bus::dispatch(ticketingBookingCommand($f)))->toThrow(\Throwable::class);

    expect(TicketBooking::count())->toBe(0)
        ->and(ticketingAccountBalance($f->company, '2350'))->toBe(0.0)
        ->and(ticketingAccountBalance($f->company, '1100'))->toBe(0.0);
});

it('refuses an agent whose customer is not the buyer on the booking', function () {
    $f = ticketingBookingContext();
    $other = ticketingCustomer($f->company);

    expect(fn () => Bus::dispatch(ticketingBookingCommand($f, ['customerId' => $other->id])))
        ->toThrow(\InvalidArgumentException::class);
});

it('converts each side at its own rate', function () {
    $f = ticketingBookingContext();

    // Sale in PKR at base; supplier in USD at 280.
    $booking = Bus::dispatch(ticketingBookingCommand($f, [
        'tickets' => [ticketingUsdSupplierTicket()],
    ]));

    $ticket = $booking->tickets->first();
    expect($ticket->supplier_cost)->toEqual(325.00)
        ->and($ticket->supplier_exchange_rate)->toEqual(280.00000000)
        ->and($ticket->supplier_cost_base)->toEqual(91_000.00);
});
```

- [ ] **Step 2: Run it and watch it fail**

- [ ] **Step 3: Write the handler**

The order inside one `DB::transaction`:

1. Look up an existing booking on `(company_id, idempotency_key)`. Return it if found — this is the idempotency guarantee and it comes first.
2. If `agent_id` is supplied, assert `agent.customer_id === customerId`; throw `InvalidArgumentException` otherwise. An agent billed to somebody else's account is a wrong-party error, not a validation nicety.
3. Convert every ticket: `*_base = round(amount * rate, 2)` when the currency differs from base, otherwise the amount itself. Compute `supplier_cost_base` **once, here** — every later reader takes this stored value rather than reconverting.
4. Create the invoice with **one** line item, described as the flight (`"Air ticket, {airline} {route}, {travel_date}"`), priced at `sum(gross_fare_base + taxes_base + service_fee_base)`, and set `discount_amount = sum(discount_base)`.
5. Create the bill with one line at `sum(supplier_cost_base)`.
6. Create the booking, then the ticket rows.
7. `postTicketInvoice($invoice, new TicketSaleAmounts(...))` with the summed figures, then `postTicketBill($bill, $supplierCostBase)`.

The invoice and bill go through the existing `Invoice`/`Bill` models, not through
`StoreInvoiceRequest` — those validators reject the account types this domain
needs, and ticket postings take every account from a template role anyway.

- [ ] **Step 4: Write the FormRequest**

`StoreTicketBookingRequest` — `customer_id` and `supplier_vendor_id` exist and
belong to the company; `tickets` is a non-empty array; every money field
`numeric|min:0`; `sale_exchange_rate` and `supplier_exchange_rate` required
when the matching currency differs from the company base; `idempotency_key`
required, max 64.

- [ ] **Step 5: Run the tests**

Expected: PASS, 6 tests.

- [ ] **Step 6: Full suite and commit**

```bash
git add modules/Umrah/ tests/
git commit -m "Sell a ticket as one atomic booking"
```

---

### Task 6: The cancellation command

**Files:**
- Create: `modules/Umrah/Commands/CancelTicket.php`
- Create: `modules/Umrah/Handlers/CancelTicketHandler.php`
- Create: `modules/Umrah/Http/Requests/StoreTicketCancellationRequest.php`
- Test: `tests/Feature/Umrah/CancelTicketTest.php`

**Interfaces:**
- Consumes: `TicketPostingService::postTicketCreditNote()` and
  `postTicketVendorCredit()` from Plan A Task 6.
- Produces:

```php
Bus::dispatch(new CancelTicket(
    ticketId: string,
    cancellationDate: string,
    buyerReturnsAmount: float,
    supplierReturnsAmount: float,
    reason: ?string,
    idempotencyKey: string,
)): TicketCancellation
```

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Modules\Umrah\Commands\CancelTicket;

it('credits the buyer and debits the supplier', function () {
    $f = ticketingSoldTicket();

    $cancellation = Bus::dispatch(new CancelTicket(
        ticketId: $f->ticket->id,
        cancellationDate: '2026-09-05',
        buyerReturnsAmount: 80_000,
        supplierReturnsAmount: 85_000,
        reason: 'Passenger withdrew',
        idempotencyKey: 'cancel-1',
    ));

    expect($cancellation->buyerCreditNote->amount)->toEqual(80_000.00)
        ->and($cancellation->supplierVendorCredit->amount)->toEqual(85_000.00)
        ->and(ticketingAccountBalance($f->company, '4160'))->toBe(-5_000.0);
});

it('raises no credit note when the buyer got nothing back', function () {
    $f = ticketingSoldTicket();

    $cancellation = Bus::dispatch(new CancelTicket(
        ticketId: $f->ticket->id,
        cancellationDate: '2026-09-05',
        buyerReturnsAmount: 0,
        supplierReturnsAmount: 85_000,
        reason: 'No-show, fully forfeit',
        idempotencyKey: 'cancel-2',
    ));

    expect($cancellation->buyer_credit_note_id)->toBeNull()
        ->and($cancellation->supplierVendorCredit)->not->toBeNull();
});

it('succeeds against a fully paid invoice and leaves the credit unapplied', function () {
    $f = ticketingSoldTicket();
    ticketingPayInFull($f->invoice);

    // Applying zero to a settled invoice is not an error. The whole
    // credit stays available for the buyer's next booking.
    $cancellation = Bus::dispatch(new CancelTicket(
        ticketId: $f->ticket->id,
        cancellationDate: '2026-09-05',
        buyerReturnsAmount: 80_000,
        supplierReturnsAmount: 85_000,
        reason: 'Cancelled after payment',
        idempotencyKey: 'cancel-3',
    ));

    expect($cancellation->buyerCreditNote->balance)->toEqual(80_000.00)
        ->and($f->invoice->fresh()->status)->toBe('paid');
});

it('applies only what the invoice still owes', function () {
    $f = ticketingSoldTicket();
    ticketingPayPartial($f->invoice, 90_000);      // 6,900 left outstanding

    $cancellation = Bus::dispatch(new CancelTicket(
        ticketId: $f->ticket->id,
        cancellationDate: '2026-09-05',
        buyerReturnsAmount: 80_000,
        supplierReturnsAmount: 85_000,
        reason: 'Cancelled part-paid',
        idempotencyKey: 'cancel-4',
    ));

    expect($f->invoice->fresh()->balance)->toEqual(0.00)
        ->and($cancellation->buyerCreditNote->balance)->toEqual(73_100.00);
});

it('marks the ticket and its booking cancelled', function () {
    $f = ticketingSoldTicket();

    Bus::dispatch(new CancelTicket($f->ticket->id, '2026-09-05', 80_000, 85_000, null, 'cancel-5'));

    expect($f->ticket->fresh()->status)->toBe('cancelled')
        ->and($f->booking->fresh()->status)->toBe('cancelled');
});

it('cancels once for a repeated key', function () {
    $f = ticketingSoldTicket();
    $command = new CancelTicket($f->ticket->id, '2026-09-05', 80_000, 85_000, null, 'cancel-6');

    $first = Bus::dispatch($command);
    $second = Bus::dispatch(new CancelTicket($f->ticket->id, '2026-09-05', 80_000, 85_000, null, 'cancel-6'));

    expect($second->id)->toBe($first->id);
});
```

- [ ] **Step 2: Run it and watch it fail**

- [ ] **Step 3: Write the handler**

One `DB::transaction`:

1. Idempotency lookup first, as in Task 5.
2. Refuse a ticket already cancelled.
3. When `buyerReturnsAmount > 0`: create the credit note (currency and rate from the ticket's sale side — this is what Plan A Task 5 added the columns for), post it via `postTicketCreditNote`, then apply it to the invoice at **`min(return amount, current invoice balance)`**. A balance of zero applies zero and **succeeds**; do not let `ApplyAction`'s paid-invoice guard turn that into an exception — call the allocation directly, or extend `ApplyAction` with a zero-amount short circuit. State in a comment which you chose and why.
4. When `supplierReturnsAmount > 0`: the same on the supplier side via `postTicketVendorCredit` against the bill.
5. Set the ticket to `cancelled`; set the booking to `cancelled` when no live ticket remains on it.
6. Write the `ticket_cancellations` row with both credit ids (null where no document was raised).

- [ ] **Step 4: Write the FormRequest**

Both return amounts `numeric|min:0`; at least one greater than zero;
`cancellation_date` on or after the booking date; `idempotency_key` required.

- [ ] **Step 5: Run the tests**

Expected: PASS, 6 tests.

- [ ] **Step 6: Full suite and commit**

```bash
git add modules/Umrah/ tests/
git commit -m "Cancel a ticket into credits on both sides"
```

---

## Done when

- `php artisan test` is at or above 185 passed.
- A booking raises an invoice and a bill together, and `2350` sits at exactly zero afterwards.
- The buyer's invoice carries one line and no trace of the supplier's price.
- A failed booking leaves no row and no journal entry anywhere.
- Cancelling a fully-paid ticket succeeds and leaves the whole credit available.
- Replaying either command on its key returns the first result rather than a second document.

## Not in this plan

Screens, routes, permissions and reports are Plan C. Residual cash settlement
(`acct.customer_refunds`, `acct.vendor_refund_receipts`) is deferred; the two
columns exist and stay null.
