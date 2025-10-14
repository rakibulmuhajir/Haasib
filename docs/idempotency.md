# Idempotency — Semantics & Usage

- Header: `Idempotency-Key: <uuid>` must be sent on all mutating API requests (POST/PUT/PATCH/DELETE) that can be retried.
- Scope: Keys are unique per `(user_id, company_id, action)` where `action` is the named route or method+path fallback.
- Behavior:
  - First request: server records the request body snapshot and processes normally. A JSON response snapshot is stored when possible.
  - Replay with the same key and identical request: server returns the stored response (same status/message/body) without reprocessing.
  - Replay with the same key but different request body: server returns `409` with error `IDEMPOTENCY_KEY_REUSED_DIFFERENT_REQUEST`.
- Best practices:
  - Use a UUID v4 key per logical “operation” on the client.
  - Persist the key across retries of the same operation; do not reuse a key across different payloads.
  - For multi-step client operations (e.g., create payment then allocate), use distinct keys per step.
- Implementation notes:
  - Stored in `public.idempotency_keys` with a unique index on `(user_id, company_id, action, key)`.
  - Middleware: `App\Http\Middleware\Idempotency` is aliased as `idempotent` in `stack/bootstrap/app.php` and applied to every mutating API route.
  - Constitution link: see `.specify/memory/constitution.md` (v2.2.0, Principle X) for non-negotiable requirements.

## How to Retry Safely (curl examples)

Create a payment (safe retry):

```
KEY=$(uuidgen)
curl -s -X POST \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $KEY" \
  -d '{
    "customer_id": "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee",
    "amount": 60.00,
    "currency_id": "11111111-2222-3333-4444-555555555555",
    "payment_method": "cash",
    "payment_date": "2025-09-15"
  }' \
  https://api.example.com/api/payments

# If the client times out, retry with the same Idempotency-Key:
curl -s -X POST \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $KEY" \
  -d '{
    "customer_id": "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee",
    "amount": 60.00,
    "currency_id": "11111111-2222-3333-4444-555555555555",
    "payment_method": "cash",
    "payment_date": "2025-09-15"
  }' \
  https://api.example.com/api/payments
```

Allocate the payment (separate key):

```
ALLOC_KEY=$(uuidgen)
curl -s -X POST \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $ALLOC_KEY" \
  -d '{
    "invoice_id": "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee",
    "amount": 50.00,
    "allocation_date": "2025-09-15"
  }' \
  https://api.example.com/api/payments/<payment_id>/allocations
```

Cancel an invoice (safe retry):

```
CANCEL_KEY=$(uuidgen)
curl -s -X POST \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $CANCEL_KEY" \
  https://api.example.com/api/invoices/<invoice_id>/cancel
```
