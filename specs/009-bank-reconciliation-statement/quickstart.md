# Quickstart – Bank Reconciliation Statement Matching

## Prerequisites
- Company has configured bank ledger accounts and permissions (`bank_reconciliation.import`, `bank_reconciliation.view`, `bank_reconciliation.match`, etc.).
- Command-bus actions registered for `bank.reconciliation.import` and `bank.reconciliation.match`.
- Storage disk configured for statement uploads; queues running for ingestion and auto-match jobs.
- WebSocket broadcasting configured for real-time collaboration.

## 1. Import a bank statement
- Navigate to **Accounting → Bank Reconciliation** and choose the bank account.
- Upload CSV/OFX/QFX file with opening/closing balances and select the covered date range.
- The system stages the file in `ops.bank_statements` and normalizes lines into `ops.bank_statement_lines`. Progress and parsing errors surface via toast + audit log entries.

### CLI
```bash
php artisan bank:statement:import \
  --company=uuid --bank-account=uuid \
  --file=/path/to/statement.qfx \
  --start=2025-09-01 --end=2025-09-30 \
  --opening=12000.00 --closing=14375.54
```

## 2. Start reconciliation
- Once parsing completes, click **Start Reconciliation**. This creates a `ledger.bank_reconciliations` record and loads statement lines alongside unmatched internal transactions.
- Auto-match suggestions appear in the UI; background jobs push progress via the `bank.reconciliation` WebSocket channel.

### CLI
```bash
php artisan bank:reconciliation:start \
  --company=uuid --statement=uuid \
  --bank-account=uuid
```

## 3. Auto-match vs manual match
- Trigger **Auto-Match** to pair lines by amount/date/reference. Review confidence scores; high-confidence matches apply automatically.
- For manual actions, select a statement line and choose one or more internal transactions. Confirm to create a match entry; the UI updates variance totals instantly.

### CLI
```bash
php artisan bank:reconciliation:auto-match \
  --reconciliation=uuid
```

## 4. Add adjustments
- Use the **Add Adjustment** action for bank fees, interest, or timing differences. The system creates journal entries via the command bus and records adjustments in `ledger.bank_reconciliation_adjustments`.
- Adjustment types include: `bank_fee`, `interest`, `transfer_error`, `timing_difference`, `unidentified_item`.

### CLI
```bash
php artisan bank:reconciliation:create-adjustment \
  --reconciliation=uuid \
  --type=bank_fee \
  --amount=25.00 \
  --description="Monthly service fee"
```

## 5. Complete and lock
- When variance reaches zero and all lines are matched or adjusted, click **Complete Reconciliation**. Optionally lock to prevent further edits; the system emits comprehensive audit entries and broadcasts completion.
- Locked reconciliations can be reopened (with reason) by users granted the appropriate permission, restoring the `in_progress` state.

### CLI
```bash
# Complete reconciliation
php artisan bank:reconciliation:complete \
  --reconciliation=uuid

# Lock reconciliation
php artisan bank:reconciliation:lock \
  --reconciliation=uuid

# Reopen reconciliation
php artisan bank:reconciliation:reopen \
  --reconciliation=uuid \
  --reason="Need to correct matching errors"
```

## 6. Reporting & audit
- Access comprehensive reports from the reconciliation detail page: Summary, Variance Analysis, and Audit Trail.
- Export reports in PDF, JSON, or CSV formats for documentation and compliance.
- Real-time metrics available via API showing completion progress, variance status, and activity timeline.

### API Endpoints
```bash
# Get available reports
GET /api/ledger/bank-reconciliations/{id}/reports

# Generate specific report
GET /api/ledger/bank-reconciliations/{id}/reports/{type}?format=json

# Export report
POST /api/ledger/bank-reconciliations/{id}/reports/{type}/export

# Get real-time metrics
GET /api/ledger/bank-reconciliations/{id}/metrics

# Bulk report generation
POST /api/ledger/bank-reconciliations/bulk-reports
```

### CLI
```bash
# Generate reconciliation report
php artisan bank:reconciliation:report \
  --reconciliation=uuid \
  --type=summary \
  --format=pdf
```

## 7. Real-time collaboration
- Multiple users can work on the same reconciliation simultaneously with live updates.
- WebSocket events broadcast match activities, adjustments, and status changes to all connected users.
- Activity logging captures all user actions with timestamps, IP addresses, and detailed context.

## 8. Permission matrix
- **View**: `bank_reconciliation.view` - Read access to reconciliations
- **Import**: `bank_reconciliation.import` - Upload and process statements
- **Match**: `bank_reconciliation.match` - Create and modify matches
- **Adjust**: `bank_reconciliation.adjust` - Create adjustment entries
- **Complete**: `bank_reconciliation.complete` - Complete reconciliations
- **Lock**: `bank_reconciliation.lock` - Lock/unlock reconciliations
- **Reopen**: `bank_reconciliation.reopen` - Reopen locked reconciliations
- **Reports View**: `bank_reconciliation_reports.view` - Access reports
- **Reports Export**: `bank_reconciliation_reports.export` - Download reports
