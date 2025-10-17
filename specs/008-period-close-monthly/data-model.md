# Data Model — Period Close (Monthly)

## Entity Overview

### AccountingPeriod (`acct.accounting_periods`) — existing
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK, RLS enforced via `company_id` | Generated in existing migrations |
| `company_id` | UUID | FK → `auth.companies.id`, required | Tenant scope |
| `fiscal_year_id` | UUID | FK → `acct.fiscal_years.id`, required | |
| `name` | string(32) | Required | e.g., `2025-09` |
| `period_type` | enum(`month`,`quarter`,`year`) | Default `month` | Monthly close targets `month` |
| `period_number` | integer | Required, >=1 | Sequence within year |
| `start_date` / `end_date` | date | Required, `end_date >= start_date` | |
| `status` | enum(`future`,`open`,`closing`,`closed`,`reopened`) | Default `future` | Extend enum to support workflow |
| `closed_by` | UUID | FK → `auth.users.id`, nullable | Required when status → `closed` |
| `closed_at` | timestamptz | Nullable | Set on successful close |
| `closing_notes` | text | Nullable | Summary from close wizard |
| `reopened_by` | UUID | FK → `auth.users.id`, nullable | New column to capture reopen authority |
| `reopened_at` | timestamptz | Nullable | Populated when status → `reopened` |
| Timestamps | | Required | |

Indexes: `(company_id, status)` for open-period lookups; `(company_id, period_number)` unique per fiscal year. Update trigger `accounting_periods_closed_check` extended to forbid reverting from `closed` without reopen command.

### PeriodClose (`ledger.period_closes`) — new
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `company_id` | UUID | FK → `auth.companies.id`, required, RLS enforced | Mirrors accounting period company |
| `accounting_period_id` | UUID | FK → `acct.accounting_periods.id`, unique, required | One close record per period |
| `template_id` | UUID | FK → `ledger.period_close_templates.id`, nullable | Template used |
| `status` | enum(`pending`,`in_review`,`awaiting_approval`,`locked`,`closed`,`reopened`) | Default `pending` | Drives UI workflow |
| `trial_balance_variance` | numeric(18,2) | Default 0, must equal 0 before `closed` | Calculated from view |
| `unposted_documents` | jsonb | Nullable | Aggregated AR/AP/invoice exceptions |
| `adjusting_entry_id` | UUID | FK → `acct.journal_entries.id`, nullable | Final balancing entry |
| `closing_summary` | text | Nullable | Controller notes |
| `started_by` | UUID | FK → `auth.users.id`, nullable | Set when moved off `pending` |
| `started_at` | timestamptz | Nullable | |
| `closed_by` | UUID | FK → `auth.users.id`, nullable | Required when status → `closed` |
| `closed_at` | timestamptz | Nullable | |
| `reopened_by` | UUID | FK → `auth.users.id`, nullable | Required when status → `reopened` |
| `reopened_at` | timestamptz | Nullable | |
| `metadata` | jsonb | Nullable | Stores checklist configuration overrides |
| Timestamps | | Required | |

Indexes: `(company_id, status)` for dashboards; `(status, closed_at)` partial for reporting. Unique constraint on `accounting_period_id`.

### PeriodCloseTask (`ledger.period_close_tasks`) — new
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `period_close_id` | UUID | FK → `ledger.period_closes.id`, required | |
| `template_task_id` | UUID | FK → `ledger.period_close_template_tasks.id`, nullable | Back-reference for audit |
| `code` | string(64) | Required | Stable identifier (e.g., `tb-validate`) |
| `title` | string(120) | Required | Display label |
| `category` | enum(`trial_balance`,`subledger`,`compliance`,`reporting`,`misc`) | Required | Drives grouping |
| `sequence` | integer | Required, >= 1 | Order within checklist |
| `status` | enum(`pending`,`in_progress`,`blocked`,`completed`,`waived`) | Default `pending` | |
| `is_required` | boolean | Default true | Must complete before close unless waived |
| `completed_by` | UUID | FK → `auth.users.id`, nullable | Required when status → `completed` |
| `completed_at` | timestamptz | Nullable | |
| `notes` | text | Nullable | Manual justification |
| `attachment_manifest` | jsonb | Nullable | Metadata for uploaded evidence |
| Timestamps | | Required | |

Indexes: `(period_close_id, sequence)` unique; `(period_close_id, status)` for filtering.

### PeriodCloseTemplate (`ledger.period_close_templates`) — new
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `company_id` | UUID | FK → `auth.companies.id`, required | Templates per tenant |
| `name` | string(120) | Required | e.g., `Monthly Close v1` |
| `frequency` | enum(`monthly`,`quarterly`,`annual`) | Default `monthly` | Supports reuse |
| `is_default` | boolean | Default false | Allow multiple templates |
| `active` | boolean | Default true | Soft toggle |
| `description` | text | Nullable | |
| `metadata` | jsonb | Nullable | Additional configuration |
| Timestamps | | Required | |

Indexes: `(company_id, active)`; partial `(company_id)` where `is_default = true`.

### PeriodCloseTemplateTask (`ledger.period_close_template_tasks`) — new
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `id` | UUID | PK | |
| `template_id` | UUID | FK → `ledger.period_close_templates.id`, required | |
| `code` | string(64) | Required, unique per template | |
| `title` | string(120) | Required | |
| `category` | enum as above | Required | |
| `sequence` | integer | Required, >=1 | |
| `is_required` | boolean | Default true | |
| `default_notes` | text | Nullable | Pre-fill guidance |
| Timestamps | | Required | |

Unique index on `(template_id, sequence)`; `(template_id, code)` unique for idempotent provisioning.

### JournalEntry (`acct.journal_entries`) — existing (adjusting entries)
| Field | Type | Constraints & Validation | Notes |
|-------|------|---------------------------|-------|
| `entry_type` | enum | Extend to include `period_adjustment` and `period_close` | Used for end-of-period adjustments |
| `accounting_period_id` | UUID | Must match associated period and remain unlocked until final close | |
| `status` | enum | Must be `posted` before period closes | |
| `metadata` | jsonb | Store linkage `{"period_close_id": "<uuid>"}` for traceability | |

Validation: adjusting entries must balance (existing trigger) and fall within period start/end. Closing workflow ensures no draft/pending entries remain.

### FinancialStatements (`rpt.financial_statements`) — existing materialized view
- Provides period income statement/balance sheet snapshots.
- Close workflow triggers refresh for target period and stores reference in `ledger.period_closes.metadata.reports`.

## Relationships
- `AccountingPeriod` 1—1 `PeriodClose` (`ledger.period_closes.accounting_period_id` unique).
- `PeriodClose` 1—* `PeriodCloseTask`.
- `PeriodCloseTemplate` 1—* `PeriodCloseTemplateTask`.
- `PeriodCloseTemplate` 1—* `PeriodClose` (optional) via `template_id`.
- `PeriodClose` ↔ `JournalEntry` (optional) for final adjusting entry.
- `PeriodCloseTask` optionally links to `JournalEntry` IDs inside `attachment_manifest` for supporting evidence.
- `AccountingPeriod` ↔ `JournalEntry` (existing) ensures posted entries tagged with `period_close_id`.

## State Machines
- **AccountingPeriod Status**: `future` → `open` → `closing` → `closed`. `closed` → `reopened` (via authorized reopen) → `closing` → `closed`. Transition to `closing` requires PeriodClose record; `reopened` requires audit trail and resets PeriodClose status to `in_review`.
- **PeriodClose Status**: `pending` (auto-created) → `in_review` (checklist in progress) → `awaiting_approval` (all required tasks complete) → `locked` (validations pass, pending final authorization) → `closed`. `closed` → `reopened` (on authorized reopen) rehydrates outstanding tasks and logs event.
- **PeriodCloseTask Status**: `pending` → `in_progress` → `completed`; any state can move to `blocked` with note; `blocked` → `completed` or `waived` (requires justification). Required tasks must be `completed` before PeriodClose can move past `awaiting_approval` unless `waived` with approver note.

Validation rules:
- Cannot set `PeriodClose.status = closed` unless `AccountingPeriod.status = closing`, `trial_balance_variance = 0`, zero unposted documents in JSON payload, and all required tasks `completed`.
- Reopen requires entry in `audit_log()` capturing reason and updates both `PeriodClose` and `AccountingPeriod`.
- Templates enforce at least one task; sequence integers must be contiguous without gaps for deterministic ordering.
