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
| `reviewed_by_user_id`, `reviewed_at`, `review_remarks` | One trio records the decision whichever way it went — accepted or rejected. Status alone distinguishes the two outcomes. |
| `settled_payment_id` | uuid, nullable — the `GroupPayment` that paid it |
| `transaction_id` | uuid, nullable — the approval's GL transaction |
| `cancelled_at`, `cancelled_by_user_id`, `cancellation_reason` | |

`amount` is a single agreed figure, not a gross-minus-deduction pair. Two
fields implying arithmetic that nobody performs would be theatre. Where a
cancellation charge was withheld, it goes in `reason` and the retained portion
is recognised separately (see Accounting).

## Lifecycle

```
                                        ┌──settle in cash──> refunded
requested ──approve──> accepted ────────┤
    │                      │            └──settle as credit─> credited
    └──reject──> rejected  └──cancel──> cancelled
```

- **requested** — recorded, no ledger effect. Anyone with `refund.create`.
- **accepted** — posts to the ledger. This is the moment the money changes
  character. Requires `refund.approve`.
- **refunded** — cash went back. Set by the settlement, not by hand.
- **credited** — settled by leaving the money with the party as an ordinary
  advance. Also set by the settlement.
- **rejected** — refused before acceptance. No ledger effect ever.
- **cancelled** — accepted but reversed before settlement. Posts a reversing
  entry. Requires `refund.cancel`.

The state is `accepted`, not `approved`, and the difference is not cosmetic.
Approving a voucher authorises work. Accepting a refund is the company
agreeing that it owes something. The person still *approves* — the action, the
permission and the method keep that name — but what the record then *is* is a
debt the company has taken on, and the chip should say so.

Approving and rejecting are both a decision, made by the same person acting
under the same permission, and both are recorded in the same
`reviewed_by_user_id` / `reviewed_at` / `review_remarks` trio. A refusal is
not a non-event: the app must show who declined a refund exactly as plainly
as it shows who approved one.

This deliberately mirrors the `GroupPayment` submit → approve flow the
accountant already operates. A new workflow to learn is a cost; a familiar one
is free.

**An agent may request. Only an approver may approve.** That split is the
entire control — it is what stops an agent refunding themselves.

## Settling a refund

An accepted refund is a debt. It can be discharged two ways, and **which one
is chosen at settlement, not at acceptance** — accepting is the company
agreeing it owes the money; how it hands it over is a later conversation,
usually had on WhatsApp days afterwards.

- **Refunded** — cash goes back. `Dr 2300 / Cr cash or bank`.
- **Kept as credit** — the money stays with the party as an ordinary advance,
  available against their next group. `Dr 2300 / Cr 2200`.

**`credited` and `cancelled` post the identical entry and mean opposite
things.** Cancelled: the company changed its mind and owes nothing. Credited:
the company honoured the refund in full and the money is staying put by
agreement. Same debits, opposite histories. Nothing may collapse them into one
state on the grounds that the ledger cannot tell them apart — that is exactly
why the status column exists.

### What credit does next needs nothing new

Once the money is back in 2200 unallocated, it is an ordinary agent advance,
and every existing path already applies to it:

- **Applying it to a new group** is the payment-allocation screen that already
  exists. De-allocating during the refund returns the credit to the original
  payment's unallocated pool, and that pool is what the allocation screen
  spends.
- **Turning it into cash later** is another refund request against that
  credit, travelling this same lifecycle. The agent asks, the company accepts,
  the accountant settles. The loop closes on itself.

One rough edge, named rather than hidden: allocation hangs off a *payment*, so
applying credit means finding the payment it came from rather than starting
from the agent. That is a discoverability problem, not a missing capability,
and it is not solved here.

### Only agent refunds may be settled as credit

A vendor refund settled as credit would be `Dr 1160 Advances to Visa Vendors /
Cr 1170` — coherent, and the company genuinely may leave money with a vendor
against future work. It is not built, because it was not asked for. The path
is written down here so that building it later is a small decision rather than
a rediscovery.

## Accounting

New accounts, added to the umrah COA template pack **and backfilled into
existing companies** (the pack is applied at company creation, so a migration
that only touches the template leaves every live company without them):

- **2300 Refunds Payable** — liability, normal balance credit
- **1170 Refunds Receivable** — asset, normal balance debit

### Agent refund

```
Accept    Dr 2200 Agent Advances        Cr 2300 Refunds Payable
Refund    Dr 2300 Refunds Payable       Cr cash / bank
Credit    Dr 2300 Refunds Payable       Cr 2200 Agent Advances
Cancel    Dr 2300 Refunds Payable       Cr 2200 Agent Advances
```

Where a cancellation charge is retained, it is earned income and is recognised
as its own line at approval:

```
          Dr 2200 Agent Advances        Cr 4100 Revenue
```

### Vendor refund

```
Accept    Dr 1170 Refunds Receivable    Cr 5100/5110/5120 cost by service
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
   party, and the two directions mean different things by "available". For an
   agent it is the 2200 balance attributable to them — what they paid in
   excess of what they owe. For a vendor there is no excess to isolate: it is
   what was actually paid on the service, full stop. The canonical vendor
   refund is a visa fee paid but never processed, where the recorded cost
   equals the amount paid — an overpayment-style ceiling would read that as
   zero credit and block the one case this feature exists for.
4. An accepted refund's `amount`, `party_id` and `service` are immutable. Cancel
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
the refund it settles, and the two settlement outcomes — cash back, or kept as
credit. Only when this lands may a chip read "Refunded": a settlement status
with no ledger entry behind it is a lie on screen.

**3 — Surfacing.** Fourth line on `umrah.cash_position` ("Refunds owed", from
2300), refunds-awaiting-approval on the accountant's tab, and "Held for agents"
narrowed to exclude what 2300 now holds.
