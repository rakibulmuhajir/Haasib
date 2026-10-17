# Withholding tax (WHT)

Pakistani withholding tax on both sides: what we deduct when we pay a vendor, and what a customer deducts when they pay us. Everything sits behind the **Charge tax** switch (Tax settings). With it off there are no WHT fields, no postings, and no menu entry. Payments that already withheld tax keep showing it.

## Rates

Rates are the company's own rows on **Tax > Withholding tax rates**. Nothing is seeded: rates change every budget, and filers and non-filers differ. Each rate has a section (`153(1)(a)` goods, `153(1)(b)` services, `155` rent, `149` salaries, other), a description, a filer %, a non-filer %, and which payments it is for (made, received, both). The section list is a suggestion only.

A new budget means a new rate row with a new "from" date; stop the old one.

- A vendor or customer has a **filer status** (filer, non-filer, not known) and a usual rate. The NTN/CNIC stays in Tax ID.
- Filer takes the filer %, non-filer takes the non-filer %, not known takes the filer %.
- A non-filer with a rate that has **no non-filer %** is refused, never given the filer rate quietly.

## Accounts

Created on first use, only while tax is on, and stored on the company's tax settings:

| Code | Name | Type |
|---|---|---|
| 2310 | Withholding Tax Payable | liability |
| 1196 | Advance Income Tax (WHT deducted by customers) | asset |

2300 and 1190 are taken by the chart packs (Sales Tax Payable, Input Tax Recoverable) and 1195 by the account template, so the next free codes in 2310-2319 and 1196-1199 are used.

## Paying a vendor (we deduct)

WHT is on the amount paid now, in base currency, rounded by the company's tax rounding. Two part-payments give two deductions that add up. A foreign payment withholds on its base-currency amount.

Pay 100,000 at 4%:

| Account | Debit | Credit |
|---|---|---|
| Accounts Payable | 100,000 | |
| Bank | | 96,000 |
| Withholding Tax Payable | | 4,000 |

The bill shows paid. The vendor gets a printable **Withholding tax deduction certificate** (company and vendor NTN, section, gross, rate, tax, payment date and reference) from the payment page.

## Customer pays us (they deduct)

`amount` stays the total the invoices are settled by; the bank gets that less the tax. Enter a rate or an amount (an amount wins), and optionally the number on the customer's certificate.

Customer pays 96,000 against a 100,000 invoice, 4,000 withheld:

| Account | Debit | Credit |
|---|---|---|
| Bank | 96,000 | |
| Advance Income Tax | 4,000 | |
| Accounts Receivable | | 100,000 |

The invoice shows paid. The 4,000 is an advance tax claim.

## Voids, edits, corrections

- Voiding a payment reverses its journal, WHT line included.
- Editing the amount of a payment re-posts it and recomputes the tax at the rate it was taken at. A customer payment with a flat tax amount (no rate) asks to be voided and entered again.
- Moving or splitting a payment that withheld tax (the Correct button) is refused: void and enter it again.

## Register

**Reports > Withholding Tax** (`/{company}/reports/withholding-tax`, needs `report.view`): filter by period and side (deducted by us, deducted from us). Per line: date, party with NTN/CNIC and filer status, section, gross, rate, WHT, payment. Totals per section, and a CSV. A voided payment keeps its line and adds a negative reversal line on the day it was reversed, so totals net to zero.

## Code

`WithholdingTax` (rates, accounts, rounding), `PostingService::postBillPayment` / `postPayment` (journals), `WithholdingRegisterService`, schema in `docs/contracts/tax-schema.md`, `ap-schema.md`, `accounting-invoicing-contract.md`.
