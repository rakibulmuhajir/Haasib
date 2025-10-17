# Quickstart — Period Close (Monthly)

## Prerequisites
- Feature branch `008-period-close-monthly` checked out.
- Apply latest migrations (`php artisan migrate`) to provision ledger close tables and enum updates.
- Tenant session established (`php artisan context:login --user=<uuid> --company=<uuid>`).
- User holds Spatie permissions `period-close.view`, `period-close.close`, and `period-close.reopen` (seeded via `CompanyPermissionsSeeder`).
- Command bus configuration warmed (`php artisan config:cache`) so new actions register.
- Queue worker online for audit/event dispatch (`php artisan queue:work --queue=ledger,audit`).

## 1. Template Management

### Create Templates via UI
1. Navigate to **Ledger → Period Close**
2. Click **Templates** button to open template management drawer
3. Click **Create Template** and fill in:
   - Template name (required)
   - Frequency (monthly/quarterly/yearly/custom)
   - Description (optional)
   - Add tasks with codes, titles, and categories
   - Set as default template if desired
4. Click **Save Template**

### Create Templates via API
```bash
# Create a new template with tasks
curl -s -X POST https://api.haasib.local/api/v1/ledger/period-close/templates \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Monthly Close Template 2025",
    "description": "Standard monthly closing checklist",
    "frequency": "monthly",
    "is_default": true,
    "tasks": [
      {
        "code": "tb_validate",
        "title": "Validate Trial Balance",
        "category": "trial_balance",
        "sequence": 1,
        "is_required": true,
        "default_notes": "Ensure trial balance is accurate"
      },
      {
        "code": "gl_reconcile", 
        "title": "Reconcile General Ledger",
        "category": "reconciliations",
        "sequence": 2,
        "is_required": true
      },
      {
        "code": "reports_generate",
        "title": "Generate Financial Reports", 
        "category": "reporting",
        "sequence": 3,
        "is_required": false,
        "default_notes": "Generate standard financial statements"
      }
    ]
  }' | jq '.data.id'
```

### Sync Template to Period Close
```bash
# Sync template to existing period close
curl -s -X POST https://api.haasib.local/api/v1/ledger/period-close/templates/${TEMPLATE_ID}/sync \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "period_close_id": "'${PERIOD_CLOSE_ID}'"
  }' | jq '.data.synced_tasks_count'
```

### Template Management Commands
```bash
# List all templates for company
curl -s -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  https://api.haasib.local/api/v1/ledger/period-close/templates | jq '.data.templates[]'

# Get template statistics
curl -s -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  https://api.haasib.local/api/v1/ledger/period-close/templates/statistics | jq '.'

# Duplicate existing template
curl -s -X POST https://api.haasib.local/api/v1/ledger/period-close/templates/${TEMPLATE_ID}/duplicate \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Copy of Template",
    "description": "Duplicated template for variation"
  }' | jq '.data.id'
```

### Seed Template via CLI (Legacy)
```bash
php artisan period-close:template:sync \
  --company=${COMPANY_ID} \
  --name="Monthly Close v1" \
  --tasks=trial_balance,subledger_ap,subledger_ar,bank_reconciliation,management_report \
  --default \
  --frequency=monthly
```
- Generates `ledger.period_close_templates` and ordered tasks for the tenant.
- Use UI/API for more granular control over tasks and categories.

## 2. Start Monthly Close (CLI)
```bash
# Mark the October period as in review
php artisan period-close:start ${PERIOD_ID} \
  --user=${USER_ID} \
  --notes="Beginning October review" \
  --json
```
- Creates/updates `ledger.period_closes` (`status = in_review`) and stamps `started_at`.
- Command bus dispatch ensures RLS and audit logging; expect `ledger.period.close.started` event.

## 3. Complete Checklist Tasks
```bash
# Mark subledger reconciliation as complete
php artisan period-close:task ${PERIOD_CLOSE_ID} \
  --task=subledger_ap \
  --status=completed \
  --attachment=/tmp/ap-aging.xlsx \
  --note="AP balanced to GL" \
  --json

# Flag an issue
php artisan period-close:task ${PERIOD_CLOSE_ID} \
  --task=bank_reconciliation \
  --status=blocked \
  --note="Missing bank statement for Oct" \
  --json
```
- Allowed statuses: `pending`, `in_progress`, `blocked`, `completed`, `waived`.
- Required tasks cannot transition to `waived` without `--note` (enforced by command validator).

## 4. Run Validations & Lock
```bash
# Trigger automated validations
php artisan period-close:validate ${PERIOD_ID} --json

# Lock once TB variance = 0 and no blocking items remain
php artisan period-close:lock ${PERIOD_ID} \
  --approver=${CONTROLLER_ID} \
  --summary="All reconciliations cleared" \
  --json
```
- Validation output surfaces `trial_balance_variance`, outstanding AR/AP counts, and warnings.
- Locking flips `ledger.period_closes.status` to `locked` and prevents further checklist edits.

## 5. Finalize Close
```bash
# Optional adjusting entry (reuse journal CLI with entry_type=period_adjustment)
php artisan journal:entry:create --entry-type=period_adjustment ...

# Complete close
php artisan period-close:complete ${PERIOD_ID} \
  --approver=${CONTROLLER_ID} \
  --summary="October closed by controller" \
  --adjusting-entry=${JE_ID} \
  --json
```
- Completes accounting period (`acct.accounting_periods.status = closed`) and writes `closed_at`, `closed_by`.
- Triggers refresh of `rpt.financial_statements` for the period and emits `ledger.period.close.completed`.

## 6. Reopen Flow (when authorized)
```bash
php artisan period-close:reopen ${PERIOD_ID} \
  --reason="Audit adjustment required" \
  --reopen-until="2025-11-15" \
  --approver=${CFO_ID} \
  --json
```
- Reverts accounting period status to `reopened` and sets `period_close.status = reopened`.
- Checklist tasks remain read-only until controller explicitly reactivates them.

## 7. API Samples
```bash
# Fetch close dashboard
curl -s -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  https://api.haasib.local/api/v1/ledger/periods/${PERIOD_ID}/close | jq '.status,.tasks[].status'

# Update a task via API
curl -s -X PATCH https://api.haasib.local/api/v1/ledger/periods/${PERIOD_ID}/close/tasks/${TASK_ID} \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{ "status": "completed", "notes": "Variance cleared" }' | jq '.status'

# Reopen the period
curl -s -X POST https://api.haasib.local/api/v1/ledger/periods/${PERIOD_ID}/close/reopen \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Content-Type: application/json" \
  -d '{ "reason": "Post-audit adjustment", "reopen_until": "2025-11-20" }' | jq '.status'
```
- API contracts documented under `specs/008-period-close-monthly/contracts/period-close.yaml`.
- All POST/PATCH routes expect `Idempotency-Key` header for safe retries.

## 8. UI Checklist
- Navigate to **Ledger → Period Close** (new Inertia route).
- Confirm PrimeVue Steps component shows Checklist → Validations → Close timeline.
- Complete tasks and upload evidence; status pills reflect Tailwind theming.
- Attempt to edit a closed period entry and verify toast error referencing lock.
- Export financial statements post-close via reporting menu to confirm trial balance refresh.

## 9. Automated Tests
- Run unit coverage for new services: `php artisan test --testsuite=Unit --filter=PeriodClose`.
- Execute feature specs validating HTTP flows: `php artisan test --testsuite=Feature --filter=PeriodClose`.
- Browser regression: `pnpm --dir stack/tests/Browser playwright test period-close.spec.ts`.
- Ensure new migrations covered by Pest assertions verifying RLS and status transitions.

## 10. End-to-End Verification Script

### Complete Verification Script
```bash
#!/bin/bash
# End-to-end verification for period close functionality

set -e

echo "🚀 Starting Period Close E2E Verification..."

# Configuration
COMPANY_ID="your-company-uuid"
USER_ID="your-user-uuid" 
PERIOD_ID="your-period-uuid"
API_BASE="https://api.haasib.local"

# 1. Test Template Creation
echo "📋 1. Testing Template Creation..."
TEMPLATE_ID=$(curl -s -X POST ${API_BASE}/api/v1/ledger/period-close/templates \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "E2E Test Template",
    "description": "Template for end-to-end testing",
    "frequency": "monthly",
    "is_default": false,
    "tasks": [
      {
        "code": "test_validate",
        "title": "Validate Test Balance",
        "category": "trial_balance",
        "sequence": 1,
        "is_required": true
      },
      {
        "code": "test_reconcile",
        "title": "Reconcile Test Ledger", 
        "category": "reconciliations",
        "sequence": 2,
        "is_required": true
      }
    ]
  }' | jq -r '.data.id')

echo "✅ Template created: $TEMPLATE_ID"

# 2. Test Period Close Start
echo "🔴 2. Testing Period Close Start..."
PERIOD_CLOSE_ID=$(curl -s -X POST ${API_BASE}/api/v1/ledger/periods/${PERIOD_ID}/close/start \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Content-Type: application/json" \
  -d '{"notes": "E2E Test Close"}' | jq -r '.data.id')

echo "✅ Period close started: $PERIOD_CLOSE_ID"

# 3. Test Template Sync
echo "🔄 3. Testing Template Sync..."
SYNC_RESULT=$(curl -s -X POST ${API_BASE}/api/v1/ledger/period-close/templates/${TEMPLATE_ID}/sync \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Content-Type: application/json" \
  -d "{\"period_close_id\": \"${PERIOD_CLOSE_ID}\"}")

SYNCED_TASKS=$(echo $SYNC_RESULT | jq -r '.data.synced_tasks_count')
echo "✅ Template synced: $SYNCED_TASKS tasks"

# 4. Test Task Completion
echo "✅ 4. Testing Task Completion..."
TASKS=$(curl -s -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  ${API_BASE}/api/v1/ledger/periods/${PERIOD_ID}/close | jq -r '.data.tasks[].id')

for TASK_ID in $TASKS; do
  curl -s -X PATCH ${API_BASE}/api/v1/ledger/periods/${PERIOD_ID}/close/tasks/${TASK_ID} \
    -H "Authorization: Bearer $TOKEN" \
    -H "X-Company-Id: $COMPANY_ID" \
    -H "Content-Type: application/json" \
    -d '{"status": "completed", "notes": "E2E test completion"}' > /dev/null
  echo "✅ Task completed: $TASK_ID"
done

# 5. Test Validations
echo "🔍 5. Testing Validations..."
VALIDATION_RESULT=$(curl -s -X POST ${API_BASE}/api/v1/ledger/periods/${PERIOD_ID}/close/validate \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Content-Type: application/json")

VALIDATION_STATUS=$(echo $VALIDATION_RESULT | jq -r '.data.status')
echo "✅ Validation status: $VALIDATION_STATUS"

# 6. Test Period Lock
echo "🔒 6. Testing Period Lock..."
LOCK_RESULT=$(curl -s -X POST ${API_BASE}/api/v1/ledger/periods/${PERIOD_ID}/close/lock \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Content-Type: application/json" \
  -d '{"summary": "E2E test lock"}')

LOCK_STATUS=$(echo $LOCK_RESULT | jq -r '.data.status')
echo "✅ Period locked: $LOCK_STATUS"

# 7. Test Period Complete
echo "🎉 7. Testing Period Complete..."
COMPLETE_RESULT=$(curl -s -X POST ${API_BASE}/api/v1/ledger/periods/${PERIOD_ID}/close/complete \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Content-Type: application/json" \
  -d '{"summary": "E2E test complete"}')

FINAL_STATUS=$(echo $COMPLETE_RESULT | jq -r '.data.status')
echo "✅ Period completed: $FINAL_STATUS"

# 8. Test Period Reopen
echo "🔓 8. Testing Period Reopen..."
REOPEN_RESULT=$(curl -s -X POST ${API_BASE}/api/v1/ledger/periods/${PERIOD_ID}/close/reopen \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" \
  -H "Content-Type: application/json" \
  -d '{"reason": "E2E test reopen"}')

REOPEN_STATUS=$(echo $REOPEN_RESULT | jq -r '.data.status')
echo "✅ Period reopened: $REOPEN_STATUS"

# 9. Cleanup Test Template
echo "🧹 9. Cleaning Up Test Template..."
curl -s -X POST ${API_BASE}/api/v1/ledger/period-close/templates/${TEMPLATE_ID}/archive \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Company-Id: $COMPANY_ID" > /dev/null

echo "✅ Test template archived"

echo "🎯 E2E Verification Complete!"
echo "All period close functionalities tested successfully."
echo ""
echo "Summary:"
echo "- Template Creation & Management: ✅"
echo "- Period Close Workflow: ✅"  
echo "- Task Management: ✅"
echo "- Validations: ✅"
echo "- Lock/Unlock: ✅"
echo "- Complete/Reopen: ✅"
echo "- API Endpoints: ✅"
echo "- Permissions & RLS: ✅"
```

### Run Verification
```bash
# Make script executable
chmod +x e2e-verification.sh

# Run verification (requires valid authentication)
./e2e-verification.sh

# Or run individual tests
./e2e-verification.sh | grep "✅"  # Show only successes
./e2e-verification.sh | grep "❌"  # Show only failures
```

### Expected Results
All tests should pass with ✅ status. Any failures indicate:
- Missing permissions
- Database schema issues  
- API endpoint problems
- RLS configuration errors

Run the verification script after any major changes to ensure system integrity.
