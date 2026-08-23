# Ticketing C — Screens, Permissions and Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give ticketing a way in — five permissions, a bookings register, a booking form, a cancellation dialog, and three reports on the existing reports page.

**Architecture:** Every screen sits on the ledger design system's shared primitives; none of them invents a table, a badge or a money format. The controllers do no arithmetic — they dispatch Plan B's commands and render what comes back. An agent sees only their own bookings and never a supplier cost.

**Tech Stack:** Laravel 12, Inertia, Vue 3 `<script setup lang="ts">`, shadcn-vue, Pest.

**Spec:** `docs/superpowers/specs/2026-08-22-umrah-ticketing-design.md`

**Depends on:** Plans A and B, both merged. Every screen here dispatches a command that does not exist until Plan B lands.

## Global Constraints

- Run everything from `D:\projects\haasib\build`.
- **Read `docs/ledger-design-system.md` before building any page.** Also `docs/frontend-experience-contract.md`.
- No raw `<input>`, `<button>` or `<table>`. Shadcn components and `LedgerRegister` only.
- No `fetch()`, no `axios`. Inertia forms.
- Every money figure goes through `MoneyText`. Every status through `StatusBadge`. Every footer is `CardFooter`. The linter enforces all of this.
- Routes are `/{company}/...` with `->middleware(['auth', 'identify.company'])`.
- Company context is `app(CurrentCompany::class)->get()`. **Never `session('active_company_id')`.**
- Authorisation is `$this->hasCompanyPermission(Permissions::UMRAH_TICKET_...)`.
- **This codebase has no model factories.** `Model::factory()` will fatal. Build
  fixtures with `Model::create([...])`, following
  `tests/Feature/CompanyRoleAccessTest.php` for the user-and-role setup this
  plan's permission tests need.
- **Menu freeze:** do not rename, move, remove or repoint any existing nav item. Adding a new one is fine.
- Currency display: the base currency is not announced beside every amount. Non-base amounts show the **symbol**, not the ISO code. Documents state `Currency: SAR` once, on export.
- **Verification quad**, from `build/`:
  - `npx vue-tsc --noEmit 2>&1 | grep -v "^resources/js/actions/" | grep -cE "error TS"` — **10 is baseline**, never higher
  - `node scripts/lint-palette.mjs` — 0
  - `node scripts/lint-nav.mjs` — **7 definitions**
  - `npx vite build` — exit 0, then `git checkout -- public/build/manifest.json`
  - **Ratchets:** `rawTable 0, uiTable 0, dataTableShim 0, directionAsSeverity 6, moneyAsText 0, moneyAsFixed 0, statusAsText 0, statusSlotAsText 0, handRolledMoney 0, deadSlot 0`
- **`novalidate` invariant: every `<form>` tag has one. 104 : 104 today; each form you add makes it 105 : 105.**
- `rm -f build/bash.exe.stackdump` before every `git add` — it reappears.
- **Test baseline 185 passed / 937 assertions.** Full suite before every commit.

---

### Task 1: The five permissions

**Files:**
- Modify: `app/Constants/Permissions.php`
- Modify: `config/role-permissions.php`
- Test: `tests/Feature/Umrah/TicketPermissionTest.php`

**Interfaces:**
- Produces: `Permissions::UMRAH_TICKET_VIEW`, `_CREATE`, `_UPDATE`, `_CANCEL`, `_OWN_VIEW`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Constants\Permissions;

it('gives an owner every ticket permission', function () {
    expect(ticketingRolePermissions('owner'))->toContain(
        Permissions::UMRAH_TICKET_VIEW,
        Permissions::UMRAH_TICKET_CREATE,
        Permissions::UMRAH_TICKET_UPDATE,
        Permissions::UMRAH_TICKET_CANCEL,
    );
});

it('lets an agent see their own bookings and nobody else’s', function () {
    $agent = ticketingRolePermissions('agent');

    expect($agent)->toContain(Permissions::UMRAH_TICKET_OWN_VIEW)
        ->and($agent)->not->toContain(Permissions::UMRAH_TICKET_VIEW);
});

it('does not let an agent cancel a ticket', function () {
    // Cancelling moves money on both sides of the book.
    expect(ticketingRolePermissions('agent'))->not->toContain(Permissions::UMRAH_TICKET_CANCEL);
});

it('lets operations create a booking but not cancel one', function () {
    $ops = ticketingRolePermissions('operations');

    expect($ops)->toContain(Permissions::UMRAH_TICKET_CREATE)
        ->and($ops)->not->toContain(Permissions::UMRAH_TICKET_CANCEL);
});
```

- [ ] **Step 2: Run it and watch it fail**

- [ ] **Step 3: Add the constants and the role map**

Add the five constants to `app/Constants/Permissions.php` beside the existing
`UMRAH_*` block. Then in `config/role-permissions.php`:

| Role | Gets |
|---|---|
| owner | VIEW, CREATE, UPDATE, CANCEL |
| manager | VIEW, CREATE, UPDATE, CANCEL |
| accountant | VIEW, CANCEL |
| operations | VIEW, CREATE, UPDATE |
| agent | OWN_VIEW |

- [ ] **Step 4: Sync and run**

```bash
php artisan rbac:sync-permissions
php artisan rbac:sync-role-permissions
php artisan test tests/Feature/Umrah/TicketPermissionTest.php
```

Expected: PASS, 4 tests. The sync output should report 5 created.

- [ ] **Step 5: Full suite and commit**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -5
rm -f bash.exe.stackdump
git add app/Constants/ config/ tests/
git commit -m "Add the five ticket permissions"
```

---

### Task 2: Routes and the bookings register

**Files:**
- Modify: `modules/Umrah/Routes/umrah.php`
- Create: `modules/Umrah/Http/Controllers/TicketBookingController.php`
- Create: `resources/js/pages/Umrah/Tickets/Index.vue`
- Modify: the Umrah nav definition (add one item; touch nothing existing)
- Test: `tests/Feature/Umrah/TicketBookingIndexTest.php`

**Interfaces:**
- Consumes: Plan B's `TicketBooking` model, Task 1's permissions.
- Produces: routes `umrah.tickets.index`, `.create`, `.store`, `.show`.

- [ ] **Step 1: Write the failing test**

```php
<?php

it('lists every booking for a manager', function () {
    $f = ticketingTwoBookingsForDifferentAgents();

    $this->actingAs($f->manager)
        ->get("/{$f->company->slug}/umrah/tickets")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Umrah/Tickets/Index')
            ->has('bookings.data', 2));
});

it('shows an agent only their own bookings', function () {
    $f = ticketingTwoBookingsForDifferentAgents();

    $this->actingAs($f->agentUser)
        ->get("/{$f->company->slug}/umrah/tickets")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('bookings.data', 1));
});

it('sends no supplier cost to an agent', function () {
    $f = ticketingTwoBookingsForDifferentAgents();

    $this->actingAs($f->agentUser)
        ->get("/{$f->company->slug}/umrah/tickets")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('bookings.data.0.supplier_cost_base')
            ->missing('bookings.data.0.commission_base'));
});

it('turns away a user with no ticket permission at all', function () {
    $f = ticketingTwoBookingsForDifferentAgents();

    $this->actingAs($f->outsider)
        ->get("/{$f->company->slug}/umrah/tickets")
        ->assertForbidden();
});
```

- [ ] **Step 2: Run it and watch it fail**

- [ ] **Step 3: Routes and controller**

In `modules/Umrah/Routes/umrah.php`, inside the existing
`['auth', 'identify.company']` group:

```php
Route::get('/{company}/umrah/tickets', [TicketBookingController::class, 'index'])->name('umrah.tickets.index');
Route::get('/{company}/umrah/tickets/create', [TicketBookingController::class, 'create'])->name('umrah.tickets.create');
Route::post('/{company}/umrah/tickets', [TicketBookingController::class, 'store'])->name('umrah.tickets.store');
Route::get('/{company}/umrah/tickets/{booking}', [TicketBookingController::class, 'show'])->name('umrah.tickets.show');
Route::post('/{company}/umrah/tickets/{ticket}/cancel', [TicketBookingController::class, 'cancel'])->name('umrah.tickets.cancel');
```

`index()` authorises `UMRAH_TICKET_VIEW` **or** `UMRAH_TICKET_OWN_VIEW`. When the
user holds only the latter, scope to bookings whose `agent_id` matches their own
agent record, **and strip the cost and commission fields from the payload
entirely** — not hidden in the UI, absent from the response. The third test above
exists because a hidden field is still a leaked field.

- [ ] **Step 4: The page**

`resources/js/pages/Umrah/Tickets/Index.vue`, `<script setup lang="ts">`.
`LedgerRegister` with columns: booking reference (`kind: 'ref'`), date
(`'date'`), buyer (`'text'`), PNR (`'ref'`), passengers (`'text'`), amount
(`'amount'`), status (`'status'`). Cost and commission columns render only when
the payload carries them.

Add one nav item pointing at `umrah.tickets.index`. `node scripts/lint-nav.mjs`
must still report **7 definitions** — you are adding an item inside an existing
definition, not a new definition.

- [ ] **Step 5: Run the tests and the quad**

```bash
php artisan test tests/Feature/Umrah/TicketBookingIndexTest.php
npx vue-tsc --noEmit 2>&1 | grep -v "^resources/js/actions/" | grep -cE "error TS"
node scripts/lint-palette.mjs
node scripts/lint-nav.mjs
```

Expected: 4 tests pass; TS at 10; palette 0; nav 7.

- [ ] **Step 6: Full suite and commit**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -5
rm -f bash.exe.stackdump
git add modules/Umrah/ resources/js/ tests/
git commit -m "List ticket bookings, scoped to what the viewer may see"
```

---

### Task 3: The booking form

**Files:**
- Create: `resources/js/pages/Umrah/Tickets/Create.vue`
- Modify: `modules/Umrah/Http/Controllers/TicketBookingController.php` (`create`, `store`)
- Test: `tests/Feature/Umrah/TicketBookingStoreTest.php`

**Interfaces:**
- Consumes: `CreateTicketBooking` from Plan B Task 5.

- [ ] **Step 1: Write the failing test**

```php
<?php

it('creates a booking from the form', function () {
    $f = ticketingFormContext();

    $this->actingAs($f->manager)
        ->post("/{$f->company->slug}/umrah/tickets", ticketingFormPayload($f))
        ->assertRedirect();

    expect(\App\Modules\Umrah\Models\TicketBooking::count())->toBe(1);
});

it('does not create a second booking when the form is submitted twice', function () {
    $f = ticketingFormContext();
    $payload = ticketingFormPayload($f);       // carries one idempotency_key

    $this->actingAs($f->manager)->post("/{$f->company->slug}/umrah/tickets", $payload);
    $this->actingAs($f->manager)->post("/{$f->company->slug}/umrah/tickets", $payload);

    expect(\App\Modules\Umrah\Models\TicketBooking::count())->toBe(1);
});

it('returns a field error rather than a toast for a missing passenger name', function () {
    $f = ticketingFormContext();
    $payload = ticketingFormPayload($f);
    $payload['tickets'][0]['passenger_name'] = '';

    $this->actingAs($f->manager)
        ->post("/{$f->company->slug}/umrah/tickets", $payload)
        ->assertSessionHasErrors('tickets.0.passenger_name');
});

it('stops an agent from creating a booking', function () {
    $f = ticketingFormContext();

    $this->actingAs($f->agentUser)
        ->post("/{$f->company->slug}/umrah/tickets", ticketingFormPayload($f))
        ->assertForbidden();
});
```

- [ ] **Step 2: Run it and watch it fail**

- [ ] **Step 3: The controller methods**

`create()` authorises `UMRAH_TICKET_CREATE` and passes customers, agents,
vendors and the company currencies. `store()` takes
`StoreTicketBookingRequest` (Plan B Task 5), dispatches the command, and
redirects to `show` with a success flash. It does no arithmetic — every figure
comes back from the command.

- [ ] **Step 4: The page**

A booking header (buyer, supplier, date, PNR) and a repeatable ticket row block:
passenger, airline, route, travel date, gross fare, taxes, discount, service fee,
supplier cost, and the two currency/rate pairs. Use `AmountInput` for money and
`useForm` from Inertia. Generate the `idempotency_key` **once when the page
mounts** — that is what makes the second test pass, and re-generating on submit
would defeat it.

Show a running total of what the buyer will pay. Do not show a running
commission: that is derived at posting time and a form-side copy would drift.

`<form novalidate>`. The invariant goes to 105 : 105.

Validation errors render inline. Server failures go to a Sonner toast — see
`AI_PROMPTS/toast.md`.

- [ ] **Step 5: Run the tests and the quad**

- [ ] **Step 6: Full suite and commit**

```bash
rm -f bash.exe.stackdump
git add modules/Umrah/ resources/js/ tests/
git commit -m "Book a ticket from a form"
```

---

### Task 4: Show a booking, and cancel from it

**Files:**
- Create: `resources/js/pages/Umrah/Tickets/Show.vue`
- Create: `resources/js/pages/Umrah/Tickets/CancelDialog.vue`
- Modify: `modules/Umrah/Http/Controllers/TicketBookingController.php` (`show`, `cancel`)
- Test: `tests/Feature/Umrah/TicketCancelRequestTest.php`

**Interfaces:**
- Consumes: `CancelTicket` from Plan B Task 6.

- [ ] **Step 1: Write the failing test**

```php
<?php

it('cancels a ticket from the booking page', function () {
    $f = ticketingSoldTicketWithUsers();

    $this->actingAs($f->manager)
        ->post("/{$f->company->slug}/umrah/tickets/{$f->ticket->id}/cancel", [
            'cancellation_date' => '2026-09-05',
            'buyer_returns_amount' => 80_000,
            'supplier_returns_amount' => 85_000,
            'reason' => 'Passenger withdrew',
            'idempotency_key' => 'ui-cancel-1',
        ])
        ->assertRedirect();

    expect($f->ticket->fresh()->status)->toBe('cancelled');
});

it('stops an agent from cancelling', function () {
    $f = ticketingSoldTicketWithUsers();

    $this->actingAs($f->agentUser)
        ->post("/{$f->company->slug}/umrah/tickets/{$f->ticket->id}/cancel", [
            'cancellation_date' => '2026-09-05',
            'buyer_returns_amount' => 80_000,
            'supplier_returns_amount' => 0,
            'idempotency_key' => 'ui-cancel-2',
        ])
        ->assertForbidden();
});

it('refuses a cancellation where neither side returns anything', function () {
    $f = ticketingSoldTicketWithUsers();

    $this->actingAs($f->manager)
        ->post("/{$f->company->slug}/umrah/tickets/{$f->ticket->id}/cancel", [
            'cancellation_date' => '2026-09-05',
            'buyer_returns_amount' => 0,
            'supplier_returns_amount' => 0,
            'idempotency_key' => 'ui-cancel-3',
        ])
        ->assertSessionHasErrors();
});
```

- [ ] **Step 2: Run it and watch it fail**

- [ ] **Step 3: The controller method**

`cancel()` authorises `UMRAH_TICKET_CANCEL`, takes
`StoreTicketCancellationRequest`, dispatches `CancelTicket`, and redirects back
with a flash naming what the buyer got back and what the supplier returned.

- [ ] **Step 4: The pages**

`Show.vue`: a `LedgerDocument`-style header, the ticket rows in a
`LedgerRegister`, and links to the invoice and the bill. **The link to the bill
and every cost column render only when the payload carries them** — same rule as
Task 2.

`CancelDialog.vue`: a shadcn `Dialog` with what the buyer gets back, what the
supplier returns, a date and a reason. Show the resulting cancellation cost as
the two are typed, with the arithmetic visible — `buyer back − supplier back`.
A negative result reads as ink with a minus sign, **not red**: direction is not
severity. `<form novalidate>` → 106 : 106.

- [ ] **Step 5: Run the tests and the quad**

- [ ] **Step 6: Full suite and commit**

```bash
rm -f bash.exe.stackdump
git add modules/Umrah/ resources/js/ tests/
git commit -m "Show a booking and cancel a ticket from it"
```

---

### Task 5: The three reports

They go on the **existing shared reports page** — a new report section, not a new
page and not a new nav item. Gated on `COMPANY_REPORTS` only; an agent never
reaches them.

**Files:**
- Modify: `modules/Umrah/Http/Controllers/UmrahReportController.php` (or wherever the module's reports live — find it first)
- Modify: the shared reports page component
- Test: `tests/Feature/Umrah/TicketReportTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

it('reports ticket sales with revenue split three ways', function () {
    $f = ticketingSeveralSoldTickets();      // 3 bookings

    $this->actingAs($f->manager)
        ->get("/{$f->company->slug}/reports/ticket-sales?from=2026-09-01&to=2026-09-30")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.totals.commission', 19_200.00)
            ->where('report.totals.service_fee', 4_500.00)
            ->where('report.totals.discount', 6_000.00)
            ->where('report.totals.net_revenue', 17_700.00));
});

it('shows supplier clearing at zero as the control figure', function () {
    $f = ticketingSeveralSoldTickets();

    // The whole point of the reconciliation report: if this is not
    // zero, an invoice and its bill have come apart.
    $this->actingAs($f->manager)
        ->get("/{$f->company->slug}/reports/ticket-supplier-reconciliation")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('report.clearing_balance', 0.00));
});

it('reports each cancellation and what it cost', function () {
    $f = ticketingSeveralSoldTickets();
    ticketingCancelOneOf($f, buyerBack: 80_000, supplierBack: 85_000);

    $this->actingAs($f->manager)
        ->get("/{$f->company->slug}/reports/ticket-cancellations")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report.rows', 1)
            ->where('report.rows.0.cost', -5_000.00));
});

it('keeps an agent out of the ticket reports', function () {
    $f = ticketingSeveralSoldTickets();

    $this->actingAs($f->agentUser)
        ->get("/{$f->company->slug}/reports/ticket-sales")
        ->assertForbidden();
});
```

- [ ] **Step 2: Run it and watch it fail**

- [ ] **Step 3: Build the three reports**

**Ticket sales** — a row per ticket over a date range: booking, buyer, passenger,
route, gross, supplier cost, commission, service fee, discount, net revenue; and
a total row. Commission comes from `Ticket::commissionBase()`, never from a
stored column.

**Supplier reconciliation** — a row per supplier: bills raised, vendor credits,
paid, outstanding, and the `2350` clearing balance as a **control figure that
must read zero**. Label it as such on the page; a non-zero figure is a defect
report, not a number to interpret.

**Cancellations** — a row per cancellation: ticket, date, buyer returned,
supplier returned, cost, reason.

- [ ] **Step 4: Run the tests and the quad**

- [ ] **Step 5: Full suite and commit**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -5
rm -f bash.exe.stackdump
git add modules/Umrah/ resources/js/ tests/
git commit -m "Report ticket sales, supplier reconciliation and cancellations"
```

---

### Task 6: Verification sweep

No new behaviour. The pass that catches what six commits of feature work let slip.

- [ ] **Step 1: The full quad**

```bash
npx vue-tsc --noEmit 2>&1 | grep -v "^resources/js/actions/" | grep -cE "error TS"   # 10
node scripts/lint-palette.mjs                                                        # 0
node scripts/lint-nav.mjs                                                            # 7 definitions
npx vite build && git checkout -- public/build/manifest.json                         # exit 0
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | tail -5                          # >= 185
```

- [ ] **Step 2: The novalidate invariant**

```bash
grep -ro "<form" resources/js modules/*/Resources/js | wc -l
grep -ro "novalidate" resources/js modules/*/Resources/js | wc -l
```

The two numbers must match. Three forms were added, so 107 : 107.

- [ ] **Step 3: The ratchets**

Run whatever script reports them and confirm: `rawTable 0, uiTable 0,
dataTableShim 0, directionAsSeverity 6, moneyAsText 0, moneyAsFixed 0,
statusAsText 0, statusSlotAsText 0, handRolledMoney 0, deadSlot 0`.
`directionAsSeverity` staying at 6 matters most here — the cancellation cost
figure is exactly the kind of thing that gets painted red by reflex.

- [ ] **Step 4: Walk it in a browser**

`php artisan octane:start --server=frankenphp --port=9001 --watch` and
`npm run dev`, then at `http://localhost:9001`: book a ticket, look at the
register, open the booking, cancel it, open all three reports. Then sign in as an
agent and confirm no supplier cost, no bill link and no reports.

- [ ] **Step 5: Commit anything the sweep fixed**

```bash
rm -f bash.exe.stackdump
git add -A
git commit -m "Close what the ticketing sweep turned up"
```

---

## Done when

- All three plans' tests pass and the suite is at or above 185.
- The quad is at baseline: TS 10, palette 0, nav 7, build clean.
- An agent can see their bookings and nothing about what they cost the company.
- The supplier reconciliation report shows clearing at zero.
- No existing nav item was renamed, moved, removed or repointed.

## Not in this plan

Residual cash settlement — paying a buyer out in cash rather than leaving a
credit — is deferred. `acct.customer_refunds` and `acct.vendor_refund_receipts`
are two new accounting document types and warrant their own spec.
