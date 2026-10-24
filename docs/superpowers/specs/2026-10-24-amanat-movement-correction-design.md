# Amanat movement: open and correct

**Status:** approved by owner 2026-10-24. **Paths** are relative to `build/`.

## Why
Amanat deposits, withdrawals and fuel purchases are listed on the holder's page (`fuel.amanat.show`,
`modules/FuelStation/Resources/js/pages/FuelStation/Amanat/Show.vue`), but rows can't be opened or
corrected the way invoices and bills can. A deposit booked to the wrong holder, or a standalone
entry with a wrong amount, has no fix in the app today. (Owner rule: fix data through the app,
never with hidden edits.)

## How amanat is stored (facts, verified)
- Books: one Amanat liability account. Standalone movements (`AmanatService::deposit/withdraw/...`,
  routes `fuel.amanat.deposit|withdraw`) post their own GL transaction (`transaction_type`
  `amanat_deposit` / `amanat_withdrawal` / `amanat_fuel_purchase`, `reference_type`
  `fuel.amanat_transactions`).
- Daily Close movements are lines inside the day's `fuel_daily_close` transaction; the
  `fuel.amanat_transactions` row points at its line by `journal_entry_id`.
- Per-holder balance = `fuel.customer_profiles.amanat_balance` (cached; `adjustAmanatBalance()`),
  built from `fuel.amanat_transactions` rows (`customer_id`, `transaction_type`, `amount`).
- A posted Daily Close keeps its form in `metadata.form_input.amanat_deposits` (used to prefill a
  reopen/amendment); `DailyCloseReopenService::reverseAmanat` deletes the close's amanat rows and
  undoes their balance effect.

## What to build
1. **Open a movement.** Each row on the holder page opens a detail view (a Sheet or its own
   page, builder's choice) showing date, type, amount, payment account, reference, notes,
   recorded by, the GL transaction number (link to `/{company}/journals/{id}`), and its source:
   "Daily Close <date>" (link to that day's close) or "Entered on this page".
2. **Change holder** (any source, any type): pick another amanat holder.
   - Move the row's `customer_id`; adjust both profiles' `amanat_balance` (deposit: −old +new;
     withdrawal/fuel purchase: +old −new).
   - Refuse if it would take a holder below zero unless that holder has `allow_amanat_borrowing`,
     same rule and message style as a withdrawal.
   - Daily Close source: also update the matching row in that close's
     `metadata.form_input.amanat_deposits` so a reopen shows the new holder.
   - Update the GL line descriptions' holder name (cosmetic). Books totals don't change.
3. **Edit amount / date / payment account / reference / notes** — standalone only.
   Under the hood: reverse the original GL transaction (use `JournalReversalService`), mark the
   original row voided and pointing at its replacement, undo its balance effect, and post the
   corrected movement through `AmanatService` (same validation as a new one). History shows the
   new row; the voided original stays, greyed, labelled "Corrected".
   Daily Close source: no in-place edit of amount/date/account; show "Correct in Daily Close"
   linking to that day (reopen/amend flow).
4. **Audit:** each change records who/when/what (use the app's existing audit mechanism).
5. **Permissions:** new `amanat.update` (add to `app/Constants/Permissions.php`, `config/role-permissions.php`
   for the roles that hold `amanat.deposit`, then `rbac:sync-*` per CLAUDE.md — document, don't run against prod).

## Schema
`fuel.amanat_transactions` gains `voided_at timestamptz null`, `voided_by_user_id uuid null`,
`replaced_by_id uuid null` (self FK). Migration follows the module's migration/RLS conventions.
Update `docs/contracts/fuel-schema.md`. Balances, Daily Close sums, reports and statements that
read `amanat_transactions` must exclude voided rows — find every reader.

## Non-goals
No amount edits on Daily Close rows in place. No bulk edit. No hard delete.
Standalone fuel purchases can't be amended (amount/date/account), and their holder can't be changed when they came from a fuel sale (they carry that sale's invoice): "This came from a fuel sale. Correct the sale instead."

## Anti-goals
- Holder balance and the books disagreeing after any change.
- A voided row still counted anywhere.
- A reopen of a Daily Close bringing back the old holder.
