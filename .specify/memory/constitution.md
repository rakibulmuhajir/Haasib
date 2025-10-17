# Haasib Architecture Constitution

## Core Principles

### I. Multi-Schema Domain Separation
We keep tenant data isolated by business capability. Every table lives in a deliberate schema (auth, acct, audit, ledger, etc.) with clear ownership and RLS policies. New domain work must start by picking or defining the correct schema before writing models.

### II. Security-First Bookkeeping
All money-affecting mutations require RLS, `company_id` scoping, and audit coverage. Migrations must include positive amount checks, FK constraints, and `current_setting('app.current_company_id')` clauses.

### III. Test & Review Discipline
Database migrations and command-bus handlers ship with regression coverage (unit or feature). PRs describe backward-compatibility impacts and note any RLS or audit updates required for QA.

### IV. Observability & Traceability
Use the shared `audit_log()` helper (or the future `audit` schema entries) for financial and security events. Surface key metrics via the monitoring playbooks in `docs/monitoring/`.

## Schema Ownership & Examples

| Schema | Purpose & Typical Tables | Notes |
| --- | --- | --- |
| `auth` | Identity, companies, RBAC (`auth.users`, `auth.companies`, `auth.company_user`, modules). | Review migrations around `2025_10_05_*` for patterns. All auth tables enforce RLS keyed to `app.current_user_id`/`app.current_company_id`. |
| `acct` | Accounts receivable & customer-facing finance (`acct.customers`, `acct.invoices`, `acct.payments`, `acct.payment_allocations`, `acct.customer_contacts`, etc.). | See migrations dated `2025_10_13_*` and `2025_10_15_*`. Every table includes `company_id`, soft deletes where relevant, RLS policies, and audit triggers hooked into `audit_log()`. |
| `ledger` | Core double-entry bookkeeping (`ledger.journal_entries`, `ledger.journal_lines`, supporting templates). | Check historical migrations in `2025_10_20_*`. Posting constraints enforce balanced debits/credits. |
| `audit` | Centralized immutable logs for compliance (`audit.entries`, `audit.financial_transactions`, `audit.permission_changes`). | Schema created 2025-10-16. The `audit_log()` helper now writes to `audit.entries`. Old `auth.audit_entries` data migrated to new schema. See migrations `2025_10_16_100001*` for implementation details. |
| `ops` | Operational support data (bank imports, tax rates, scheduled jobs). | Ops tables follow the same RLS template; reference `docs/monitoring/` for expectations. |

**Implementation tip:** For concrete column layouts and policy snippets, inspect the corresponding migration files under `stack/database/migrations/`. They serve as the canonical examples for new work.

## Delivery Workflow

- Scope new features with an ADR or spec before writing migrations.
- Keep migrations additive; coordinate destructive changes with rollback scripts.
- Update the constitution and `docs/tasks.md` when introducing a new schema or cross-cutting rule.

## Governance

- This constitution is the source of truth for architectural rules. Any divergence must be documented and ratified.
- Amendments require a PR that highlights the change, updates affected documentation, and links related tasks/specs.
- Reviews must confirm schema placement, RLS/audit coverage, and adherence to the principles above.

**Version**: 1.2.0 | **Ratified**: 2025-10-15 | **Last Amended**: 2025-10-16
