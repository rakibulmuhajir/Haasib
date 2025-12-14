# UI Screen Specifications – Canonical Reference

**Last Updated**: 2025-12-11
**Purpose**: Complete specification for all transaction and master data screens
**Audience**: Developers implementing UI, backend APIs, and business logic

---

## Related Documents

| Document | Purpose |
|----------|---------|
| **`docs/frontend-experience-contract.md`** | **UX philosophy, user modes, interaction patterns** |
| `docs/contracts/acct-schema.md` | Database schema for accounting module |
| `AI_PROMPTS/toast.md` | Error handling & toast implementation |
| `AI_PROMPTS/FRONTEND_REMEDIATION.md` | Vue component standards |

### Document Relationship

**Read `frontend-experience-contract.md` FIRST** for:
- Owner Mode vs Accountant Mode (information density)
- The Resolution Engine (bank feed processing)
- Navigation & layout patterns
- Dashboard experiences
- Terminology mapping (Owner ↔ Accountant language)
- Mobile strategy
- Interaction patterns & animations

**THIS document** defines:
- Exact fields, types, and validation rules per screen
- Actions with preconditions and effects
- Posting logic (DR/CR entries)
- State models
- Multi-currency and period control rules
- Error handling patterns (Section 15.11)

---

## Table of Contents

0. [Generic Screen Spec Template](#0-generic-screen-spec-template)
1. [Invoice Creation Screen](#1-invoice-creation-screen--full-spec)
2. [Payment Receipt Screen](#2-payment-receipt-screen)
3. [Credit Note Screen](#3-credit-note-screen)
4. [Bill Creation Screen](#4-bill-creation-screen)
5. [Bill Payment Screen](#5-bill-payment-screen)
6. [Vendor Credit Screen](#6-vendor-credit-screen)
7. [Journal Entry Screen](#7-journal-entry-screen)
8. [Bank Reconciliation Screen](#8-bank-reconciliation-screen)
9. [Account Management Screen](#9-account-management-screen)
10. [Customer Management Screen](#10-customer-management-screen)
11. [Vendor Management Screen](#11-vendor-management-screen)
12. [Fiscal Year & Period Management](#12-fiscal-year--period-management)
13. [Currency & FX Rate Management](#13-currency--fx-rate-management)
14. [Tax Configuration Screen](#14-tax-configuration-screen)
15. [Global Rules & Constraints](#15-global-rules--constraints)
    - [Error Handling & Toast Notifications](#1511-error-handling--toast-notifications)

---

## 0. Generic Screen Spec Template

For every screen, you define:

1. **Fields table**
2. **Actions table**
3. **State model**
4. **Posting logic** (DR/CR)
5. **Period & FX rules**
6. **Interaction rules** (how it plays with other screens)

### 0.1 Fields table template

| Field name | Type | Required | Default | Validation / Rules | Ledger impact / Usage | UI notes |
| ---------- | ---- | -------- | ------- | ------------------ | --------------------- | -------- |

### 0.2 Actions table template

| Action | Preconditions | Effect on state | Ledger impact | Permissions / constraints |
| ------ | ------------- | --------------- | ------------- | ------------------------- |

### 0.3 Error Handling & User Feedback

**All screens must use Sonner toast for user feedback. Never expose plain Laravel errors.**

See `AI_PROMPTS/toast.md` for full implementation details.

**Success path:**
```vue
import { useFormFeedback } from '@/composables/useFormFeedback'
const { showSuccess } = useFormFeedback()

// On successful action
showSuccess('Invoice created successfully')
```

**Error path:**
```vue
const { showError } = useFormFeedback()

// Validation errors (shows first error as toast + inline)
form.post(url, {
  onError: (errors) => {
    showError(errors)  // Handles validation error object
  }
})

// System/business logic errors
showError('Cannot void invoice: period is closed')
```

**Backend redirects with flash:**
```php
// Success
return redirect()->route('invoices.index', ['company' => $company->slug])
    ->with('success', 'Invoice created successfully');

// Error
return back()->with('error', 'Cannot approve invoice: period is closed');
```

**Critical rules:**
- ✅ Every user action must show feedback (success or error toast)
- ✅ Validation errors: show inline + toast
- ✅ System errors: catch and show friendly message via toast
- ❌ Never show plain Laravel error pages for business logic failures
- ❌ Never return raw JSON errors that aren't handled by frontend
- ❌ Never let exceptions reach the user without friendly message

---

## 1. Invoice Creation Screen – Full Spec

### 1.1 Purpose

Single economic event: **sale on credit or immediate sale**.
Primary journal (functional currency):

* DR Accounts Receivable
* CR Revenue
* CR Tax Payable (if applicable)

No payments, no credits, no bank movement here.

---

### 1.2 Fields

| Field name        | Type              | Required    | Default                          | Validation / Rules                                                                                              | Ledger impact / Usage                                                      | UI notes                                        |
| ----------------- | ----------------- | ----------- | -------------------------------- | --------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- | ----------------------------------------------- |
| id                | UUID              | system      | auto                             | immutable                                                                                                       | key for joins                                                              | hidden                                          |
| customer_id       | FK: customers     | yes         | –                                | must be active; no posting if inactive                                                                          | defines AR subledger account and aging bucket                              | searchable dropdown                             |
| invoice_number    | string            | yes         | auto sequence by series          | unique per company; locked after Approved                                                                       | used in journal memo and AR reference                                      | editable before approval only                   |
| status            | enum              | system      | `draft`                          | one of: draft, approved, voided                                                                                 | controls whether journal exists                                            | badge in header                                 |
| settlement_status | enum              | system      | `unpaid`                         | unpaid/partially_paid/paid/overpaid                                                                             | derived from allocations (payments + credits)                              | read-only                                       |
| invoice_date      | date              | yes         | today                            | cannot be in future (configurable); cannot be in closed period                                                  | defines accounting period & FX rate date                                   | date picker                                     |
| due_date          | date              | yes         | invoice_date + customer terms    | must be ≥ invoice_date                                                                                          | used for aging                                                             | auto from payment terms, editable               |
| payment_terms_id  | FK: payment_terms | yes         | from customer                    | must be active                                                                                                  | informs due_date only                                                      | dropdown                                        |
| currency_code     | char(3)           | yes         | customer default OR company base | ISO code; allowed currencies list                                                                               | determines which FX logic to use                                           | dropdown if multi-currency enabled              |
| fx_rate           | decimal(18,8)     | conditional | 1.0                              | required if currency ≠ company functional currency; pulled from rate table for invoice_date; locked on approval | converts all TC (transaction currency) amounts to FC (functional currency) | user-visible but read-only if pulled from rates |
| reference         | string            | no          | –                                | length limit                                                                                                    | memo only                                                                  | optional                                        |
| notes             | text              | no          | –                                | –                                                                                                               | memo only                                                                  | textarea                                        |
| line_items[]      | array             | ≥1          | –                                | see below                                                                                                       | drives revenue and tax postings                                            | editable grid                                   |

**Line item structure:**

| Field name         | Type               | Required | Default                              | Validation / Rules                                   | Ledger impact / Usage                         | UI notes                  |
| ------------------ | ------------------ | -------- | ------------------------------------ | ---------------------------------------------------- | --------------------------------------------- | ------------------------- |
| line_id            | UUID               | system   | auto                                 | –                                                    | key only                                      | hidden                    |
| item_id            | FK: items          | no       | –                                    | if present, item must be active                      | can map to default revenue account & tax code | dropdown                  |
| description        | string             | yes      | from item if selected                | non-empty                                            | printed on invoice                            | multiline                 |
| quantity           | decimal(18,4)      | yes      | 1                                    | > 0                                                  | line_total = qty × unit_price – line_discount | numeric                   |
| unit_price         | decimal(18,4)      | yes      | from price list                      | ≥ 0                                                  | part of line_total                            | numeric                   |
| line_discount      | decimal(18,4) or % | no       | 0                                    | cannot exceed qty × price                            | reduces revenue                               | numeric                   |
| revenue_account_id | FK: accounts       | yes      | from item or default revenue account | must be revenue-type account                         | this is CR account                            | dropdown with type filter |
| tax_code_id        | FK: tax_codes      | no       | from item / customer tax profile     | if set, tax calculated per tax rules                 | defines tax payable account                   | dropdown                  |
| line_total_tc      | decimal(18,2)      | system   | calc                                 | qty × unit_price – discount                          | TC = transaction currency                     | read-only                 |
| line_total_fc      | decimal(18,2)      | system   | calc                                 | round(line_total_tc × fx_rate, company_fc_precision) | FC = functional currency                      | read-only                 |

**Header totals (computed):**

| Field                       | Notes                                                  |
| --------------------------- | ------------------------------------------------------ |
| subtotal_tc / fc            | sum of line_total_* before invoice-level disc          |
| invoice_discount_tc / fc    | optional invoice-level discount                        |
| tax_total_tc / fc           | sum by tax_code with fx conversion                     |
| rounding_adjustment_tc / fc | to reconcile to smallest currency unit if needed       |
| grand_total_tc / fc         | subtotal − discounts + tax + rounding                  |
| amount_due_tc / fc          | grand_total − allocations (payments + applied credits) |

**Rounding rules:**

* Monetary display always at currency decimal precision (e.g. 2).
* Internally store at precision (2) and maintain **rounding_adjustment** line:
  * If difference between computed sum and rounded grand_total in FC exceeds config threshold (e.g. 0.01), block and force manual edit.
  * Else post difference to **Rounding Difference** account:
    * If rounding_adjustment_fc > 0: DR AR, CR Rounding Difference
    * If < 0: DR Rounding Difference, CR AR

---

### 1.3 Actions

| Action                  | Preconditions                                                                       | Effect on state                                                                                    | Ledger impact                                                                           | Permissions / constraints                                     |
| ----------------------- | ----------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- | ------------------------------------------------------------- |
| Save Draft              | minimal mandatory fields (customer_id, at least one line item with qty & price)     | status = `draft`                                                                                   | no journal yet                                                                          | Permissions::INVOICE_CREATE                                   |
| Approve                 | draft; passed all validations; period open                                          | status = `approved`; lock fx_rate, customer_id, invoice_number, currency_code, invoice_date, lines | create AR/revenue/tax journal(s) in FC; lock posting period                             | Permissions::INVOICE_APPROVE; log user+timestamp              |
| Edit Draft              | status = `draft`                                                                    | update allowed fields                                                                              | no journal                                                                              | Permissions::INVOICE_UPDATE                                   |
| Edit Approved (limited) | status = `approved`                                                                 | only allow non-financial fields (notes, internal tags) by default                                  | no journal change                                                                       | any change that impacts totals must force credit note instead |
| Void (Open Period)      | status = `approved`; no payments or credits allocated; period for invoice_date open | status = `voided`; mark original as voided, keep visible                                           | create reversing journal in same period: DR Revenue, DR Tax, CR AR (mirroring original) | Permissions::INVOICE_VOID; log full audit                     |
| Void (Closed Period)    | status = `approved`; no allocations; period for invoice_date closed                 | **disallowed**; user must issue credit note                                                        | none                                                                                    | error message "Period closed; use credit note"                |
| Print/PDF               | any status except voided                                                            | no state change                                                                                    | none                                                                                    | Permissions::INVOICE_VIEW                                     |
| Email                   | same as print                                                                       | log email metadata                                                                                 | none                                                                                    | Permissions::INVOICE_VIEW                                     |
| Send                    | status = `approved`                                                                 | update sent_at timestamp, increment send_count                                                     | none                                                                                    | Permissions::INVOICE_SEND                                     |
| Delete                  | status = `draft`; no allocations                                                    | hard delete                                                                                        | none (no journal exists)                                                                | Permissions::INVOICE_DELETE                                   |

---

### 1.4 State Model

Two orthogonal dimensions:

1. **Document status**
   * `draft` → `approved` → `voided`
   * `draft` can be deleted
   * `approved` cannot be deleted, only voided if constraints met
   * `voided` cannot change again

2. **Settlement status** (derived, not manually set)
   * `unpaid` – no payments/credits allocated
   * `partially_paid` – allocations sum < grand_total
   * `paid` – allocations sum == grand_total (within rounding tolerance)
   * `overpaid` – allocations sum > grand_total (overpayment logic lives on payment/credit side, not here)

---

### 1.5 Posting Logic (Approved)

**In FC:**

```
DR Accounts Receivable (customer AR control)  = grand_total_fc
  CR Revenue (per line revenue_account_id)      = sum(line_total_fc – tax portion)
  CR Tax Payable (per tax code)                 = tax_total_fc
  CR/DR Rounding Difference (if any)            = rounding_adjustment_fc
```

Store parallel TC totals for reporting, but posting happens in FC.

FX is locked at **fx_rate on invoice_date** at the moment of approval.

---

### 1.6 Multi-Currency Rules

* If `currency_code == company.functional_currency`, `fx_rate = 1`, FC=TC, no FX handling.
* If different:
  * On approval, fetch FX from rates table for `invoice_date`. Allow override only for users with FX override permission; log overrides.
  * Store: grand_total_tc, fx_rate, grand_total_fc (rounded)
  * AR open balance is tracked both in TC and FC for reporting, but **ledger is FC**.

Realized FX gains/losses recognized on **Payment** screen when comparing original AR FC amount vs payment FC amount.

---

### 1.7 Period Controls

Validation on Approve:

* `invoice_date` must belong to an **open accounting period** for this company.
* If date is in a closed period:
  * Block approval with error: "Posting period closed. Change invoice date or ask admin to reopen period."

---

### 1.8 Interactions: Payments, Credits

**Amount due calculation:**

```
amount_due_tc = grand_total_tc - sum(allocated_payments_tc) - sum(allocated_credits_tc)
amount_due_fc = grand_total_fc - sum(allocated_payments_fc) - sum(allocated_credits_fc)
```

Rules:

* Invoice cannot directly create or modify payments or credits. It only links to them.
* "Receive Payment" button from invoice:
  * Opens Payment Receipt screen with `customer_id`, `invoice_id` pre-selected and allocation suggested.
* "Apply Credit" from invoice:
  * Opens Credit Allocation UI, not part of this screen.

---

### 1.9 Voiding vs Reversing

* **Draft delete**: hard delete allowed, since no journal exists.
* **Approved → Void (open period)**:
  * Keep original invoice with status `voided`.
  * Create reversing journal with same posting_date and same accounts/amounts but inverted signs.
  * Link reverse_journal_id to original.
* **Approved → Void (closed period)**:
  * Disallowed. User must create a **credit note** linked to the invoice.

---

## 2. Payment Receipt Screen

### 2.1 Purpose

Record customer payment against one or more invoices or as advance payment.

Primary journal (functional currency):

* DR Bank/Cash Account
* CR Accounts Receivable (per allocated invoice)
* CR Customer Deposits (for unapplied amount)

### 2.2 Fields

| Field name         | Type              | Required    | Default                          | Validation / Rules                                                         | Ledger impact / Usage                                        | UI notes                      |
| ------------------ | ----------------- | ----------- | -------------------------------- | -------------------------------------------------------------------------- | ------------------------------------------------------------ | ----------------------------- |
| id                 | UUID              | system      | auto                             | immutable                                                                  | key for joins                                                | hidden                        |
| customer_id        | FK: customers     | yes         | –                                | must be active                                                             | defines which AR account to credit                           | searchable dropdown           |
| payment_number     | string            | yes         | auto sequence                    | unique per company                                                         | reference in journal                                         | read-only                     |
| status             | enum              | system      | `draft`                          | draft, posted, voided                                                      | controls journal existence                                   | badge                         |
| payment_date       | date              | yes         | today                            | cannot be in future; cannot be in closed period                            | defines accounting period & FX rate date                     | date picker                   |
| currency_code      | char(3)           | yes         | customer default OR company base | ISO code                                                                   | determines FX logic                                          | dropdown                      |
| fx_rate            | decimal(18,8)     | conditional | 1.0                              | required if currency ≠ FC; locked on posting                               | converts TC to FC                                            | read-only if auto-pulled      |
| bank_account_id    | FK: bank_accounts | yes         | –                                | must be active; must match currency or be multi-currency                   | DR this account                                              | dropdown filtered by currency |
| payment_method_id  | FK: payment_methods | yes       | –                                | e.g., cash, check, wire, card                                              | informational; may affect bank reconciliation                | dropdown                      |
| reference          | string            | no          | –                                | e.g., check number, transaction ID                                         | shown in bank rec                                            | text input                    |
| amount_tc          | decimal(18,2)     | yes         | –                                | > 0                                                                        | total payment received                                       | numeric                       |
| amount_fc          | decimal(18,2)     | system      | calc                             | amount_tc × fx_rate                                                        | what posts to ledger                                         | read-only                     |
| unapplied_tc       | decimal(18,2)     | system      | calc                             | amount_tc − sum(allocations_tc)                                            | if > 0, becomes customer deposit                             | read-only                     |
| unapplied_fc       | decimal(18,2)     | system      | calc                             | amount_fc − sum(allocations_fc)                                            | if > 0, posts to Customer Deposits liability                 | read-only                     |
| notes              | text              | no          | –                                | –                                                                          | memo only                                                    | textarea                      |
| allocations[]      | array             | ≥0          | –                                | see below                                                                  | drives AR reduction per invoice                              | editable grid                 |

**Allocation structure:**

| Field name       | Type            | Required | Default | Validation / Rules                                                                 | Ledger impact / Usage                              | UI notes      |
| ---------------- | --------------- | -------- | ------- | ---------------------------------------------------------------------------------- | -------------------------------------------------- | ------------- |
| allocation_id    | UUID            | system   | auto    | –                                                                                  | key only                                           | hidden        |
| invoice_id       | FK: invoices    | yes      | –       | must be approved; must belong to same customer                                     | links to invoice                                   | dropdown      |
| allocated_tc     | decimal(18,2)   | yes      | –       | > 0; cannot exceed invoice.amount_due_tc + tolerance                               | reduces invoice.amount_due_tc                      | numeric       |
| allocated_fc     | decimal(18,2)   | system   | calc    | allocated_tc × fx_rate (payment fx_rate)                                           | amount applied to AR in FC                         | read-only     |
| fx_gain_loss_fc  | decimal(18,2)   | system   | calc    | invoice.grand_total_fc × (allocated_tc / invoice.grand_total_tc) − allocated_fc    | realized FX gain/loss                              | read-only     |

**FX Gain/Loss Calculation:**

When payment currency differs from invoice currency:

```
Expected FC amount = invoice.fx_rate × allocated_tc
Actual FC amount   = payment.fx_rate × allocated_tc
FX Gain/Loss       = Expected − Actual
```

If positive = gain (CR FX Gain), if negative = loss (DR FX Loss)

---

### 2.3 Actions

| Action            | Preconditions                               | Effect on state                                                        | Ledger impact                                                                                   | Permissions / constraints         |
| ----------------- | ------------------------------------------- | ---------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- | --------------------------------- |
| Save Draft        | minimal fields (customer, amount, bank)     | status = `draft`                                                       | no journal                                                                                      | Permissions::PAYMENT_CREATE       |
| Post              | draft; period open; allocations valid       | status = `posted`; lock all fields                                     | DR Bank, CR AR (per allocation), CR Customer Deposits (unapplied), DR/CR FX Gain/Loss          | Permissions::PAYMENT_POST         |
| Edit Draft        | status = `draft`                            | update allowed                                                         | no journal                                                                                      | Permissions::PAYMENT_UPDATE       |
| Void (Open Period)| status = `posted`; period open              | status = `voided`                                                      | reversing journal in same period                                                                | Permissions::PAYMENT_VOID         |
| Void (Closed Period)| status = `posted`; period closed          | **disallowed**                                                         | none; must create journal entry to reverse                                                      | error message                     |
| Print Receipt     | status = `posted`                           | no state change                                                        | none                                                                                            | Permissions::PAYMENT_VIEW         |
| Delete            | status = `draft`; no allocations            | hard delete                                                            | none                                                                                            | Permissions::PAYMENT_DELETE       |

---

### 2.4 State Model

**Document status:**

* `draft` → `posted` → `voided`
* `draft` can be deleted
* `posted` cannot be deleted, only voided if period open

**Allocation status** (derived per invoice):

* Shown as invoice.settlement_status updates when payment posted

---

### 2.5 Posting Logic (Posted)

**Basic posting:**

```
DR Bank Account (bank_account_id)             = amount_fc
  CR Accounts Receivable (per invoice)         = sum(allocated_fc)
  CR Customer Deposits (if unapplied_fc > 0)   = unapplied_fc
  DR/CR FX Gain/Loss                           = sum(fx_gain_loss_fc)
```

**FX Gain/Loss account:**

* If sum(fx_gain_loss_fc) > 0: CR FX Gain (revenue)
* If sum(fx_gain_loss_fc) < 0: DR FX Loss (expense)

---

### 2.6 Multi-Currency Rules

* Payment currency must either:
  * Match invoice currency, OR
  * Be company functional currency
* Cross-currency payments (e.g., USD payment for EUR invoice) require manual journal entry or special handling (Phase 2).

**Phase 1 constraint:** Payment currency = Invoice currency OR Payment currency = FC

---

### 2.7 Period Controls

* `payment_date` must be in open period.
* If closed, block posting.

---

### 2.8 Interactions

**From Invoice screen:**

* "Receive Payment" button opens this screen with:
  * customer_id pre-filled
  * One allocation line with invoice_id and suggested allocated_tc = invoice.amount_due_tc

**Overpayment handling:**

* If user allocates more than invoice.amount_due_tc + tolerance:
  * Block with error: "Allocation exceeds invoice balance."
* If total amount_tc > sum(allocations_tc):
  * Unapplied amount posts to Customer Deposits.
  * User can later apply from Credit Allocation screen.

**Partial payments:**

* Allowed. Invoice.settlement_status becomes `partially_paid`.

---

### 2.9 Voiding vs Reversing

* Same rules as Invoice.
* Void in open period = reversing journal.
* Void in closed period = disallowed.

---

## 3. Credit Note Screen

### 3.1 Purpose

Issue credit to customer for returns, adjustments, or errors.

Primary journal (functional currency):

* DR Revenue (per line)
* DR Tax Payable (if applicable)
* CR Accounts Receivable

Can be linked to original invoice or standalone.

### 3.2 Fields

| Field name        | Type              | Required    | Default                          | Validation / Rules                                                         | Ledger impact / Usage                                  | UI notes                      |
| ----------------- | ----------------- | ----------- | -------------------------------- | -------------------------------------------------------------------------- | ------------------------------------------------------ | ----------------------------- |
| id                | UUID              | system      | auto                             | immutable                                                                  | key for joins                                          | hidden                        |
| credit_note_number| string            | yes         | auto sequence                    | unique per company                                                         | reference in journal                                   | read-only                     |
| customer_id       | FK: customers     | yes         | –                                | must be active                                                             | defines AR to credit                                   | searchable dropdown           |
| invoice_id        | FK: invoices      | no          | –                                | if set, must belong to customer; used for returns                          | links to original invoice                              | dropdown (optional)           |
| status            | enum              | system      | `draft`                          | draft, approved, voided, applied                                           | controls journal                                       | badge                         |
| credit_date       | date              | yes         | today                            | cannot be in closed period                                                 | defines period & FX rate date                          | date picker                   |
| currency_code     | char(3)           | yes         | from invoice OR customer default | must match invoice currency if invoice_id set                              | determines FX logic                                    | dropdown (locked if linked)   |
| fx_rate           | decimal(18,8)     | conditional | 1.0                              | locked on approval                                                         | converts TC to FC                                      | read-only if auto-pulled      |
| reason_code       | enum              | yes         | –                                | e.g., return, discount, error correction                                   | informational; may affect accounts used                | dropdown                      |
| reference         | string            | no          | –                                | –                                                                          | memo                                                   | text input                    |
| notes             | text              | no          | –                                | –                                                                          | memo                                                   | textarea                      |
| line_items[]      | array             | ≥1          | –                                | see below                                                                  | drives revenue/tax reversal                            | editable grid                 |
| amount_remaining_tc | decimal(18,2)   | system      | calc                             | credit_total_tc − sum(applications_tc)                                     | available to apply to invoices                         | read-only                     |
| amount_remaining_fc | decimal(18,2)   | system      | calc                             | credit_total_fc − sum(applications_fc)                                     | available to apply in FC                               | read-only                     |

**Line item structure:**

| Field name         | Type               | Required | Default                     | Validation / Rules                           | Ledger impact / Usage       | UI notes                  |
| ------------------ | ------------------ | -------- | --------------------------- | -------------------------------------------- | --------------------------- | ------------------------- |
| line_id            | UUID               | system   | auto                        | –                                            | key only                    | hidden                    |
| description        | string             | yes      | from invoice line if linked | non-empty                                    | printed on credit note      | multiline                 |
| quantity           | decimal(18,4)      | yes      | 1                           | > 0                                          | line_total = qty × unit_price | numeric                 |
| unit_price         | decimal(18,4)      | yes      | from invoice line if linked | ≥ 0                                          | part of line_total          | numeric                   |
| revenue_account_id | FK: accounts       | yes      | from invoice line           | must be revenue-type                         | this is DR account          | dropdown                  |
| tax_code_id        | FK: tax_codes      | no       | from invoice line           | –                                            | defines tax payable account | dropdown                  |
| line_total_tc      | decimal(18,2)      | system   | calc                        | qty × unit_price                             | TC amount                   | read-only                 |
| line_total_fc      | decimal(18,2)      | system   | calc                        | line_total_tc × fx_rate                      | FC amount                   | read-only                 |

**Header totals:**

| Field                   | Notes                                       |
| ----------------------- | ------------------------------------------- |
| subtotal_tc / fc        | sum of line_total_*                         |
| tax_total_tc / fc       | sum by tax_code                             |
| credit_total_tc / fc    | subtotal + tax                              |
| amount_remaining_tc / fc| credit_total − applications (to invoices)   |

---

### 3.3 Actions

| Action            | Preconditions                           | Effect on state                                            | Ledger impact                                                      | Permissions / constraints         |
| ----------------- | --------------------------------------- | ---------------------------------------------------------- | ------------------------------------------------------------------ | --------------------------------- |
| Save Draft        | minimal fields (customer, ≥1 line)      | status = `draft`                                           | no journal                                                         | Permissions::CREDIT_NOTE_CREATE   |
| Approve           | draft; period open                      | status = `approved`; lock fields                           | DR Revenue, DR Tax Payable, CR AR                                  | Permissions::CREDIT_NOTE_APPROVE  |
| Apply to Invoice  | status = `approved`; amount_remaining > 0| create application record; reduce amount_remaining         | no new journal; updates invoice.amount_due                         | Permissions::CREDIT_NOTE_APPLY    |
| Void (Open Period)| status = `approved`; no applications; period open | status = `voided`                                  | reversing journal                                                  | Permissions::CREDIT_NOTE_VOID     |
| Void (Closed Period)| status = `approved`; period closed    | **disallowed**                                             | none                                                               | error message                     |
| Print             | status = `approved`                     | no state change                                            | none                                                               | Permissions::CREDIT_NOTE_VIEW     |
| Delete            | status = `draft`                        | hard delete                                                | none                                                               | Permissions::CREDIT_NOTE_DELETE   |

---

### 3.4 State Model

**Document status:**

* `draft` → `approved` → `applied` (fully applied) or `voided`
* Partial application: status stays `approved`, amount_remaining decreases

**Application status** (derived):

* `unapplied` – amount_remaining_tc == credit_total_tc
* `partially_applied` – 0 < amount_remaining_tc < credit_total_tc
* `fully_applied` – amount_remaining_tc == 0

---

### 3.5 Posting Logic (Approved)

**On approval:**

```
DR Revenue (per line revenue_account_id)  = sum(line_total_fc – tax)
DR Tax Payable (per tax code)             = tax_total_fc
  CR Accounts Receivable (customer)        = credit_total_fc
```

**On application to invoice:**

No new journal. Just create `credit_note_applications` record:

* credit_note_id
* invoice_id
* applied_tc
* applied_fc

Invoice.amount_due_tc/fc decreases by applied_tc/fc.

---

### 3.6 Multi-Currency Rules

* Credit note currency must match invoice currency if linked.
* If standalone, can use any active currency.
* FX rate locked at credit_date on approval.
* Application to invoice in different period with different FX rate:
  * Use credit note's fx_rate (locked at approval), not current rate.

---

### 3.7 Period Controls

* `credit_date` must be in open period.
* Applications can occur in later periods (as long as application period is open).

---

### 3.8 Interactions

**From Invoice screen:**

* "Issue Credit Note" button opens this screen with:
  * customer_id, invoice_id, currency_code pre-filled
  * Line items can default from invoice lines (for returns)

**Applying credit to invoice:**

* From Credit Note screen: "Apply to Invoice" button
* Opens allocation dialog
* Select invoice(s), enter applied_tc per invoice
* Cannot exceed invoice.amount_due_tc or credit_note.amount_remaining_tc

**Refund vs Credit:**

* Credit note reduces AR (customer owes less).
* If customer wants refund (cash back), that's a separate **Customer Refund** transaction (Bill Payment to customer).

---

### 3.9 Voiding vs Reversing

* Same rules as Invoice.
* Void in open period = reversing journal.
* Cannot void if any applications exist (must un-apply first or block).

---

## 4. Bill Creation Screen

### 4.1 Purpose

Record vendor bill (purchase on credit).

Primary journal (functional currency):

* DR Expense/Asset/Inventory (per line)
* DR Tax Recoverable (if applicable)
* CR Accounts Payable

Mirror of Invoice, but for vendors.

---

### 4.2 Fields

| Field name        | Type              | Required    | Default                          | Validation / Rules                                                         | Ledger impact / Usage                                  | UI notes                      |
| ----------------- | ----------------- | ----------- | -------------------------------- | -------------------------------------------------------------------------- | ------------------------------------------------------ | ----------------------------- |
| id                | UUID              | system      | auto                             | immutable                                                                  | key for joins                                          | hidden                        |
| vendor_id         | FK: vendors       | yes         | –                                | must be active                                                             | defines AP subledger account                           | searchable dropdown           |
| bill_number       | string            | yes         | manual or auto                   | unique per company                                                         | reference in journal                                   | text input                    |
| status            | enum              | system      | `draft`                          | draft, approved, voided                                                    | controls journal                                       | badge                         |
| payment_status    | enum              | system      | `unpaid`                         | unpaid/partially_paid/paid/overpaid                                        | derived from payments                                  | read-only                     |
| bill_date         | date              | yes         | today                            | cannot be in closed period                                                 | defines period & FX rate date                          | date picker                   |
| due_date          | date              | yes         | bill_date + vendor terms         | must be ≥ bill_date                                                        | used for AP aging                                      | auto, editable                |
| payment_terms_id  | FK: payment_terms | yes         | from vendor                      | must be active                                                             | informs due_date                                       | dropdown                      |
| currency_code     | char(3)           | yes         | vendor default OR company base   | ISO code                                                                   | determines FX logic                                    | dropdown                      |
| fx_rate           | decimal(18,8)     | conditional | 1.0                              | locked on approval                                                         | converts TC to FC                                      | read-only if auto-pulled      |
| reference         | string            | no          | –                                | vendor's invoice number                                                    | memo                                                   | text input                    |
| notes             | text              | no          | –                                | –                                                                          | memo                                                   | textarea                      |
| line_items[]      | array             | ≥1          | –                                | see below                                                                  | drives expense/tax postings                            | editable grid                 |

**Line item structure:**

| Field name         | Type               | Required | Default                       | Validation / Rules                 | Ledger impact / Usage       | UI notes                  |
| ------------------ | ------------------ | -------- | ----------------------------- | ---------------------------------- | --------------------------- | ------------------------- |
| line_id            | UUID               | system   | auto                          | –                                  | key only                    | hidden                    |
| item_id            | FK: items          | no       | –                             | if present, must be active         | maps to expense account     | dropdown                  |
| description        | string             | yes      | from item if selected         | non-empty                          | shown on bill               | multiline                 |
| quantity           | decimal(18,4)      | yes      | 1                             | > 0                                | line_total = qty × unit_price | numeric                 |
| unit_price         | decimal(18,4)      | yes      | –                             | ≥ 0                                | part of line_total          | numeric                   |
| expense_account_id | FK: accounts       | yes      | from item or default expense  | must be expense/asset/inventory    | this is DR account          | dropdown with type filter |
| tax_code_id        | FK: tax_codes      | no       | from item / vendor tax profile| –                                  | defines tax recoverable     | dropdown                  |
| line_total_tc      | decimal(18,2)      | system   | calc                          | qty × unit_price                   | TC amount                   | read-only                 |
| line_total_fc      | decimal(18,2)      | system   | calc                          | line_total_tc × fx_rate            | FC amount                   | read-only                 |

**Header totals:**

| Field                   | Notes                                       |
| ----------------------- | ------------------------------------------- |
| subtotal_tc / fc        | sum of line_total_*                         |
| tax_total_tc / fc       | sum by tax_code                             |
| grand_total_tc / fc     | subtotal + tax + rounding                   |
| amount_due_tc / fc      | grand_total − payments                      |

---

### 4.3 Actions

| Action                  | Preconditions                           | Effect on state                            | Ledger impact                                                      | Permissions / constraints         |
| ----------------------- | --------------------------------------- | ------------------------------------------ | ------------------------------------------------------------------ | --------------------------------- |
| Save Draft              | minimal fields (vendor, ≥1 line)        | status = `draft`                           | no journal                                                         | Permissions::BILL_CREATE          |
| Approve                 | draft; period open                      | status = `approved`; lock fields           | DR Expense/Tax, CR AP                                              | Permissions::BILL_APPROVE         |
| Edit Draft              | status = `draft`                        | update allowed                             | no journal                                                         | Permissions::BILL_UPDATE          |
| Void (Open Period)      | status = `approved`; no payments; period open | status = `voided`                    | reversing journal                                                  | Permissions::BILL_VOID            |
| Void (Closed Period)    | status = `approved`; period closed      | **disallowed**                             | none; use vendor credit instead                                    | error message                     |
| Print                   | status = `approved`                     | no state change                            | none                                                               | Permissions::BILL_VIEW            |
| Delete                  | status = `draft`                        | hard delete                                | none                                                               | Permissions::BILL_DELETE          |

---

### 4.4 State Model

Same as Invoice, mirrored:

* **Document status:** `draft` → `approved` → `voided`
* **Payment status:** `unpaid` → `partially_paid` → `paid` → `overpaid`

---

### 4.5 Posting Logic (Approved)

```
DR Expense/Asset/Inventory (per line)    = sum(line_total_fc – tax)
DR Tax Recoverable (per tax code)        = tax_total_fc
  CR Accounts Payable (vendor)            = grand_total_fc
```

---

### 4.6 Multi-Currency Rules

* Same as Invoice.
* FX locked at bill_date on approval.
* Realized FX gains/losses on Bill Payment.

---

### 4.7 Period Controls

* `bill_date` must be in open period.

---

### 4.8 Interactions

* "Make Payment" button opens **Bill Payment** screen with vendor_id, bill_id pre-filled.
* Vendor Credit can be applied to reduce amount_due.

---

### 4.9 Voiding vs Reversing

* Same as Invoice.
* Cannot void if payments exist.

---

## 5. Bill Payment Screen

### 5.1 Purpose

Record payment to vendor against one or more bills or as advance payment.

Primary journal (functional currency):

* DR Accounts Payable (per allocated bill)
* DR Vendor Deposits (for unapplied amount)
* CR Bank/Cash Account

Mirror of Payment Receipt, but for vendors.

---

### 5.2 Fields

| Field name         | Type              | Required    | Default                          | Validation / Rules                         | Ledger impact / Usage                | UI notes                      |
| ------------------ | ----------------- | ----------- | -------------------------------- | ------------------------------------------ | ------------------------------------ | ----------------------------- |
| id                 | UUID              | system      | auto                             | immutable                                  | key for joins                        | hidden                        |
| vendor_id          | FK: vendors       | yes         | –                                | must be active                             | defines which AP to debit            | searchable dropdown           |
| payment_number     | string            | yes         | auto sequence                    | unique per company                         | reference in journal                 | read-only                     |
| status             | enum              | system      | `draft`                          | draft, posted, voided                      | controls journal                     | badge                         |
| payment_date       | date              | yes         | today                            | cannot be in closed period                 | defines period & FX rate             | date picker                   |
| currency_code      | char(3)           | yes         | vendor default OR company base   | ISO code                                   | determines FX logic                  | dropdown                      |
| fx_rate            | decimal(18,8)     | conditional | 1.0                              | locked on posting                          | converts TC to FC                    | read-only if auto-pulled      |
| bank_account_id    | FK: bank_accounts | yes         | –                                | must be active; currency match             | CR this account                      | dropdown                      |
| payment_method_id  | FK: payment_methods | yes       | –                                | e.g., check, wire                          | informational                        | dropdown                      |
| reference          | string            | no          | –                                | check number, wire ref                     | shown in bank rec                    | text input                    |
| amount_tc          | decimal(18,2)     | yes         | –                                | > 0                                        | total payment made                   | numeric                       |
| amount_fc          | decimal(18,2)     | system      | calc                             | amount_tc × fx_rate                        | posts to ledger                      | read-only                     |
| unapplied_tc       | decimal(18,2)     | system      | calc                             | amount_tc − sum(allocations_tc)            | becomes vendor deposit if > 0        | read-only                     |
| unapplied_fc       | decimal(18,2)     | system      | calc                             | amount_fc − sum(allocations_fc)            | posts to Vendor Deposits asset       | read-only                     |
| notes              | text              | no          | –                                | –                                          | memo                                 | textarea                      |
| allocations[]      | array             | ≥0          | –                                | see below                                  | drives AP reduction per bill         | editable grid                 |

**Allocation structure:**

| Field name       | Type            | Required | Default | Validation / Rules                                     | Ledger impact / Usage       | UI notes      |
| ---------------- | --------------- | -------- | ------- | ------------------------------------------------------ | --------------------------- | ------------- |
| allocation_id    | UUID            | system   | auto    | –                                                      | key only                    | hidden        |
| bill_id          | FK: bills       | yes      | –       | must be approved; same vendor                          | links to bill               | dropdown      |
| allocated_tc     | decimal(18,2)   | yes      | –       | > 0; cannot exceed bill.amount_due_tc + tolerance      | reduces bill.amount_due_tc  | numeric       |
| allocated_fc     | decimal(18,2)   | system   | calc    | allocated_tc × fx_rate (payment fx_rate)               | amount applied to AP in FC  | read-only     |
| fx_gain_loss_fc  | decimal(18,2)   | system   | calc    | bill FC vs payment FC difference                       | realized FX gain/loss       | read-only     |

---

### 5.3 Actions

| Action            | Preconditions                           | Effect on state                            | Ledger impact                                                      | Permissions / constraints         |
| ----------------- | --------------------------------------- | ------------------------------------------ | ------------------------------------------------------------------ | --------------------------------- |
| Save Draft        | minimal fields (vendor, amount, bank)   | status = `draft`                           | no journal                                                         | Permissions::BILL_PAYMENT_CREATE  |
| Post              | draft; period open; allocations valid   | status = `posted`; lock fields             | DR AP, DR Vendor Deposits, CR Bank, DR/CR FX Gain/Loss             | Permissions::BILL_PAYMENT_POST    |
| Edit Draft        | status = `draft`                        | update allowed                             | no journal                                                         | Permissions::BILL_PAYMENT_UPDATE  |
| Void (Open Period)| status = `posted`; period open          | status = `voided`                          | reversing journal                                                  | Permissions::BILL_PAYMENT_VOID    |
| Print             | status = `posted`                       | no state change                            | none                                                               | Permissions::BILL_PAYMENT_VIEW    |
| Delete            | status = `draft`                        | hard delete                                | none                                                               | Permissions::BILL_PAYMENT_DELETE  |

---

### 5.4 State Model

* **Document status:** `draft` → `posted` → `voided`

---

### 5.5 Posting Logic (Posted)

```
DR Accounts Payable (per bill)           = sum(allocated_fc)
DR Vendor Deposits (if unapplied_fc > 0) = unapplied_fc
DR/CR FX Gain/Loss                       = sum(fx_gain_loss_fc)
  CR Bank Account                         = amount_fc
```

---

### 5.6 Multi-Currency Rules

* Same as Payment Receipt.
* Payment currency must match bill currency OR be FC.

---

### 5.7 Period Controls

* `payment_date` must be in open period.

---

### 5.8 Interactions

* From Bill screen: "Make Payment" button.
* Vendor Credit can be applied separately (not via payment).

---

### 5.9 Voiding vs Reversing

* Same as Payment Receipt.

---

## 6. Vendor Credit Screen

### 6.1 Purpose

Record credit from vendor for returns, adjustments, or errors.

Primary journal (functional currency):

* DR Accounts Payable
* CR Expense (per line)
* CR Tax Recoverable (if applicable)

Mirror of Credit Note, but for vendors.

---

### 6.2 Fields

| Field name        | Type              | Required    | Default                          | Validation / Rules                         | Ledger impact / Usage          | UI notes                      |
| ----------------- | ----------------- | ----------- | -------------------------------- | ------------------------------------------ | ------------------------------ | ----------------------------- |
| id                | UUID              | system      | auto                             | immutable                                  | key for joins                  | hidden                        |
| vendor_credit_number | string         | yes         | auto sequence                    | unique per company                         | reference in journal           | read-only                     |
| vendor_id         | FK: vendors       | yes         | –                                | must be active                             | defines AP to debit            | searchable dropdown           |
| bill_id           | FK: bills         | no          | –                                | if set, must belong to vendor              | links to original bill         | dropdown (optional)           |
| status            | enum              | system      | `draft`                          | draft, approved, voided, applied           | controls journal               | badge                         |
| credit_date       | date              | yes         | today                            | cannot be in closed period                 | defines period & FX rate       | date picker                   |
| currency_code     | char(3)           | yes         | from bill OR vendor default      | must match bill currency if linked         | determines FX logic            | dropdown (locked if linked)   |
| fx_rate           | decimal(18,8)     | conditional | 1.0                              | locked on approval                         | converts TC to FC              | read-only if auto-pulled      |
| reference         | string            | no          | –                                | vendor's credit note number                | memo                           | text input                    |
| notes             | text              | no          | –                                | –                                          | memo                           | textarea                      |
| line_items[]      | array             | ≥1          | –                                | see below                                  | drives expense reversal        | editable grid                 |
| amount_remaining_tc | decimal(18,2)   | system      | calc                             | credit_total_tc − applications             | available to apply             | read-only                     |
| amount_remaining_fc | decimal(18,2)   | system      | calc                             | credit_total_fc − applications             | available in FC                | read-only                     |

**Line item structure:**

| Field name         | Type               | Required | Default               | Validation / Rules       | Ledger impact / Usage | UI notes      |
| ------------------ | ------------------ | -------- | --------------------- | ------------------------ | --------------------- | ------------- |
| line_id            | UUID               | system   | auto                  | –                        | key only              | hidden        |
| description        | string             | yes      | from bill line        | non-empty                | shown on credit       | multiline     |
| quantity           | decimal(18,4)      | yes      | 1                     | > 0                      | line_total = qty × price | numeric    |
| unit_price         | decimal(18,4)      | yes      | from bill line        | ≥ 0                      | part of line_total    | numeric       |
| expense_account_id | FK: accounts       | yes      | from bill line        | must be expense/asset    | this is CR account    | dropdown      |
| tax_code_id        | FK: tax_codes      | no       | from bill line        | –                        | tax recoverable       | dropdown      |
| line_total_tc      | decimal(18,2)      | system   | calc                  | qty × unit_price         | TC amount             | read-only     |
| line_total_fc      | decimal(18,2)      | system   | calc                  | line_total_tc × fx_rate  | FC amount             | read-only     |

---

### 6.3 Actions

| Action            | Preconditions                           | Effect on state                    | Ledger impact                     | Permissions / constraints          |
| ----------------- | --------------------------------------- | ---------------------------------- | --------------------------------- | ---------------------------------- |
| Save Draft        | minimal fields (vendor, ≥1 line)        | status = `draft`                   | no journal                        | Permissions::VENDOR_CREDIT_CREATE  |
| Approve           | draft; period open                      | status = `approved`; lock fields   | DR AP, CR Expense, CR Tax         | Permissions::VENDOR_CREDIT_APPROVE |
| Apply to Bill     | status = `approved`; amount_remaining > 0| create application; reduce remaining | no new journal; updates bill.amount_due | Permissions::VENDOR_CREDIT_APPLY |
| Void (Open Period)| status = `approved`; no applications; period open | status = `voided`        | reversing journal                 | Permissions::VENDOR_CREDIT_VOID    |
| Print             | status = `approved`                     | no state change                    | none                              | Permissions::VENDOR_CREDIT_VIEW    |
| Delete            | status = `draft`                        | hard delete                        | none                              | Permissions::VENDOR_CREDIT_DELETE  |

---

### 6.4 State Model

* **Document status:** `draft` → `approved` → `applied` (fully) or `voided`
* **Application status:** `unapplied` → `partially_applied` → `fully_applied`

---

### 6.5 Posting Logic (Approved)

```
DR Accounts Payable (vendor)            = credit_total_fc
  CR Expense (per line)                  = sum(line_total_fc – tax)
  CR Tax Recoverable (per tax code)      = tax_total_fc
```

---

### 6.6 Multi-Currency Rules

* Same as Credit Note.
* Must match bill currency if linked.

---

### 6.7 Period Controls

* `credit_date` must be in open period.

---

### 6.8 Interactions

* From Bill screen: "Record Vendor Credit" button.
* Application reduces bill.amount_due_tc/fc.

---

### 6.9 Voiding vs Reversing

* Same as Credit Note.

---

## 7. Journal Entry Screen

### 7.1 Purpose

Manual general ledger posting for:

* Adjustments
* Accruals/deferrals
* Reclassifications
* Period-end entries

Must balance (total DR = total CR in FC).

---

### 7.2 Fields

| Field name        | Type              | Required    | Default     | Validation / Rules                           | Ledger impact / Usage            | UI notes           |
| ----------------- | ----------------- | ----------- | ----------- | -------------------------------------------- | -------------------------------- | ------------------ |
| id                | UUID              | system      | auto        | immutable                                    | key for joins                    | hidden             |
| journal_number    | string            | yes         | auto sequence | unique per company                         | reference                        | read-only          |
| status            | enum              | system      | `draft`     | draft, posted, voided                        | controls posting                 | badge              |
| journal_date      | date              | yes         | today       | cannot be in closed period                   | defines period                   | date picker        |
| reference         | string            | no          | –           | –                                            | memo                             | text input         |
| description       | text              | yes         | –           | non-empty                                    | shown in ledger                  | textarea           |
| recurring         | boolean           | no          | false       | if true, create recurring template           | informational                    | checkbox           |
| lines[]           | array             | ≥2          | –           | see below; must balance                      | drives all GL postings           | editable grid      |

**Line structure:**

| Field name    | Type            | Required | Default | Validation / Rules                           | Ledger impact / Usage | UI notes      |
| ------------- | --------------- | -------- | ------- | -------------------------------------------- | --------------------- | ------------- |
| line_id       | UUID            | system   | auto    | –                                            | key only              | hidden        |
| account_id    | FK: accounts    | yes      | –       | must be active; cannot be control account unless override | posts to this account | dropdown |
| description   | string          | no       | from header | –                                        | line memo             | text input    |
| debit_fc      | decimal(18,2)   | conditional | 0    | ≥ 0; either debit_fc or credit_fc must be > 0 | DR amount           | numeric       |
| credit_fc     | decimal(18,2)   | conditional | 0    | ≥ 0; cannot have both debit and credit > 0   | CR amount             | numeric       |

**Validation:**

* Sum(debit_fc) must equal Sum(credit_fc) within tolerance (e.g., 0.01).
* Each line must have exactly one of debit_fc or credit_fc > 0.

---

### 7.3 Actions

| Action            | Preconditions                           | Effect on state                | Ledger impact                     | Permissions / constraints      |
| ----------------- | --------------------------------------- | ------------------------------ | --------------------------------- | ------------------------------ |
| Save Draft        | minimal fields (date, ≥2 lines)         | status = `draft`               | no journal                        | Permissions::JE_CREATE         |
| Post              | draft; period open; balanced            | status = `posted`; lock fields | post all lines to GL              | Permissions::JE_POST           |
| Edit Draft        | status = `draft`                        | update allowed                 | no journal                        | Permissions::JE_UPDATE         |
| Void (Open Period)| status = `posted`; period open          | status = `voided`              | reversing journal                 | Permissions::JE_VOID           |
| Void (Closed Period)| status = `posted`; period closed      | **disallowed**                 | none                              | error message                  |
| Copy              | any status                              | create new draft with same lines | no journal                      | Permissions::JE_CREATE         |
| Delete            | status = `draft`                        | hard delete                    | none                              | Permissions::JE_DELETE         |

---

### 7.4 State Model

* **Document status:** `draft` → `posted` → `voided`

---

### 7.5 Posting Logic (Posted)

* Direct posting: each line creates one transaction in `acct.transactions`:
  * account_id
  * debit_fc or credit_fc
  * journal_date
  * reference: journal_number + description

---

### 7.6 Multi-Currency Rules

* Journal entries post in FC only.
* For multi-currency adjustments, user must calculate FC amounts manually or use specialized FX revaluation screen.

---

### 7.7 Period Controls

* `journal_date` must be in open period.

---

### 7.8 Interactions

* Standalone screen.
* Can reference other documents in description field (e.g., "Accrual for Invoice INV-1234").

---

### 7.9 Voiding vs Reversing

* Void in open period = reversing entry.
* Cannot void in closed period.

---

## 8. Bank Reconciliation Screen

### 8.1 Purpose

Match bank statement lines to internal transactions (payments, receipts, journal entries).

Identifies:

* Matched transactions (reconciled)
* Unmatched bank lines (needs categorization)
* Unmatched internal transactions (not yet cleared)

Minimal posting:

* Only for bank charges, interest, adjustments via quick JE.

---

### 8.2 Fields

| Field name           | Type              | Required | Default | Validation / Rules                      | Ledger impact / Usage                 | UI notes           |
| -------------------- | ----------------- | -------- | ------- | --------------------------------------- | ------------------------------------- | ------------------ |
| id                   | UUID              | system   | auto    | immutable                               | key for joins                         | hidden             |
| bank_account_id      | FK: bank_accounts | yes      | –       | must be active                          | defines account to reconcile          | dropdown           |
| reconciliation_number| string            | yes      | auto sequence | unique per company                  | reference                             | read-only          |
| status               | enum              | system   | `draft` | draft, completed                        | controls lock                         | badge              |
| statement_date       | date              | yes      | –       | must be ≥ previous reconciliation date  | cutoff date for matching              | date picker        |
| statement_balance_fc | decimal(18,2)     | yes      | –       | ending balance from bank statement      | compared to GL balance                | numeric            |
| gl_balance_fc        | decimal(18,2)     | system   | calc    | sum of all posted transactions to bank_account_id up to statement_date | starting point | read-only |
| reconciled_balance_fc| decimal(18,2)     | system   | calc    | gl_balance_fc + matched bank lines – matched internal lines | should equal statement_balance_fc | read-only |
| difference_fc        | decimal(18,2)     | system   | calc    | statement_balance_fc − reconciled_balance_fc | must be 0 to complete            | read-only (highlight if != 0) |
| notes                | text              | no       | –       | –                                       | memo                                  | textarea           |
| bank_lines[]         | array             | ≥0       | imported| see below                               | imported or manual                    | grid               |
| internal_lines[]     | array             | ≥0       | from GL | transactions to bank_account_id not yet reconciled | for matching               | grid               |
| matches[]            | array             | ≥0       | –       | see below                               | links bank to internal                | created by user    |

**Bank line structure:**

| Field name       | Type            | Required | Default | Validation / Rules | Ledger impact / Usage                 | UI notes      |
| ---------------- | --------------- | -------- | ------- | ------------------ | ------------------------------------- | ------------- |
| bank_line_id     | UUID            | system   | auto    | –                  | key only                              | hidden        |
| transaction_date | date            | yes      | –       | –                  | date from bank                        | read-only     |
| description      | string          | yes      | –       | –                  | from bank                             | read-only     |
| amount_fc        | decimal(18,2)   | yes      | –       | can be + or −      | positive = deposit, negative = withdrawal | read-only |
| matched          | boolean         | system   | false   | –                  | true if matched to internal line      | indicator     |

**Internal line structure:**

| Field name       | Type            | Required | Default | Validation / Rules | Ledger impact / Usage                 | UI notes      |
| ---------------- | --------------- | -------- | ------- | ------------------ | ------------------------------------- | ------------- |
| transaction_id   | UUID            | system   | auto    | –                  | links to acct.transactions            | hidden        |
| transaction_date | date            | yes      | –       | –                  | from GL                               | read-only     |
| description      | string          | yes      | –       | –                  | from source document                  | read-only     |
| amount_fc        | decimal(18,2)   | yes      | –       | can be + or −      | debit = +, credit = −                 | read-only     |
| matched          | boolean         | system   | false   | –                  | true if matched to bank line          | indicator     |

**Match structure:**

| Field name       | Type            | Required | Default | Validation / Rules | Ledger impact / Usage | UI notes      |
| ---------------- | --------------- | -------- | ------- | ------------------ | --------------------- | ------------- |
| match_id         | UUID            | system   | auto    | –                  | key only              | hidden        |
| bank_line_id     | UUID            | yes      | –       | –                  | links to bank line    | hidden        |
| transaction_id   | UUID            | yes      | –       | –                  | links to internal line| hidden        |
| match_type       | enum            | yes      | –       | exact, partial, many-to-one | describes match relationship | dropdown |
| difference_fc    | decimal(18,2)   | system   | calc    | bank − internal    | if != 0, may need adjustment | read-only |

---

### 8.3 Actions

| Action            | Preconditions                                      | Effect on state                                  | Ledger impact                                                      | Permissions / constraints              |
| ----------------- | -------------------------------------------------- | ------------------------------------------------ | ------------------------------------------------------------------ | -------------------------------------- |
| Import Statement  | bank_account_id, statement_date                    | populate bank_lines[] from file (CSV, OFX, etc.) | none                                                               | Permissions::BANK_REC_CREATE           |
| Match (Manual)    | select bank line + internal line                   | create match record; mark both as matched        | none                                                               | Permissions::BANK_REC_MATCH            |
| Auto-Match        | click button                                       | algorithm matches exact amounts & dates          | none                                                               | Permissions::BANK_REC_MATCH            |
| Unmatch           | select match                                       | delete match record; unmark lines                | none                                                               | Permissions::BANK_REC_MATCH            |
| Add Adjustment    | unmatched bank line (e.g., fee, interest)          | create quick JE; post to GL; add to internal_lines[] | DR/CR Bank, CR/DR Expense/Income                                | Permissions::BANK_REC_ADJUST           |
| Complete          | difference_fc == 0 (within tolerance)              | status = `completed`; lock; mark all matched lines as reconciled | all matched transactions marked reconciled_at = statement_date | Permissions::BANK_REC_COMPLETE         |
| Reopen            | status = `completed`; no subsequent reconciliation | status = `draft`; unmark reconciled_at           | none                                                               | Permissions::BANK_REC_REOPEN           |
| Delete            | status = `draft`                                   | hard delete; unmark all matches                  | none                                                               | Permissions::BANK_REC_DELETE           |

---

### 8.4 State Model

* **Status:** `draft` → `completed`
* **Completed** locks the reconciliation. Cannot edit matches or balances.
* **Reopen** only if no later reconciliation exists (to preserve chronology).

---

### 8.5 Posting Logic

**No posting for matching itself.**

**Posting only for adjustments:**

* User selects unmatched bank line → "Add Adjustment" → creates mini JE:
  * If bank fee:
    ```
    DR Bank Charges Expense = amount_fc
      CR Bank Account         = amount_fc
    ```
  * If interest income:
    ```
    DR Bank Account       = amount_fc
      CR Interest Income   = amount_fc
    ```

* This JE is posted immediately and appears in internal_lines[] for matching.

---

### 8.6 Multi-Currency Rules

* Bank account has single currency.
* All amounts in bank reconciliation in that currency (no FX conversion).
* If company FC ≠ bank currency, bank account balance is tracked in bank currency; revalued separately at period end.

---

### 8.7 Period Controls

* `statement_date` can be in closed period (reconciliation is allowed retroactively).
* Adjustments (JEs) created during reconciliation:
  * `journal_date` defaults to statement_date.
  * If statement_date in closed period, must use next open period's date.

---

### 8.8 Interactions

* Internal lines come from:
  * Payment Receipts (DR Bank)
  * Bill Payments (CR Bank)
  * Journal Entries (DR or CR Bank)
* Matched transactions marked with reconciliation_id and reconciled_at.
* Reports can filter "cleared" vs "uncleared" transactions.

---

### 8.9 Voiding vs Reversing

* Cannot void a reconciliation. Can only **Reopen** if no subsequent reconciliation.
* Adjustments (JEs) can be voided separately via Journal Entry screen.

---

## 9. Account Management Screen

### 9.1 Purpose

Create and maintain chart of accounts.

No posting here; just master data.

---

### 9.2 Fields

| Field name       | Type              | Required | Default | Validation / Rules                                    | Ledger impact / Usage                         | UI notes                  |
| ---------------- | ----------------- | -------- | ------- | ----------------------------------------------------- | --------------------------------------------- | ------------------------- |
| id               | UUID              | system   | auto    | immutable                                             | key for joins                                 | hidden                    |
| account_code     | string            | yes      | –       | unique per company; format per company policy         | shown in reports and dropdowns                | text input                |
| account_name     | string            | yes      | –       | non-empty                                             | shown in reports and dropdowns                | text input                |
| account_type     | enum              | yes      | –       | asset, liability, equity, revenue, expense            | determines DR/CR normal balance               | dropdown                  |
| account_subtype  | enum              | no       | –       | e.g., current_asset, fixed_asset, AR, AP, etc.        | for reports and grouping                      | dropdown                  |
| currency_code    | char(3)           | yes      | company FC | ISO code; immutable after first posting            | if ≠ FC, tracks foreign currency balances     | dropdown (locked after use)|
| parent_account_id| FK: accounts      | no       | –       | if set, creates hierarchy                             | for sub-accounts and roll-ups                 | dropdown (self-referencing)|
| is_active        | boolean           | yes      | true    | –                                                     | only active accounts shown in transaction entry | toggle                  |
| is_system        | boolean           | system   | false   | if true, cannot delete or change type                 | for system accounts like AR, AP control       | read-only                 |
| description      | text              | no       | –       | –                                                     | informational                                 | textarea                  |
| opening_balance_fc | decimal(18,2)   | no       | 0       | for migration; entered once                           | initial balance before transactions           | numeric (migration only)  |
| balance_fc       | decimal(18,2)     | system   | calc    | sum(transactions) + opening_balance_fc                | current balance                               | read-only                 |

---

### 9.3 Actions

| Action   | Preconditions                              | Effect on state                | Ledger impact | Permissions / constraints   |
| -------- | ------------------------------------------ | ------------------------------ | ------------- | --------------------------- |
| Create   | unique account_code, valid account_type    | new account record             | none          | Permissions::ACCOUNT_CREATE |
| Update   | account exists; not locked by transactions | update allowed fields          | none          | Permissions::ACCOUNT_UPDATE |
| Deactivate | is_active = true; no future postings      | is_active = false              | none          | Permissions::ACCOUNT_UPDATE |
| Delete   | no transactions ever posted; not system    | hard delete                    | none          | Permissions::ACCOUNT_DELETE |
| Reorder  | –                                          | update display_order or parent | none          | Permissions::ACCOUNT_UPDATE |

---

### 9.4 State Model

* **Active** vs **Inactive**
* **System** accounts cannot be deleted

---

### 9.5 Posting Logic

None. Accounts are master data.

Balances computed from transactions:

```sql
SELECT account_id,
       SUM(debit_fc) - SUM(credit_fc) AS balance_fc
FROM acct.transactions
WHERE company_id = ?
GROUP BY account_id
```

---

### 9.6 Multi-Currency Rules

* Account can have currency ≠ FC for **foreign currency accounts** (e.g., USD bank account for PKR company).
* Track balance in both account currency and FC.
* Currency immutable after first transaction posted to prevent data corruption.

---

### 9.7 Period Controls

None (master data).

---

### 9.8 Interactions

* Used in all transaction screens as account dropdowns.
* Type filtering (e.g., only show revenue accounts for invoice lines).

---

### 9.9 Special Accounts

Certain accounts are created automatically (system accounts):

* Accounts Receivable (control)
* Accounts Payable (control)
* Customer Deposits
* Vendor Deposits
* Rounding Difference
* FX Gain
* FX Loss

Mark as `is_system = true`; prevent deletion/type change.

---

## 10. Customer Management Screen

### 10.1 Purpose

Maintain customer master data.

No posting; subledger details tracked separately.

---

### 10.2 Fields

| Field name          | Type              | Required | Default                | Validation / Rules                 | Ledger impact / Usage                    | UI notes                    |
| ------------------- | ----------------- | -------- | ---------------------- | ---------------------------------- | ---------------------------------------- | --------------------------- |
| id                  | UUID              | system   | auto                   | immutable                          | key for joins                            | hidden                      |
| customer_number     | string            | yes      | auto sequence          | unique per company                 | displayed on invoices and reports        | text input (auto)           |
| customer_name       | string            | yes      | –                      | non-empty                          | shown everywhere                         | text input                  |
| display_name        | string            | no       | from customer_name     | –                                  | short name for dropdowns                 | text input                  |
| email               | string            | no       | –                      | valid email format                 | for sending invoices                     | text input                  |
| phone               | string            | no       | –                      | –                                  | contact info                             | text input                  |
| billing_address     | JSON/text         | no       | –                      | –                                  | printed on invoices                      | address component           |
| shipping_address    | JSON/text         | no       | same as billing        | –                                  | printed on invoices                      | address component           |
| currency_code       | char(3)           | yes      | company base           | ISO code                           | default invoice currency for this customer | dropdown                  |
| payment_terms_id    | FK: payment_terms | yes      | company default        | must be active                     | default for invoices                     | dropdown                    |
| tax_profile_id      | FK: tax_profiles  | no       | –                      | –                                  | default tax treatment                    | dropdown                    |
| credit_limit_fc     | decimal(18,2)     | no       | 0 (unlimited)          | ≥ 0; 0 = no limit                  | warn if AR balance > credit_limit        | numeric                     |
| is_active           | boolean           | yes      | true                   | –                                  | only active customers selectable         | toggle                      |
| notes               | text              | no       | –                      | –                                  | informational                            | textarea                    |
| balance_tc          | decimal(18,2)     | system   | calc                   | sum(invoices) − sum(payments/credits) in customer currency | AR balance | read-only        |
| balance_fc          | decimal(18,2)     | system   | calc                   | sum in FC                          | AR balance in FC                         | read-only                   |

---

### 10.3 Actions

| Action     | Preconditions         | Effect on state                | Ledger impact | Permissions / constraints     |
| ---------- | --------------------- | ------------------------------ | ------------- | ----------------------------- |
| Create     | unique customer_number | new customer record           | none          | Permissions::CUSTOMER_CREATE  |
| Update     | customer exists       | update allowed fields          | none          | Permissions::CUSTOMER_UPDATE  |
| Deactivate | is_active = true; no open invoices (optional) | is_active = false | none     | Permissions::CUSTOMER_UPDATE  |
| Delete     | no transactions ever  | hard delete                    | none          | Permissions::CUSTOMER_DELETE  |
| Merge      | duplicate customers   | move transactions to primary; delete duplicate | none | Permissions::CUSTOMER_MERGE |

---

### 10.4 State Model

* **Active** vs **Inactive**

---

### 10.5 Posting Logic

None. Master data only.

Balance calculated from invoices/payments:

```sql
SELECT customer_id,
       SUM(CASE WHEN type = 'invoice' THEN grand_total_fc ELSE -amount_fc END) AS balance_fc
FROM (
    SELECT customer_id, 'invoice' AS type, grand_total_fc FROM acct.invoices WHERE status = 'approved'
    UNION ALL
    SELECT customer_id, 'payment' AS type, amount_fc FROM acct.payments WHERE status = 'posted'
    UNION ALL
    SELECT customer_id, 'credit' AS type, credit_total_fc FROM acct.credit_notes WHERE status = 'approved'
) t
GROUP BY customer_id
```

---

### 10.6 Multi-Currency Rules

* Customer has default currency.
* Can invoice in any currency, but default is customer's currency.

---

### 10.7 Period Controls

None (master data).

---

### 10.8 Interactions

* Used in Invoice, Payment, Credit Note screens.
* Credit limit warning shown on invoice approval if balance exceeds limit.

---

### 10.9 Inline Editing

✅ Suitable fields for inline editing:

* customer_name
* display_name
* email
* phone
* is_active (toggle)

❌ Not suitable:

* billing_address (complex)
* currency_code (affects transactions)
* balance_tc/fc (calculated)

---

## 11. Vendor Management Screen

### 11.1 Purpose

Maintain vendor master data.

Mirror of Customer, but for AP.

---

### 11.2 Fields

| Field name          | Type              | Required | Default                | Validation / Rules                 | Ledger impact / Usage                    | UI notes                    |
| ------------------- | ----------------- | -------- | ---------------------- | ---------------------------------- | ---------------------------------------- | --------------------------- |
| id                  | UUID              | system   | auto                   | immutable                          | key for joins                            | hidden                      |
| vendor_number       | string            | yes      | auto sequence          | unique per company                 | displayed on bills and reports           | text input (auto)           |
| vendor_name         | string            | yes      | –                      | non-empty                          | shown everywhere                         | text input                  |
| display_name        | string            | no       | from vendor_name       | –                                  | short name for dropdowns                 | text input                  |
| email               | string            | no       | –                      | valid email format                 | for correspondence                       | text input                  |
| phone               | string            | no       | –                      | –                                  | contact info                             | text input                  |
| address             | JSON/text         | no       | –                      | –                                  | vendor address                           | address component           |
| currency_code       | char(3)           | yes      | company base           | ISO code                           | default bill currency                    | dropdown                    |
| payment_terms_id    | FK: payment_terms | yes      | company default        | must be active                     | default for bills                        | dropdown                    |
| tax_profile_id      | FK: tax_profiles  | no       | –                      | –                                  | default tax treatment                    | dropdown                    |
| is_active           | boolean           | yes      | true                   | –                                  | only active vendors selectable           | toggle                      |
| notes               | text              | no       | –                      | –                                  | informational                            | textarea                    |
| balance_tc          | decimal(18,2)     | system   | calc                   | sum(bills) − sum(payments/credits) | AP balance                               | read-only                   |
| balance_fc          | decimal(18,2)     | system   | calc                   | sum in FC                          | AP balance in FC                         | read-only                   |

---

### 11.3 Actions

Same as Customer, mirrored for vendors.

---

### 11.4 State Model

* **Active** vs **Inactive**

---

### 11.5 Posting Logic

None. Balance calculated from bills/payments.

---

### 11.6 Multi-Currency Rules

Same as Customer.

---

### 11.7 Period Controls

None.

---

### 11.8 Interactions

Used in Bill, Bill Payment, Vendor Credit screens.

---

### 11.9 Inline Editing

Same as Customer.

---

## 12. Fiscal Year & Period Management

### 12.1 Purpose

Define accounting periods for posting control and reporting.

No posting; controls when transactions can be posted.

---

### 12.2 Fields (Fiscal Year)

| Field name   | Type     | Required | Default | Validation / Rules                      | Ledger impact / Usage                    | UI notes      |
| ------------ | -------- | -------- | ------- | --------------------------------------- | ---------------------------------------- | ------------- |
| id           | UUID     | system   | auto    | immutable                               | key for joins                            | hidden        |
| name         | string   | yes      | –       | e.g., "FY 2025"                         | displayed in reports and screens         | text input    |
| start_date   | date     | yes      | –       | –                                       | beginning of fiscal year                 | date picker   |
| end_date     | date     | yes      | –       | must be > start_date; typically 12 months | end of fiscal year                     | date picker   |
| is_closed    | boolean  | yes      | false   | if true, cannot reopen without override | controls posting to all periods          | toggle        |
| notes        | text     | no       | –       | –                                       | informational                            | textarea      |

### 12.3 Fields (Accounting Period)

| Field name       | Type              | Required | Default | Validation / Rules                      | Ledger impact / Usage                    | UI notes      |
| ---------------- | ----------------- | -------- | ------- | --------------------------------------- | ---------------------------------------- | ------------- |
| id               | UUID              | system   | auto    | immutable                               | key for joins                            | hidden        |
| fiscal_year_id   | FK: fiscal_years  | yes      | –       | –                                       | belongs to fiscal year                   | hidden        |
| period_number    | int               | yes      | –       | 1-12 (or more for adjustments)          | sequence within fiscal year              | numeric       |
| name             | string            | yes      | –       | e.g., "Jan 2025", "Period 1"            | displayed in reports and screens         | text input    |
| start_date       | date              | yes      | –       | –                                       | beginning of period                      | date picker   |
| end_date         | date              | yes      | –       | must be > start_date; within fiscal year | end of period                           | date picker   |
| status           | enum              | yes      | `open`  | open, closed, locked                    | controls posting                         | badge         |
| notes            | text              | no       | –       | –                                       | informational                            | textarea      |

**Period status:**

* `open` – can post transactions
* `closed` – cannot post; can reopen if fiscal year not closed
* `locked` – cannot reopen (for audit/compliance)

---

### 12.4 Actions

| Action             | Preconditions                           | Effect on state                | Ledger impact | Permissions / constraints           |
| ------------------ | --------------------------------------- | ------------------------------ | ------------- | ----------------------------------- |
| Create Fiscal Year | unique start/end dates                  | new fiscal year record         | none          | Permissions::FISCAL_YEAR_CREATE     |
| Create Periods     | fiscal year exists                      | generate periods (monthly/quarterly) | none    | Permissions::PERIOD_CREATE          |
| Close Period       | status = `open`; all transactions posted | status = `closed`             | none          | Permissions::PERIOD_CLOSE           |
| Reopen Period      | status = `closed`; fiscal year not closed | status = `open`              | none          | Permissions::PERIOD_REOPEN          |
| Lock Period        | status = `closed`                       | status = `locked`              | none          | Permissions::PERIOD_LOCK (admin)    |
| Close Fiscal Year  | all periods closed                      | is_closed = true               | none          | Permissions::FISCAL_YEAR_CLOSE      |

---

### 12.5 State Model

**Fiscal Year:**

* `open` → `closed` (is_closed = true)

**Period:**

* `open` → `closed` → `locked` (optional)

---

### 12.6 Posting Logic

None. Controls only.

---

### 12.7 Period Control Rules

For every transaction with a posting date:

1. Find period containing posting date.
2. Check period.status:
   * `open` → allow
   * `closed` or `locked` → block with error

---

### 12.8 Interactions

All transaction screens check period status on approval/posting.

---

### 12.9 Adjustment Periods

* Period 13 (or 14, 15) for year-end adjustments.
* Same fiscal year, but after period 12 end date.
* Used for closing entries, depreciation, accruals.

---

## 13. Currency & FX Rate Management

### 13.1 Purpose

Define currencies and maintain exchange rates.

No posting; used by transaction screens for FX conversion.

---

### 13.2 Fields (Currency)

| Field name       | Type     | Required | Default | Validation / Rules                 | Ledger impact / Usage                    | UI notes      |
| ---------------- | -------- | -------- | ------- | ---------------------------------- | ---------------------------------------- | ------------- |
| code             | char(3)  | yes      | –       | ISO 4217; PK                       | used everywhere as currency reference    | text input    |
| name             | string   | yes      | –       | e.g., "US Dollar"                  | displayed in UI                          | text input    |
| symbol           | string   | no       | –       | e.g., "$", "€"                     | displayed in amounts                     | text input    |
| decimal_places   | int      | yes      | 2       | 0-4; for display precision         | rounding display                         | numeric       |
| is_active        | boolean  | yes      | true    | –                                  | only active currencies selectable        | toggle        |

### 13.3 Fields (Exchange Rate)

| Field name       | Type            | Required | Default | Validation / Rules                 | Ledger impact / Usage                    | UI notes      |
| ---------------- | --------------- | -------- | ------- | ---------------------------------- | ---------------------------------------- | ------------- |
| id               | UUID            | system   | auto    | immutable                          | key for joins                            | hidden        |
| from_currency    | char(3)         | yes      | –       | FK to currencies                   | currency being converted                 | dropdown      |
| to_currency      | char(3)         | yes      | –       | FK to currencies                   | target currency (typically FC)           | dropdown      |
| rate_date        | date            | yes      | –       | –                                  | date of exchange rate                    | date picker   |
| rate             | decimal(18,8)   | yes      | –       | > 0; 8 decimal precision           | multiply by this to convert              | numeric       |
| is_manual        | boolean         | system   | false   | true if user-entered               | flag for audit                           | indicator     |

**Rate convention:**

* `rate` = 1 `from_currency` = X `to_currency`
* Example: 1 USD = 278.50 PKR → rate = 278.50000000

**Unique constraint:** (from_currency, to_currency, rate_date)

---

### 13.4 Actions

| Action          | Preconditions             | Effect on state                | Ledger impact | Permissions / constraints       |
| --------------- | ------------------------- | ------------------------------ | ------------- | ------------------------------- |
| Create Currency | unique code               | new currency record            | none          | Permissions::CURRENCY_CREATE    |
| Update Currency | currency exists           | update allowed fields          | none          | Permissions::CURRENCY_UPDATE    |
| Add Rate        | currencies exist          | new rate record                | none          | Permissions::FX_RATE_CREATE     |
| Import Rates    | CSV/API                   | bulk insert rates              | none          | Permissions::FX_RATE_IMPORT     |
| Delete Rate     | no transactions using it  | hard delete                    | none          | Permissions::FX_RATE_DELETE     |

---

### 13.5 State Model

* Currencies: **Active** vs **Inactive**
* Rates: No state (just historical data)

---

### 13.6 Posting Logic

None. Used by transaction screens for conversion.

---

### 13.7 FX Rate Lookup

When transaction requires FX conversion:

1. Look up rate for (from_currency, to_currency, rate_date = transaction_date).
2. If exact match found, use it.
3. If not found:
   * Option A (strict): block transaction with error "No FX rate for [currency] on [date]."
   * Option B (fallback): use latest rate before transaction_date.
   * Configurable per company.

---

### 13.8 Interactions

All multi-currency transaction screens (Invoice, Payment, Bill, etc.) use this.

---

### 13.9 Manual Rate Override

* If user has permission, can override auto-fetched rate.
* Mark as `is_manual = true` and log in audit trail.

---

## 14. Tax Configuration Screen

### 14.1 Purpose

Define tax codes, rates, and jurisdictions.

No posting; used by transaction screens to calculate tax.

---

### 14.2 Fields (Tax Code)

| Field name       | Type     | Required | Default | Validation / Rules                 | Ledger impact / Usage                    | UI notes      |
| ---------------- | -------- | -------- | ------- | ---------------------------------- | ---------------------------------------- | ------------- |
| id               | UUID     | system   | auto    | immutable                          | key for joins                            | hidden        |
| code             | string   | yes      | –       | unique per company; e.g., "VAT17"  | displayed in dropdowns and reports       | text input    |
| name             | string   | yes      | –       | e.g., "VAT 17%"                    | displayed in UI                          | text input    |
| rate             | decimal(8,4) | yes  | –       | 0-100; percentage                  | tax calculation                          | numeric       |
| tax_type         | enum     | yes      | –       | sales_tax, vat, gst, withholding, etc. | informational                        | dropdown      |
| is_compound      | boolean  | no       | false   | if true, applies after other taxes | tax calculation order                    | checkbox      |
| tax_account_id   | FK: accounts | yes | –       | must be liability for sales tax    | where tax posts                          | dropdown      |
| is_recoverable   | boolean  | no       | false   | if true, can claim back (input VAT)| affects account type                     | checkbox      |
| is_active        | boolean  | yes      | true    | –                                  | only active codes selectable             | toggle        |

### 14.3 Fields (Tax Rate)

For time-based rate changes (e.g., VAT rate changed from 17% to 18% on 2025-07-01):

| Field name       | Type            | Required | Default | Validation / Rules                 | Ledger impact / Usage                    | UI notes      |
| ---------------- | --------------- | -------- | ------- | ---------------------------------- | ---------------------------------------- | ------------- |
| id               | UUID            | system   | auto    | immutable                          | key for joins                            | hidden        |
| tax_code_id      | FK: tax_codes   | yes      | –       | –                                  | which tax code                           | hidden        |
| rate             | decimal(8,4)    | yes      | –       | 0-100                              | effective rate                           | numeric       |
| effective_from   | date            | yes      | –       | –                                  | start date of this rate                  | date picker   |
| effective_to     | date            | no       | –       | if null, current rate              | end date (optional)                      | date picker   |

**Tax code lookup:**

* For transaction with date X, find tax_rate where effective_from ≤ X and (effective_to ≥ X or effective_to is null).

---

### 14.4 Actions

| Action          | Preconditions             | Effect on state                | Ledger impact | Permissions / constraints       |
| --------------- | ------------------------- | ------------------------------ | ------------- | ------------------------------- |
| Create Tax Code | unique code               | new tax code record            | none          | Permissions::TAX_CODE_CREATE    |
| Update Tax Code | tax code exists           | update allowed fields          | none          | Permissions::TAX_CODE_UPDATE    |
| Add Rate        | tax code exists           | new rate record                | none          | Permissions::TAX_RATE_CREATE    |
| Deactivate      | is_active = true          | is_active = false              | none          | Permissions::TAX_CODE_UPDATE    |
| Delete          | no transactions using it  | hard delete                    | none          | Permissions::TAX_CODE_DELETE    |

---

### 14.5 State Model

* **Active** vs **Inactive**

---

### 14.6 Posting Logic

None. Used by transaction screens for tax calculation.

---

### 14.7 Tax Calculation

On invoice/bill line:

1. Get line_total (before tax).
2. Look up tax_code_id → get rate for transaction_date.
3. Calculate tax_amount = line_total × (rate / 100).
4. Round to currency precision.
5. Post to tax_account_id.

**Compound tax:**

* If `is_compound = true`, apply after other taxes.
* Example: Line total = 100, Tax1 = 10% (not compound), Tax2 = 5% (compound).
  * Tax1 = 100 × 10% = 10
  * Tax2 = (100 + 10) × 5% = 5.5
  * Grand total = 115.5

---

### 14.8 Interactions

Used in Invoice, Bill, Credit Note, Vendor Credit screens.

---

### 14.9 Tax Exemptions

If customer/vendor has tax exemption:

* Link tax_exemption record to customer_id or vendor_id.
* Tax calculation skips for exempt customers/vendors.

---

## 15. Global Rules & Constraints

### 15.1 Rounding Rules

**Display:**

* All monetary amounts displayed at currency decimal_places (typically 2).

**Calculation:**

* Internal calculations at higher precision (e.g., 4 decimal places for unit prices).
* Final totals rounded to currency precision.

**Rounding account:**

* Differences post to **Rounding Difference** account (expense or revenue).
* Should be immaterial (<0.01 per transaction).

### 15.2 Tolerance Rules

**Allocation tolerance:**

* Allow allocation mismatch up to 0.01 FC per invoice/bill.
* Example: Invoice total = 100.00, Payment allocated = 99.99 → accept as fully paid.

**Bank reconciliation tolerance:**

* Allow difference_fc up to 0.01 per reconciliation to complete.

**Balance check tolerance:**

* Journal entry must balance within 0.01 FC.

### 15.3 Audit Trail

Every transaction must log:

* created_by (user_id)
* created_at (timestamp)
* updated_by (user_id)
* updated_at (timestamp)
* approved_by / posted_by (user_id) where applicable
* voided_by (user_id) where applicable

### 15.4 Soft Delete

All transaction documents use soft delete (`deleted_at` timestamp).

* Voiding != deleting.
* Void keeps document visible with reversing journal.
* Delete (soft) only for drafts or admin cleanup.

### 15.5 Company Isolation

**All queries scoped by company_id.**

* Use Row Level Security (RLS) policies on PostgreSQL.
* Middleware injects `current_setting('app.current_company_id')`.

### 15.6 Permissions

All actions require specific permissions (see `app/Constants/Permissions.php`).

Examples:

* `Permissions::INVOICE_CREATE`
* `Permissions::INVOICE_APPROVE`
* `Permissions::PAYMENT_POST`
* `Permissions::PERIOD_CLOSE`

### 15.7 Validation Order

For all transaction screens:

1. **Structural validation** (required fields, data types) – FormRequest
2. **Business rules** (dates, amounts, relationships) – FormRequest
3. **Period check** (posting period open) – Action/Service
4. **Permission check** (user has permission) – FormRequest authorize()
5. **Balance check** (totals match, allocations valid) – Action/Service
6. **Post to ledger** – Action/Service

If any step fails, abort and return error. **No partial commits.**

### 15.8 Concurrency Control

Use optimistic locking for transaction documents:

* Add `version` or `updated_at` column.
* On update, check version matches.
* If mismatch, reject with "Document has been modified by another user."

### 15.9 Batch Operations

Avoid batch operations in UI screens (too risky).

For bulk imports (invoices, payments), use:

* Separate import queue/jobs
* Validation before commit
* Rollback on any error
* Log all imports

### 15.10 Inline Editing Rules

**Use inline editing ONLY for:**

* Simple, atomic fields (name, email, status)
* No side effects (no recalculations, no journal impact)
* Master data screens (customers, vendors, accounts)

**Never use inline editing for:**

* Financial amounts (totals, balances)
* Dates that affect periods or FX rates
* Multi-line data (addresses, line items)
* Calculated fields

See `docs/inline-editing-system.md` for implementation.

---

### 15.11 Error Handling & Toast Notifications

**CRITICAL: All user feedback must use Sonner toast. No plain Laravel error pages for business logic.**

See `AI_PROMPTS/toast.md` for complete implementation guide.

#### Frontend Implementation

**Import the composable:**
```vue
<script setup lang="ts">
import { useFormFeedback } from '@/composables/useFormFeedback'

const { showSuccess, showError } = useFormFeedback()
</script>
```

**Success feedback:**
```typescript
// After successful form submission
showSuccess('Invoice created successfully')
showSuccess('Payment posted')
showSuccess('Period closed')
```

**Error feedback:**
```typescript
// Validation errors (from Inertia form)
form.post(url, {
  onError: (errors) => {
    showError(errors)  // Shows first validation error as toast
  }
})

// Business logic errors (from backend flash)
// Handled automatically by flash message watcher in layout

// Manual error display
showError('Cannot void invoice: period is closed')
showError('Allocation exceeds invoice balance')
```

#### Backend Implementation

**Never throw unhandled exceptions for business logic failures.**

**Pattern 1: Validation errors (automatic)**
```php
// FormRequest validation
public function rules(): array
{
    return [
        'customer_id' => 'required|exists:acct.customers,id',
        'invoice_date' => 'required|date',
    ];
}

// Frontend receives errors object, shows first error as toast + inline
```

**Pattern 2: Business logic errors (flash message)**
```php
// In controller or action
if ($period->status === 'closed') {
    return back()->with('error', 'Cannot post transaction: period is closed');
}

if ($invoice->settlement_status === 'paid') {
    return back()->with('error', 'Cannot void paid invoice: create credit note instead');
}

// Frontend flash watcher shows toast automatically
```

**Pattern 3: Success messages (flash message)**
```php
// After successful action
return redirect()
    ->route('invoices.show', ['company' => $company->slug, 'invoice' => $invoice->id])
    ->with('success', 'Invoice created successfully');
```

**Pattern 4: JSON responses (for AJAX actions)**
```php
// Success
return response()->json([
    'success' => true,
    'message' => 'Invoice approved',
    'data' => $invoice
], 200);

// Error
return response()->json([
    'success' => false,
    'message' => 'Cannot approve invoice: period is closed'
], 422);

// Frontend must handle and show toast
```

**Pattern 5: Exception handling (last resort)**
```php
// In controller method
try {
    $result = Bus::dispatch('invoice.approve', $request->validated());
    return redirect()
        ->route('invoices.show', ['company' => $company->slug, 'invoice' => $result->id])
        ->with('success', 'Invoice approved');
} catch (PeriodClosedException $e) {
    return back()->with('error', $e->getMessage());
} catch (\Exception $e) {
    Log::error('Invoice approval failed', [
        'invoice_id' => $request->invoice_id,
        'error' => $e->getMessage()
    ]);
    return back()->with('error', 'Failed to approve invoice. Please contact support.');
}
```

#### Error Types & Messages

**Validation errors:**
- Shown inline on form fields + first error as toast
- Use Laravel validation rules
- Messages: "The customer field is required", "Invalid email format"

**Period control errors:**
- Block action if period closed
- Messages: "Cannot post transaction: period is closed", "Cannot void: period locked"

**State transition errors:**
- Block invalid state changes
- Messages: "Cannot approve voided invoice", "Cannot edit posted payment"

**Business rule errors:**
- Block actions that violate rules
- Messages: "Allocation exceeds invoice balance", "Cannot delete account with transactions"

**Multi-currency errors:**
- Block invalid currency operations
- Messages: "No exchange rate for USD on 2025-01-15", "Payment currency must match invoice currency"

**Permission errors:**
- Block unauthorized actions (handled by FormRequest)
- Messages: "You do not have permission to approve invoices"

**System errors:**
- Catch all unexpected errors
- Log full details to server logs
- Messages: "An error occurred. Please try again or contact support."

#### Loading States

**Always show loading state for async actions:**

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button'

const form = useForm({ ... })

const handleSubmit = () => {
  form.post(url, { ... })
}
</script>

<template>
  <Button
    @click="handleSubmit"
    :disabled="form.processing"
  >
    <span v-if="form.processing">Saving...</span>
    <span v-else>Save Invoice</span>
  </Button>
</template>
```

#### Complete Action Cycle

Every user action must complete this cycle:

1. **User triggers action** → Button click, form submit
2. **Loading state** → Button disabled, spinner shown
3. **Backend processing** → Validation, business logic, posting
4. **Response handling** → Success or error
5. **User feedback** → Toast notification
6. **UI update** → Redirect, refresh data, or update state
7. **Loading complete** → Button enabled, spinner hidden

**Example: Invoice Approval**

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { useFormFeedback } from '@/composables/useFormFeedback'
import { Button } from '@/components/ui/button'

const { showSuccess, showError } = useFormFeedback()
const isApproving = ref(false)

const approveInvoice = () => {
  isApproving.value = true

  router.post(
    `/${company.slug}/invoices/${invoice.id}/approve`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        showSuccess('Invoice approved successfully')
        isApproving.value = false
      },
      onError: (errors) => {
        showError(errors)
        isApproving.value = false
      },
      onFinish: () => {
        isApproving.value = false
      }
    }
  )
}
</script>

<template>
  <Button
    @click="approveInvoice"
    :disabled="isApproving || invoice.status !== 'draft'"
  >
    <span v-if="isApproving">Approving...</span>
    <span v-else>Approve Invoice</span>
  </Button>
</template>
```

#### Toast Message Guidelines

**Length:**
- Keep under 60 characters
- Be specific but concise

**Tone:**
- Success: "Invoice created successfully", "Payment posted"
- Error: "Cannot void invoice: period is closed" (explain why)
- Avoid: "Success!", "Error occurred" (too vague)

**Context:**
- Include what failed and why
- Good: "Cannot approve invoice: period is closed"
- Bad: "Approval failed"

**Action items:**
- If user needs to do something, tell them
- "No exchange rate for USD on 2025-01-15. Please add rate in Settings."

#### Checklist for Every Screen

Before marking implementation complete:

- [ ] Success actions show success toast
- [ ] Validation errors show inline + toast
- [ ] Business logic errors show error toast with reason
- [ ] System errors caught and show friendly message
- [ ] Loading states on all async actions
- [ ] No plain Laravel error pages exposed
- [ ] No unhandled exceptions reaching user
- [ ] Flash messages handled in layout
- [ ] All AJAX responses return proper error messages
- [ ] Error messages are actionable (tell user what to do)

---

## End of Document

This specification defines the canonical interface contracts for all major screens in the Haasib accounting system.

**For developers:**

1. Read this spec before building any screen.
2. Follow the field tables exactly (names, types, validation).
3. Implement all actions with preconditions and effects as specified.
4. Use the posting logic patterns (DR/CR) precisely.
5. Enforce period controls and multi-currency rules.
6. **CRITICAL: Implement error handling with Sonner toast (Section 15.11). Never expose plain Laravel errors.**
7. Do not invent new fields or actions without updating this spec.

**For product owners:**

1. This spec is the contract between product and engineering.
2. Changes to this spec require review and approval.
3. Any ambiguity or missing detail must be resolved here before implementation.

**Version control:**

* Update `Last Updated` date at top of document when making changes.
* Track changes in git commits with descriptive messages.

---

**Next steps:**

* Implement each screen following this spec.
* Update `docs/contracts/{schema}-schema.md` to match field definitions.
* Run `composer quality-check` and `php artisan layout:validate` to ensure compliance.
* Test all actions, state transitions, and posting logic.
* Review with team before marking feature complete.

---

**Questions or clarifications?**

Update this document first, then communicate to team.