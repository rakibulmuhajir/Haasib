# Umrah booking cancellation and readiness

## Scope

This feature set covers operational confirmation of company-arranged hotel and transport bookings, cancellation tracking, and the owner-facing readiness summary on the Groups list. It does not change prices, invoices, payments, supplier costs, or passenger-facing voucher content.

## Booking status

Each hotel stay and transport booking has its own status:

- **Pending** — the request exists but the provider has not confirmed it.
- **Confirmed** — the provider accepted it; a reference is optional.
- **Needs reconfirmation** — a material booking detail changed after confirmation.
- **Cancelled — replacement needed** — the provider booking was cancelled. Staff record the reason and supplier acknowledgement; the replacement is a new confirmation decision.
- **Not recorded** — legacy or incomplete data that needs operational review.

Cancellation is deliberately not a refund. It does not reverse an accounting entry or return money. A user with refund permission can open the existing refund workflow separately. This keeps the operational fact (the provider booking ended) separate from the commercial decision (whether money is refundable and who absorbs a fee).

Transport provider acceptance is also separate from vehicle/driver dispatch. The standard bus booking is shown at group level; specialized transport is shown per transport item/sector. Dispatch details can therefore be completed without falsely treating an unconfirmed provider booking as ready.

## Owner Groups readiness

The **Booking readiness** column is separate from payment status and is visible to owners/staff who can supervise the company. It follows the current approved travelling vouchers and each passenger's original service ownership:

- **Green — Ready:** every required company-arranged hotel and transport booking is confirmed.
- **Orange — Attention:** a required booking is pending/reconfirmation/cancelled and arrival is more than 72 hours away.
- **Red — Urgent:** a required booking is unresolved and arrival is within 72 hours (including exactly 72 hours) or overdue.
- **Grey — Review/not applicable:** no current travelling voucher, missing arrival, unrecorded booking, completed/cancelled journey, or no company-arranged service to confirm.

The most urgent unresolved booking determines the group color. Payment status remains its own column. Moving a passenger between travelling parties changes which future service bookings the readiness calculation follows, but never moves past visa/transport accounting.

## Acceptance checklist

1. Confirm and cancel a hotel stay; verify reason and supplier acknowledgement are visible in staff history and absent from passenger print output.
2. Confirm and cancel standard-bus and specialized transport; verify replacement rows are new pending decisions.
3. Change a material hotel/transport detail after confirmation; verify **Needs reconfirmation** appears.
4. Verify cancellations do not create refunds, reverse charges, or alter payment balances.
5. Open Groups as owner/staff and verify green, orange, red, and grey cases. At the 72-hour boundary the unresolved case is red.
6. Verify the group readiness follows the travelling voucher and original service owner after a cross-agent passenger move.
7. Verify a restricted agent cannot see company-wide readiness or another agent's private booking notes.

## Verification evidence

The local regression covering hotel confirmations, transport confirmations, readiness, Operations, transfers, voucher amendments, draft editing, print profiles, and accounting passed **209 tests / 2,104 assertions**. The production frontend build and targeted lint also pass. The additive transport metadata migration and voucher metadata migration are applied locally only; this feature set has not been deployed.

