# Payment Allocations API — Quick Guide

- GET `/api/payments/{id}/allocations`
  - Lists allocations for a payment (id = payment_id UUID)

- POST `/api/payments/{id}/allocations` (idempotent)
  - Body example:
    - `{ "invoice_id": "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee", "amount": 50.00, "allocation_date": "2025-09-15", "notes": "Initial alloc" }`
  - Returns created allocation with `allocation_id` (UUID) and computed fields.

- POST `/api/payments/{paymentId}/allocations/{allocationId}/void` (idempotent)
  - Body example: `{ "reason": "Customer dispute" }`

- POST `/api/payments/{paymentId}/allocations/{allocationId}/refund` (idempotent)
  - Body example: `{ "amount": 10.00, "reason": "Partial refund" }`

Headers
- Include `Idempotency-Key: <uuid>` on all mutating requests.

Notes
- All IDs are UUID strings.
- Line-item taxes for invoices are specified as `items[].taxes[]` with `{ name, rate }`.
- RLS is enforced by `app.current_company`; ensure your tenant context middleware sets it per request/job.
