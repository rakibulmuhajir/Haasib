# Fuel Daily Close Next Steps

Keep daily close simple for operators, but make the accounting lifecycle complete.

## Priority 1: Daily Close Correction Flow (superseded 2026-09-17)

Superseded by the owner's decision that a posted Daily Close is never reversed and
re-posted. What shipped instead: every close is a snapshot close; corrections are dated
adjustments layered on top (declared expenses, reading corrections, late canonical
activity captured in `fuel.daily_close_activity`) rather than a reversal+correction pair.
`Transaction::isAmendable()` now always returns false and the amend/reversal code path
(`DailyCloseAmendmentService`, the `/amend` routes and page, the amendment chain UI) has
been removed. See docs/contracts/fuel-schema.md, "Standalone fuel-sale invoices as a
close channel" and the Daily Close reconciliation section for the mechanism that replaced
this.

## Priority 2: Payment Settlement

- Add a simple settlement screen for POS, fuel-card, wallet, and vendor-card clearing.
- Settlement without fees: Dr Bank, Cr Clearing.
- Settlement with fees: Dr Bank, Dr Bank Charges, Cr Clearing.
- Show unsettled clearing balances by channel.

## Priority 3: Cash Deposit Flow

- Allow cash drawer deposits to bank outside daily close when needed.
- Posting: Dr Bank, Cr Cash on Hand.
- Show current Cash on Hand balance before deposit.

## Priority 4: Inventory And Margin Checks

- Confirm each fuel product posts to its own Sales, COGS, and Inventory accounts.
- Report by fuel type: sales, liters, COGS, gross profit, and margin.
- Reconcile tank inventory reduction against sold liters and shrinkage/gain.

## Priority 5: Payroll And Daily Close Cash

- Salary advance paid from daily close: Dr Employee Advances, Cr Cash on Hand.
- Salary recovery in payroll: Cr Employee Advances as part of payslip posting.
- Show employee outstanding advance balance while entering daily close.

## Priority 6: Live Daily Close Helpers

- Search/select partners, investors, Amanat holders, and employees from live company data.
- Show current balance/exposure next to each selected person.
- Keep accounting hints inline and short, not as a separate accounting lesson.
