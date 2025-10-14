# Quickstart — Payment Receipt & Allocation

## Prerequisites
- Feature branch `005-payment-processing-receipt` checked out.
- Latest migrations applied (`php artisan migrate`).
- Company + user context set via `php artisan context:login --user=<uuid> --company=<uuid>` or equivalent helper.
- Ensure permissions `accounting.payments.*` assigned to the acting role (see `stack/database/seeders/PermissionSeeder.php`).

## 1. Record a Receipt
```bash
php artisan payment:record \
  --customer="Acme Corp" \
  --amount=1250.00 \
  --method=bank_transfer \
  --date=$(date +%Y-%m-%d) \
  --reference="WIRE-9821" \
  --auto \
  --strategy=fifo \
  --json
```
- Generates a new payment number, persists the receipt, and (if `--auto`) dispatches the automatic allocation action through the command bus.
- CLI output includes remaining amount, allocations, and idempotency key echo for retries.

## 2. Manual Allocation
```bash
php artisan payment:allocate PAY-2025-00042 \
  --invoices="INV-2025-109,INV-2025-112" \
  --amounts="600,650" \
  --json
```
- Validates invoice ownership, applies allocations, and updates payment status to `completed` when fully allocated.
- Use `--dry-run` to preview without committing.

## 3. Automatic Allocation via API
```bash
curl -s -X POST https://api.haasib.local/api/payments/{payment_id}/allocations/auto \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{
        "strategy": "overdue_first",
        "options": { "max_invoices": 5 }
      }' | jq
```
- Response lists allocations performed; repeat with same idempotency key safely on network failure.

## 4. Generate Receipt Confirmation
```bash
curl -s -X GET \
  "https://api.haasib.local/api/payments/{payment_id}/receipt?format=pdf" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  --output receipt.pdf
```
- Use `format=json` to retrieve machine-readable data for email templates.

## 5. Batch Upload
```bash
curl -s -X POST https://api.haasib.local/api/payment-batches \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Idempotency-Key: $(uuidgen)" \
  -F source_type=csv_import \
  -F file=@fixtures/payment-batch.csv
```
- Poll `/api/payment-batches/{batch_id}` for status; failed rows include validation messages and raw payload.

## 6. Reverse Allocation / Payment
```bash
php artisan payment:allocation:reverse {allocation_id} \
  --reason="Customer dispute" \
  --json

curl -s -X POST https://api.haasib.local/api/payments/{payment_id} \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{ "reason": "Chargeback", "amount": 1250.00, "method": "chargeback" }'
```
- CLI command flips allocation to reversed and reopens invoice balance; API reversal logs audit event and queues refund workflow.

## 7. UI Smoke
- Navigate to **Accounts Receivable → Payments** (new Inertia view).
- Use the “Record Receipt” button to launch PrimeVue dialog.
- Confirm table refresh shows allocation, remaining amount, and unallocated cash totals.
- Run Playwright scenario `tests/playwright/payments/receipt-allocation.spec.ts` (to be added) to validate GUI/CLI parity.

## 8. Audit & Reporting
```bash
curl -s https://api.haasib.local/api/payments/{payment_id}/audit \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" | jq '.[].action'

php artisan payment:allocation:report --payment=PAY-2025-00042 --format=json | jq
```
- Confirms audit entries for creation, allocation, reversal, and batch ingestion.
- Reports feed export to bank reconciliation and aging dashboards.
