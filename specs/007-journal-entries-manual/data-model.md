# Data Model — Journal Entries & Ledger Enhancements

## Entity Overview

### JournalBatch (`acct.journal_batches`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK, default `generate_uuid()` | Batch identifier |
| `company_id` | UUID | FK → `auth.companies.id`, required, RLS enforced | Tenant scope |
| `batch_number` | string(20) | Unique per `company_id`, required | Sequential e.g., `JNB-000123` |
| `status` | enum(`draft`,`ready`,`scheduled`,`posted`,`void`) | Default `draft` | Drives workflow |
| `scheduled_post_at` | timestamp | Nullable, >= now | Future posting |
| `total_entries` | integer | Default 0, >=0 | Count of entries in batch |
| `total_debits` / `total_credits` | decimal(20,2) | Default 0, >=0 | Validated to match | 
| `created_by` | UUID | FK → `auth.users.id`, required | Creator audit |
| `approved_by` | UUID | FK → `auth.users.id`, nullable | Approval workflow |
| `approved_at` | timestamp | Nullable | Set when status moves to `ready` |
| `posted_by` | UUID | FK → `auth.users.id`, nullable | Set on `posted` |
| `posted_at` | timestamp | Nullable | Auto-set on posting |
| `voided_by` | UUID | FK → `auth.users.id`, nullable | |
| `voided_at` | timestamp | Nullable | |
| `void_reason` | text | Nullable | Required when status transitions to `void` |
| `attachments` | jsonb | Nullable | Stores document metadata |
| `metadata` | jsonb | Nullable | Free-form configuration |
| Timestamps | | Required | |

Indexes: `(company_id, batch_number)` unique; `(company_id, status, scheduled_post_at)` for queueing; partial index on `(company_id)` where `status = 'ready'` for approval worklists.

### JournalEntry (`acct.journal_entries`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK, default `generate_uuid()` | |
| `company_id` | UUID | FK → `auth.companies.id`, required, RLS enforced | |
| `batch_id` | UUID | FK → `acct.journal_batches.id`, nullable | Links manual entries to batch |
| `template_id` | UUID | FK → `acct.recurring_journal_templates.id`, nullable | Origin recurring template |
| `reference` | string(100) | Required, unique per company | Journal number e.g., `JE-2025-000123` |
| `sequence` | bigserial | Unique per `company_id` via partial index | Supports numbering configuration |
| `description` | string(500) | Required | |
| `date` | date | Required, must fall in open period | Period guard validated |
| `type` | enum(`sales`,`purchase`,`payment`,`receipt`,`adjustment`,`closing`,`opening`,`reversal`,`automation`) | Required | |
| `status` | enum(`draft`,`pending_approval`,`approved`,`posted`,`void`) | Default `draft` | Workflow states |
| `approval_note` | text | Nullable | Captures approver feedback |
| `created_by` | UUID | FK → `auth.users.id`, required | |
| `approved_by` | UUID | FK → `auth.users.id`, nullable | Required when status → `approved` |
| `approved_at` | timestamp | Nullable | |
| `posted_by` | UUID | FK → `auth.users.id`, nullable | Set when `status = posted` |
| `posted_at` | timestamp | Nullable | Auto-populated via trigger |
| `voided_by` | UUID | FK → `auth.users.id`, nullable | |
| `voided_at` | timestamp | Nullable | |
| `void_reason` | text | Nullable | Required when status → `void` |
| `currency` | char(3) | Required, ISO 4217 | Defaults to tenant currency |
| `exchange_rate` | decimal(15,8) | Default 1, >0 | For multi-currency entries |
| `fiscal_year_id` | UUID | FK → `acct.fiscal_years.id`, required | |
| `accounting_period_id` | UUID | FK → `acct.accounting_periods.id`, required | Must not be locked |
| `source_document_type` | string(100) | Nullable | e.g., `Invoice`, `Payment`, `Manual` |
| `source_document_id` | UUID | Nullable | Links to originating document |
| `origin_command` | string(150) | Nullable | Command bus action, e.g., `payment.record` |
| `auto_generated` | boolean | Default false | True for system-created entries |
| `reverse_of_entry_id` | UUID | FK → `acct.journal_entries.id`, nullable | Points to original entry when reversal |
| `reversal_entry_id` | UUID | FK → `acct.journal_entries.id`, nullable | Populated when reverse entry created |
| `attachments` | jsonb | Nullable | Supporting documents |
| `metadata` | jsonb | Nullable | Stores line-level analytics, tags |
| Timestamps | | Required | |

Indexes: `(company_id, reference)` unique; `(company_id, status, date)`; `(accounting_period_id, status)`; `(company_id, source_document_type, source_document_id)` for traceability.

Triggers: `acct.validate_journal_entry_posting()` ensures balanced totals on `status = posted`. Additional trigger will enforce `acct.is_period_locked(accounting_period_id) = false` before posting.

### JournalTransaction (`acct.journal_transactions`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `journal_entry_id` | UUID | FK → `acct.journal_entries.id`, required | |
| `line_number` | integer | Required, >=1, sequential per entry | Supports ordering |
| `account_id` | UUID | FK → `acct.accounts.id`, required | |
| `debit_credit` | enum(`debit`,`credit`) | Required | |
| `amount` | decimal(20,2) | Required, >0 | Stored in entry currency |
| `currency` | char(3) | Required, defaults to entry currency | |
| `exchange_rate` | decimal(15,8) | Default 1 | |
| `description` | text | Nullable | Line memo |
| `source_line_type` | string(100) | Nullable | e.g., `InvoiceLine`, `TaxLine` |
| `source_line_id` | UUID | Nullable | Link to originating line |
| `reconcile_id` | UUID | Nullable | Bank reconciliation linkage |
| `tax_code_id` | UUID | Nullable | FK → `acct.tax_codes.id` |
| `tax_amount` | decimal(20,2) | Default 0 | |
| `metadata` | jsonb | Nullable | Additional tags |
| Timestamps | | Required | |

Indexes: `(journal_entry_id, line_number)` unique; `(account_id, debit_credit, date(created_at))` for reporting; `(source_line_type, source_line_id)` for trace back.

### JournalEntrySource (`acct.journal_entry_sources`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `journal_entry_id` | UUID | FK → `acct.journal_entries.id`, required | |
| `journal_transaction_id` | UUID | FK → `acct.journal_transactions.id`, nullable | When trace is line-specific |
| `source_type` | string(100) | Required | Enum of supported objects |
| `source_id` | UUID | Required | References invoice/payment/etc |
| `source_reference` | string(150) | Nullable | Human-friendly identifier |
| `link_type` | enum(`origin`,`supporting`,`reversal`) | Required | |
| `created_at` | timestamp | Required | |

Indexes: `(journal_entry_id, source_type, source_id)` unique to prevent duplicates; `(source_type, source_id)` for reverse lookup from source document.

### RecurringJournalTemplate (`acct.recurring_journal_templates`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `company_id` | UUID | FK → `auth.companies.id`, required | |
| `name` | string(150) | Required, unique per company | |
| `description` | string(500) | Nullable | |
| `frequency` | enum(`daily`,`weekly`,`monthly`,`quarterly`,`annually`,`custom`) | Required | |
| `custom_cron` | string(100) | Nullable, required when `frequency = custom` | |
| `next_run_at` | timestamp | Required | |
| `last_run_at` | timestamp | Nullable | |
| `auto_post` | boolean | Default false | Determines posted vs draft |
| `active` | boolean | Default true | |
| `created_by` | UUID | FK → `auth.users.id`, required | |
| `metadata` | jsonb | Nullable | Stores generation options |
| Timestamps | | | |

Unique index `(company_id, name)`; schedule index on `(active, next_run_at)`.

### RecurringJournalLine (`acct.recurring_journal_template_lines`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `template_id` | UUID | FK → `acct.recurring_journal_templates.id`, required | |
| `line_number` | integer | Required | |
| `account_id` | UUID | FK → `acct.accounts.id`, required | |
| `debit_credit` | enum(`debit`,`credit`) | Required | |
| `amount_formula` | string(255) | Required | Supports fixed amount or expression placeholders |
| `description` | text | Nullable | |
| `metadata` | jsonb | Nullable | |
| Timestamps | | | |

Unique index `(template_id, line_number)`; validation ensures template lines balance when concrete values resolved.

### JournalAudit (`acct.journal_audit_log`)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `journal_entry_id` | UUID | FK → `acct.journal_entries.id`, required | |
| `event_type` | enum(`created`,`updated`,`posted`,`voided`,`approved`,`reversed`,`attachment_added`) | Required | |
| `actor_id` | UUID | FK → `auth.users.id`, nullable | Null when system automated |
| `payload` | jsonb | Required | Before/after snapshot diff |
| `created_at` | timestamp | Required | |

Backed by existing `App\Models\AuditEntry` pattern; index on `(journal_entry_id, created_at)` for chronological retrieval.

### TrialBalanceView (`acct.trial_balance`)
Materialized view providing aggregated balance per account:
- Columns: `company_id`, `account_id`, `code`, `account_name`, `account_class`, `total_debits`, `total_credits`, `balance`.
- Refresh triggered after batch posting or nightly job.
- Backed by existing SQL view created in migrations.

## Relationships
- `JournalBatch` 1—* `JournalEntry` (manual entries grouped for approval/posting).
- `JournalEntry` 1—* `JournalTransaction` ordered by `line_number`.
- `JournalEntry` 1—* `JournalEntrySource` (document traceability).
- `JournalEntry` ↔ `JournalEntry` (self-reference) for reversal mapping (`reverse_of_entry_id`/`reversal_entry_id`).
- `RecurringJournalTemplate` 1—* `RecurringJournalLine`; templates generate `JournalEntry` records on schedule.
- `JournalEntry` *—* `acct.accounts` via `JournalTransaction.account_id`.
- `JournalEntry` 1—* `JournalAudit` (chronological audit trail).

## State Machines
- **JournalBatch Status**: `draft` → `ready` (requires balanced totals) → `scheduled` (optional) → `posted`; `draft|ready` → `void`. `scheduled` auto-transitions to `posted` when time reached.
- **JournalEntry Status**: `draft` → `pending_approval` → `approved` → `posted`; `draft|pending_approval|approved` → `void`. Reversal creates paired entry and marks original as `posted` with `reversal_entry_id`.
- **Recurring Template Lifecycle**: `active` ↔ `inactive`; scheduler uses `next_run_at`, updates `last_run_at` after generation; templates flagged `auto_post = true` create entries in `posted` state once lines balance.

Validation rules ensure every entry balances, respects closed-period locks (`acct.is_period_locked`), and that reversal entries mirror original amounts with inverted debit/credit polarity.
