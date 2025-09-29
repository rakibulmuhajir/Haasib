# Business Journey – EAK Golden Hospitality

_A guided, story-like walkthrough of how Salmi the accountant runs EAK Golden’s books for a full year using the Haasib accounting platform._

Characters:
- **Salmi** – lead accountant, loves tidy ledgers and coffee at 4 p.m.
- **EAK Golden Hotel** – 10 floors, 80 rooms (5 reserved for staff housing).
- **Owner/Vendor** – E.A.K. Holdings (also called “EAK”), leases the hotel block to EAK Golden.
- **Customers** – Dunya Travels, ASB Travels, Meezab Group (bulk travel partners).
- **Residents** – Ahmad Yamni (tuck shop tenant), Saeed (restaurant tenant), Kareem the barber.

Constants & Assumptions:
- Base currency: **SAR** (Saudi Riyal).
- Most customer payments arrive in **PKR** at an average exchange rate of **78 PKR = 1 SAR**.
- Retail room rate: **100 SAR/night**; bulk rate: **80 SAR/night** (minimum 30 nights).
- Lease/purchase tariff paid to E.A.K.: **60 SAR/night** per room for 365 days.
- Payroll: 4 cleaners × 1,500 SAR/month, 2 receptionists × 2,500 SAR/month, Salmi × 3,500 SAR/month.
- Utilities: 50,000 SAR/month on average.
- Maintenance: ongoing plumbing, electrical, elevator work (expensed monthly as incurred).
- Ancillary rental income: Ahmad Yamni’s tuck shop 30,000 SAR/year, Saeed’s restaurant 450,000 SAR/year, Kareem’s barber shop 15,000 SAR/year (all invoiced annually at contract start).
- Hotel closed for the first 30 days (pre-opening preparation).

---

## 1. Foundation (Month 0 – Pre-opening)

1. **Company registration**
   - Salmi registers **EAK Golden Hospitality LLC** in Haasib → `core.companies` with base currency SAR.
   - Admin users and roles are created (`core.user_accounts`, `core.company_users`).

2. **Fiscal year and periods**
   - Fiscal year FY-2025 is created in `acct.fiscal_years` (Jan 1 – Dec 31, 2025).
   - Twelve monthly periods populate `acct.accounting_periods`, all `open` except future months (marked `future`).

3. **Chart of accounts**
   - Salmi loads template accounts: Cash, Meezan Bank, Accounts Receivable (AR), Accounts Payable (AP), Revenue (Room Revenue, Lease Income), COGS (Room Lease Expense), Utilities Expense, Payroll Expense, Maintenance Expense, VAT Payable, etc. → stored in `acct.accounts`.
   - Ancillary income accounts: Tuck Shop Rent, Restaurant Rent, Barber Rent.

4. **Posting rules**
   - Posting profiles configured in `posting.ruleset_headers` for:
     - Sales invoices (room revenue & tenant rent).
     - Vendor bills (lease cost, utilities, maintenance).
     - Payroll journals.
     - Bank receipts/payments (including FX revaluations).

5. **Master data setup**
   - **Vendors**: E.A.K. Holdings (lease), electricity company, water board, elevator maintenance firm, cleaning supplies vendor, payroll service.
   - **Customers**: Dunya Travels, ASB Travels, Meezab Group; also tenants Ahmad, Saeed, Kareem (treated as “customers” because they pay rent).
   - **Bank accounts**: Cash-on-hand and Meezan Bank SAR account in `bank.bank_accounts`.

6. **Opening balances**
   - No opening balances; all accounts start at zero.

7. **Property lease contract**
   - E.A.K. charges 60 SAR/room/night × 80 rooms × 365 = **1,752,000 SAR** annual lease.
   - Salmi schedules a recurring vendor bill (monthly accrual) of 146,000 SAR (1,752,000 / 12) in `ap.vendor_bills` so expense matches occupancy period.

8. **Pre-opening expenses**
   - Initial fit-out (painting, signage, license fees) recorded as vendor bills and fixed assets if capitalized.
   - Paid via Meezan Bank; posting: Debit Fixed Assets or Prepaid Expenses, Credit Meezan Bank.

---

## 2. Month 1 – Soft Opening (Hotel empty)

- Hotel remains empty for 30 days while staff train, systems tested.
- Activities:
  1. **Lease expense** – Accrue first month: Debit `Room Lease Expense` 146,000 SAR, Credit `Accounts Payable – E.A.K.`.
  2. **Payroll** – Record monthly salaries:
     - Cleaners: 4 × 1,500 = 6,000 SAR
     - Receptionists: 2 × 2,500 = 5,000 SAR
     - Salmi: 3,500 SAR
     - Journal entry (if payroll service pays cash):
       - Debit Payroll Expense 14,500 SAR, Credit Payroll Payable 14,500 SAR → then pay via bank (Debit Payroll Payable, Credit Meezan Bank).
  3. **Utilities** – Record vendor bills for 50,000 SAR; pay in cash/bank.
  4. **Maintenance** – Minimal but record small vendor invoice (elevator inspection 5,000 SAR).
  5. **No revenue yet**.
- Reports to review:
  - Expense report shows 215,500 SAR total spending (lease + payroll + utilities + maintenance).
  - Cash flow negative; Salmi confirms owner funded initial working capital.

---

## 3. Months 2–3 – First Customers Arrive

### 3.1 Long-term bulk bookings
- Salmi negotiates with **Dunya Travels** for 40 rooms at bulk rate 80 SAR/night, min 30 nights.
  - Booking: 40 rooms × 30 nights × 80 SAR = 96,000 SAR.
  - Sales order in `ar.sales_orders`; once guests check-in, Salmi issues invoice `INV-AR-0001`:
    - Debit AR 96,000 SAR, Credit Room Revenue 96,000 SAR.
  - Payment arrives in PKR to Meezan Bank:
    - Dunya wires 96,000 × 78 = 7,488,000 PKR.
    - Bank records SAR equivalent 96,000 (assuming fixed rate 78).
    - If rate differs, FX gain/loss recognized.

### 3.2 Additional travel agencies
- **ASB Travels** books 20 rooms for 30 nights at 80 SAR → 48,000 SAR invoice `INV-AR-0002`.
- **Meezab Group** books 10 rooms for 30 nights → 24,000 SAR invoice `INV-AR-0003`.
- Combined revenue for Month 2: 168,000 SAR.

### 3.3 Lease & expenses continue
- Lease expense accrual 146,000 SAR per month continues.
- Payroll 14,500 SAR, Utilities 50,000 SAR, Maintenance 8,000 SAR (more usage now).

### 3.4 Cash receipts
- Dunya pays in PKR; ASB pays cash at front desk (SAR), Meezab pays wire.
- Salmi records all in bank ledger, ensures conversions correct.
- Outstanding AR tracked; if ASB delays, aging reflects.

### 3.5 Month-end reports
- **Income statement (Month 2)**:
  - Revenue: 168,000 SAR
  - Expenses: Lease 146,000 + Payroll 14,500 + Utilities 50,000 + Maintenance 8,000 = 218,500 SAR
  - Net loss: (50,500) SAR (expected while occupancy ramps up).
- **Balance sheet**: AR ~72,000 SAR if some invoices unpaid; AP includes lease & utilities.

---

## 4. Months 4–6 – Stabilisation & Ancillary Rentals

### 4.1 Occupancy rises
- Bulk partners maintain 70 rooms occupancy; 5 staff rooms remain non-revenue.
- Retail guests (individuals) take remaining 5 rooms @ 100 SAR/night average occupancy 60%.
- Monthly revenue snapshot:
  - Bulk contracts: 70 rooms × 30 nights × 80 SAR = 168,000 SAR.
  - Retail: 5 rooms × 18 nights × 100 SAR ≈ 9,000 SAR.
  - Total room revenue ≈ 177,000 SAR/month.

### 4.2 Ancillary revenue invoices
- Start-of-quarter (Month 4) Salmi issues annual rent invoices:
  - **INV-RENT-001**: Ahmad Yamni tuck shop 30,000 SAR → due in 30 days.
  - **INV-RENT-002**: Saeed restaurant 450,000 SAR → due in 60 days (allows instalments).
  - **INV-RENT-003**: Kareem barber shop 15,000 SAR → due immediately.
- Posting: Debit AR, Credit Rental Income.
- Payments:
  - Ahmad pays via bank transfer (SAR) within 30 days.
  - Saeed pays three instalments (150,000 SAR × 3) partly in PKR; Salmi applies each to invoice.
  - Kareem pays cash.

### 4.3 Expenses and maintenance
- Continuous payroll, utilities, and targeted maintenance (plumbing 12,000 SAR in Month 5, elevator service 20,000 SAR in Month 6).
- Cleaning supplies vendor bills recorded, average 5,000 SAR/month.

### 4.4 Bank reconciliations
- Monthly statements imported; Salmi matches receipts, payments, payroll cash-outs in `bank.reconciliations`.

### 4.5 Quarterly review (end of Month 6)
- **Trial balance** shows:
  - Room revenue ~ 3 × 177,000 = 531,000 SAR
  - Lease expense 874,000 SAR (146,000 × 6)
  - Rental income 495,000 (Ahmad + Kareem + portion of Saeed) recognized as invoiced.
  - Maintenance cumulative 45,000 SAR.
- Net result still slightly negative due to heavy lease & utilities, but trending positive.

---

## 5. Months 7–9 – Peak Season & FX Challenges

### 5.1 Higher occupancy & retail bookings
- Peak travel season; agency contracts extend.
- Dunya extends additional 10 rooms for 60 nights (2 months) at 80 SAR.
- Retail rate increased to 110 SAR for high demand.

### 5.2 Multi-currency payments
- PKR weakens to 80 PKR/SAR in Month 8.
  - Example: ASB owes 80,000 SAR; pays 6,400,000 PKR which converts to 80,000 SAR at new rate. No FX difference.
  - Later when rate returns to 78, any outstanding PKR receivable is revalued; Salmi posts FX gains/losses via `acct.journal_entries`.

### 5.3 Maintenance spike
- Elevator breakdown in Month 7 – urgent repair 60,000 SAR vendor bill.
- Plumbing upgrades 25,000 SAR.
- Salmi decides to capitalise elevator part (if life extends >1 year) in fixed assets, remainder expensed.

### 5.4 Payroll & staff housing
- Staff wages steady; employee housing costs (5 rooms) are considered internal use (no revenue). Salmi tracks the opportunity cost in management reports but no GL entry.

### 5.5 Taxes
- If VAT applicable (assume 15% in KSA on room revenue), Salmi calculates output tax on revenue and input tax on expenses.
- Files monthly VAT return via `tax.tax_transactions`.

### 5.6 Reporting
- End of Month 9, net profit emerges as occupancy covers lease.
- Salmi shares dashboard with owner: occupancy rate, ADR (average daily rate), RevPAR, cash flow, outstanding AR by customer.

---

## 6. Months 10–12 – Year-end Preparation

### 6.1 Slower demand
- Off-season; occupancy drops to 55 rooms average.
- Bulk agencies maintain base contract but at reduced nights; retail promos offered.

### 6.2 Expense discipline
- Utilities drop to 45,000 SAR/month due to lower usage.
- Maintenance continues (monthly 10,000 SAR average).

### 6.3 Tenants renewals
- Ahmad and Kareem renew rent for next year; advance invoices issued in Month 12 for 2026.
- Saeed negotiates 2% discount for early payment; Salmi applies credit memo.

### 6.4 Payroll and bonuses
- Performance bonus for receptionists (1,000 SAR each) recorded as additional payroll expense.
- Salmi’s fee increases to 3,800 SAR/month for next year; recognized as accrual when contract signed.

### 6.5 Period close tasks (December)
1. **Finalize receivables** – ensure all travel agencies settled; any remaining amounts aged and provision considered.
2. **Accrue unpaid expenses** – utilities bill for December arrives in January → accrue 50,000 SAR in December.
3. **Depreciation** – apply monthly depreciation for capitalised elevator asset.
4. **Inventory check** – if minibar stock tracked, adjust shrinkage.
5. **Bank reconciliation** – tie out Meezan Bank to GL.

### 6.6 Year-end financial statements
- **Income Statement 2025** (illustrative numbers):
  - Room Revenue: 1,950,000 SAR
  - Rental Income (tenants): 495,000 SAR
  - Total Revenue: 2,445,000 SAR
  - Lease Expense: 1,752,000 SAR
  - Payroll: 174,000 SAR (14,500 × 12)
  - Utilities: 600,000 SAR (approx 50,000 × 12)
  - Maintenance: 180,000 SAR
  - Other Expenses (supplies, FX): 60,000 SAR
  - EBITDA ≈ -321,000 SAR (first-year loss expected due to ramp-up & heavy lease rate).
- **Balance Sheet** highlights:
  - Assets: Cash (from tenant rent + investors) 350,000 SAR; AR 120,000 SAR (if some invoices outstanding); Fixed Assets 40,000 SAR (capitalised elevator component).
  - Liabilities: AP 200,000 SAR (utilities, lease last month), VAT Payable 45,000 SAR.
  - Equity: Owner capital injection + accumulated loss.

### 6.7 Retained earnings & close
- Post year-end adjustments: revalue foreign currency balances, confirm accruals.
- Close periods in `acct.accounting_periods` and flag fiscal year closed in `acct.fiscal_years` once audited.

---

## 7. Ledger & Module Touchpoints to Test

| Business Event | Schema/Table Highlights | Test Ideas |
| --- | --- | --- |
| Company setup | `core.companies`, `acct.accounts`, `acct.fiscal_years` | Verify multi-company support, default COA load, permission roles |
| Vendor lease | `ap.vendor_bills`, `acct.journal_entries` | Recurring bills, approval workflow, posting to AP and expense |
| Customer invoices | `ar.sales_invoices`, `posting.ruleset_lines` | Bulk invoice creation, tax calculation, currency overrides |
| Cash receipts (PKR) | `bank.bank_transactions`, FX revaluation | Simulate variable exchange rates, check realised/unrealised FX entries |
| Payroll | `acct.manual_journal_entries` or payroll module | Mass entry of salaries, allocation to cost centres |
| Utilities & maintenance | `ap.vendor_bills`, `tax.tax_transactions` | Input VAT capture, scheduled payments, ageing reports |
| Tenant rent | `ar.sales_invoices`, revenue schedules | Annual invoicing, instalments, credit memos |
| Bank reconciliation | `bank.bank_statement_lines`, `bank.reconciliations` | Import CSV, auto-match, manual match |
| Period close | `acct.accounting_periods`, `reporting.trial_balance` | Lock periods, re-open with approvals, run trial balance |
| Reporting | `fin_reports.*`, `reporting.kpi_snapshots` | Income statement, balance sheet, cash flow, occupancy metrics |

---

## 8. Walkthrough Checklist for QA Teams

1. **Initial setup** – verify all master data matches story (accounts, vendors, customers, rates).
2. **Simulate first-quarter transactions** – create invoices/bills per story and confirm GL postings.
3. **Validate multi-currency** – capture PKR payments at multiple exchange rates; confirm FX gains/losses.
4. **Track tenant rent** – create annual invoices, apply partial payments, test credit memo.
5. **Record payroll** – monthly journal & disbursement.
6. **Enter maintenance spikes** – ensure capitalisation optionality works.
7. **Generate interim financials** – run P&L and balance sheet each quarter, compare to expected narrative totals.
8. **Perform bank reconciliations** – import sample statements.
9. **Execute VAT return** – confirm tax codes applied on revenue/expenses.
10. **Close the year** – lock periods, carry retained earnings, produce year-end reports.

---

## 9. MVP Readiness Checklist

Salmi’s story is a great end-to-end test. If the platform can execute every step and satisfy the checks below, you’re in minimum-viable shape for early customers:

1. **Core flows run from the UI** – company onboarding, chart-of-accounts load, vendor/customer lifecycles, multi-currency receipts, recurring bills, payroll, and period close all work without direct database edits.
2. **Data integrity holds** – every journal entry balances, FX revaluations land in the right accounts, closed periods stay locked, and the audit trail (`acct.audit_log`, posting queue) reflects user activity.
3. **Reports reconcile** – trial balance, income statement, balance sheet, tax/VAT schedules, aging, and dashboards match the narrative’s expected totals.
4. **Guardrails protect data** – roles/permissions enforced, void/delete actions logged, error messaging covers missing rates, duplicate numbers, and other edge cases.
5. **UX is consistent** – forms use shared validation, loading states prevent duplicate submissions, toast/dialog feedback confirms outcomes.
6. **Operational readiness** – configuration management, seed/onboarding scripts, environment-specific settings (currencies, tax regimes) are documented and automated.
7. **Observability & regression tests** – logging and monitoring surface failures; automated tests cover the critical flows (especially multi-currency and high-volume postings).

Anything still shaky becomes the next sprint’s priority; otherwise the product is ready for pilot users.

---

## 10. Epilogue – Lessons from Salmi

- Hospitality margins depend on sustained occupancy; heavy fixed lease requires constant monitoring.
- Multi-currency cash flows demand timely FX revaluation to keep statements accurate.
- Ancillary rentals (tuck shop, restaurant, barber) provide steady income; ensure leases are invoiced promptly.
- Maintenance costs ebb and flow—capture them on time to keep profit realistic.
- With Haasib, every module (AR/AP/Banking/Tax) feeds the general ledger automatically, letting Salmi focus on strategy rather than spreadsheets.

This fictional journey can drive demos, user training, regression tests, or documentation for the Haasib accounting stack. Follow it step-by-step to ensure the product supports every stage of a real-world hospitality business.
