# Quickstart — Journal Entries (Manual & Automatic)

## Prerequisites
- Feature branch `007-journal-entries-manual` checked out.
- Latest migrations applied (`php artisan migrate`).
- Tenant context set (`php artisan context:login --user=<uuid> --company=<uuid>`).
- Ensure roles include permissions `ledger.postJournal`, `accounting.journal_entries.*`, and `accounting.trial_balance.view` (see `stack/database/seeders/PermissionSeeder.php`).
- Run queue worker for recurring/batch automation (`php artisan queue:work --queue=journal,ledger`).

## 1. Create Manual Journal Entry (CLI)
```bash
php artisan journal:entry:create \
  --description="Accrue payroll" \
  --date=$(date +%Y-%m-%d) \
  --line="acct=5100,debit=8500,desc='Payroll expense accrual'" \
  --line="acct=2100,credit=8500,desc='Wages payable accrual'" \
  --reference="JE-$(date +%Y%m%d)-001" \
  --attachments=/tmp/payroll-support.pdf \
  --format=json
```
- Validates debits = credits and writes entry in `draft` status using the command bus.
- `--line` accepts repeated account rows (`acct=<code|uuid>,debit|credit=<amount>[,desc=...]`).
- Add `--submit` to immediately move the entry into `pending_approval`.

## 2. Approve & Post Entry
```bash
# Submit for approval (if not auto-submitted)
php artisan journal:entry:submit JE-20251015-001 --json

# Approve (requires `ledger.postJournal` + approver role)
php artisan journal:entry:approve JE-20251015-001 \
  --note="Reviewed by controller" --json

# Post to the ledger
php artisan journal:entry:post JE-20251015-001 --json
```
- Posting enforces closed-period guards, fires ledger events, and refreshes trial balance materialized view.
- Add `--dry-run` to any command to preview validation without committing.

## 3. Reverse a Posted Entry
```bash
php artisan journal:entry:reverse JE-20251015-001 \
  --reversal-date="2025-11-01" \
  --description="Reversal of payroll accrual" \
  --json
```
- Creates a mirrored entry with inverted debit/credit amounts and links it via `reverse_of_entry_id`.
- Use `--auto-post` to post immediately; defaults to `draft`.

## 4. Batch Posting Workflow
```bash
# Group multiple drafts into a batch
php artisan journal:batch:create \
  --entries="JE-20251015-001,JE-20251015-002" \
  --schedule="2025-10-20T09:00:00Z" \
  --json

# Approve and post when ready
php artisan journal:batch:approve JNB-2025-010 --json
php artisan journal:batch:post JNB-2025-010 --json
```
- Batch approval confirms totals balance before posting.
- Scheduled batches auto-dispatch when `scheduled_post_at` is reached.

## 5. Recurring Template Management
```bash
# Create template for monthly rent accrual
php artisan journal:template:create "Monthly Rent Accrual" \
  --frequency=monthly \
  --next-run="2025-11-01T00:00:00Z" \
  --line="acct=6100,debit=15000" \
  --line="acct=2600,credit=15000" \
  --auto-post \
  --json

# Update or deactivate
php artisan journal:template:update tpl-uuid --set=auto_post=false --json
php artisan journal:template:deactivate tpl-uuid --json
```
- Scheduler job `journal:templates:run` (queued hourly) materializes entries from active templates.
- Use `--preview` to see the generated entry before committing.

## 6. API Examples
```bash
# Create entry via API
curl -s -X POST https://api.haasib.local/api/ledger/journal-entries \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{
        "description": "Manual depreciation",
        "date": "2025-10-31",
        "type": "adjustment",
        "lines": [
          { "account_id": "acct-asset-uuid", "debit_credit": "debit", "amount": 2500.00, "description": "Depreciation expense" },
          { "account_id": "acct-accum-uuid", "debit_credit": "credit", "amount": 2500.00, "description": "Accumulated depreciation" }
        ]
      }' | jq '.status,.totals'

# Post entry
curl -s -X POST https://api.haasib.local/api/ledger/journal-entries/{id}/post \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" | jq '.status'

# Trial balance snapshot
curl -s -G https://api.haasib.local/api/ledger/trial-balance \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  --data-urlencode "period_id=${PERIOD_ID}" | jq '.totals'
```
- API responses conform to `contracts/journal-entries.openapi.yaml`.
- Attach `Idempotency-Key` to all POSTs for safe retries.

## 7. UI Smoke Checklist
- Navigate to **Accounting → Journal Entries** (new Inertia page).
- Use “New Journal Entry” to launch PrimeVue dialog backed by `JournalEntryForm`.
- Confirm balance indicator turns green when debits=credits and validation errors surface inline.
- Post entry from detail view and verify audit timeline updates.
- Run Playwright scenario (to add) `stack/tests/Browser/journal.manual-entry.spec.ts` to assert GUI parity.

## 8. Audit & Reporting
```bash
# Inspect audit trail
curl -s https://api.haasib.local/api/ledger/journal-entries/{id}/audit \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" | jq '.[].event_type'

# Export trial balance to CSV (future CLI)
php artisan journal:trial-balance --period=${PERIOD_ID} --format=csv --output=tb.csv
```
- Audit log events capture approvals, postings, reversals, and attachment updates.
- Trial balance export aligns with `acct.trial_balance` materialized view refreshed after posting cycles.
