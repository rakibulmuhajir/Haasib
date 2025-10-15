# Payment Allocation API Cheatsheet

## Basic Allocation Operations

- GET `/api/payments/{id}/allocations` — lists allocations for a payment (`id` is a UUID)

## Requirements
- `Authorization: Bearer {token}` — Sanctum token with `payments.allocate`
- `Idempotency-Key: <uuid>` — required on every POST
- `X-Company-Id: <uuid>` — required for non-session clients so `SetCompanyContext` can set `app.current_company_id`

## Endpoints

### List Allocations
```http
GET /api/payments/{paymentId}/allocations
```
Returns allocations, applied amounts, invoice metadata, and timestamps. Respects tenant scoping.

### Create Allocation
```http
POST /api/payments/{paymentId}/allocations
Headers: Authorization, Idempotency-Key, X-Company-Id
```
**Body example**
```json
{
  "invoice_id": "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee",
  "amount": 50.00,
  "allocation_date": "2025-09-15",
  "notes": "Initial allocation"
}
```

### Void Allocation
```http
POST /api/payments/{paymentId}/allocations/{allocationId}/void
Headers: Authorization, Idempotency-Key, X-Company-Id
```
Body example: `{ "reason": "Customer dispute" }`

### Refund Allocation
```http
POST /api/payments/{paymentId}/allocations/{allocationId}/refund
Headers: Authorization, Idempotency-Key, X-Company-Id
```
Body example: `{ "amount": 10.00, "reason": "Partial refund" }`

## Early Payment Discounts

- POST `/api/payments/{id}/allocations` with optional discount application:
  ```json
  {
    "allocations": [
      {
        "invoice_id": "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee",
        "amount": 1000.00,
        "apply_early_payment_discount": true,
        "notes": "Payment with early payment discount"
      }
    ]
  }
  ```
  - System checks `invoice.early_payment_discount_percent` and `invoice.early_payment_discount_days`
  - Discount applies when payment date is within the discount window
  - Response includes `discount_amount` and `discount_percent` for each allocation

- Discount eligibility checklist:
  - Invoice discount percent/days must both be > 0
  - Current date ≤ `invoice.due_date - early_payment_discount_days`
  - Invoice must not be overdue

## Unallocated Cash Management

- Any payment amount not allocated becomes unallocated cash
- Tracked per customer and company; ready for future allocations or refunds
- GET `/api/customers/{customerId}/unallocated-cash`
  ```json
  {
    "customer_id": "customer-uuid",
    "total_unallocated": 500.00,
    "currency": "USD",
    "payments_count": 2,
    "unallocated_entries": [
      {
        "payment_id": "payment-uuid-1",
        "amount": 300.00,
        "payment_date": "2025-01-15"
      },
      {
        "payment_id": "payment-uuid-2",
        "amount": 200.00,
        "payment_date": "2025-01-20"
      }
    ]
  }
  ```

## Receipt Generation

- GET `/api/payments/{paymentId}/receipt?format=json`
  - Returns complete receipt data including allocations and discounts
  - Response includes company details, customer details, payment summary, and allocation breakdown
- GET `/api/payments/{paymentId}/receipt?format=pdf`
  - Downloads PDF receipt with branding; shows original amounts, discounts, and final allocation totals
- Receipt structure highlight:
  ```json
  {
    "receipt_number": "R-PAY-2025-001",
    "company_details": { "name": "...", "address": "..." },
    "customer_details": { "name": "...", "email": "..." },
    "payment_details": { "payment_number": "...", "payment_method_label": "..." },
    "amount_summary": {
      "payment_amount": 1000.00,
      "total_allocated": 950.00,
      "total_discount_applied": 20.00,
      "remaining_amount": 50.00
    },
    "allocations": [
      {
        "invoice_number": "INV-2025-001",
        "allocation_date": "2025-01-15",
        "original_amount": 1000.00,
        "discount_amount": 20.00,
        "discount_percent": 2.0,
        "allocated_amount": 950.00
      }
    ]
  }
  ```

## Auto-Allocation Strategies

- POST `/api/payments/{id}/allocations/auto`
  ```json
  {
    "strategy": "overdue_first",
    "priorityRules": "due_date ASC"
  }
  ```
- Supported strategies: `fifo`, `proportional`, `overdue_first`, `largest_first`, `percentage_based`, `custom_priority`

## Batch Processing Endpoints

- POST `/api/accounting/payment-batches` (idempotent)
  - Create a batch for processing multiple payments
  - Body examples:

    **CSV Import**
    ```json
    {
      "source_type": "csv_import",
      "file": "[multipart file data]",
      "notes": "Monthly bank import"
    }
    ```

    **Manual Entries**
    ```json
    {
      "source_type": "manual",
      "entries": [
        {
          "entity_id": "customer-uuid",
          "payment_method": "bank_transfer",
          "amount": 500.00,
          "currency_id": "currency-uuid",
          "payment_date": "2025-01-15",
          "reference_number": "PAY-001",
          "auto_allocate": true,
          "allocation_strategy": "fifo"
        }
      ]
    }
    ```

  - Returns batch metadata: `batch_id`, `batch_number`, status, and processing summary

- GET `/api/accounting/payment-batches/{batchId}` — retrieve batch status, progress, and any validation errors
- GET `/api/accounting/payment-batches` — list batches (filters: `status`, `limit`, `offset`, `source_type`)

**Batch Processing Highlights**
- Real-time progress tracking and percentage completion
- Row-level error reporting with remediation tips
- Supports CSV uploads, manual entry, and bank feed ingestion
- Queue-based background execution with automatic allocation strategies
- Idempotent batch creation to prevent duplicates

## Notes
- All identifiers (`paymentId`, `allocationId`, `invoice_id`) are UUID strings
- Allocation commands route through the command bus (`PaymentApiController` → `PaymentService`), so audit + idempotency hooks fire automatically
- `app.current_company_id` is set by `SetCompanyContext`; missing or incorrect context yields 403/404
- Invoice line taxes should mirror `{ items[].taxes[] }` objects to keep allocation previews accurate
- Line-item taxes for invoices use `{ name, rate }`; maintain parity for consistent totals
- Tenant isolation relies on RLS plus `Idempotency-Key` headers; include them for CLI/API parity
- Batch processing supports up to 10,000 payments per batch with a 10 MB file size limit
- See `.specify/memory/constitution.md` (v2.2.0) for tenancy, RBAC, and idempotency requirements expected by reviewers
- Use the [Batch Processing Quick Start Guide](./payment-batch-quickstart.md) for end-to-end examples
