# General company walkthrough — 2026-10-16

A headless browser run through one month as a new "General business" company on the dev
database (slug `walkthrough-general-co`, branch `core-independence`). Screenshots are kept outside the
repo in `D:\projects\haasib-walkthrough-2026-10-16\` (94 PNGs, named by step).

## Numbers check — consistent (as at 2026-10-16)

| Report | Result |
|---|---|
| Trial balance | 585,000 = 585,000 |
| Balance sheet | Assets 552,000 = Liabilities 120,000 + Equity 432,000 (OBE 310,000 + profit 122,000) |
| P&L 1–16 Oct | Income 155,000 − expenses 33,000 = 122,000 (depreciation missing: journal stuck in draft, see 2) |
| Receivables aging | 85,000 |
| Payables aging | 20,000 |
| Opening balances | Assets 430,000, liabilities 120,000, opening equity 310,000 |

## Findings and what happened to them

| # | Finding | Severity | Status |
|---|---|---|---|
| 1 | **Server runs UTC; Pakistan is UTC+5.** From 00:00–05:00 local, "today" is yesterday: payment forms reject the browser's date, reports default to yesterday and drop today's entries, home "This month" shows 0 | major | **Left for you** — needs a timezone decision (app `timezone` is `UTC`; `auth.companies.timezone` exists, default `UTC`). Recommendation: set each company's timezone (Asia/Karachi) and resolve "today" per company in validation (`before_or_equal:today`) and report defaults, rather than flipping the app timezone globally. |
| 2 | Manual journal "Post immediately" saves as Draft; drafts can't be posted from the UI | major | **Fixed** `0e8612f75`: the form's `post` field was shadowed by Inertia's `form.post()`; added `journal.post` + Post button for drafts (reuses `journal.create` permission) |
| 3 | Bank reconciliation 500: `acct.bank_reconciliation_items` missing | blocker on dev | **Not a code bug** — the dev DB has 17 pending migrations (incl. `2026_09_28_100000_bank_reconciliation_book_lines`). Run `php artisan migrate` on dev when you choose. |
| 4 | Bank accounts page shows opening balances, not ledger; "All reconciled" + "Never reconciled" together | major | **Fixed** `26859bdf4`: balance from posted ledger entries; total sums; "Never reconciled" |
| 5 | Bill saves with total 0.00 | major | **Fixed** `2b906b3b2`: bills and invoices need a subtotal above zero |
| 6–8 | Fuel/umrah wording and fields in a general company (balance sheet "tanks", bill edit "tank", supplier-claims description, invoice "Slip no." / "Slip on another day", expenses "Daily Close", customer type "Agent", customer "Units" card, transit labels) | minor | **Fixed** `5d950a146` (gated to the module; units card shows for fuel or when a customer already has units) |
| 9 | New company lands on dashboard, not onboarding | minor | **Fixed** `22ad42cac`: a company with no module landing page redirects to `/onboarding`; the red name field is the autofocus ring, not validation |
| 10 | Fiscal year label "FY 2026-2027" for a Jan–Dec year; "2 Bank Accounts" counts cash | minor | **Fixed** `46095ba23`: one `FiscalYear::labelFor` rule; summary says "2 accounts" |
| 11 | Bill numbering ignores the onboarding start number (BILL-00001 vs chosen 1001) | minor | **Fixed** `2f9671f47`: bills use the saved prefix and start number for the first bill; existing bills keep theirs |
| 12 | Home: duplicate "Where you stand" heading; "Across 2 accounts" with no balance | cosmetic | **Fixed** `5d950a146` |
| 13 | Chart of accounts grouping: tax/accrued/payroll liabilities under "Loans and Line of Credit"; empty groups; codes not shown | minor | **Fixed** `a8e062f4c`: other liabilities grouped by name (taxes, payroll, other), empty groups hidden, code shown |
| 14 | Opening balances: bank row needs "Add bank account"; pickers say "Start typing"; two toasts on save | minor | Toast **fixed** `df646b415`; rest open |
| 15 | "Mark as sent" stays after sending | minor | **Fixed** `8cba23806` |
| 16 | Vendor vs Supplier vs Buyer wording | minor | **Open — your call**: menus/forms say Vendor/Customer, reports say Supplier/Buyer; no doc fixes the word for general accounting |
| 17 | Bill edit / bill payment pages on the older UI; unformatted amounts; raw ISO date in payment invoice row; US placeholders | minor | ISO date **fixed** `b7e2a9be5`; rest open |
| 18 | Activity feed omits the rent expense; shows unpaid bill as outflow | minor | open |
| 19 | Closing a period: no confirmation; no year-end close; fiscal years not in menu | minor | Close confirmation **fixed** `4b81930f8`; Fiscal Years in menu `6b9e9bcb5`; no year-end close yet |
| 20 | Expenses and Fiscal Years missing from menus; banking menu naming | minor | Fiscal Years and Expenses in menu **fixed** `6b9e9bcb5`; banking menu naming open |
