# Story of Accounting

_A practical walkthrough of the Haasib accounting schemas for non-accountants._

---

## 1. Meet the World

| Layer | Schema(s) | What lives here? |
| --- | --- | --- |
| Identity & Tenancy | `core` | Companies, users, roles, teams |
| Accounting Backbone | `acct` | Fiscal years, periods, chart of accounts, journal entries |
| Selling (Accounts Receivable) | `ar` | Customers, price lists, sales invoices, receipts |
| Buying (Accounts Payable) | `ap` | Vendors, bills, purchase orders, vendor payments |
| Banking & Cash | `bank` | Bank accounts, statements, reconciliations |
| Taxes | `tax` | Tax rates, groups, filings |
| Posting Guards | `posting` | Rules that validate every GL move |
| Reporting | `reporting`, `fin_reports` | Trial balance, financial statements, dashboards |

Everything else—inventory, payroll, CRM—plugs into these foundations later, but the story below focuses on the core accounting flow.

---

## 2. Chapter One – A Company Is Born

1. **Register the company**
   - Insert into `core.companies` with name, currency, timezone.
   - Attach the first admin user via `core.company_users`.

2. **Open the master books**
   - Create a fiscal year (`acct.fiscal_years`) with start/end dates.
   - Auto-generate twelve monthly accounting periods in `acct.accounting_periods`.
   - Mark the current period as `open`; everything else is `future` until you reach them.

3. **Build the chart of accounts**
   - Seed core account types (`asset`, `liability`, `equity`, `revenue`, `expense`) from `acct.account_classes`.
   - Create detailed accounts in `acct.accounts` (e.g., 1000 Cash, 1200 Accounts Receivable).
   - Group them with `acct.account_groups` so reports make sense.

4. **Define posting rules**
   - Enable posting profiles in `posting.ruleset_headers` (e.g., “Sales Invoice Posting”).
   - Each profile points to debit/credit mappings in `posting.ruleset_lines`.

At this point the books are empty but structured. The company can now record any financial event without breaking GAAP/IFRS rules.

---

## 3. Chapter Two – Preparing to Trade

1. **Products & Services (optional)**
   - If you sell items, define them in `inventory.items`.
   - Link revenue and cost accounts so invoices can post automatically.

2. **Payment terms**
   - Create terms like “Net 30” in `core.payment_terms` and reuse them for customers and vendors.

3. **Taxes**
   - Configure VAT/GST rates in `tax.tax_codes` and map them to accounts (sales tax payable, input tax).

4. **Bank accounts**
   - Register company bank accounts in `bank.bank_accounts`; they become GL accounts as well.

The foundation now understands what you sell, how you collect cash, and where taxes land.

---

## 4. Chapter Three – Working With Customers (Accounts Receivable)

1. **Create customers**
   - Insert records into `ar.customers` with contact details, billing currency, and credit limits.
   - Optional supporting tables: `ar.customer_addresses`, `ar.customer_contacts`.

2. **Quote → Order → Invoice** (simplified)
   - Quotes live in `ar.sales_quotes` (optional), orders in `ar.sales_orders`.
   - The important bit: a sales invoice in `ar.sales_invoices`.
     - Header fields include customer, invoice date, due date, currency.
     - Lines go into `ar.sales_invoice_lines` with quantities, unit price, tax code.

3. **Posting the invoice**
   - When the invoice status changes to `posted`, a journal entry is created in `acct.journal_entries`:
     - Debit `Accounts Receivable` (customer’s control account).
     - Credit `Sales Revenue` (and `Tax Payable` if any).
   - Posting metadata is stored in `posting.posting_queue` for audit.

4. **Collecting payment**
   - Record receipts in `ar.receipts` (cash, bank transfer, etc.).
   - Allocate the receipt to open invoices via `ar.receipt_applications`.
   - Posting rule: Debit `Cash/Bank`, Credit `Accounts Receivable`.

5. **Customer statements & aging**
   - Aging buckets live in `ar.customer_balances` and `ar.aging_summaries`.
   - Reports pull from these tables plus the GL.

Now the customer side is humming: every invoice increases receivables; every receipt reduces them and boosts cash.

---

## 5. Chapter Four – Working With Vendors (Accounts Payable)

1. **Create vendors**
   - Records in `ap.vendors` with payment terms, tax IDs, and default expense accounts.
   - Contacts and addresses stored similarly to customers (`ap.vendor_contacts`).

2. **Capture bills**
   - Bills land in `ap.vendor_bills`.
     - Each line (`ap.vendor_bill_lines`) references an expense or inventory account, plus tax codes.

3. **Posting the bill**
   - Debit `Expense` (or inventory/asset), credit `Accounts Payable`.
   - Uses `posting` rules to ensure the correct accounts fire.

4. **Paying vendors**
   - Create payments in `ap.vendor_payments`.
   - Allocate them to outstanding bills through `ap.payment_applications`.
   - Posting: Debit `Accounts Payable`, Credit `Cash/Bank`.

5. **Track balances**
   - Aging tables (`ap.vendor_balances`, `ap.aging_summaries`) mirror the AR side.

At this point cash is moving out to suppliers, while expenses and assets accumulate in the books.

---

## 6. Chapter Five – Cash, Banks, and Expenses

1. **Bank statements**
   - Import statements into `bank.bank_statements` and lines into `bank.bank_statement_lines`.
   - Reconcile each line against receipts, payments, or journal entries (`bank.reconciliations`).

2. **Petty cash & expense claims**
   - Employees submit claims in `expenses.expense_reports` (if module enabled).
   - Approved expenses post to the GL: Debit expense, Credit payable (employee or corporate card).

3. **Cash forecasts**
   - Combine open AR invoices and AP bills to predict cash positions; stored in reporting tables.

---

## 7. Chapter Six – General Ledger & Journal Entries

1. **Automatic postings**
   - Every AR/AP/Bank action pushes entries into `acct.journal_entries` with lines in `acct.journal_entry_lines`.
   - The `posting` schema enforces balancing rules and prevents closed-period postings.

2. **Manual journals**
   - Accountants can create adjustments via `acct.journal_batches` and `acct.manual_journal_entries`.
   - Examples: depreciation, accruals, payroll summaries.

3. **Audit trail**
   - Each journal line stores the source document (`source_type`, `source_id`).
   - `acct.audit_log` keeps snapshots of changes for compliance.

4. **Trial balance**
   - Summaries stored in `reporting.trial_balance` for quick queries.
   - Balanced? Assets = Liabilities + Equity. If not, the posting guards will raise errors before closing periods.

---

## 8. Chapter Seven – Period Close & Reporting

1. **Review open items**
   - Ensure all invoices and bills are posted, payments applied, and bank reconciliations complete.

2. **Adjustments**
   - Post accruals and corrections through manual journals.

3. **Close the period**
   - Update `acct.accounting_periods.is_closed = true` for the month.
   - Lock AR/AP documents so they cannot be backdated into closed periods.

4. **Financial statements**
   - Income Statement (`fin_reports.income_statement_lines`): Pulls revenue and expense accounts.
   - Balance Sheet (`fin_reports.balance_sheet_lines`): Uses asset, liability, equity balances.
   - Cash Flow Statement (`fin_reports.cash_flow_lines`): Derived from GL movements.

5. **Management dashboards**
   - KPIs stored in `reporting.kpi_snapshots` (e.g., DSO, DPO, cash runway).

6. **Tax filings**
   - Sales tax reports draw from `tax.tax_transactions` aggregated by code, ready for submission.

Period close completes the monthly loop; year-end follows the same process but closes the fiscal year and rolls retained earnings into equity.

---

## 9. Chapter Eight – Year-End & Beyond

1. **Year-end adjustments**
   - Depreciation, inventory counts, payroll accruals—posted via journals.

2. **Close fiscal year**
   - Run the retained earnings transfer: sum net income into the `Retained Earnings` equity account.
   - Mark `acct.fiscal_years.is_closed = true` and open the next year/periods.

3. **Archive & audit**
   - Export GL and supporting documents; auditors trace entries back through source IDs.
   - The `posting` schema plus `acct.audit_log` ensures nothing changes without a trail.

4. **Continuous improvements**
   - Add advanced modules: inventory costing (`inventory_costing`), payroll (`payroll`), CRM-driven revenue forecasts (`crm`).

---

## 10. Recap – The Flow at a Glance

1. **Company registers** → fiscal year & chart of accounts.
2. **Products, taxes, banks configured**.
3. **Customers** create invoices → **Receipts** → post to AR & GL.
4. **Vendors** submit bills → **Payments** → post to AP & GL.
5. **Bank** data reconciled, **expenses** recorded.
6. Every event creates **journal entries** → collected in the **General Ledger**.
7. **Periods close**, **reports** generated, **audits** satisfied.

That’s the entire rabbit hole—from the first company record to year-end close—expressed through the Haasib schemas. Each schema acts like a chapter of the story, and every table is a character keeping the books accurate, auditable, and ready for decision-making.
