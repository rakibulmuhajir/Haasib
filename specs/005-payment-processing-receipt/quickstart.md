# Quickstart — Payment Receipt & Allocation

## Prerequisites
- Feature branch `005-payment-processing-receipt` checked out.
- Latest migrations applied (`php artisan migrate`).
- Company + user context set via `php artisan context:login --user=<uuid> --company=<uuid>` or equivalent helper.
- Ensure permissions `accounting.payments.*` assigned to the acting role (see `stack/database/seeders/PermissionSeeder.php`).

## 1. Record a Receipt
```bash
php artisan payment:record "Acme Corp" 1250.00 \
  --method=bank_transfer \
  --date=$(date +%Y-%m-%d) \
  --reference="WIRE-9821" \
  --auto-allocate \
  --strategy=fifo \
  --format=json
```
- Generates a new payment number, persists the receipt, and (if `--auto-allocate`) dispatches the automatic allocation action through the command bus.
- CLI output includes remaining amount, allocations, and payment status.
- Available payment methods: `cash`, `bank_transfer`, `card`, `cheque`, `other`.
- Allocation strategies: `fifo`, `proportional`, `overdue_first`, `largest_first`, `percentage_based`, `custom_priority`.

## 2. Manual Allocation
```bash
php artisan payment:allocate PAY-2025-00042 \
  --invoices="INV-2025-109,INV-2025-112" \
  --amounts="600,650" \
  --format=table
```
- Validates invoice ownership, applies allocations, and updates payment status to `completed` when fully allocated.
- Use `--dry-run` to preview allocations without committing.
- For automatic allocation, use: `php artisan payment:allocate PAY-2025-00042 --auto --strategy=overdue_first`

## 3. Automatic Allocation via API
```bash
curl -s -X POST https://api.haasib.local/api/accounting/payments/{payment_id}/allocations/auto \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{
        "strategy": "overdue_first",
        "priorityRules": "due_date ASC"
      }' | jq
```
- Response includes allocations performed, remaining amount, and payment status.
- For manual allocation via API, POST to `/api/accounting/payments/{payment_id}/allocations` with allocations array.
- Use same idempotency key safely on network failure for replay protection.

## 4. Early Payment Discounts & Overpayment Receipts

### Apply Early Payment Discounts
```bash
curl -s -X POST https://api.haasib.local/api/accounting/payments/{payment_id}/allocations \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{
        "allocations": [
          {
            "invoice_id": "invoice-uuid",
            "amount": 1000.00,
            "apply_early_payment_discount": true,
            "notes": "Early payment discount applied"
          }
        ]
      }' | jq
```
- Discount eligibility: Invoice must have `early_payment_discount_percent > 0` and be paid within `early_payment_discount_days`
- System automatically calculates discount amount and updates allocation totals

### Generate Enhanced Receipt
```bash
# JSON receipt with discount details
curl -s -X GET \
  "https://api.haasib.local/api/accounting/payments/{payment_id}/receipt?format=json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" | jq '.amount_summary'

# PDF receipt with professional formatting
curl -s -X GET \
  "https://api.haasib.local/api/accounting/payments/{payment_id}/receipt?format=pdf" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  --output receipt.pdf
```

### Check Unallocated Cash Balance
```bash
# Query customer unallocated cash
curl -s -X GET \
  "https://api.haasib.local/api/customers/{customer_id}/unallocated-cash" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" | jq '.total_unallocated'
```

### CLI Receipt Generation
```bash
# Display receipt in terminal
php artisan payment:receipt PAY-2025-001

# Download JSON receipt
php artisan payment:receipt PAY-2025-001 --download=json --output=receipt.json

# Download PDF receipt  
php artisan payment:receipt PAY-2025-001 --download=pdf --output=receipt.pdf
```

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
