# Data Model – Bank Reconciliation Statement Matching

## Schema Overview

- `ops.bank_statements`: Stores uploaded bank statement metadata and ingestion state per company/bank account.
- `ops.bank_statement_lines`: Holds normalized statement line items linked to an `ops.bank_statement`.
- `ledger.bank_reconciliations`: Tracks reconciliation sessions per company/bank account with status, variances, and locking.
- `ledger.bank_reconciliation_matches`: Junction table connecting statement lines to internal transactions (`ledger.journal_entries`, `acct.payments`, etc.).
- `ledger.bank_reconciliation_adjustments`: Records adjustments (fees, interest, write-offs) generated during reconciliation.

All tables include `company_id` for RLS and reference existing ledger/accounting entities.

## Entities

### BankStatement (`ops.bank_statements`)
- **Fields**
  - `id` (uuid, PK)
  - `company_id` (uuid, FK → `auth.companies.id`)
  - `ledger_account_id` (uuid, FK → `ledger.chart_of_accounts.id`) — bank ledger account being reconciled
  - `statement_uid` (string, unique per company) — hash of file + date range for idempotency
  - `statement_name` (string) — original file name / label
  - `opening_balance` (decimal(16,4))
  - `closing_balance` (decimal(16,4))
  - `currency` (char(3))
  - `statement_start_date` / `statement_end_date` (date)
  - `file_path` (string) — stored upload location
  - `format` (enum: csv|ofx|qfx)
  - `imported_by` (uuid, FK → `auth.users.id`)
  - `imported_at` (timestamp with time zone)
  - `processed_at` (timestamp with time zone, nullable) — populated after line normalization
  - `status` (enum: `pending`, `processed`, `reconciled`, `archived`)
  - `created_at` / `updated_at` (timestamps)
- **Relationships**
  - Has many `BankStatementLine`
  - Belongs to `Company`
  - Belongs to `LedgerAccount`
  - May be linked to zero or one `BankReconciliation`
- **Validations / Constraints**
  - `statement_uid` unique for (`company_id`, `ledger_account_id`)
  - `statement_end_date` ≥ `statement_start_date`
  - `currency` must match ledger account currency
  - `opening_balance + sum(line.amount) = closing_balance` enforced during reconciliation approval

### BankStatementLine (`ops.bank_statement_lines`)
- **Fields**
  - `id` (uuid, PK)
  - `statement_id` (uuid, FK → `ops.bank_statements.id`)
  - `company_id` (uuid)
  - `transaction_date` (date)
  - `posted_at` (timestamp, nullable) — actual posting time, if provided
  - `description` (string)
  - `reference_number` (string, nullable)
  - `amount` (decimal(16,4)) — positive credits / negative debits (normalized)
  - `balance_after` (decimal(16,4), nullable)
  - `external_id` (string, nullable) — OFX/QFX identifiers
  - `line_hash` (string, unique per statement) — ensures deduplication
  - `categorization` (jsonb, nullable) — AI/manual tagging for suggestions
  - `created_at` / `updated_at`
- **Relationships**
  - Belongs to `BankStatement`
  - Has one `BankReconciliationMatch`
- **Validations / Constraints**
  - `line_hash` unique (`statement_id`, `company_id`)
  - Amount precision enforced via database check
  - `transaction_date` within statement date range

### BankReconciliation (`ledger.bank_reconciliations`)
- **Fields**
  - `id` (uuid, PK)
  - `company_id` (uuid)
  - `statement_id` (uuid, FK → `ops.bank_statements.id`)
  - `ledger_account_id` (uuid, FK → `ledger.chart_of_accounts.id`)
  - `started_by` (uuid, FK → `auth.users.id`)
  - `started_at` (timestamp with time zone)
  - `completed_by` (uuid, nullable)
  - `completed_at` (timestamp, nullable)
  - `status` (enum: `draft`, `in_progress`, `completed`, `locked`, `reopened`)
  - `unmatched_statement_total` (decimal(16,4))
  - `unmatched_internal_total` (decimal(16,4))
  - `variance` (decimal(16,4))
  - `notes` (text, nullable)
  - `locked_at` (timestamp, nullable)
  - `created_at` / `updated_at`
- **Relationships**
  - Belongs to `BankStatement`
  - Has many `BankReconciliationMatch`
  - Has many `BankReconciliationAdjustment`
  - Belongs to `LedgerAccount`
- **Validations / Constraints**
  - One active (`status` not `completed`/`locked`) reconciliation per (`company_id`, `ledger_account_id`)
  - `variance` must be zero before transition to `completed`
  - `locked_at` required when status = `locked`

**State Transitions**
```
draft → in_progress → completed → locked
        ↘ reopened (from locked) → in_progress
```
- Transition to `completed` requires zero variance and all lines matched or adjusted.
- `reopened` only allowed from `locked` with audit trail entry.

### BankReconciliationMatch (`ledger.bank_reconciliation_matches`)
- **Fields**
  - `id` (uuid, PK)
  - `reconciliation_id` (uuid, FK → `ledger.bank_reconciliations.id`)
  - `statement_line_id` (uuid, FK → `ops.bank_statement_lines.id`)
  - `source_type`/`source_id` (morph to internal transaction: `ledger.journal_entries`, `acct.payments`, `acct.credit_notes`, etc.)
  - `matched_at` (timestamp with time zone)
  - `matched_by` (uuid, FK → `auth.users.id`)
  - `amount` (decimal(16,4))
  - `auto_matched` (boolean)
  - `confidence_score` (decimal(5,2), nullable) — auto-match confidence
  - `created_at` / `updated_at`
- **Relationships**
  - Belongs to `BankReconciliation`
  - Belongs to `BankStatementLine`
  - Morphs to multiple internal sources
- **Validations / Constraints**
  - Unique (`statement_line_id`, `source_type`, `source_id`) to prevent duplicate matches
  - Amount must equal underlying transaction remaining balance

### BankReconciliationAdjustment (`ledger.bank_reconciliation_adjustments`)
- **Fields**
  - `id` (uuid, PK)
  - `reconciliation_id` (uuid)
  - `company_id` (uuid)
  - `statement_line_id` (uuid, nullable) — when adjustment ties to a specific line
  - `adjustment_type` (enum: `bank_fee`, `interest`, `write_off`, `timing`)
  - `journal_entry_id` (uuid, FK → `ledger.journal_entries.id`)
  - `amount` (decimal(16,4))
  - `description` (string)
  - `created_by` (uuid)
  - `created_at` / `updated_at`
- **Relationships**
  - Belongs to `BankReconciliation`
  - Optionally references `BankStatementLine`
  - Belongs to `JournalEntry`
- **Validations / Constraints**
  - `amount` sign must align with adjustment type (e.g., fees negative)
  - Journal entry must post to designated adjustment accounts per company configuration

### Supporting Concepts

- **BankAccount Configuration**: Reuse existing ledger chart-of-account records. Augment via configuration table (e.g., `acct.bank_accounts`) if needed to attach bank metadata (routing numbers, default fee/interest accounts).
- **Audit Trail**: All reconciliation state changes and manual match operations call `audit_log()` with reconciliation identifiers and variance snapshots. Broadcast events on `bank.reconciliation` channel mirror the audit context for real-time UI updates.
