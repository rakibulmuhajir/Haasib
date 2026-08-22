# Umrah Ticketing — Design

**Date:** 2026-08-22
**Status:** Approved for planning
**Scope:** The accounting of air ticket sales. No booking engine, no airline API, no seat inventory.

## The three parties

Every sentence below uses these words and no synonyms.

| Word | Who |
|---|---|
| **Company** | The Haasib tenant. The travel business using the app. |
| **Supplier** | Who the company buys tickets from — an airline, or a B2B consolidator. |
| **Buyer** | Who the company sells to — an agent on account, or a walk-in customer. |

```
Supplier  ──sells to──►  Company  ──sells to──►  Buyer
```

## Why this exists

The Umrah module sells visas, transport and hotels. It cannot sell a ticket,
so ticket sales are kept outside the app and reconciled by hand. The schema
contract anticipated this: *"avoid generic travel complexity until
ticketing/hotel/transport are separately required."* Ticketing is now required.

Ticketing is not a visa group with different labels. A visa group has one sale
price and one supplier cost. A ticket has five money lines, because the company
earns from the supplier and gives part of that away to win the sale. A design
that collapses those into "price minus cost" cannot answer the two questions
that matter: what did we give away, and can we reconcile against the supplier's
statement.

## Decisions

| Question | Decision |
|---|---|
| Who buys | **Agents and walk-in customers.** Walk-ins reuse `acct.customers`; one person is one record across the app. |
| Money lines | **Full trade anatomy** — gross fare, taxes, supplier cost, discount, service fee. |
| Record shape | **A booking holds tickets**, mirroring `visa_groups → passengers`. |
| Supplier record | **Rename `visa_vendors` → `vendors`**, add `vendor_type = 'ticket_supplier'`. |
| Cancellation | **One cancellation record raises both refund legs.** |
| Supplier refunds | **Settleable as credit**, not cash only. |

---

## 1. The vendor rename

`umrah.visa_vendors` already holds visa providers, transport providers and
government bodies, keyed by `vendor_type`. Ticket suppliers are the fourth kind.
The name is now actively misleading, so it changes with this work rather than
after it.

- Table `umrah.visa_vendors` → `umrah.vendors`
- Model `VisaVendor` → `UmrahVendor`
- `vendor_type` gains `ticket_supplier`

**Blast radius: ~70 files**, all mechanical. 7 models, 7 controllers, 12 form
requests, 4 services, ~9 Vue pages, 7 test files, 2 seeders. The 13 existing
migrations that name the old table are **not edited** — a rename migration runs
after them.

Routes are already `umrah/vendors`, not `umrah/visa-vendors`, so no URL moves
and the menu freeze is untouched.

Refunds' `party_type` keeps its existing values (`visa_vendor`,
`transport_vendor`) even though the table renames. Those describe *what the
vendor is*, not *where it lives*, and changing them would rewrite live refund
rows for no gain.

**This lands as its own commit, before any ticketing table exists.** A
mechanical refactor must never share a diff with new behaviour — a reviewer
cannot tell them apart, and a bisect cannot separate them.

## 2. The chart of accounts

Four accounts, added to the umrah COA template pack **and backfilled into
existing companies**. The pack is applied at company creation, so a migration
that only touches the template leaves every live company without them — the
same trap `2300`/`1170` had to avoid.

| Code | Account | Type |
|---|---|---|
| 4130 | Ticket Revenue | revenue |
| 4135 | Ticket Discount | contra-revenue |
| 4140 | Ticket Service Fee Income | revenue |
| 5130 | Ticket Cost | cogs |

`UmrahCoreService::accountId()` gains roles `ticket_revenue`,
`ticket_discount`, `ticket_service_fee`, `ticket_cost`. It is the only
account-role map in the module and it stays that way.

**4110 and 4120 are already taken** by Transport Revenue and Hotel Revenue.
Ticket accounts start at 4130.

## 3. Tables

### `umrah.ticket_bookings`

One PNR, one buyer, one supplier. Carries the receivable.

| Column | Notes |
|---|---|
| `id`, `company_id` | uuid, RLS scoped |
| `booking_number` | `TKB-00001`, company-sequential, same generator as vouchers |
| `buyer_type` | `agent` \| `customer` |
| `buyer_id` | uuid → `umrah.agents` or `acct.customers`, per `buyer_type`. **No database FK** — the target table varies, so the constraint is a CHECK on `buyer_type` plus validation in the form request. |
| `supplier_id` | uuid → `umrah.vendors` where `vendor_type = 'ticket_supplier'` |
| `pnr` | the supplier's booking reference, nullable |
| `booking_date` | date the sale is recognised |
| `status` | `issued` \| `partially_cancelled` \| `cancelled` |
| `currency`, `exchange_rate`, `base_currency` | mirrors `group_payments` exactly |
| `gross_fare_amount`, `tax_amount` | rolled up from tickets |
| `supplier_cost_amount`, `discount_amount`, `service_fee_amount` | rolled up from tickets |
| `buyer_total_amount` | what the buyer owes |
| `total_paid`, `balance` | maintained by the existing allocation machinery |
| `sale_transaction_id`, `cost_transaction_id` | GL idempotency links |
| `notes` | |

No `draft` status. A booking exists because a ticket was issued; a booking
nobody issued is not a record, it is an abandoned form.

### `umrah.tickets`

One passenger, one ticket. Carries the itinerary and its own money.

| Column | Notes |
|---|---|
| `id`, `company_id`, `booking_id` | |
| `ticket_number` | `TKT-00001`, internal, company-sequential |
| `airline_ticket_number` | the supplier's stock number, nullable until issued |
| `passenger_name`, `passenger_type` | `adult` \| `child` \| `infant` |
| `passport_number`, `date_of_birth` | nullable |
| `airline` | IATA code, 2 chars |
| `origin`, `destination` | IATA codes, 3 chars |
| `departure_at`, `return_at` | `return_at` nullable — one-way is normal |
| `route_description` | free text for multi-sector, nullable |
| `cabin_class` | `economy` \| `premium_economy` \| `business` \| `first` |
| `gross_fare`, `taxes` | what the fare is before anything |
| `supplier_cost` | what the company owes the supplier for this ticket |
| `discount`, `service_fee` | what the company gave away and what it charged |
| `buyer_amount` | `gross_fare + taxes - discount + service_fee` |
| `visa_group_id` | nullable — links a pilgrim's flight to their group |
| `status` | `issued` \| `cancelled` |

**Commission is derived, never stored.** It is
`(gross_fare + taxes) - supplier_cost`. Storing it would create a fifth figure
that can contradict the other four. It is computed for display and for the
supplier reconciliation report.

The `visa_group_id` link is nullable and costs one column. Umrah pilgrims fly,
and being able to see a group's flights beside its visas is most of why this
module is being built here rather than as a separate product.

### `umrah.ticket_cancellations`

| Column | Notes |
|---|---|
| `id`, `company_id`, `ticket_id` | |
| `cancellation_number` | `TCX-00001` |
| `cancelled_at`, `cancelled_by_user_id`, `reason` | reason required |
| `supplier_returns_amount` | what the supplier gives the company back |
| `buyer_returns_amount` | what the company gives the buyer back |
| `buyer_refund_id`, `supplier_refund_id` | the two legs it raised |

Both amounts are entered, not calculated. What the supplier withholds is the
supplier's decision and what the company passes on is the company's — the app
records an agreement, it does not compute one. This follows the existing refund
contract: *"It is not computed."*

A **void** — cancelling the same day, before the supplier bills — is this record
with nothing withheld. It is not a separate concept and gets no separate screen.

### Changes to `umrah.refunds`

- `party_type` gains `customer` and `ticket_supplier`
- `service` gains `ticket`
- `booking_id` and `ticket_id` added, nullable
- CHECK constraint: **at most one** of `visa_group_id`, `booking_id`,
  `ticket_id` is set — the same pattern shipped on `payment_allocations` in
  `2026_08_22_000001`. All three null remains legal; a refund need not belong to
  anything.

---

## 4. The postings

### Selling a ticket

Using the worked example: gross fare 85,000, taxes 12,400, supplier cost
91,000, discount 2,000, service fee 1,500. The buyer pays 96,900.

```
Dr  Accounts Receivable (buyer)        96,900
Dr  Ticket Discount        (4135)       2,000
    Cr  Ticket Revenue     (4130)                97,400
    Cr  Service Fee Income (4140)                 1,500

Dr  Ticket Cost            (5130)      91,000
    Cr  Accounts Payable (supplier)              91,000
```

Margin is 5,900 — revenue less discount less cost. Commission (6,400) is the gap
between gross sale and supplier cost; it is reported, not posted. Posting it
would double-count what 4130 and 5130 already say.

### Cancelling a ticket

The cancellation raises two refunds. Each travels the lifecycle that already
shipped — `requested → accepted → settled`, with request and approve held by
different people.

Worked example — a **different, single-ticket booking**, so the arithmetic below
stands on its own: the buyer paid 32,300, the supplier cost 30,400. The supplier
returns 20,300 and the buyer gets back 22,800.

**Buyer leg** — the company owes the buyer 22,800.

```
Accept    Dr  Ticket Revenue      (4130)   22,800
              Cr  Refunds Payable (2300)             22,800
```

Debiting revenue, not agent advances. This was an open question and this is the
answer: the existing agent refund debits `2200 Agent Advances`, which assumes
the buyer prepaid. A cancelled-before-payment booking is the common case, not
the edge one, and debiting revenue is correct either way — the sale is being
partly undone, whether or not cash ever arrived.

**Supplier leg** — the supplier owes the company 20,300.

```
Accept    Dr  Refunds Receivable  (1170)   20,300
              Cr  Ticket Cost     (5130)             20,300
```

**No commission clawback entry exists.** Commission is derived from revenue and
cost, and both have just moved. An explicit clawback would reverse the same
money twice. (An earlier draft of this design had one; it was wrong.)

### Settlement

Each leg settles independently, often weeks apart. Three routes each, uniform in
shape:

| Buyer leg (`Dr 2300`) | Credits |
|---|---|
| Pay cash | bank / cash |
| Keep as credit | `2200 Agent Advances` — **agents only**; a walk-in has no running account |
| Offset what they owe | Accounts Receivable |

| Supplier leg (`Cr 1170`) | Debits |
|---|---|
| Receive cash | bank / cash |
| Leave with supplier | `1160 Vendor Advances` |
| Offset what we owe | Accounts Payable |

Supplier-credit settlement is **new**. The refund contract sketched it and
declined to build it — *"not built, because it was not asked for"*. Ticketing
asks for it: B2B suppliers run standing accounts and net refunds off the next
purchase rather than wiring money. Recording that as cash received followed by
cash paid would invent two bank movements that never happened.

That contract section is updated by this work, not contradicted.

### What the cancellation cost

`supplier_returns_amount - buyer_returns_amount` — in the example, −2,500.

The ticket's margin was 1,900 and is now −600. Both figures are shown: the
change is what a manager asks about, the resulting margin is what the report
totals.

---

## 5. Screens

Following the existing module's shape, and the ledger design system.

- **Bookings** — index (`LedgerRegister`), show, create/edit. The show page is a
  document: booking header, ticket rows, money block, payments, cancellations.
- **Tickets** — no index of its own. A ticket is reached through its booking, or
  through search. It has a show page because a cancellation acts on one ticket.
- **Cancellation** — a dialog on the ticket, not a page. Two amounts and a
  reason.
- **Refunds** — the existing screens, widened. A refund now shows which booking
  or ticket it came from, and a cancellation's two legs each name the other.
- **Suppliers** — the renamed vendor screens, with the fourth type.

### Reports

Three, on the existing shared reports page — which means they inherit the date
validation, the PDF export and the pagination for free.

- **Ticket sales** — bookings in a period with margin and discount given.
- **Supplier reconciliation** — tickets by supplier with cost and derived
  commission, to check against the supplier's statement. This is the report that
  justifies the full money anatomy.
- **Cancellations** — what was cancelled and what it cost.

`TravelReportRequest::COMPANY_REPORTS` gains all three. Agents get none of them:
supplier cost and commission are company-only, as the schema contract requires.

## 6. Permissions

Following `app/Constants/Permissions.php` and the four-step RBAC process:

`UMRAH_TICKET_VIEW`, `UMRAH_TICKET_CREATE`, `UMRAH_TICKET_UPDATE`,
`UMRAH_TICKET_CANCEL`, `UMRAH_TICKET_OWN_VIEW`.

Cancellation is its own permission, separate from update. It moves money and
raises two refunds; whoever may correct a passenger name should not
automatically be able to do that.

Role assignment: owner and manager get all; accountant gets view, create,
update; operations gets view and create; agent gets `OWN_VIEW` only, scoped by
`Agent.user_id` the way group access already is.

## 7. Build order

Each step lands complete, verified, and committed before the next begins.

1. **Vendor rename.** No new behaviour. Full test suite must pass unchanged.
2. **COA accounts** + backfill migration + `accountId()` roles.
3. **Tables and models** — bookings, tickets, cancellations. RLS policies.
4. **Booking CRUD and the sale posting.** The module can sell a ticket.
5. **Payments** — verify the existing allocation machinery works against a
   booking with no changes. If it needs changes, that is a finding, not a task.
6. **Refund extension** — party types, service, target columns.
7. **Cancellation** — the record, both legs, settlement routes.
8. **Reports and permissions.**

## Testing

- The sale posting balances, for every combination of discount and service fee
  including both zero.
- Commission derives correctly and is never stored.
- A cancellation raises exactly two refunds and links them.
- Each of the six settlement routes posts a balanced entry.
- A cancelled-before-payment booking and a cancelled-after-payment booking both
  end with the right receivable.
- An agent sees only their own bookings and no supplier cost anywhere.
- The rename breaks nothing: the suite passes at 185/937 before step 2 starts.

## Deliberately not built

- **Airline or GDS integration.** No fare search, no issuance, no PNR sync.
- **Seat inventory or block bookings.**
- **Date changes / reissues.** A reissue is a cancellation plus a new ticket
  until someone asks otherwise.
- **Discount schemes per agent.** Discount is typed per ticket. A rule engine
  can come when there is a rule.
- **Multi-sector fare breakdown.** One fare per ticket; the route is text.
- **BSP / IATA settlement files.**

## Open

Nothing blocking. One thing to watch: `acct.customers` is the invoicing
module's table and this is the first time Umrah reaches across into `acct`. The
FK is legal and RLS applies on both sides, but step 3 should confirm the
customer selector honours company scope before it is trusted.
