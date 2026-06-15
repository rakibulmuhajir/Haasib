# Fuel Daily Close Next Steps

Keep daily close simple for operators, but make the accounting lifecycle complete.

## Priority 1: Daily Close Correction Flow

- Prevent duplicate posting for the same date unless using amendment/correction flow.
- Keep posted closes read-only.
- Reverse posted entries instead of deleting or editing GL.
- Let users copy a reversed close into a new correction draft.
- Show the amendment chain clearly in close history and close detail.

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
