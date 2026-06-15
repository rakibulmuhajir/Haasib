# Petrol Pump Module Client Handover Checklist

Use this as the single completion tracker before handing the petrol pump module to a client.

Product rule: operators should be able to run daily work without accounting knowledge. Accounting controls must stay available for owners/accountants, but defaults and automation should handle normal station use.

## 1. Setup And Master Data

- [ ] Setup wizard creates the minimum usable station setup: company, bank, chart accounts, fuel products, tanks, pumps, attendants, vendors, and opening rates.
- [ ] Station Settings is maintenance-only after setup, not a second wizard.
- [ ] Fuel products auto-map sales, COGS, and inventory accounts.
- [ ] Product saving has one backend source of truth: every fuel, lubricant, onboarding, and inventory product creation path uses the inventory product catalog service.
- [ ] Fuel station navigation exposes one normal product setup path: Products You Sell.
- [ ] Tank creation does not create products as a hidden side effect; tanks link to products created through product setup.
- [ ] Users cannot accidentally remove required product account mappings after products are active.
- [ ] Multiple fuel vendors are supported.
- [ ] Vendor types are available: refinery, distributor, fuel station, transporter, other.
- [ ] Station-selected supplier appears correctly in bills, receipts, vendor cards, and setup hints.
- [ ] Tank capacity, linked product, pump assignment, and current stock are visible from a stock management home.
- [ ] Payment channels can be enabled/disabled without exposing unnecessary accounting fields to operators.
- [ ] Fallback accounts are auto-created or auto-selected and hidden from normal users unless advanced settings are opened.

## 2. Daily Close

- [ ] Daily close can be created from live station data.
- [ ] Daily close supports fuel sales by product, rate, liters, and amount.
- [ ] Daily close supports oil/lubricant sales when enabled.
- [ ] Daily close supports cash, bank transfer, POS/card, fuel card, wallet, and credit/customer deposit payments.
- [ ] Daily close supports cash payouts: salary advances, partner drawings, expenses, and deposits.
- [ ] Daily close validates that payment totals, cash expected, and readings are understandable before posting.
- [ ] Daily close posts one balanced journal entry or a clearly linked journal group.
- [ ] Posted daily closes are read-only.
- [ ] Duplicate posting for the same station/date is prevented unless using amendment flow.
- [ ] Corrections reverse the old posting instead of editing/deleting posted GL entries.
- [ ] Amendment chain is visible from daily close list and detail.
- [ ] Daily close screen shows inline hints for account impact, for example cash sale increases Cash on Hand and Fuel Sales.
- [ ] Operators see plain-English descriptions first; debit/credit detail is available but not dominant.

## 3. Daily Close Double Entry

- [ ] Cash fuel sale posts: Dr Cash on Hand, Cr product Fuel Sales.
- [ ] Bank transfer sale posts: Dr Operating Bank, Cr product Fuel Sales.
- [ ] POS/card sale posts: Dr POS Clearing, Cr product Fuel Sales.
- [ ] Fuel card sale posts: Dr Vendor Card Clearing, Cr product Fuel Sales.
- [ ] Wallet sale posts to either clearing or bank based on channel setup.
- [ ] Credit customer sale posts: Dr Customer Receivable, Cr product Fuel Sales.
- [ ] Customer deposit usage reduces deposit liability/amanat correctly.
- [ ] Fuel COGS posts by product: Dr product Fuel COGS, Cr product Fuel Inventory.
- [ ] Cash deposit to bank posts: Dr Operating Bank, Cr Cash on Hand.
- [ ] Employee advance from cash posts: Dr Employee Advances, Cr Cash on Hand.
- [ ] Partner drawing posts: Dr Partner Drawings, Cr Cash on Hand.
- [ ] Cash over/short posts to the configured Cash Over/Short account.
- [ ] Every daily close journal balances in base currency.
- [ ] Journal detail links back to the daily close.

## 4. Stock Management

- [ ] Stock management has a clear home/dashboard.
- [ ] Dashboard shows current fuel stock by tank and product.
- [ ] Dashboard shows low stock, capacity usage, shrinkage/gain alerts, and recent stock movement.
- [ ] Upcoming deliveries are visible.
- [ ] Paid, unpaid, and partially paid fuel bills are visible from stock/vendor context.
- [ ] Stock can increase through fuel receipt/purchase bill posting.
- [ ] Stock can increase/decrease through approved stock adjustment.
- [ ] Stock can decrease through daily close sales.
- [ ] Stock can decrease/increase through confirmed tank reading variance.
- [ ] Every stock movement has a source document link.
- [ ] Every stock movement that affects value has GL transaction linkage.
- [ ] Stock adjustment has reason, approval/status, and journal posting.
- [ ] Stock ledger can be filtered by product, tank, date, and source type.

## 5. Fuel Purchasing, Bills, And Payments

- [ ] Fuel purchase bill can select vendor from station vendors and general vendors.
- [ ] Bill line can select fuel product and tank/receipt destination when relevant.
- [ ] Bill posting increases fuel inventory and credits Accounts Payable.
- [ ] Bill can be unpaid, partially paid, or fully paid.
- [ ] Credit terms are supported without forcing immediate payment.
- [ ] Remaining unpaid bill balance stays in Accounts Payable.
- [ ] Bill payment can use one or multiple payment sources.
- [ ] Payment sources can include bank, cash, wallet, fuel card, or other configured accounts.
- [ ] Payment form shows estimated account balance before and after payment.
- [ ] Payment form clearly shows payment now, allocated amount, and remaining vendor credit.
- [ ] Split-source payment is shown as one grouped payment in the frontend.
- [ ] Split-source payment keeps child payment rows linked by group id/number.
- [ ] Payment detail opens from bill payment list.
- [ ] Payment detail shows sources, allocations, journal link, and human-readable dates.
- [ ] Payment journal posts: Dr Accounts Payable, Cr payment source accounts.
- [ ] Partial payment journal only posts the paid amount.
- [ ] Bill and payment detail pages link to each other.

## 6. Payment Settlement

- [ ] POS/card clearing settlement screen exists.
- [ ] Vendor/fuel-card clearing settlement screen exists.
- [ ] Wallet settlement is supported where wallet payments settle later.
- [ ] Settlement without fee posts: Dr Bank, Cr Clearing.
- [ ] Settlement with fee posts: Dr Bank, Dr Bank Charges, Cr Clearing.
- [ ] Settlement can be partial.
- [ ] Unsettled clearing balances are visible by channel.
- [ ] Settlement detail links to journal and source sales.

## 7. Credit Customers, Amanat, And Depositors

- [ ] Daily close can search/select credit customers from live data.
- [ ] Daily close shows customer current balance and credit limit before adding credit sale.
- [ ] Customer deposit/amanat holders can be searched from live data.
- [ ] Daily close shows depositor balance before applying deposit.
- [ ] Amanat receipt and usage both post balanced double entries.
- [ ] Customer statements show sales, collections, deposits, and current balance.
- [ ] Credit blocking or limit warning appears before accepting risky credit sales.

## 8. Partners And Investors

- [ ] Daily close can search/select partners from live data.
- [ ] Daily close shows partner current capital/drawing exposure.
- [ ] Partner investment posts to cash/bank and partner capital.
- [ ] Partner drawing posts to partner drawings and cash/bank.
- [ ] Investor lots can be recorded when enabled.
- [ ] Investor entitlement, remaining units, commission, and settlement are visible.
- [ ] Investor lot tracking stays hidden when the feature is disabled.

## 9. Payroll Integration

- [ ] Employee list is searchable from daily close salary advance flow.
- [ ] Daily close shows employee outstanding advance balance.
- [ ] Salary advance payment posts: Dr Employee Advances, Cr Cash/Bank.
- [ ] Payroll payslip can recover salary advance.
- [ ] Salary advance recovery posts: Dr Salary Payable or payroll clearing, Cr Employee Advances.
- [ ] Payroll approval posts payroll expenses and liabilities.
- [ ] Payroll payment posts liability reduction and cash/bank reduction.
- [ ] Payslip detail links to related advance recovery lines.
- [ ] Employee profile shows advance balance, recovered amount, and outstanding amount.

## 10. Reports

- [ ] Daily close report by date, shift, attendant, pump, product, and payment method.
- [ ] Fuel sales report by product: liters, sales, COGS, gross profit, margin.
- [ ] Stock report by product and tank.
- [ ] Shrinkage/gain report by tank and product.
- [ ] Vendor payable report for unpaid and partially paid bills.
- [ ] Clearing account report by POS, vendor card, wallet, and settlement status.
- [ ] Cash on hand movement report.
- [ ] Partner capital/drawing report.
- [ ] Employee advance report.
- [ ] Customer/depositor balance report.
- [ ] Reports export to CSV/PDF where required by client.

## 11. User Experience

- [ ] Station operators are not asked to choose accounting accounts during normal daily work.
- [ ] Advanced account settings are available only where needed.
- [ ] All date/time displays use the universal formatter.
- [ ] Money amounts consistently show currency and two decimal places where appropriate.
- [ ] Zero values display as `0.00` where decimals are expected.
- [ ] Forms show remaining amounts while entering partial payments or allocations.
- [ ] Errors are shown as toasts or inline validation, not Laravel error pages.
- [ ] Loading states disable submit buttons.
- [ ] Success states redirect or refresh the relevant list/detail screen.
- [ ] Empty states explain what to do next without accounting jargon.
- [ ] Mobile layout is usable for daily close and quick station operations.

## 12. Permissions And Audit

- [ ] Operator, manager, accountant, and owner permissions are defined.
- [ ] Operators can create drafts but cannot change account mappings.
- [ ] Only authorized users can post, reverse, settle, or delete draft records.
- [ ] Posted financial records cannot be silently edited.
- [ ] Every posting records company, user, source type, and source id.
- [ ] Audit trail exists for daily close posting, reversal, payment, settlement, and stock adjustment.
- [ ] All tenant routes use company slug and `identify.company`.
- [ ] RLS is enabled and validated for fuel, inventory, payroll, and accounting tenant tables.

## 13. Data Integrity And Edge Cases

- [ ] Negative stock is blocked or explicitly approved based on station policy.
- [ ] Product account mappings are required before posting sales.
- [ ] Payment channels cannot post without required bank/clearing routing.
- [ ] Bill payment cannot exceed selected payment source amount unless overdraft behavior is intentionally allowed.
- [ ] Partial payments do not clear full bill balance.
- [ ] Split payments remain grouped in the UI.
- [ ] Currency rules follow the multi-currency contract.
- [ ] Journals balance in base currency.
- [ ] Deleted draft records do not delete posted journals.
- [ ] Reversal entries preserve the original audit trail.
- [ ] Date-only fields do not shift by timezone.

## 14. Testing Checklist

- [ ] Run migrations from a clean database.
- [ ] Run seeders and verify demo station setup.
- [ ] Create station setup from wizard.
- [ ] Create fuel products, tanks, pumps, vendors, and payment channels.
- [ ] Create a fuel bill with no payment.
- [ ] Create a fuel bill with partial payment.
- [ ] Create a fuel bill with split-source payment.
- [ ] Verify bill, payment, journal, AP, and stock balances.
- [ ] Record stock adjustment and verify stock plus GL.
- [ ] Create daily close with cash sale only.
- [ ] Create daily close with mixed payment channels.
- [ ] Create daily close with credit customer sale.
- [ ] Create daily close with salary advance and partner drawing.
- [ ] Post daily close and verify all double entries.
- [ ] Reverse/amend daily close and verify reversal journals.
- [ ] Settle POS/fuel-card clearing and verify clearing balance.
- [ ] Run payroll with salary advance recovery.
- [ ] Verify reports match journal and stock ledgers.
- [ ] Test permissions for operator, manager, accountant, and owner.
- [ ] Test validation and error messages for incomplete mappings.
- [ ] Run frontend build.
- [ ] Run backend test suite or targeted module tests.
- [ ] Run quality checks before handover.

## 15. Client Handover Package

- [ ] Client-ready demo tenant prepared.
- [ ] Opening balances and sample data reviewed with client.
- [ ] Admin/owner account created.
- [ ] Operator roles and users created.
- [ ] Chart of Accounts reviewed with accountant/owner.
- [ ] Station settings reviewed and locked down.
- [ ] Daily close SOP documented.
- [ ] Fuel purchase and bill payment SOP documented.
- [ ] Stock adjustment SOP documented.
- [ ] Settlement SOP documented.
- [ ] Payroll advance SOP documented if payroll is in scope.
- [ ] Known limitations documented.
- [ ] Backup and restore process documented.
- [ ] Support contacts and escalation path documented.
- [ ] Client sign-off received for setup, posting, reports, and permissions.

## 16. Final Sign-Off

- [ ] Product owner sign-off.
- [ ] Accounting sign-off.
- [ ] Technical lead sign-off.
- [ ] Client admin sign-off.
- [ ] Production deployment complete.
- [ ] First real daily close supervised.
- [ ] First real fuel bill and payment supervised.
- [ ] First settlement supervised.
- [ ] Handover marked complete.
