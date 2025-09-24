# Haasib — Tasks & Checklists (v1)

Single source of truth for execution. Pulled from the technical brief, schemas, and engineering log. Track progress by checking items. When in doubt, link a PR or note next action.

---

## Global Conventions

- Naming: schema-per-module (e.g., `auth`, `ledger`, `billing`, `crm`, `ops`, `audit`); tables in those schemas (`ledger.journal_entries`).
- IDs: prefer UUIDs for tenant data; natural/bigint allowed where needed (e.g., `invoices.invoice_id`).
- Tenant key: every tenant row has `company_id` (UUID). RLS policy uses `current_setting('app.current_company', true)`.
- Constraints: CHECK amounts ≥ 0; due_date ≥ invoice_date; valid status enums; FK everywhere; unique business keys per company.
- Money: calculations with `brick/money`. Storage is DECIMAL(15,2) for invoicing/payments in this codebase; document deviations.
- API: `/api/v1`, snake_case, ISO‑8601 UTC; idempotent writes; `{ data, meta, links }`; filters/sort; sparse fields and delta sync supported.
- RBAC: roles via `spatie/laravel-permission` — `owner | admin | accountant | member`.

---

## Cross‑Cutting Tasks

- [x] SetTenantContext middleware sets `app.current_company` per request and jobs
- [x] TransactionPerRequest middleware for mutating HTTP verbs
- [x] Health endpoint `/health` (DB, Redis, queue, build SHA)
- [ ] Idempotency-Key middleware/validation on money-affecting writes
- [x] Audit trail for financial mutations (writes to `audit.audit_logs`)
- [ ] OpenAPI/Scribe docs published behind auth
- [x] CI basics: lint, static analysis, tests, build
- [ ] Pre-deploy `pg_dump` backup step with checksum
- [ ] Weekly restore drill + RLS validation script
- [ ] Performance budgets enforced (P95 < 250ms APIs); slow query log monitored

---

## Module Checklists

### 1) Foundations: Auth + Multi-company + RBAC

- [x] Schemas: `auth` created; core tables `auth.companies`, `auth.company_user`
- [x] RLS helper schema `app` and policy template
- [x] Middleware: SetTenantContext wired (web + api); session and X-Company-Id support
- [x] Company switcher UI and fallback when no company
- [x] Routes: `GET /api/v1/me/companies`, `POST /api/v1/me/companies/switch`
- [x] RBAC roles and permissions seeded; policies in place
- [x] HealthController outside tenant stack
- [x] Engineering Log entries dated 2025‑08‑23 to 2025‑08‑24 confirm completion

### 2) Ledger Core

- [x] Schema `ledger` with `ledger_accounts`, `journal_entries`, `journal_lines`
- [x] RLS policies and indexes
- [x] Posting service with balance validation and `UnbalancedJournalException`
- [x] State machine for journal entries (post/void)
- [x] CRUD UI for journal entries and accounts
- [x] Audit logs on create/post/void
- [x] Tests: RLS and balance enforced
- [x] Engineering Log 2025‑09‑11 confirms completion

### 3) Invoicing (Sales)

- [x] Tables: `invoices`, `invoice_items`, constraints (due_date ≥ invoice_date, amounts ≥ 0)
- [x] Status machine `InvoiceStateMachine` (draft → sent → posted/paid → cancelled/void)
- [x] Accounts receivable summary table exists
- [x] Jobs: AR aging updates by customer/company (e.g., `UpdateForCompany`, `UpdateForCustomer`)
- [x] Events: `InvoiceSent`, `InvoicePosted`, `InvoicePaid`, `InvoiceCancelled`
- [x] Controller/UI: Invoices listing with DataTablePro + Filters DSL and chips
- [ ] PDF generation and send email flow
- [ ] OpenAPI docs for invoice endpoints

### 4) Payments (AR Receipts)

- [x] Tables: `payments`, `payment_allocations` (+ soft deletes)
- [x] Controller: `PaymentController` with store/update/allocate/refund requests
- [x] Validation requests: `StorePaymentRequest`, `UpdatePaymentRequest`, `AllocatePaymentRequest`, `RefundPaymentRequest`
- [x] DataTablePro listing with chips and fixed date handling
- [x] Approval/posting workflow integrated with ledger
- [ ] Receipt upload and attachment storage
- [ ] Reconciliation reference to bank transactions (when available)

### 5) Payables (AP)

- [ ] Tables: `vendors`, `bills`, `bill_items`, `bill_payments`
- [ ] State machine `BillStateMachine` (draft → received → approved → paid → void)
- [ ] Accounts payable summary table exists
- [ ] Jobs: AP aging updates by vendor/company
- [ ] Events: `BillApproved`, `BillPaid`, `BillVoided`
- [ ] Controller/UI: Bills listing with DataTablePro + Filters DSL

### 6) Bank Reconciliation (CSV)

- [ ] Schema: `ops.bank_accounts`, `ops.bank_transactions`, `ops.bank_reconciliations`
- [ ] CSV import pipeline with validation
- [ ] Matching rules (exact amount+date; then amount+reference; manual override)
- [ ] Audit every match/unmatch; reconciliation report

### 7) Taxes

- [ ] Tables: `ops.tax_rates` scoped per company
- [ ] TaxCalculator service (AE‑VAT, PK‑GST presets)
- [ ] Hooks into invoice/payment posting
- [ ] Ledger postings for tax liability and adjustments

### 8) Reporting v1

- [ ] Materialized views `trial_balance_mv`, `aging_report_mv`
- [x] Application summary tables (e.g., `accounts_receivable`) refreshed by command (`ar:update-aging`)
- [ ] Scheduled refresh and report endpoints

### 9) API v1 & Mobile Sync

- [x] Route namespace `/api/v1` with Sanctum auth
- [ ] Sparse fieldsets `?fields[entity]=...` across list endpoints
- [ ] ETag/If‑None‑Match on list endpoints
- [ ] Delta sync `/sync?updated_since=` endpoint for mobile
- [ ] Rate limiting buckets and structured error codes
- [ ] Scribe/OpenAPI docs published

### 10) Internationalization & Localization

- [ ] Company locale/currency preferences applied
- [ ] RTL support for Arabic; localized PDFs/exports
- [ ] Translation JSONs and date/number via Intl

### 11) Module Registry & Extensibility

- [ ] `config/modules.php` or registry
- [ ] Service providers register routes/policies/migrations per module
- [ ] Sample Visitor module posting fees to ledger

### 12) Observability & Health

- [x] Sentry integrated for errors and traces
- [ ] Metrics: p95/p99, queue depth/latency, DB slow query dashboard
- [ ] `/health` extended with version/build and dependency checks

### 13) Backups & DR Automation

- [ ] Nightly encrypted `pg_dump` with retention policy
- [ ] Weekly restore drill; automated RLS isolation validation
- [ ] Trial balance verification post-restore

### 14) Onboarding Wizard & SaaS Subscription (Manual)

- [ ] Create company wizard (base currency/locale)
- [ ] Seed COA
- [ ] Create subscription invoice; receipt upload; activation job

---

## Frontend DataTablePro + Filters DSL Adoption

- [x] Payments: migrated; date off‑by‑one fixed (local YYYY‑MM‑DD)
- [x] Invoices: column‑menu filters + chips; `FilterBuilder` backend
- [x] Customers: advanced filters enabled; chips added
- [ ] Remaining tables: opt‑in only where needed

---

## References

- Brief: `docs/briefs/haasib-technical-brief-and-progress_v2.1_2025-08-22.md`
- Schemas: `docs/schemas/*.sql`, `docs/schema-v2.sql`
- Engineering Log: Section 13 in the brief (dated entries used to mark [x])
