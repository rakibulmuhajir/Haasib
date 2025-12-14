# Manual Test Plan — GL/AR/AP Flow

Single source of truth for quick end‑to‑end smoke tests. Extend this as features land.

## Reset & Start
- `php artisan migrate:fresh --seed --force`
- `php artisan octane:start --server=frankenphp --port=9001 --watch`
- `npm run dev`
- Login: admin@haasib.com / password
- Company: **Nvidia Corporation** (slug `nvidia-corporation`)
- Ensure an open fiscal year/period covers today; if missing, create FY 2025 with monthly periods.

## Chart of Accounts (ensure active)
- AR control: `1100 Accounts Receivable` (subtype accounts_receivable)
- AP control: `2000 Accounts Payable` (subtype accounts_payable)
- Revenue: `4000 Consulting Revenue`
- Expense: `6000 Office Supplies`
- Bank: `1000 Operating Bank`
- Cash: `1010 Cash on Hand`

## Master Data
- Customers: **Acme Robotics** (USD, AR 1100), **Nova BioTech** (USD, AR 1100)
- Vendors: **SupplyWorks LLC** (USD, AP 2000), **Cloud Parts Inc.** (USD, AP 2000)

## Transactions (dated today, USD)
1) **Invoice I-1** (Acme)
   - AR 1100
   - Line: 10 @ $100, income 4000
   - Action: Save, then **Send**
   - Expect JE: DR 1100 1,000; CR 4000 1,000; `transaction_id` set.

2) **Invoice I-2** (Nova)
   - AR 1100
   - Line: 5 @ $200, income 4000
   - Action: **Send**
   - Expect JE: DR 1100 1,000; CR 4000 1,000.

3) **Payment P-1** (for I-1)
   - Amount $1,000; deposit 1000 Bank; AR 1100
   - Expect JE: DR 1000 1,000; CR 1100 1,000; invoice I-1 status paid/balance 0.

4) **Credit Note CN-1** (for I-2)
   - Customer Nova; Status **Issued**; Amount $200; revenue 4000, AR 1100
   - Expect JE: DR 4000 200; CR 1100 200; `transaction_id` set; invoice I-2 balance decreases to $800 (before application).

5) **Bill B-1** (SupplyWorks)
   - AP 2000
   - Line: 1 @ $500, expense 6000
   - Action: **Receive**
   - Expect JE: DR 6000 500; CR 2000 500; `transaction_id` set; bill balance 500.

6) **Bill Payment BP-1** (for B-1)
   - Amount $500; pay-from 1000 Bank; AP 2000
   - Expect JE: DR 2000 500; CR 1000 500; bill status paid/balance 0.

7) **Vendor Credit VC-1** (Cloud Parts)
   - Status **Received**; Amount $150; expense 6000; AP 2000
   - Expect JE: DR 2000 150; CR 6000 150; `transaction_id` set; credit balance 150.

## What to Check
- Each posted doc has a non-null `transaction_id`.
- Status/balances: I-1 paid; I-2 sent (balance 800 after CN-1); B-1 paid; VC-1 received.
- Trial Balance (sanity): AR ~800 DR; AP ~150 CR; Revenue ~1,800 CR; Expense ~350 DR; Bank net +500 DR. TB should balance.
- Reports/pages: invoices, payments, credit notes, bills, bill payments, vendor credits show posted status and GL link.

## Additional Scenarios (run after core flow)
8) Apply Credit Note to Invoice
   - Apply CN-1 to Invoice I-2.
   - Expect: credit note status → applied; invoice I-2 balance drops by $200 (to $800). If cross-customer apply isn’t allowed, ensure same customer; if a reclass entry is produced, AR-to-AR lines should balance and `transaction_id` stays intact.

9) Apply Vendor Credit to Bill
   - Create Bill B-2 (Cloud Parts) $200 (expense 6000, AP 2000), receive it.
   - Apply VC-1 ($150) to B-2.
   - Expect: vendor credit status → applied; bill B-2 balance $50. If AP reclass is emitted, verify AP lines balance and `transaction_id` remains set.

10) Multi-Currency Posting
   - Create Invoice I-3 in EUR with exchange rate (e.g., €1,000, rate 1.10, AR 1100, revenue 4000), send.
   - Expect: exchange_rate required; transaction stores currency/base_currency, totals balanced; TB shows base amounts; no posting if rate missing.
   - Repeat for a Bill in EUR (expense 6000, AP 2000) and receive it.

11) Closed Period Rejection
   - Close the prior period (or set bill/invoice date in a closed period).
   - Attempt to send/post an invoice or receive a bill dated in the closed period.
   - Expect: posting blocked with clear error about closed period.

12) Tax Posting (when tax engine enabled)
   - Enable tax for company; set default tax rate (e.g., 10%) and tax payable account.
   - Create Invoice with taxable line (income 4000) and send.
   - Expect JE lines include Tax Payable: DR AR total, CR Revenue net, CR Tax Payable tax amount; balances match contract. Repeat for Bill with input tax if supported (DR Expense, DR Recoverable/Input Tax, CR AP).
