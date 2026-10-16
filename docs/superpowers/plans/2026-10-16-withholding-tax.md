# Withholding Tax Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Haasib handles Pakistani withholding tax (WHT) on both sides:
- **When the company pays a vendor**, it deducts WHT, pays the vendor the net amount, owes the WHT to FBR, and can print a deduction certificate.
- **When a customer pays the company**, the customer may have deducted WHT. The company records the net receipt, the gross settles the invoice, and the WHT becomes an advance tax claim.

A WHT register report lists what was deducted, by period, section and party, for the monthly statement and the annual claim.

**Why:** In Pakistan, companies and many individuals must deduct WHT when paying for goods, services, rent and so on (Income Tax Ordinance s.153, s.155, etc.). Accountants deal with this every month. Today Haasib has tax rates with a `withholding` type and NTN/STRN registrations, but nothing ever deducts or posts WHT.

**Ground rules (from the owner):**
- Taxation is **off by default**. The company-wide "Charge tax" switch (`app(\App\Modules\Accounting\Services\CompanyTax::class)->enabled($company)`) governs WHT too. When it's off, no WHT fields or postings appear at all.
- Rates change every budget, and they differ for active (filer) and inactive (non-filer) taxpayers. **Seed no rate numbers.** Users enter rates under Tax, with a section and description. The UI may offer a section list (153(1)(a) goods, 153(1)(b) services, 155 rent, 149 salaries, other) with **blank rates**.

**Paths** are relative to `build/`.

## Global Constraints

- **Core independence:** core must not name FuelStation or Umrah (`CoreIndependenceTest`).
- **Code rules:** FormRequests, CommandBus, schema-prefixed tables, `uuid('id')->primary()`, RLS on new tables (copy the policy from a sibling `acct` table migration), and the schema contracts in `docs/contracts/` if present.
- **Company date:** "today" comes from `CompanyClock`.
- **Data safety:** never run `migrate:fresh` or anything with `--env=testing`. Run one test process at a time. **Never run `php artisan migrate` against the dev DB without printing the database name first.** Applying only this feature's new migrations by `--path` is allowed.
- **Payment math never changes when WHT is zero.** Every existing payment and bill-payment test must pass untouched.
- **Short UI copy.** The words are "vendor" and "customer". Write "WHT" with "withholding tax" spelled out on first use per screen.

## Review Focus

1. **The math balances.** For a bill payment of gross 100,000 at 4%, the vendor's AP is debited 100,000, the bank is credited 96,000 and WHT payable is credited 4,000. The bill shows fully paid. For a customer receipt with net 96,000 and WHT 4,000, the bank is debited 96,000, advance tax is debited 4,000, AR is credited 100,000, and the invoice shows paid.
2. **Partial payments.** WHT applies to the amount being paid now, not the whole bill. Two part-payments give two deductions that add up.
3. **Filer status picks the rate.** A non-filer vendor gets the non-filer rate. If a rate has no non-filer value, the user is warned, not silently given the filer rate.
4. **Voiding a payment reverses its WHT lines too.** The register then shows the reversal, not a deleted row.
5. **Tax switched off:** no WHT UI and no WHT postings. Old payments with WHT still show it.
6. **Multi-currency:** WHT is computed on the base-currency amount. Check how `bill_payment.create` handles `exchange_rate`, and post WHT in base currency.

---

## Task 1: Data model

- **Rates:** WHT rates live in the existing tax rates with `tax_type = 'withholding'`. Add nullable columns as the contract requires: `section` (string, e.g. "153(1)(a)"), `rate_non_filer` (decimal; `rate` is the filer rate), and `applies_to` (`payments_made`, `payments_received` or `both`).
- **Party fields:** customers and vendors get `filer_status` (`filer`, `non_filer` or `unknown`, default `unknown`) and an optional `default_wht_rate_id`. Check where `tax_id` (NTN/CNIC) lives and keep it there.
- **Payment fields:** bill payments and customer payments get nullable `wht_rate_id`, `wht_amount` (decimal, base currency) and `wht_section`, a snapshot for the register.
- **Accounts:** provision, on first use and only when tax is enabled, through `DefaultAccountProvisioner`'s existing pattern:
  - **Withholding tax payable** (liability), pick a free code near 2300;
  - **Advance income tax (WHT deducted by customers)** (asset), pick a free code near 1190.

  Check the general and fuel packs for code collisions (`database/seeders/IndustryCoaPackSeeder.php` plus migration `2026_10_15_000001_replace_general_coa_pack.php`). Store their ids on the company's tax settings, as the other default accounts are stored.
- Migrations need RLS. Update the schema contract docs.

- [ ] Write the migrations, the models (`$fillable`/`$casts`) and a test that they migrate, then commit.

## Task 2: Paying vendors with WHT

- **Posting:** `bill_payment.create` (and its FormRequest) takes optional `wht_rate_id`. When it's present and tax is enabled:
  - WHT equals the payment amount (the gross applied to bills) times the rate, the filer or non-filer rate per the vendor's status, rounded per the company's tax rounding settings.
  - The posting debits AP for the gross, credits the bank/cash for the net, and credits WHT payable for the WHT.
  - `transaction_charge` and `payment_splits` keep working. WHT is a separate line.
- **Void:** voiding reverses the WHT lines along with the rest.
- **UI:** the bill payment form (`modules/Accounting/Resources/js/pages/bill-payments/Create.vue` / `Edit.vue`) gets a "Withhold tax" row, shown only when tax is enabled.
  - It's a rate select that defaults to the vendor's `default_wht_rate_id`.
  - It shows "WHT 4,000; vendor receives 96,000" live.
  - A non-filer vendor shows the non-filer rate, labelled as such.
- **Certificate:** a printable "Withholding tax deduction certificate" per bill payment. Reuse the existing printed-document component (find `LedgerDocument` and `printSheet`, used for invoices and receipts). It shows:
  - the company's NTN and the vendor's NTN/CNIC;
  - the section, the gross, the rate and the WHT;
  - the payment date and reference.
- **Palette:** the catalog picks up `wht_rate_id` automatically as a search field. Add a `tax_rate` search source limited to withholding rates if the field needs one, and give it a `DescribesPalette` hint.

**Tests:** gross/net/WHT posting (Review Focus 1), partial payments (2), non-filer rate (3), void reversal (4), tax off ignores `wht_rate_id` (5), foreign currency in base (6), and the certificate route renders.

- [ ] Write the tests, implement, run the Accounting suite, then commit.

## Task 3: Receiving payments with WHT deducted by the customer

- **Posting:** `payment.create` takes optional `wht_amount` (or `wht_rate_id`, which computes from the gross being settled; support both, since customers sometimes deduct odd amounts).
  - The posting debits the bank for the net and debits advance tax for the WHT. AR is credited for net plus WHT, and the allocations settle invoices by the gross.
  - `received_as` modes (fuel amanat) are untouched.
- **UI:** the receive-payment form (`payments/Create.vue`) gets a "Customer deducted tax" row, shown only when tax is enabled. Enter the amount or pick a rate; the invoice settles by the total.
- **Customer certificates:** keep an optional field for the certificate number the customer issued.

**Tests:** posting (Review Focus 1), invoice shows paid, void reverses, tax off ignores.

- [ ] Write the tests, implement, run the suite, then commit.

## Task 4: WHT register report

`/{company}/reports/withholding-tax`, with filters for period and side (deducted by us / deducted from us). Columns:
- date;
- party, with NTN/CNIC and filer status;
- section;
- gross, rate and WHT;
- the payment reference, linked to the payment.

Show totals per section, and a CSV export (match how other reports export, if they do). Add it to the Reports menu, shown only when tax is enabled. Value trail optional.

**Tests:** totals per section, the period filter, voided payments shown as reversals, and access only with the reports permission.

- [ ] Write the tests, implement, crawl the new page with `build/scripts/smoke-crawl.cjs` (`ONLY='^/reports/withholding'`), then commit.

## Task 5: Settings and docs

- Tax settings page: a "Withholding tax rates" section listing withholding rates. Each has a section, description, filer % and non-filer %. The section list is offered as suggestions, with blank rates.
- Vendor and customer forms: filer status and default WHT rate, shown only when tax is enabled.
- Docs: a short `docs/` note on how WHT posts, with the two journal examples.

- [ ] Write the tests (settings save, fields hidden when tax is off), implement, run vue-tsc and the crawl of touched pages, then commit.
