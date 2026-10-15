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
| 2 | Manual journal "Post immediately" saves as Draft; drafts can't be posted from the UI | major | fix dispatched |
| 3 | Bank reconciliation 500: `acct.bank_reconciliation_items` missing | blocker on dev | **Not a code bug** — the dev DB has 17 pending migrations (incl. `2026_09_28_100000_bank_reconciliation_book_lines`). Run `php artisan migrate` on dev when you choose. |
| 4 | Bank accounts page shows opening balances, not ledger; "All reconciled" + "Never reconciled" together | major | fix dispatched |
| 5 | Bill saves with total 0.00 | major | fix dispatched |
| 6–8 | Fuel/umrah wording and fields in a general company (balance sheet "tanks", bill edit "tank", supplier-claims description, invoice "Slip no." / "Slip on another day", expenses "Daily Close", customer type "Agent", customer "Units" card, transit labels) | minor | cleanup dispatched |
| 9 | New company lands on dashboard, not onboarding | minor | open |
| 10 | Fiscal year label "FY 2026-2027" for a Jan–Dec year; "2 Bank Accounts" counts cash | minor | open |
| 11 | Bill numbering ignores the onboarding start number (BILL-00001 vs chosen 1001) | minor | open |
| 12 | Home: duplicate "Where you stand" heading; "Across 2 accounts" with no balance | cosmetic | cleanup dispatched |
| 13 | Chart of accounts grouping: tax/accrued/payroll liabilities under "Loans and Line of Credit"; empty groups; codes not shown | minor | open |
| 14 | Opening balances: bank row needs "Add bank account"; pickers say "Start typing"; two toasts on save | minor | open |
| 15 | "Mark as sent" stays after sending | minor | fix dispatched |
| 16 | Vendor vs Supplier vs Buyer wording | minor | cleanup dispatched (per one-word-per-entity) |
| 17 | Bill edit / bill payment pages on the older UI; unformatted amounts; raw ISO date in payment invoice row; US placeholders | minor | ISO date fix dispatched; rest open |
| 18 | Activity feed omits the rent expense; shows unpaid bill as outflow | minor | open |
| 19 | Closing a period: no confirmation; no year-end close; fiscal years not in menu | minor | open |
| 20 | Expenses and Fiscal Years missing from menus; banking menu naming | minor | open |
