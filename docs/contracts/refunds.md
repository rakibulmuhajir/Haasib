# Refunds

## The problem this exists to solve

Two completely different situations produce an identical number today, and the
app calls both of them "Unallocated advance":

- **An advance.** The agent paid ahead. Roll it onto the next group. The
  company keeps the cash.
- **A refund owed.** Something went wrong and money has to go back. That cash
  is not the company's.

The difference is *intent*, and intent has nowhere to live. Both appear as a
credit sitting in account 2200 (Agent Advances / Unearned Visa Revenue), and
both render on screen with the same words.

The consequence reaches the dashboard: `umrah.cash_position` cannot show a
"refunds owed" line, so its "Held for agents" figure silently includes money
the company has already agreed to hand back.

## What a refund is

**A refund is an obligation, not a payment.** It is to a payment what an
invoice is to its settlement: it exists first, it can be approved or refused,
and money moving is the *last* step, not the record itself.

This is the whole design. A refund that is only a payment cannot be counted
before it is paid, and a figure that only counts money already gone is not a
position — it is history.

## What a refund is not

- **It is not derived from a cancellation.** A refund can be a partial credit
  against a service that was delivered badly — two nights in the booked hotel
  and then a move elsewhere. There is no passenger to cancel and no count to
  multiply.
- **It is not computed.** The amount and any deduction are agreed between
  people, case by case. The system records the agreement; it does not
  calculate it.
- **It is not triggered automatically.** See "What is deliberately not built".

## Two directions

| | Agent refund | Vendor refund |
|---|---|---|
| Who is owed | The agent | The company |
| Nature | Liability | Asset |
| Control account | 2300 Refunds Payable | 1170 Refunds Receivable |
| Settled by | `GroupPayment` `direction = sent` | `GroupPayment` `direction = received` |
| Typical cause | Service delivered badly, or paid-for work never done | Visa never processed; vendor returns the cost |

They share one table, one lifecycle and one set of screens. `party_type`
distinguishes them and decides which accounts the postings touch. Building
them apart would mean building the same workflow twice.

## The record

`umrah.refunds`

| Column | Notes |
|---|---|
| `id` | uuid primary key |
| `company_id` | uuid, RLS scoped |
| `party_type` | `agent` \| `visa_vendor` \| `transport_vendor` \| `hotel_vendor` |
| `party_id` | uuid of the agent or vendor |
| `visa_group_id` | uuid, nullable — a refund need not belong to a group |
| `service` | `visa` \| `transport` \| `hotel` \| `other` — what was paid for and went wrong |
| `refund_number` | `URF-00001`, company-sequential, same generator as vouchers |
| `amount` | decimal(18,2), the agreed net figure. Always positive. |
| `currency`, `exchange_rate`, `base_currency`, `base_amount` | mirrors `GroupPayment` exactly |
| `reason` | required text. Why money is going back. |
| `status` | see lifecycle |
| `requested_by_user_id`, `requested_at` | |
| `approved_by_user_id`, `approved_at`, `approval_remarks` | |
| `settled_payment_id` | uuid, nullable — the `GroupPayment` that paid it |
| `transaction_id` | uuid, nullable — the approval's GL transaction |
| `cancelled_at`, `cancelled_by_user_id`, `cancellation_reason` | |

`amount` is a single agreed figure, not a gross-minus-deduction pair. Two
fields implying arithmetic that nobody performs would be theatre. Where a
cancellation charge was withheld, it goes in `reason` and the retained portion
is recognised separately (see Accounting).

## Lifecycle

```
requested ──approve──> approved ──settle──> paid
    │                      │
    └──reject──> rejected  └──cancel──> cancelled
```

- **requested** — recorded, no ledger effect. Anyone with `refund.create`.
- **approved** — posts to the ledger. This is the moment the money changes
  character. Requires `refund.approve`.
- **paid** — a `GroupPayment` settled it. Set by the settlement, not by hand.
- **rejected** — refused before approval. No ledger effect ever.
- **cancelled** — approved but reversed before payment. Posts a reversing
  entry. Requires `refund.cancel`.

This deliberately mirrors the `GroupPayment` submit → approve flow the
accountant already operates. A new workflow to learn is a cost; a familiar one
is free.

**An agent may request. Only an approver may approve.** That split is the
entire control — it is what stops an agent refunding themselves.

## Accounting

New accounts, added to the umrah COA template pack **and backfilled into
existing companies** (the pack is applied at company creation, so a migration
that only touches the template leaves every live company without them):

- **2300 Refunds Payable** — liability, normal balance credit
- **1170 Refunds Receivable** — asset, normal balance debit

### Agent refund

```
Approve   Dr 2200 Agent Advances        Cr 2300 Refunds Payable
Pay       Dr 2300 Refunds Payable       Cr cash / bank
Cancel    Dr 2300 Refunds Payable       Cr 2200 Agent Advances
```

Where a cancellation charge is retained, it is earned income and is recognised
as its own line at approval:

```
          Dr 2200 Agent Advances        Cr 4100 Revenue
```

### Vendor refund

```
Approve   Dr 1170 Refunds Receivable    Cr 5100/5110/5120 cost by service
Receive   Dr cash / bank                Cr 1170 Refunds Receivable
Cancel    Dr 5100/5110/5120             Cr 1170 Refunds Receivable
```

Crediting the cost account rather than booking income is the point: an
unprocessed visa was never a cost, so the correction belongs where the cost was
recorded. Service-to-account mapping reuses `UmrahCoreService::accountId()`
roles `visa_cost`, `transport_cost`, `hotel_cost` — do not introduce a second
mapping.

## The allocation problem

`UmrahCoreService::recalculateGroup()` computes `total_paid` from allocations
where `direction = received` **only**. A refund paid to an agent therefore does
not reduce it, and a group settled by refund would sit at a permanent negative
balance.

**Decision: de-allocate, then refund.** Reversing the specific allocation
returns the credit to 2200 and lets `recalculateGroup()` arrive at the right
answer with no change to what `total_paid` means.

The alternative — netting refunds inside `recalculateGroup()` — is a smaller
diff and a worse one: `total_paid` would mean two things at once and every
report reading it inherits the ambiguity.

This needs one new primitive: **reversing a single allocation without reversing
its whole payment.** `PaymentAllocation` already has `reversed_at`,
`reversed_by_user_id`, `reversal_reason` and `reversal_transaction_id`, and
`UmrahCoreService::reversePayment()` already posts the reversing entry and
recalculates the affected groups. Only the entry point is missing. Extract it;
do not duplicate it.

A refund against a group **must** reverse the corresponding allocation before
it can be approved. A refund with no `visa_group_id` skips this entirely.

## What is deliberately not built

- **No automated triggers.** A rejected visa is *not* refundable — the vendor
  consumed the fee. An unprocessed one is. Nothing in `Passenger.visa_status`
  distinguishes "the vendor was paid and failed" from "the vendor was never
  engaged", so the system cannot know. It shows the status as context beside
  the decision and lets a person decide. A system that guesses here creates
  refunds that should not exist.
- **No default cash-out.** Rolling credit forward onto the next group is the
  default and already works. A refund record exists only when someone asks for
  money back. Nothing about today's behaviour changes.
- **No computed deduction.** Case by case, entered by hand.

## Permissions

`umrah.refund.view`, `umrah.refund.create`, `umrah.refund.approve`,
`umrah.refund.cancel`.

Role assignment in `config/role-permissions.php`:

| Role | view | create | approve | cancel |
|---|---|---|---|---|
| owner | ✓ (all) | ✓ | ✓ | ✓ |
| manager | ✓ | ✓ | ✓ | ✓ |
| accountant | ✓ | ✓ | ✓ | ✓ |
| operations | ✓ | ✓ | — | — |
| agent | ✓ own | ✓ own | — | — |

An agent's view is scoped to their own records by
`TravelAccessService::scopeAgentRecords`, exactly as payments are.

## Invariants

1. `amount` is always positive. Direction is carried by `party_type`, never by
   a sign. This is the same rule the ledger grammar applies on screen.
2. Every status transition that touches the ledger happens inside one DB
   transaction with `lockForUpdate`, matching `reversePayment()`.
3. A refund cannot be approved for more than the credit available to that
   party. For an agent that is the 2200 balance attributable to them; for a
   vendor it is what was actually paid on the service.
4. An approved refund's `amount`, `party_id` and `service` are immutable. Cancel
   and re-request instead — the same rule an approved voucher already follows.
5. The settling payment's `base_amount` must equal the refund's `base_amount`.
   Partial settlement is not supported; issue two refunds instead.

## Phases

**0 — Name the credit.** No schema. Credit balances stop reading as
"Unallocated advance" and read as credit held awaiting a decision; the payment
form warns on overpayment instead of absorbing it silently. Ships alone and is
worth shipping alone.

**1 — The obligation.** Table, model, statuses, permissions, request and
approve screens, accountant queue. No money moves.

**2 — Settlement.** Single-allocation reversal extracted as a primitive, the
two new accounts with backfill, the postings above, `GroupPayment` linked to
the refund it settles.

**3 — Surfacing.** Fourth line on `umrah.cash_position` ("Refunds owed", from
2300), refunds-awaiting-approval on the accountant's tab, and "Held for agents"
narrowed to exclude what 2300 now holds.
