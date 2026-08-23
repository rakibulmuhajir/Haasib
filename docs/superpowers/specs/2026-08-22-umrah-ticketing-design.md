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
| Supplier record | **`acct.vendors`**, unchanged. No rename, no profile table. |
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

**Buyer invoice** — `acct.invoices`, three lines plus an invoice-level discount
(the mechanism is spelled out below):

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

Clearing nets to zero — see §3 for why. Reported revenue is net and correct:

```
Commission        6,400
Service fee       1,500
Discount         (2,000)
=================  =====
Net revenue       5,900
```

### How this posts, precisely

An earlier draft called this "four ordinary invoice lines." **That was wrong,
and the wrongness is worth being specific about**, because the shape it implies
is unbuildable against the current code and would have been discovered mid-build.

What the existing engine actually does, verified in `PostingService`:

| Mechanism | Behaviour |
|---|---|
| Invoice line | **Always credited** to `income_account_id` (`buildInvoiceEntries`) |
| Bill line | **Always debited** to `expense_account_id` (`buildBillEntries`) |
| Invoice-level `discount_amount` | **Debited** to the template's `DISCOUNT_GIVEN` role |
| AR debit | `total_amount`, i.e. subtotal + tax − discount |

So the mechanics fit the posting above exactly — a credited clearing line and a
debited discount are both native behaviour. **The discount is not a line.** It is
the invoice's `discount_amount`, with `DISCOUNT_GIVEN` mapped to `4150`:

```
Three lines            98,900   (91,000 clearing + 6,400 commission + 1,500 fee)
Invoice discount_amount 2,000   → Dr 4150
AR                     96,900   = 98,900 − 2,000        ✓ balances
```

**What genuinely blocks it is validation, not arithmetic:**

- `StoreInvoiceRequest` requires `income_account_id` to be `type = revenue`.
  `2350` is a liability.
- `StoreBillRequest` allows `expense_account_id` in `expense | cogs | asset`.
  `2350` is a liability.
- `unit_price` has `min:0` on both, so no negative line is possible anyway.

A service-layer command bypasses FormRequests entirely, so ticketing *could*
simply write the models. **It must not.** A liability that is legal in a field
called `income_account_id` when Umrah writes it and illegal when a person writes
it is not a rule, it is a loophole, and nothing at the database level would stop
the next author from widening it.

**Instead: a ticket posting adapter over the existing GL engine.** `PostingService`
already resolves a `PostingTemplate` by `doc_type`, so ticketing adds
`TICKET_INVOICE` and `TICKET_BILL` templates carrying the existing `CLEARING`
role alongside `AR`, `AP`, `REVENUE` and `DISCOUNT_GIVEN`. The clearing leg comes
from the template role, not from a line account. Ticket
invoices and bills stay ordinary subledger documents — aging, allocation and
statements all work — while the clearing leg is supplied by a posting strategy
that knows what a ticket is.

This is a specialised template, not a second ledger. The account-type validators
stay exactly as strict as they are.

### Cancelling a ticket

A cancellation raises **one credit note and one vendor credit**. Neither is a
refund. Refunds exist only for residual cash actually going back.

```
Buyer side     acct.credit_notes    for buyer_returns_amount
               applied to the ticket invoice

Supplier side  acct.vendor_credits  for supplier_returns_amount
               applied to the supplier bill
```

**The GL entries, which the first draft left undefined:**

```
Buyer credit note
Dr  Ticket Cancellation Adjustments (4160)    buyer return
    Cr  Accounts Receivable                              buyer return

Supplier vendor credit
Dr  Accounts Payable                          supplier return
    Cr  Ticket Cancellation Adjustments (4160)           supplier return
```

The net debit left in `4160` is `buyer return − supplier return` — **the
cancellation cost, falling out of the ledger rather than being computed beside
it**. That is the figure the cancellations report reads.

These post through the same adapter as the sale: `TICKET_CREDIT_NOTE` and
`TICKET_VENDOR_CREDIT` templates with a `CANCELLATION_ADJUSTMENT` role. The
existing credit documents cannot carry it themselves — `CreditNoteItem` has no
account field at all, and `CreditNote` stores only `base_currency` with no
`currency` or `exchange_rate`. Both are additions this work must make, not
assumptions it may lean on.

### A zero leg creates nothing

Both credit links are **nullable**, because either side can return nothing:

```
buyer return    > 0  →  buyer credit note      ;  otherwise null
supplier return > 0  →  supplier vendor credit ;  otherwise null
```

The command creates each document, and its application, only when the amount is
above zero. A supplier that withholds the whole fare produces a cancellation with
a buyer credit note and no vendor credit, and that is a complete record, not a
half-written one. An earlier draft marked both links required while also saying a
zero leg raises nothing — the two could not both be true.

### Applying a credit

```
amount applied  = min(return amount, current document balance)
residual credit = return amount − amount applied
```

**A fully paid invoice applies zero and leaves the whole amount as available
credit. That is the correct outcome, not an error.** `CreditNote\ApplyAction`
currently throws on a `paid` invoice and on `amount > balance`, which is right
for a person applying a credit by hand and wrong for a cancellation, where the
amount is set by what the supplier returned. The cancellation command computes
the applied amount from the formula above and applies only that; the residual
stays on the credit note.

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

`buyer_returns_base - supplier_returns_base` — the net debit left in `4160`. In
base, for the same reason commission is: the two sides may be in different
currencies. The company loses when it hands back more than it gets back, so the
buyer leg is the one that comes first. Reported on the
cancellation and in the cancellations report. It is the number a manager asks
about and it exists nowhere today.

## 3. Currency

`docs/contracts/multicurrency-rules.md` is **LOCKED**. This design complies with
it; it does not amend it.

- Revenue and contra-revenue accounts are **base-currency only** — the contract
  forbids foreign currency on revenue, COGS and expense accounts. `4130`, `4140`,
  `4150` and `4160` are therefore base-only.
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

**They net to zero because both legs are written from the same immutable
`supplier_cost_base`** — not because the two documents share a rate. They do not
share a rate: the invoice and the bill each carry their own, and saying otherwise
was a real error in an earlier draft.

Four rules make it exact:

1. `2350` is a **base-denominated** clearing account. It is not a USD account or
   a SAR account, and no foreign-currency balance is ever parked in it.
2. The invoice clearing leg is booked at exactly `supplier_cost_base`.
3. The bill clearing leg is booked at exactly `supplier_cost_base`.
4. `supplier_cost_base` is computed once, in the §5 command, and is immutable
   thereafter.

Clearing therefore closes at **exactly zero**, per ticket, by construction.

This is also why §5 requires the invoice and the bill to be created by one atomic
command. Supplier cost is known at the instant of issue — the consolidator portal
charges it, or the card is debited. There is no window in which the company has
sold a ticket but does not yet know what it cost.

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

Because both clearing legs use the same `supplier_cost_base`, they cannot
disagree — the rounding happens once, before either leg is written.

Where rounding can still bite is the **invoice as a whole** when the buyer is
billed in a third currency: subtotal, discount and total each round to two
decimals in the sale currency and again into base, and the sum of the parts can
miss the whole by `0.01`. That difference goes to a designated **rounding
account**, named in the posting template as a `ROUNDING` role, in the same entry.

**Tolerance in clearing is zero, not "under one unit."** A per-ticket tolerance of
PKR 0.99 is real money at volume and hides the failure it was meant to absorb. A
non-zero clearing balance is a defect and fails a test.

## 4. Tables

### `umrah.ticket_bookings`

Operational only. It holds no balance, no `total_paid`, no transaction link —
Accounting owns all of that.

| Column | Notes |
|---|---|
| `id`, `company_id` | uuid, RLS scoped |
| `booking_number` | `TKB-00001`, company-sequential |
| `customer_id` | uuid → `acct.customers`, **not null** |
| `agent_id` | uuid → `umrah.agents`, nullable |
| `supplier_vendor_id` | uuid → `acct.vendors` |
| `invoice_id` | uuid → `acct.invoices` |
| `bill_id` | uuid → `acct.bills`, **not null** — see §3 |
| `idempotency_key` | text, **unique per company** — see §5 |
| `pnr` | supplier booking reference, nullable |
| `booking_date` | |
| `status` | `issued` \| `partially_cancelled` \| `cancelled` |
| `created_by_user_id`, `updated_by_user_id` | |

`invoice_id` and `bill_id` are each **unique** — one booking per document, both
ways. Without that, a retry that slipped past the key could attach a second
booking to the same invoice and no constraint would notice.

**Real foreign keys, no polymorphism.** A `buyer_type`/`buyer_id` pair cannot be
constrained by the database and was a mistake in the first draft.

**The two columns are not alternatives.** An earlier draft had
`CHECK (num_nonnulls(agent_id, customer_id) = 1)`, which contradicted this
spec's own decision that every buyer is an `acct.customer`. They answer different
questions:

- `customer_id` — **who is billed.** Always present. The accounting party.
- `agent_id` — **who sold it**, when an agent did. The operational relationship,
  and what scopes an agent's own view.

A walk-in booking has a customer and no agent. An agent booking has both, and the
command enforces `booking.customer_id === agent.customer_id`; a mismatch is a bug,
not a configuration.

**Every agent is linked to an `acct.customer`** for ticket billing — a nullable
`customer_id` on `umrah.agents`, created on demand the first time an agent is
billed for a ticket.

**That link does not by itself produce one statement, and this spec does not
claim it does.** Visa balances stay in the Umrah subledger while ticket balances
live in Accounting, so an agent has two balances in two places until visas move
to `acct`. A combined agent statement is **out of scope here and named in "Not
built"** — the split is temporary and documented rather than papered over.

### `umrah.tickets`

| Column | Notes |
|---|---|
| `id`, `company_id`, `booking_id` | |
| `ticket_number` | `TKT-00001`, internal. **Unique per company.** |
| `airline_ticket_number` | supplier stock number, nullable. **Unique per company when set.** |
| `passenger_id` | uuid → `umrah.passengers`, nullable |
| `passenger_name`, `passenger_type` | **name is a snapshot**, always stored |
| `passport_number` | nullable — see below |
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

**`date_of_birth` is dropped and `passport_number` is kept, narrowly.** This
scope is accounting; neither figure appears in a posting. But a ticket is matched
to a supplier statement and to a visa by passport number, and staff read it off
the ticket every day — it earns its place. A date of birth does not: it is
personal data with no reconciliation use here, and the passenger record already
holds it for anyone who needs it.

### `umrah.ticket_cancellations`

| Column | Notes |
|---|---|
| `id`, `company_id`, `ticket_id` | **Unique on `ticket_id`** — one cancellation per ticket |
| `cancellation_number` | `TCX-00001` |
| `idempotency_key` | text, **unique per company** |
| `cancelled_at`, `cancelled_by_user_id`, `reason` | reason required |
| `supplier_returns_amount` | supplier currency, entered not computed |
| `buyer_returns_amount` | sale currency, entered not computed |
| `supplier_returns_base`, `buyer_returns_base` | at the credit documents' rates |
| `buyer_credit_note_id` | uuid → `acct.credit_notes`, nullable, **unique** |
| `supplier_vendor_credit_id` | uuid → `acct.vendor_credits`, nullable, **unique** |
| `buyer_refund_id` | nullable — `acct.customer_refunds`, see below |
| `supplier_refund_receipt_id` | nullable — `acct.vendor_refund_receipts`, see below |

### Residual settlement lives in Accounting, and does not exist yet

The first draft named these two columns as if they pointed at something. **They
did not.** `umrah.refunds` is built around agents and Umrah vendors, with credit
ceilings and party types that know nothing about `acct.customers`,
`acct.vendors`, or foreign currency. Pointing a ticket cancellation at it would
mean either a walk-in customer that the refund table cannot represent, or a
second refund concept wearing the first one's name.

**Decision: extend Accounting, not `umrah.refunds`.** Two small documents,
`acct.customer_refunds` and `acct.vendor_refund_receipts`, settling a residual
credit balance in cash:

```
Customer refund       Dr Credit balance / AR      Cr bank
Vendor refund receipt Dr bank                     Cr Credit balance / AP
```

This keeps financial ownership inside `acct`, where the credit balance being
settled already lives. Extending `umrah.refunds` instead would drag the Umrah
subledger into `acct` parties — the exact coupling this design spent §1 and §2
avoiding.

**These are build steps, not existing machinery**, and they are sized as such in
§8. Umrah's own visa and hotel refunds are untouched and keep `refunds.md`
exactly as it stands.

Both amounts are entered. What the supplier withholds is the supplier's decision
and what the company passes on is the company's; the app records an agreement, it
does not compute one. This follows `refunds.md`: *"It is not computed."*

A **void** — a same-day full cancellation before either side has settled — is
this record with nothing withheld. It cannot be "before the supplier bills": §3
makes the bill simultaneous with the sale. Not a separate concept, not a separate screen.

## 5. Atomicity and immutability

**One command creates a booking.** Booking, tickets, invoice, bill and postings
are created inside one transaction. Half a booking is a reconciliation problem
that outlives whoever created it.

**Idempotency is a unique column, not a cache.** The existing command
idempotency cache expires after 24 hours, which makes it a duplicate-submit
guard rather than a guarantee. `idempotency_key` is a real unique constraint on
the booking and cancellation rows, so a replay at any distance in time fails at
the database and returns the original record.

**Numbering is serialised.** `TKB-`, `TKT-` and `TCX-` sequences are allocated
under a row lock on the company's counter, with the unique constraint as the
backstop and a bounded retry on collision. Two people creating bookings in the
same second is the ordinary case in an office, not a race worth discovering in
production.

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
- **Suppliers** — `acct.vendors`, unchanged. Ticketing adds no screen here.

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

1. **Contracts.** `umrah-schema.md` (three new tables), `refunds.md` (ticket
   residuals settle in `acct`, and Umrah's own refunds are unchanged),
   `accounting-invoicing-contract.md` (ticket posting templates, credit-note
   currency fields, the two new settlement documents), `coa-schema.md` (the new
   accounts).
2. **Chart of accounts** — `4130`, `4140`, `4150`, `4160`, `2350` and a
   `ROUNDING` account added to the umrah COA pack **and backfilled into existing
   companies**. The pack applies at company creation, so a template-only
   migration leaves every live company without them.
3. **Posting templates and the ticket adapter.** **The load-bearing step; §2 and
   §3 are unbuildable without it**, and it comes before any ticket table so the
   posting shape is proven first.

   Doc types follow the existing enum style — `TICKET_INVOICE`, `TICKET_BILL`,
   `TICKET_CREDIT_NOTE`, `TICKET_VENDOR_CREDIT`, not the lowercase names an
   earlier draft used. Adding them means touching four places, all of which
   reject these templates today:

   - `posting_templates_doc_type_chk` — currently nine values, none of them ours.
   - `posting_template_lines_role_chk` — needs `CANCELLATION_ADJUSTMENT` and
     `ROUNDING`.
   - `StorePostingTemplateRequest`.
   - `PostingTemplateValidator::requiredRoles()` — add the four doc types, which
     otherwise fall to `default => []` and require nothing at all.

   **Two things do not need changing, and the spec should not pretend they do.**
   `CLEARING` is already an allowed role and already falls through
   `validateRoleAccountCompatibility` to `default => true`, so `2350` works as a
   liability with no widening — there is no need to invent `SUPPLIER_CLEARING`.
   And `DISCOUNT_GIVEN` already accepts `type = revenue`, so `4150` as
   contra-revenue validates as it stands.
4. **Credit-note currency fields** — `currency` and `exchange_rate` on
   `acct.credit_notes`, and an account field on credit-note and vendor-credit
   items. Existing rows are base-currency by definition, so the backfill is
   `currency = base_currency, exchange_rate = NULL`.
5. **Agent → customer link.** Nullable `customer_id` on `umrah.agents`, plus
   on-demand creation.
6. **Tables and models**, with RLS policies, audit triggers, and every unique and
   CHECK constraint named above.
7. **The booking command** — atomic, idempotent on a unique key, creates invoice
   and bill.
8. **The cancellation command** — atomic, idempotent, credit note and vendor
   credit with applications, using the `min(return, balance)` rule.
9. **Residual settlement** — `acct.customer_refunds` and
   `acct.vendor_refund_receipts`, built here, not assumed.
10. **Reports and permissions.**

## Testing

- The buyer invoice balances and the clearing account nets to **exactly** zero
  for every combination of discount and service fee, including both zero.
- Clearing closes at **exactly zero** with a foreign-currency supplier bill —
  PKR invoice, USD bill — because both legs use one `supplier_cost_base`.
- Buyer in SAR, supplier in USD, base PKR: clearing still closes at exactly zero,
  and any `0.01` invoice-level difference lands in the rounding account.
- Net revenue equals commission + fee − discount, always, in base.
- Commission derives in base and is never stored.
- A cancellation against an unpaid, a part-paid and a fully-paid invoice each
  produce the right AR and the right residual credit. **The fully-paid case
  applies zero and succeeds** — it is not an error.
- `4160` nets to the cancellation cost, and the report figure equals the ledger
  balance.
- The account-type validators still reject a liability on a manual invoice or
  bill line — the adapter widens nothing.
- Retrying the booking command with the same idempotency key creates one
  invoice, not two — including after 24 hours, when a cache would have expired.
- Two concurrent bookings get distinct numbers.
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
- **A ticketing supplier profile.** An earlier draft mentioned one as "optional"
  without defining it, which is the same as not having designed it.
  `acct.vendors` carries everything ticketing needs today — name, AP account,
  currency, contact. **Dropped.** When a real ticketing-only attribute appears
  (IATA code, BSP participation), it gets a table and a reason then.
- **A combined agent statement.** Visa balances stay in the Umrah subledger and
  ticket balances in Accounting, so an agent has two balances until visas move to
  `acct`. Named in §4 as a known, temporary split.
- **Moving visa groups onto `acct`.** The right long-term direction, and
  explicitly not in this blast radius. Ticketing is built the way visas should
  eventually be, which makes that migration easier rather than harder.

## Open

Nothing blocking. One thing to confirm during the build: that the customer
selector honours company scope the first time Umrah reaches into
`acct.customers`.
