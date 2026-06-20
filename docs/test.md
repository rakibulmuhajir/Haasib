• Test it like one full petrol pump operating cycle, not as isolated screens.

  1. Setup Flow

  - Create a new company.
  - Add fuel products with opening stock.
  - Confirm product creation creates:
      - product
      - tank/warehouse
      - pump/nozzles where applicable
      - opening stock movement
      - rate
      - accounting mappings

  - Check products, stock management, and daily close all show the same stock.

  2. First Daily Close
  Run a first close from opening stock:

  - enter meter readings
  - enter tank dip
  - record cash in/payment channels
  - record employee advance
  - record Amanat disbursement if enabled
  - record expenses
  - post daily close

  Verify:

  - tank opening baseline uses opening stock
  - variance is reasonable
  - cash expected vs actual is correct
  - journal entry is balanced
  - daily close appears in history

  3. Next-Day Daily Close
  Create the next daily close.

  Verify:

  - tank opening baseline uses previous daily close closing dip, not opening stock
  - nozzle opening readings use previous close
  - cash opening uses previous close
  - stock variance is calculated from previous dip correctly

  4. Fuel Purchase / Stock Increase
  Test fuel stock increase from the intended path:

  - create vendor
  - create fuel bill
  - partial/multiple-source payment if needed
  - receive stock into tank
  - check stock movement
  - check products page stock
  - check daily close baseline after delivery

  Verify:

  - unpaid bill remains in AP
  - paid/received stock affects inventory
  - payment sources create correct accounting

  5. Payment Channels
  For daily close, test:

  - cash sale
  - bank transfer
  - card POS
  - fuel card
  - mobile wallet if enabled

  Verify:

  - cash affects Cash on Hand
  - bank transfer goes to bank
  - POS/fuel card goes to clearing or configured account
  - no missing mappings
  - journal balances

  6. Payroll / Salary Advances
  Test:

  - create employee
  - record salary advance from Daily Close
  - view salary advances page
  - generate payroll
  - approve payslip
  - verify advance recovery
  - verify approved salary appears in Daily Close money out if paid from station
    cash

  Check double entry:

  - advance: Dr Employee Advances, Cr Cash on Hand
  - salary approval: Dr Salary Expense, Cr Payroll Payable
  - salary payment: Dr Payroll Payable, Cr Cash/Bank
  - advance recovery reduces Employee Advances

  7. Amanat
  Test:

  - create Amanat depositor
  - record deposit
  - record disbursement from Daily Close
  - verify balance decreases
  - verify transaction history

  Check:

  - deposit liability increases
  - disbursement debits Amanat liability and credits cash

  8. Amendments / Corrections
  Post a wrong daily close, then amend it.

  Verify:

  - original remains in history
  - reversal is created
  - corrected entry is created
  - stock/cash/accounting reports reflect corrected state
  - locked entries cannot be amended

  9. Reports Reconciliation
  After several closes, compare:

  - Daily Close history
  - products stock
  - stock management
  - GL trial balance
  - profit/loss
  - salary reports
  - Amanat balances
  - vendor/AP balances
  - bank/cash accounts

  The key production-ready check: every number shown in operational screens must
  reconcile to accounting and stock reports.

  10. UX Failure Testing
  Try bad inputs:

  - post without closing cash
  - missing account mapping
  - no employees
  - no Amanat holder
  - duplicate nozzle code
  - partial bill payment
  - overpay bill
  - stock adjustment without account/cost
  - daily close on a past date

  You should see friendly validation/toast errors, never Laravel error pages.

  I’d test in this order: new company setup, first close, second close, fuel
  purchase/receipt, payroll advance, Amanat, reports reconciliation.
