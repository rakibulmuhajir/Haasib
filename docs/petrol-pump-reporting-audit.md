# Petrol Pump Reporting Audit

Date: 2026-06-22

## Purpose

This document records the current reporting state for the petrol pump module and defines what should be improved before reports are useful for daily station owners, managers, and accountants.

The product goal remains simple: non-accountants should see business answers first, while accounting detail remains available when needed.

## Current Report Inventory

### Profit and Loss

Location:
- Backend: `build/modules/Accounting/Services/ProfitLossReportService.php`
- Frontend: `build/modules/Accounting/Resources/js/pages/reports/ProfitLoss.vue`
- Route: `/{company}/reports/profit-loss`

Current value:
- This is the most reliable financial report because it is based on posted GL journal entries.
- It summarizes income, COGS, expenses, and profit for a selected date range.

Current gaps:
- Only supports start date and end date.
- No day, week, or month grouping.
- No petrol-pump view of liters, gross margin per liter, fuel product profit, purchases, stock loss, or payment channels.
- No drilldown into source documents such as Daily Close, bills, bill payments, payroll, or amanat movements.
- Useful for accountants, but too abstract for station operators.

Verdict:
- Keep it as the accountant-level report.
- Do not make this the main petrol pump reporting screen.

### Fuel Sales Report

Location:
- Backend: `build/modules/FuelStation/Http/Controllers/SalesReportController.php`
- Frontend: `build/modules/FuelStation/Resources/js/pages/FuelStation/Reports/SalesReport.vue`
- Route: `/{company}/fuel/reports/sales`

Current value:
- The intended UI is useful: date range, fuel filter, day/week/month grouping, and export.

Current issue:
- The backend currently queries transaction type `daily_close`.
- The live Daily Close posting uses transaction type `fuel_daily_close`.
- The backend reads `metadata.sales`.
- The live Daily Close metadata uses fields such as `fuel_sales`, `rate_change_segments`, `other_sales`, `tank_variances`, `bill_payments`, `employee_advances`, `amanat_*`, and cash movement fields.

Verdict:
- This report is likely stale and cannot be trusted until rebuilt.
- It should be rebuilt around `fuel_daily_close` metadata and GL journal entries.

### Shrinkage Report

Location:
- Backend: `build/modules/FuelStation/Http/Controllers/ShrinkageReportController.php`
- Frontend: `build/modules/FuelStation/Resources/js/pages/FuelStation/Reports/ShrinkageReport.vue`
- Route: `/{company}/fuel/reports/shrinkage`

Current value:
- The concept is important: station owners need to know stock loss, stock gain, delivery shortage, and claim status.

Current issue:
- The backend imports older model names: `Fuel` and `Tank`.
- The current module uses inventory items and warehouses/tanks.
- It queries transaction type `daily_close`, while the current posting path uses `fuel_daily_close`.
- It reads `metadata.tank_readings` in an older shape. Current stock truth is closer to `fuel.tank_readings`, stock movements, and Daily Close metadata such as `tank_variances`, `total_shrinkage`, and `total_gain`.

Verdict:
- This report should be replaced, not patched lightly.
- It should become a Stock Variance and Claims report.

### Salary Report

Location:
- Backend: `build/modules/Payroll/Http/Controllers/SalaryReportController.php`
- Frontend: `build/modules/Payroll/Resources/js/pages/Reports/Salary.vue`
- Route: `/{company}/payroll/reports/salary`

Current value:
- Useful and mostly aligned with business needs.
- Shows gross salary, deductions, net salary, paid/unpaid status, advance given, advance recovered, and outstanding advances.

Current gaps:
- Month-only filter.
- Needs employee/status filters.
- Needs better linkage back to Daily Close for cash salary payments and advances.
- Should clearly separate draft payroll from approved posted payroll.

Verdict:
- Keep and improve later.
- It should feed into station reporting, but not replace Daily Close reports.

### Daily Close History and Detail

Location:
- History: `build/modules/FuelStation/Resources/js/pages/FuelStation/DailyClose/Index.vue`
- Detail: `build/modules/FuelStation/Resources/js/pages/FuelStation/DailyClose/Show.vue`
- Route: `/{company}/fuel/daily-close/history`

Current value:
- This is currently the most valuable petrol pump report because Daily Close is the operational source of truth.
- It already contains revenue, COGS, gross profit, cash movement, expenses, employee advances, payroll payouts, bill payments, partner movements, amanat movement, tank variances, and cash variance.

Current gaps:
- It is a history/detail screen, not a report dashboard.
- No strong date range filtering.
- No day/week/month grouping.
- No export.
- No product, expense, vendor, payment-channel, employee, partner, or amanat filters.

Verdict:
- This should be the foundation for the petrol pump reporting module.

## Reports That Actually Add Value

### 1. Station Performance Report

This should be the main report for non-accountants.

Primary questions it answers:
- How much did we sell?
- How many liters did we sell?
- What was the gross profit?
- What expenses were paid?
- What was the net station profit?
- Did cash match?
- Did stock match?
- What bills or salaries were paid?

Recommended summary cards:
- Revenue
- Liters sold
- COGS
- Gross profit
- Gross margin percent
- Operating expenses
- Net station profit
- Cash over/short
- Stock loss/gain value
- Purchases received
- Bill payments
- Outstanding vendor credit
- Salary advances
- Amanat received and used

Recommended rows:
- Date
- Revenue
- Liters
- COGS
- Gross profit
- Expenses
- Net profit
- Cash variance
- Stock variance
- Purchases
- Bill payments
- Closing cash
- Status

### 2. Product Profitability Report

Primary questions it answers:
- Which fuel or lubricant is making money?
- What is the margin per liter or unit?
- Which product is selling but not profitable?
- Which product has stock loss?

Recommended columns:
- Product
- Liters or units sold
- Revenue
- Average sale rate
- Average cost
- COGS
- Gross profit
- Margin per liter/unit
- Margin percent
- Stock loss/gain liters
- Stock loss/gain value
- Closing stock

Filters:
- Date preset or custom range
- Product category
- Product
- Group by day, week, or month

### 3. Expenses Report

Primary questions it answers:
- Where is money going?
- Which expenses are increasing?
- Which expenses were paid from cash, bank, or fuel card?

Recommended columns:
- Date
- Expense account/category
- Description
- Paid from
- Amount
- Source: Daily Close, bill, payroll, or adjustment
- Reference

Filters:
- Date preset or custom range
- Expense account/category
- Payment source
- Vendor
- Source type
- Group by day, week, or month

### 4. Purchases and Vendor Credit Report

Primary questions it answers:
- What fuel/lubricants were purchased?
- What has been received?
- What has been paid?
- What is still payable?
- Which delivery shortages are claimable or final loss?

Recommended columns:
- Bill date
- Vendor
- Product
- Quantity billed
- Quantity received
- Unit cost
- Bill amount
- Paid amount
- Remaining payable
- Delivery variance
- Claim status
- Receipt status

Filters:
- Date preset or custom range
- Vendor
- Product
- Payment status
- Receipt status
- Claim status

### 5. Cash Control Report

Primary questions it answers:
- Did the drawer/till balance correctly?
- What made cash go up or down?
- Which day had a cash shortage?

Recommended columns:
- Date
- Opening cash
- Cash sales
- Cash deposits received
- Amanat received
- Employee advances
- Expenses paid
- Bill payments from cash
- Payroll paid
- Bank deposits
- Expected closing cash
- Actual closing cash
- Over/short

Filters:
- Date preset or custom range
- Cash source/type
- Show only variance days

### 6. Stock Variance and Claims Report

Primary questions it answers:
- Which tanks/products have physical variance?
- Was the variance a delivery shortage, evaporation, gain, correction, claimable shortage, or final loss?
- Which claims are still pending?

Recommended columns:
- Date
- Tank/warehouse
- Product
- Previous dip
- Purchases/receipts
- Sales
- Expected stock
- Physical dip
- Variance liters
- Variance value
- Reason
- Claim status
- Journal status

Filters:
- Date preset or custom range
- Tank/warehouse
- Product
- Variance type: loss, gain, none
- Claim status: pending, received, final loss

### 7. Payment Channel and Settlement Report

Primary questions it answers:
- How much was collected by cash, card, fuel card, bank, or wallet?
- What is still stuck in clearing?
- What reached the bank?

Recommended columns:
- Date
- Payment channel
- Gross amount
- Clearing account
- Settled account
- Settled amount
- Outstanding clearing balance

Filters:
- Date preset or custom range
- Payment channel
- Settlement status
- Bank/clearing account

### 8. Payroll and Advances Report

Primary questions it answers:
- What salary is due?
- What salary was paid?
- What advances are outstanding?
- What recoveries happened in payroll?

Recommended columns:
- Employee
- Base salary
- Gross salary
- Deductions
- Advance recovered
- Net salary
- Paid amount
- Outstanding salary payable
- Outstanding advance

Filters:
- Month
- Employee
- Status: draft, approved, paid
- Show only employees with outstanding advance

## Recommended Report Navigation

Keep reports simple and grouped under a single "Reports" area:

1. Station Performance
2. Product Profitability
3. Expenses
4. Purchases and Vendor Credit
5. Cash Control
6. Stock Variance and Claims
7. Payment Channels
8. Payroll and Advances
9. Profit and Loss

For non-accountants, default to Station Performance.

For accountants, keep Profit and Loss and allow drilldown to journal entries.

## Standard Filters

Every major report should share the same filter language:

- Today
- Yesterday
- Last 7 days
- This month
- Last month
- Custom date range
- Group by day, week, or month

Where relevant:
- Product
- Product category
- Tank/warehouse
- Vendor
- Employee
- Partner
- Amanat holder
- Expense account/category
- Payment source/channel
- Status

## Data Source Rules

### Daily Close should be the operational source of truth

Use `acct.transactions.transaction_type = fuel_daily_close` and its posted metadata for:
- Fuel sales
- Other sales
- Cash in
- Cash out
- Expenses paid at station
- Employee advances
- Payroll cash payouts
- Amanat received/used
- Partner deposits/withdrawals
- Bill payments picked up by Daily Close
- Cash variance
- Daily close status

### GL should be the accounting source of truth

Use posted journal entries for:
- Profit and Loss
- Account balances
- Official revenue, expense, asset, liability, and equity totals
- Accountant drilldowns

### Inventory should be the stock source of truth

Use stock movements and tank readings for:
- Current stock
- Purchase receipts
- Tank dip history
- Stock loss/gain
- Claimable delivery shortage
- Final loss

### Payroll should remain payroll source of truth

Use payroll tables for:
- Payslips
- Salary payable
- Advance recovery
- Net salary status

Daily Close should only record actual cash movement at the station.

## Immediate Fixes Before Building More Reports

1. Rebuild Fuel Sales Report to use `fuel_daily_close` and `metadata.fuel_sales`.
2. Replace Shrinkage Report with Stock Variance and Claims Report using `fuel.tank_readings` and stock movements.
3. Promote Daily Close history/detail into the base Station Performance Report.
4. Add consistent date presets and grouping across reports.
5. Add drilldowns from report rows to Daily Close, bill, payment, payroll, tank reading, or journal entry.
6. Add CSV export only after the screen data is trustworthy.
7. Hide or remove stale reports until rebuilt so users do not trust incorrect numbers.

## MVP Implementation Order

### Phase 1: Trustworthy Daily Close Reporting

- Build Station Performance Report from `fuel_daily_close`.
- Add date presets and day/week/month grouping.
- Add product sales and gross profit summary.
- Add cash control summary.
- Add expense summary.
- Link each row to Daily Close detail.

### Phase 2: Product and Stock Reporting

- Rebuild Product Profitability Report.
- Replace Shrinkage Report with Stock Variance and Claims Report.
- Include rate-change split impact where available.
- Include claim pending, claim received, and final loss states.

### Phase 3: Purchases and Expenses

- Build Purchases and Vendor Credit Report.
- Build Expense Report.
- Connect bill payments posted through Daily Close.
- Show vendor payable movement clearly.

### Phase 4: Accountant Drilldowns

- Improve Profit and Loss with day/week/month grouping.
- Add account drilldown to source documents.
- Add journal-entry drilldown.

## Testing Checklist

- Post several Daily Closes across different dates.
- Include fuel sales for multiple products.
- Include a rate change with meter snapshot.
- Include cash sale, bank transfer, card POS, and fuel card sale.
- Include station expenses.
- Include employee advance.
- Include payroll payout.
- Include amanat deposit and fuel disbursement.
- Receive a fuel delivery.
- Record a tank dip after delivery.
- Record a daily closing tank dip.
- Record stock loss as final loss.
- Record stock loss as claimable and later claim received.
- Create a bill and pay it through Daily Close.
- Confirm report totals match Daily Close detail.
- Confirm report totals match posted journal entries where accounting totals are expected.
- Confirm date filters and grouping produce the same total at all grouping levels.

## Summary

The best next move is not to add more report screens immediately. The current fuel sales and shrinkage reports should first be corrected or replaced because they appear to use stale transaction types and metadata.

The highest-value report is a Station Performance Report based on Daily Close. It should show revenue, liters, COGS, gross profit, expenses, cash variance, stock variance, purchases, bill payments, payroll, and amanat movement in one simple owner-friendly view, with drilldown into the underlying Daily Close and accounting entries.
