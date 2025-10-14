# Data Model — Payment Processing · Receipt & Allocation

## Entities & Attributes

### Payment (`invoicing.payments`)
- `id` (uuid, pk)
- `company_id` (uuid, fk → `auth.companies.id`)
- `customer_id` (uuid, fk → `invoicing.customers.id`)
- `payment_number` (string[50], unique per company)
- `payment_date` (date)
- `payment_method` (string[50], enum: cash, bank_transfer, card, cheque, other)
- `reference_number` (string[100], nullable)
- `amount` (numeric(15,2))
- `currency` (char[3], ISO code)
- `status` (string[20], enum: pending, completed, failed, cancelled, reversed)
- `notes` (text, nullable)
- `paymentable_id` / `paymentable_type` (uuid/string, legacy polymorphic bridge to invoices)
- `batch_id` (uuid, nullable, fk → `invoicing.payment_receipt_batches.id`) — new
- `created_by_user_id` (uuid, fk → `auth.users.id`)
- `created_at` / `updated_at` (timestamps)
- `deleted_at` (timestamp, soft delete)

**Derived attributes**
- `total_allocated` (sum over active allocations)
- `remaining_amount` (`amount - total_allocated`)
- `is_fully_allocated` (boolean flag when remaining ≤ 0)

**Validations**
- `amount > 0`
- `company_id`, `customer_id`, `created_by_user_id` must belong to same tenant as current context
- `status` transitions (see state machine)

### Payment Allocation (`invoicing.payment_allocations`)
- `id` (uuid, pk)
- `company_id` (uuid, fk)
- `payment_id` (uuid, fk → `invoicing.payments.id`)
- `invoice_id` (uuid, fk → `invoicing.invoices.id`)
- `allocated_amount` (numeric(15,2))
- `allocation_date` (timestamp)
- `allocation_method` (string[50], enum: manual, automatic)
- `allocation_strategy` (string[50], nullable; eg fifo, proportional, overdue_first, largest_first, percentage_based, custom_priority)
- `notes` (text, nullable)
- `created_by_user_id` (uuid, nullable fk → `auth.users.id`)
- `reversed_at` (timestamp, nullable)
- `reversal_reason` (text, nullable)
- `reversed_by_user_id` (uuid, nullable fk → `auth.users.id`)
- `created_at` / `updated_at`
- `deleted_at` (soft delete)

**Validations**
- `allocated_amount > 0`
- `allocated_amount ≤ invoice.balance_due`
- `payment` and `invoice` share same `company_id`
- Unique constraint `(payment_id, invoice_id, reversed_at)` prevents duplicate active allocations

### Payment Receipt Batch (`invoicing.payment_receipt_batches`) — new
- `id` (uuid, pk)
- `company_id` (uuid, fk → `auth.companies.id`)
- `batch_number` (string[50], unique per company)
- `status` (string[20], enum: pending, processing, completed, failed, archived)
- `receipt_count` (integer)
- `total_amount` (numeric(18,2))
- `currency` (char[3])
- `processed_at` (timestamp, nullable)
- `processing_started_at` / `processing_finished_at` (timestamps, nullable)
- `created_by_user_id` (uuid, fk → `auth.users.id`)
- `notes` (text, nullable)
- `metadata` (jsonb, nullable; stores import source, bank reference, etc.)
- `created_at` / `updated_at`

**Relationships**
- `hasMany` payments (via `batch_id`)
- cascade failure updates payment statuses when batch fails

### Payment Discount (value object)
- Represents early-payment discount applied during allocation
- Attributes: `discount_type` (enum: percentage, fixed_amount), `value`, `applied_at`
- Stored within allocation metadata / line item adjustments (needs schema extension)

### Unallocated Cash (derived view)
- Rendered via computed view aggregating payments with `remaining_amount > 0`
- Fields: `payment_id`, `company_id`, `customer_id`, `remaining_amount`, `currency`, `aging_bucket`
- Backed by SQL view or Eloquent scope for reporting; no dedicated table.

### Payment Reversal (`invoicing.payment_reversals`) — optional supporting table
- `id` (uuid, pk)
- `payment_id` (uuid, fk)
- `reason` (text)
- `reversed_amount` (numeric(15,2))
- `reversal_method` (string[50], eg: void, chargeback, refund)
- `initiated_by_user_id` (uuid, fk)
- `initiated_at`, `settled_at`
- `status` (enum: pending, completed, rejected)
- Allows reconciliation with bank events and ledger adjustments.

## Relationships
- `Payment` 1—N `PaymentAllocation` (active allocations only where `reversed_at IS NULL`)
- `Payment` N—N `Invoice` via allocations pivot (exposes invoice metadata for receipts)
- `PaymentReceiptBatch` 1—N `Payment`
- `Payment` N—1 `Customer`
- `Payment` N—1 `Company`
- `PaymentAllocation` N—1 `User` (creator / reverser)
- `PaymentReversal` 1—1 `Payment` (optional)
- Ledger integration: each payment posts journal entries via `ledger.journal_entries` with `source_type = 'payment'` and contextual IDs.

## State Machines & Transitions

### Payment Status
```
pending ──record allocation/auto-allocation──▶ completed
     │                │
     │                └─▶ failed (validation / processing error)
     └──cancel──▶ cancelled
completed ──reverse──▶ reversed (creates reversal record, reopens allocations)
failed ──retry──▶ pending
```
- Transitions gated by command bus actions with audit logging.
- Batch ingestion sets payments to `pending` during processing; completion flips to `completed`.

### Allocation Lifecycle
```
created (active) ──reverse──▶ reversed (sets reversed_at, retains history)
```
- Reversal triggers invoice balance restoration and payment status review.

## Validation Rules
- Receipt creation:
  - Amount matches currency precision (2 decimals) and respects configured minimum/maximum if provided by company settings.
  - Payment method must be in allowed list (configurable per company; default {cash, bank_transfer, card, cheque, other}).
  - Early-payment discount requires invoice due-dates before payment date.
  - Duplicate detection prevents same `payment_number` per company.
- Batch import:
  - File hash/idempotency key ensures repeated uploads do not duplicate entries.
  - Each entry validated against customer/invoice existence and outstanding balances; failures logged per row.
- Allocation:
  - Total allocated ≤ payment.amount.
  - Automatic strategies (fifo, proportional, overdue_first, largest_first, percentage_based, custom_priority) use deterministic ordering to support idempotency.
  - Partial payments update invoice status transitions (open → partial → paid).

## Reporting & Views
- `payment_allocation_summary` service aggregates per-payment totals for API and CLI responses.
- `unallocated_cash_view` groups by customer for FR-005.
- `payment_allocation_reports` extend `PaymentAllocationReportService` to include batch metadata and discount usage.
