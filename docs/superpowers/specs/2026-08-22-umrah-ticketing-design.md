# Umrah Ticketing — Design

**Date:** 2026-08-22 · revised 2026-08-23 after accounting review
**Status:** Draft for review
**Scope:** The accounting of air ticket sales. No booking engine, no airline API, no seat inventory.

## The three parties

Every sentence below uses these words and no synonyms.

| Word | Who |
|---|---|
| **Company** | The Haasib tenant. The travel business using the app. |
| **Supplier** | Who the company gets tickets from — an airline, or a B2B consolidator. |
| **Buyer** | Who the company sells to — an agent on account, or a walk-in customer. |

```
Supplier  ──────────►  Company  ──────────►  Buyer
```

## Why this exists

The Umrah module sells visas, transport and hotels. It cannot sell a ticket, so
ticket sales are kept outside the app and reconciled by hand.

Ticketing is not a visa group with different labels. A visa group has one sale
price and one supplier cost. A ticket has five money lines, because the company
earns from the supplier and gives part of that away to win the sale. A design
that collapses those into "price minus cost" cannot answer the two questions
that matter: what did we give away, and can we reconcile against the supplier's
statement.

## Decisions

| Question | Decision |
|---|---|
| Revenue basis | **Agent — net revenue.** Phase 1 invariant. |
| Who buys | Agents and walk-in customers, both as `acct.customers`. |
| Money lines | Full trade anatomy — gross fare, taxes, supplier cost, discount, service fee. |
| Record shape | A booking holds tickets. |
| Where the money lives | **`acct.invoices` and `acct.bills`.** The booking is operational only. |
| Supplier record | **`acct.vendors`**, with an optional ticketing profile. No rename. |
| Cancellation | **Credit note + vendor credit.** Refunds only for residual cash. |
| Atomicity | Booking, invoice, bill and postings created by **one command, idempotently**. |

### What changed from the first draft, and why

The first draft had the company as principal, a bespoke Umrah subledger, a
70-file vendor rename, and a cancellation that always raised two refund records.
All four were wrong. The review that found them is the reason this section
exists — a spec that quietly replaces its own history teaches nobody.

- **Principal → agent.** See below.
- **Umrah subledger → `acct`.** A CHECK constraint on `group_payments` forbids a
  received payment without an agent, so a walk-in customer literally could not
  pay. That was discoverable before the draft was written.
- **Vendor rename dropped.** Once suppliers are `acct.vendors`, renaming
  `umrah.visa_vendors` is unrelated work. It may still be worth doing; it is not
  this.
- **Two refunds → one credit note and one vendor credit.** The old design
  created a refund liability even when the buyer still owed money, overstating
  AR and refunds payable simultaneously.

---

## 1. Revenue basis: agent

**The company arranges air travel; it does not control a seat.** Revenue is what
the company keeps — commission plus service fee, less discount given.

This is a **Phase 1 invariant, not a per-booking setting.** Seat blocks,
inventory risk and booking control are explicitly out of scope, so the agent
conclusion follows from the scope rather than from a judgement someone makes per
sale. A dropdown offering principal or agent per booking would be an invitation
to choose whichever produces a nicer revenue figure.

If a company later genuinely buys seat inventory at its own risk, principal
accounting is added as a **separate capability** with its own posting path, not
by loosening this one.

Bearing the buyer's credit risk does not make the company principal. An agent
who invoices a buyer carries that receivable too. Control of the seat is the
test, and the company never has it.

**Consequence: there is no ticket COGS account.** Supplier cost is a payable
that passes through a clearing account, never an expense.

## 2. The postings

### Selling a ticket

Worked example: gross fare 85,000, taxes 12,400, supplier cost 91,000, discount
2,000, service fee 1,500. The buyer pays 96,900.

**Buyer invoice** — `acct.invoices`, four lines, each carrying its own
`income_account_id`:

```
Dr  Accounts Receivable                    96,900
Dr  Ticket Discount            (4150)       2,000
    Cr  Ticket Supplier Clearing (2350)             91,000
    Cr  Ticket Commission Revenue (4130)             6,400
    Cr  Ticket Service Fee Revenue (4140)            1,500
```

**Supplier bill** — `acct.bills`:

```
Dr  Ticket Supplier Clearing   (2350)      91,000
    Cr  Accounts Payable                            91,000
```

Clearing nets to zero — always, because §5 creates both documents in one
transaction at one rate. Reported revenue is net and correct:

```
Commission        6,400
Service fee       1,500
Discount         (2,000)
=================  =====
Net revenue       5,900
```

`InvoiceLineItem.income_account_id` already exists, so this is four ordinary
invoice lines — no bespoke journal entry and no new posting engine.

**To verify in step 4:** whether the invoice posting path accepts a negative
line (the discount) or whether the discount must use the invoice-level
`discount_amount` field, and if so which account that posts to. This decides
whether `4150` is reachable as a line account or needs a different mechanism.

### Cancelling a ticket

A cancellation raises **one credit note and one vendor credit**. Neither is a
refund. Refunds exist only for residual cash actually going back.

```
Buyer side     acct.credit_notes    for buyer_returns_amount
               applied to the ticket invoice

Supplier side  acct.vendor_credits  for supplier_returns_amount
               applied to the supplier bill
```

This handles every payment state without branching:

| Invoice state | What the credit note does |
|---|---|
| Unpaid | Reduces AR. Nothing else happens. |
| Part-paid | Clears the remaining AR; the excess stays as customer credit. |
| Fully paid | The whole amount becomes customer credit. |
| Buyer wants cash | A refund settles the remaining customer credit. |

The supplier side mirrors it exactly through AP and vendor credit.

**"Exactly two refunds" was wrong** and is replaced by "one credit note and one
vendor credit; settlement records are optional and created later." A zero-value
leg raises nothing at all — `payment_allocations` already carries
`CHECK (base_amount > 0)`, so a zero refund is not representable and should not
be invented.

### What the cancellation cost

`supplier_returns_base - buyer_returns_base`. In base, for the same reason
commission is: the two sides may be in different currencies. Reported on the
cancellation and in the cancellations report. It is the number a manager asks
about and it exists nowhere today.

## 3. Currency

`docs/contracts/multicurrency-rules.md` is **LOCKED**. This design complies with
it; it does not amend it.

- Revenue and contra-revenue accounts are **base-currency only** — the contract
  forbids foreign currency on revenue, COGS and expense accounts. `4130`, `4140`
  and `4150` are therefore base-only.
- `2350 Ticket Supplier Clearing` is an **Other Current Liability**, so the
  contract permits it to hold foreign currency.
- The buyer invoice and the supplier bill carry **independent currencies and
  independent rates**. A booking does not have "a" currency. The buyer may be
  billed PKR while the supplier charges USD, and that is the normal case, not an
  edge one.
- Base amounts are immutable once posted. Rule 3: *"Exchange rates on posted
  transactions never change."*

### Base currency holds the two sides together

The journal is base-only at `numeric(15,2)`. A USD supplier bill is logged with
`base_amount` in the company's base currency, and so is the buyer invoice. So the
clearing account is a **base-currency comparison** between two documents that may
each have been quoted in something else.

```
Supplier bill    USD    325.00  × 280.00  →  PKR 91,000.00 base
Buyer invoice    PKR  96,900.00 (base)    →  clearing line PKR 91,000.00
                                             ────────────────
Clearing balance                             PKR      0.00
```

**The two sides net to zero because the design converts them at the same rate in
the same transaction.** That is not an accident and it is not an assumption to be
monitored — it is why §5 requires the invoice and the bill to be created by one
atomic command. Supplier cost is known at the instant of issue: the consolidator
portal charges it, or the card is debited. There is no window in which the
company has sold a ticket but does not yet know what it cost.

**An earlier draft made `bill_id` nullable "until the supplier bills". That was
the flaw.** An invoice posted today at 280 and a bill posted next week at 284
would leave a residual in `2350` that nothing ever clears — not FX, just a hole
that grows one ticket at a time. The bill is therefore **created with the
booking, never later**. A supplier's own paperwork may arrive whenever it likes;
the amount does not wait for it.

### The FX that does exist

Ordinary and already handled. The USD bill sits in AP as a foreign-currency
payable. Paying it recognises realized FX through the existing bill-payment path,
per the contract's *"only realized FX on payments"*. Ticketing builds nothing for
this and changes nothing about it.

### Rounding

When the buyer invoice is in a **third** currency — buyer billed SAR, supplier
charging USD, base PKR — both convert to base independently and rounding to two
decimals can leave a sub-unit residual in clearing. That is rounding, not FX.
Tolerance is one base-currency unit per booking; anything larger is a defect and
should fail a test, not be written off.

## 4. Tables

### `umrah.ticket_bookings`

Operational only. It holds no balance, no `total_paid`, no transaction link —
Accounting owns all of that.

| Column | Notes |
|---|---|
| `id`, `company_id` | uuid, RLS scoped |
| `booking_number` | `TKB-00001`, company-sequential |
| `agent_id` | uuid → `umrah.agents`, nullable |
| `customer_id` | uuid → `acct.customers`, nullable |
| `supplier_vendor_id` | uuid → `acct.vendors` |
| `invoice_id` | uuid → `acct.invoices` |
| `bill_id` | uuid → `acct.bills`, **not null** — see §3 |
| `pnr` | supplier booking reference, nullable |
| `booking_date` | |
| `status` | `issued` \| `partially_cancelled` \| `cancelled` |
| `created_by_user_id`, `updated_by_user_id` | |

**Real foreign keys, no polymorphism.** `agent_id` and `customer_id` are both
nullable with `CHECK (num_nonnulls(agent_id, customer_id) = 1)`. A
`buyer_type`/`buyer_id` pair cannot be constrained by the database and was a
mistake in the first draft.

**Every agent is linked to an `acct.customer`** for ticket billing. That link is
added to `umrah.agents` as a nullable `customer_id`, created on demand the first
time an agent is billed for a ticket. Without it an agent has two identities and
two statements.

### `umrah.tickets`

| Column | Notes |
|---|---|
| `id`, `company_id`, `booking_id` | |
| `ticket_number` | `TKT-00001`, internal. **Unique per company.** |
| `airline_ticket_number` | supplier stock number, nullable. **Unique per company when set.** |
| `passenger_id` | uuid → `umrah.passengers`, nullable |
| `passenger_name`, `passenger_type` | **name is a snapshot**, always stored |
| `passport_number`, `date_of_birth` | nullable |
| `airline`, `origin`, `destination` | IATA codes |
| `departure_at`, `return_at` | `return_at` nullable |
| `route_description` | free text for multi-sector, nullable |
| `cabin_class` | `economy` \| `premium_economy` \| `business` \| `first` |
| `gross_fare`, `taxes`, `discount`, `service_fee` | in the **sale** currency |
| `gross_fare_base`, `taxes_base`, `discount_base`, `service_fee_base` | |
| `supplier_cost` | in the **supplier** currency |
| `supplier_cost_base` | |
| `visa_group_id` | nullable |
| `status` | `issued` \| `cancelled` |

Dual recording per the multicurrency contract: every figure is stored in its
document's currency **and** in base. The rates live on the invoice and the bill;
tickets do not carry their own.

**Commission is derived in base, never in a document currency:**

```
commission_base = (gross_fare_base + taxes_base) - supplier_cost_base
```

Subtracting a USD supplier cost from a PKR fare is meaningless, and a design that
stores the five figures without their base counterparts invites exactly that
subtraction. It is derived and never stored — a sixth figure could contradict the
other five.

`passenger_id` **plus** a name snapshot, not `visa_group_id` alone — a group link
cannot say which pilgrim holds the ticket. The snapshot survives the passenger
record being corrected or the group being restructured.

### `umrah.ticket_cancellations`

| Column | Notes |
|---|---|
| `id`, `company_id`, `ticket_id` | **Unique on `ticket_id`** — one cancellation per ticket |
| `cancellation_number` | `TCX-00001` |
| `cancelled_at`, `cancelled_by_user_id`, `reason` | reason required |
| `supplier_returns_amount` | supplier currency, entered not computed |
| `buyer_returns_amount` | sale currency, entered not computed |
| `supplier_returns_base`, `buyer_returns_base` | at the credit documents' rates |
| `buyer_credit_note_id` | uuid → `acct.credit_notes` |
| `supplier_vendor_credit_id` | uuid → `acct.vendor_credits` |
| `buyer_refund_id` | nullable — only if residual settled in cash |
| `supplier_refund_receipt_id` | nullable — same, other direction |

Both amounts are entered. What the supplier withholds is the supplier's decision
and what the company passes on is the company's; the app records an agreement, it
does not compute one. This follows `refunds.md`: *"It is not computed."*

A **void** — same-day cancellation before the supplier bills — is this record
with nothing withheld. Not a separate concept, not a separate screen.

## 5. Atomicity and immutability

**One command creates a booking.** Booking, tickets, invoice, bill and postings
are created inside one transaction, keyed by an idempotency token so a retried
submit cannot produce two invoices. Half a booking is a reconciliation problem
that outlives whoever created it.

**One command cancels a ticket.** Cancellation, credit note, vendor credit, both
applications and the ticket status change are one atomic, idempotent unit.

**Issued records are not freely editable.**

| Change | How |
|---|---|
| Passenger name, passport, itinerary | Edit in place, audited via `TravelChangeLogger` |
| Any monetary field | **Not editable.** Cancel and re-issue, or raise an adjustment. |

An issued ticket's fare has already been invoiced and billed. Letting someone
retype it silently desynchronises three documents.

**Period lock is validated** before any posting, using the existing
`AccountingPeriod` check. A booking dated into a closed period is refused with a
reason, not posted.

## 6. Screens

- **Bookings** — index (`LedgerRegister`), show, create. The show page is a
  document: header, ticket rows, money block, and links to its invoice and bill.
- **Tickets** — reached through the booking. Own show page, because cancellation
  acts on one ticket.
- **Cancellation** — a dialog on the ticket. Two amounts and a reason.
- **Suppliers** — `acct.vendors`, with the ticketing profile on the vendor page.

All following `docs/ledger-design-system.md`: Shadcn components, Inertia forms,
`useLexicon()` for terminology, Sonner for server errors, inline errors for
validation, explicit loading states on every submit.

### Reports

Three, on the existing shared Umrah reports page, inheriting its date
validation, PDF export and pagination.

- **Ticket sales** — bookings in a period, with commission, discount and net
  revenue.
- **Supplier reconciliation** — tickets by supplier with cost and derived
  commission, to check against the supplier's statement. Shows the clearing
  balance as a control figure: it should read zero, and a non-zero balance means
  a booking was posted through a path that bypassed the command in §5.
- **Cancellations** — what was cancelled and what it cost.

`TravelReportRequest::COMPANY_REPORTS` gains all three. Agents get none:
supplier cost and commission are company-only.

## 7. Permissions

`UMRAH_TICKET_VIEW`, `UMRAH_TICKET_CREATE`, `UMRAH_TICKET_UPDATE`,
`UMRAH_TICKET_CANCEL`, `UMRAH_TICKET_OWN_VIEW`.

Cancellation is its own permission. It moves money and raises two accounting
documents; whoever may correct a passenger name should not automatically do that.

Owner and manager get all. Accountant gets view, create, update. Operations gets
view and create. Agent gets `OWN_VIEW` only, scoped by `Agent.user_id`.

Added via the four-step RBAC process in `CLAUDE.md`.

## 8. Build order

**Contracts before migrations**, per `CLAUDE.md`.

1. **Contracts.** `umrah-schema.md` (three new tables), `refunds.md` (refunds are
   now residual-only for tickets), `accounting-invoicing-contract.md` (ticket
   invoices and their line accounts), `coa-schema.md` (four new accounts).
2. **Chart of accounts** — `4130`, `4140`, `4150`, `2350` added to the umrah COA
   pack **and backfilled into existing companies**. The pack applies at company
   creation, so a template-only migration leaves every live company without them.
3. **Agent → customer link.** Nullable `customer_id` on `umrah.agents`, plus
   on-demand creation.
4. **Tables and models**, with RLS policies, audit triggers, and the unique and
   CHECK constraints named above. Verify the discount line question from §2.
5. **The booking command** — atomic, idempotent, creates invoice and bill.
6. **The cancellation command** — atomic, idempotent, credit note and vendor
   credit with applications.
7. **Residual refunds** — only the remainder after offsets, through the existing
   refund lifecycle.
8. **Reports and permissions.**

## Testing

- The buyer invoice balances and the clearing account nets to **exactly** zero
  for every combination of discount and service fee, including both zero.
- Clearing nets to zero when the supplier bill is in a foreign currency —
  PKR invoice, USD bill — because both convert at the same rate in one
  transaction.
- Buyer in SAR, supplier in USD, base PKR: the clearing residual is under one
  base unit. Larger fails.
- Net revenue equals commission + fee − discount, always, in base.
- Commission derives in base and is never stored.
- A cancellation against an unpaid, a part-paid and a fully-paid invoice each
  produce the right AR and the right residual credit.
- A zero-value leg raises no refund record.
- Retrying the booking command with the same idempotency token creates one
  invoice, not two.
- Paying a foreign-currency supplier bill at a moved rate recognises realized FX
  through the existing path, and does not touch clearing.
- A closed period refuses the posting with a reason.
- An agent sees only their own bookings and no supplier cost anywhere.

## Deliberately not built

- **Principal accounting.** A separate capability if a company ever buys seat
  inventory at its own risk.
- **Airline or GDS integration**, seat inventory, block bookings.
- **Date changes / reissues.** A reissue is a cancellation plus a new ticket.
- **Discount schemes per agent.** Typed per ticket; a rule engine when there is a
  rule.
- **Multi-sector fare breakdown.** One fare per ticket; route is text.
- **BSP / IATA settlement files.**
- **Moving visa groups onto `acct`.** The right long-term direction, and
  explicitly not in this blast radius. Ticketing is built the way visas should
  eventually be, which makes that migration easier rather than harder.

## Open

Nothing blocking. Two things to settle during the build, both named above: the
discount-line mechanism in step 4, and confirming the customer selector honours
company scope the first time Umrah reaches into `acct.customers`.
