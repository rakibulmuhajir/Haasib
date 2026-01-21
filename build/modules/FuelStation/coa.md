# FuelStation COA Defaults

These accounts are auto-created during Fuel Station onboarding in
`build/modules/FuelStation/Services/FuelStationOnboardingService.php`.
They provide a working chart of accounts out of the box and can be edited later.

## Default accounts (created if missing)

### Assets
| Code | Name | Purpose |
| --- | --- | --- |
| 1000 | Operating Bank Account | Primary bank account for deposits |
| 1030 | Vendor Card Clearing | Vendor card sales awaiting settlement |
| 1040 | Card Receipts Clearing | Bank card sales awaiting settlement |
| 1050 | Cash on Hand | Physical cash in the station |
| 1100 | Accounts Receivable | Customer balances owed to you |
| 1150 | Employee Advances | Salary advances recoverable from payroll |
| 1200 | Fuel Inventory | Value of fuel in tanks |
| 1210 | Lubricants Inventory | Value of lubricants and oils |

### Liabilities
| Code | Name | Purpose |
| --- | --- | --- |
| 2100 | Accounts Payable - Fuel Supplier | Amounts owed to fuel suppliers for purchases |
| 2200 | Amanat Deposits | Customer trust deposits |
| 2210 | Investor Deposits | Capital deposits from partners |

### Equity
| Code | Name | Purpose |
| --- | --- | --- |
| 3100 | Retained Earnings | Accumulated profit at year end |
| 3200 | Partner Drawings | Partner withdrawals |

### Revenue
| Code | Name | Purpose |
| --- | --- | --- |
| 4100 | Fuel Sales | Primary fuel revenue |
| 4110 | Shop Sales | Convenience store sales |
| 4200 | Lubricant Sales | Lubricant revenue |
| 4300 | Discounts Received | Supplier discounts on bills |

### Cost of Goods Sold (COGS)
| Code | Name | Purpose |
| --- | --- | --- |
| 5100 | Cost of Goods - Fuel | Cost of fuel sold |
| 5200 | Cost of Goods - Lubricants | Cost of lubricants sold |
| 5900 | Fuel Shrinkage Loss | Evaporation/variance losses |

### Expenses
| Code | Name | Purpose |
| --- | --- | --- |
| 6100 | Investor Commission Expense | Profit share paid to investors |
| 6150 | Salaries and Wages | Payroll expense |
| 6180 | Cash Short/Over | Daily cash variance |
| 6200 | Card Processing Fees | Vendor/bank card fees |
| 6300 | Utilities | Utilities expense |
| 6400 | Pump Maintenance | Pump maintenance expense |
| 6500 | General Expenses | Miscellaneous operating expenses |

## Company default account mappings

These defaults are saved on the Company record and used by posting templates:

- AR (Accounts Receivable): 1100 Accounts Receivable
- AP (Accounts Payable): 2100 Accounts Payable - Fuel Supplier
- Revenue: 4100 Fuel Sales
- Expense: 6500 General Expenses (fallback)
- Bank/Cash: 1000 Operating Bank Account (fallback to 1050 Cash on Hand)
- Retained Earnings: 3100 Retained Earnings

## Posting template defaults

Posting templates are auto-created in
`build/modules/Accounting/Services/PostingTemplateInstaller.php`.
Line-level accounts on documents (for example, bill line inventory accounts)
override the template EXPENSE/REVENUE fallbacks.

### AR (sales) documents
- AR_INVOICE
  - AR: 1100 Accounts Receivable
  - REVENUE: 4100 Fuel Sales
  - TAX_PAYABLE: company sales tax payable (if configured)
  - DISCOUNT_GIVEN: default expense (fallback)
- AR_PAYMENT
  - AR: 1100 Accounts Receivable
  - BANK: 1000 Operating Bank Account (or 1050 Cash on Hand)
- AR_CREDIT_NOTE
  - AR: 1100 Accounts Receivable
  - REVENUE: 4100 Fuel Sales
  - TAX_PAYABLE: company sales tax payable (if configured)

### AP (vendor) documents
- AP_BILL
  - AP: 2100 Accounts Payable - Fuel Supplier
  - EXPENSE: 6500 General Expenses (fallback)
  - TAX_RECEIVABLE: company purchase tax receivable (if configured)
  - DISCOUNT_RECEIVED: 4300 Discounts Received
- AP_PAYMENT
  - AP: 2100 Accounts Payable - Fuel Supplier
  - BANK: 1000 Operating Bank Account (or 1050 Cash on Hand)
- AP_VENDOR_CREDIT
  - AP: 2100 Accounts Payable - Fuel Supplier
  - EXPENSE: 6500 General Expenses (fallback)
  - TAX_RECEIVABLE: company purchase tax receivable (if configured)

## Notes

- Fuel purchases should use inventory accounts on bill lines (1200 Fuel Inventory,
  1210 Lubricants Inventory). The template EXPENSE role is a fallback only.
- "Discounts Received" (4300) is used for bill discounts and should exist by default.
