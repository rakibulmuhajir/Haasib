# Payment Allocation API Cheatsheet

Tenancy-aware allocation routes live in `stack/routes/api.php` under the `Route::prefix('payments')` group. All URLs below are automatically prefixed with `/api`.

## Requirements
- `Authorization: Bearer {token}` — Sanctum token for a user with `payments.allocate`.
- `Idempotency-Key: <uuid>` — REQUIRED on every POST.
- `X-Company-Id: <uuid>` — Required for non-session clients so `SetCompanyContext` can set `app.current_company_id`.

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

## Notes
- All identifiers (`paymentId`, `allocationId`, `invoice_id`) are UUIDs.
- Allocation commands route through the command bus, hitting `PaymentApiController` → `PaymentService`, so audit and idempotency hooks fire automatically.
- `app.current_company_id` is set by `SetCompanyContext`; missing or incorrect context yields 403/404.
- Keep invoice line taxes in sync with `{ items[].taxes[] }` objects so allocation previews remain accurate.
- See `.specify/memory/constitution.md` (v2.2.0) for tenancy, RBAC, and idempotency requirements that reviewers expect.
