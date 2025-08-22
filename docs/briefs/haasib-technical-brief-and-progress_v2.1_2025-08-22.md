# Haasib SaaS — Technical Brief & Progress Snapshot (v2.1)

A concise, durable brief you can hand to future-you (or a collaborator) to resume instantly.

---

## 1) Product Brief

* **Problem**: SME accounting + extensible business modules with rock‑solid data integrity and low latency.
* **Audience**: International SMEs (starting with UAE), owners, bookkeepers, and external accountants who may access multiple companies.
* **Core outcomes**: Accurate double‑entry ledger, fast UX, multi‑company access, manual payments + reconciliation, open‑ended module model.

---

## 2) Architecture & Stack

* **Pattern**: Laravel **modular monolith**.
* **Backend**: Laravel 11 (PHP 8.3), **Octane + Swoole** (WSL/Linux), Redis (cache/queues), Horizon, Sanctum.
* **Frontend**: Inertia + Vue 3 + Vite (SSR + hydration).
* **DB**: PostgreSQL 16, **module‑per‑schema** (e.g., `auth`, `ledger`, `billing`, `ops`, `crm`), UUID PKs.
* **Storage**: S3‑compatible object storage for uploads.
* **Edge**: Cloudflare (Brotli, HTTP/3, WAF, cache rules for `/build/*`).

---

## 3) Tenancy & Access Control

* **Multi‑company**: `auth.users`, `auth.companies`, **pivot** `auth.company_user(company_id, user_id, role)`; active company via session/header.
* **RLS**: Every tenant table includes `company_id`. Policies enforce `company_id = current_setting('app.current_company')::uuid`.
* **Context middleware**: `SetTenantContext` sets `app.current_company` and `app.current_user` per request (API, web, jobs, CLI).
* **RBAC**: `spatie/laravel-permission` with roles `owner|admin|accountant|member` and granular permissions (`ledger.post`, `invoice.view`, `report.export`, ...).

---

## 4) Accounting Core

* **Double‑entry ledger**: `ledger.ledger_accounts`, `ledger.journal_entries` (header), `ledger.journal_lines` (debit/credit). All financial ops **post balanced entries**.
* **Immutability**: Documents (invoices/bills/payments) are not edited in place; changes use credit notes or reversing entries; soft‑delete only with `voided_at` + reason.
* **Money math**: Minor units per currency; calculations via `brick/money`.

---

## 5) Internationalization & Currency

* **Per‑company base currency** and locale: `auth.companies.base_currency`, `language`, `locale`.
* **Rates**: `ops.currencies`, `ops.exchange_rates(base, quote, rate_ppm, as_of)`; `CurrencyService` converts safely.
* **i18n**: JSON translations; number/date/currency formatting; RTL support for Arabic; PDFs respect locale.

---

## 6) Payments & Onboarding (Manual First)

* **Tenant onboarding**: wizard collects base currency/locale, seeds chart of accounts (localized).
* **Your SaaS billing**: **manual bank/wire** subscription invoices with receipt upload + approval flow (Stripe/Paddle postponed to post‑MVP).
* **Tenant payments**: `billing.payments(...)` recorded with method/reference/attachment; **approve → post to ledger**.

---

## 7) Bank Reconciliation (No Bank APIs)

* **Statements**: `ops.bank_transactions` populated via CSV uploads.
* **Matching**: fuzzy rules by amount/date/reference; queue for unmatched; full audit log of matches/edits.

---

## 8) API Design

* **Namespace**: `/api/v1` with Sanctum auth.
* **Conventions**: UUIDs, `snake_case`, ISO‑8601 UTC, `{ data, meta, links }` envelope, filters `?filter[...]`, sorts `?sort=-created_at`.
* **Writes**: **Idempotency‑Key** required; service‑layer `DB::transaction(...)` for all monetary mutations.
* **Mobile‑friendly**: compact JSON media type, **sparse fields** (`?fields[entity]=id,name`), **delta sync** `/api/v1/sync?updated_since=...`.
* **Versioning**: path‑based; deprecation headers; 90‑day grace; structured error codes.

---

## 9) Web UI (Inertia + Vue)

* SPA‑feel via server routing; SSR where needed; tables with server filters/sorts; optimistic updates when safe.
* Company switcher in navbar; locale/currency in user/company profile.

---

## 10) Extensibility (Module System)

* **Per‑module** schema, routes, policies, migrations under `database/migrations/<module>`.
* **Module registry**: `config/modules.php` toggles features.
* **Domain events**: modules can react/extend without modifying core (e.g., Visitor Management posts fees into ledger).

---

## 11) Data Conventions

* UUID PKs; timestamps; `(company_id, created_at)` composite indexes.
* Aggressive **CHECK** constraints (amounts ≥ 0; valid statuses) and FKs.

---

## 12) Performance

* Octane + Swoole; OPcache; config/route/view caches; Redis cache/queues; avoid N+1 with eager loads; materialized views for heavy reports.

---

## 13) Security & Compliance

* HTTPS, HSTS, strict CORS for `/api`; CSRF on web; secrets in env; audit logs (append‑only, exportable per company).
* Roles/policies tests for sensitive actions; rate limits; WAF at edge.

---

## 14) Observability & Health

* Sentry for errors/perf; structured JSON logs; metrics: API p95/p99, queue depth/latency, slow queries.
* `/health`: read‑only query against `ledger` + Redis + queue checks + build SHA.

---

## 15) CI/CD

* GitHub Actions: lint (Pint), static analysis (Larastan), tests (Pest), build (Vite), migrate.
* **Pre‑deploy backup hook** (`pg_dump` + checksum). **Zero‑downtime** deploy (atomic symlink switch). Two‑phase migrations.

---

## 16) Backups & DR

* Nightly encrypted backups to object storage; **weekly restore drill** verifies RLS and **trial balance** matches.

---

## 17) Timeline (baseline)

* **Week 0**: Local env, auth, multi‑company pivot, RLS table, onboarding skeleton.
* **Week 1**: SetTenantContext, policies, ledger accounts scaffold.
* **Week 2**: Ledger journal entries/lines + balancing tests.
* **Week 3**: First business module (e.g., invoicing) web + API v1.
* **Week 4**: CLI/queues; delta sync; compact JSON; i18n surface.
* **Week 5**: Manual subscription invoices + receipt approval; reconciliation CSV flow.
* **Week 6**: Reports v1 (trial balance, aging) via materialized views.
* **Week 7**: Perf pass, metrics/alerts, backup/restore drill.
* **Week 8**: Staging soak, seed demo tenants, production cutover.
* **Weeks 9–12 (optional)**: Online payments (Stripe/Paddle), advanced reporting, localizations.

---

## 18) Definition of Done (per module)

* ✅ Schema + RLS + CHECK/FK + indexes
* ✅ Domain services with **transactions** + tests
* ✅ Web UI (Inertia/Vue) + validation + flashes
* ✅ API v1 + OpenAPI + rate limits + **Idempotency‑Key** + structured errors
* ✅ CLI/jobs set `app.current_company` before DB access
* ✅ RBAC policies + tests; audit trail for create/update/void
* ✅ Caching/invalidations; reporting views refreshed
* ✅ Health/metrics cover module; backup includes new tables

---

# Progress Snapshot (to date)

**Environment & tooling**

* WSL Ubuntu 24.04 under VS Code; installed PHP 8.3 toolchain, Node 20, Redis, PostgreSQL 16.
* Installed Swoole via PECL (no ZTS/threads), enabled for CLI; Octane will use Swoole.

**Laravel app**

* Created Laravel 11 project; generated app key; installed Breeze (Inertia + Vue + SSR + TS).
* Installed core packages: Sanctum, Octane, Horizon, spatie/permission, spatie/activitylog, brick/money.
* Installed dev tools: Pint, Pest (+ Laravel plugin), Larastan, Telescope, Scribe.

**Database**

* Created role `superadmin` and DB `acctdb`.
* Updated `pg_hba.conf` to **scram‑sha‑256** for `127.0.0.1/32` and `::1/128`; reloaded Postgres.
* Set and tested password auth over TCP; updated `.env` to use pgsql with `acctdb`.
* Ran default migrations successfully (users/cache/jobs tables created).

**Gotchas resolved**

* Avoided accidental SQLite path by updating `.env` and removing `database.sqlite`.
* Resolved `peer`/password auth mismatch; normalized CRLF/LF plan noted to avoid Windows/WSL line‑ending noise.

**Next short steps**

* Add `auth` and `ledger` schemas/migrations; implement `SetTenantContext` and per‑request `TransactionPerRequest` middleware.
* Seed roles/permissions; scaffold company switcher; create `ledger` balancing tests.
* Create docs under `/docs` (ADR, API, DB, runbooks) using the templates from the Kickstart plan.

This brief is intended to be the single source of truth for resuming work or onboarding a collaborator.

---

## 19) Module‑by‑Module Development Plan

**Standard loop for every module**

1. **Data model**: schema-qualified tables with `company_id`, UUID PKs, CHECK/FK constraints, indexes.
2. **RLS**: enable RLS + policy using `current_setting('app.current_company', true)`; add RLS tests.
3. **Domain services**: business logic wrapped in `DB::transaction(...)` where needed; events emitted.
4. **Web UI (Inertia/Vue)**: pages, forms, server validation, optimistic updates where safe.
5. **API v1**: controllers + Resources, filters/sort/pagination, Idempotency‑Key on writes.
6. **CLI/Jobs**: commands and queued jobs accept `--company` and set tenant context.
7. **RBAC & policies**: roles/permissions mapped; policy tests for sensitive actions.
8. **Caching**: tag caches, invalidation on writes; background refresh if applicable.
9. **Reporting hooks**: materialized view refresh or summary tables.
10. **Definition of Done**: health/metrics, audit trail, OpenAPI, rate limits, backup inclusions.

---

## 20) Module Task Backlog (v1 scope)

> Track these as GitHub issues. Each task is a module with its own sub‑issues following the loop above.

1. [ ] **Foundations: Auth + Multi‑company + RBAC**
   *Tables*: `auth.users`, `auth.companies`, `auth.company_user`
   *Deliverables*: company switcher, roles (`owner|admin|accountant|member`), policies, seeders, RLS smoke test.

2. [ ] **Ledger Core**
   *Tables*: `ledger.ledger_accounts`, `ledger.journal_entries`, `ledger.journal_lines`
   *Deliverables*: posting service with balance enforcement, reversing entries, credit notes, unit tests.

3. [ ] **Invoicing (Sales)**
   *Tables*: `billing.invoices`, `billing.invoice_items`
   *Deliverables*: CRUD, statuses, posting to ledger, PDF, API v1, OpenAPI docs.

4. [ ] **Payments (Manual Receipts)**
   *Tables*: `billing.payments`
   *Deliverables*: receipt upload, approval workflow, ledger posting, idempotent API writes.

5. [ ] **Bank Reconciliation (CSV)**
   *Tables*: `ops.bank_accounts`, `ops.bank_transactions`
   *Deliverables*: CSV import, fuzzy matching, unmatched queue, audits, reconciliation report.

6. [ ] **Taxes (Calculator + PK/AE presets)**
   *Tables*: `ops.tax_rates`
   *Deliverables*: `TaxCalculator` interface, `PakistanGSTCalculator`, `UAE-VAT` preset, posting of tax liabilities.

7. [ ] **Reporting v1**
   *Views/Tables*: `trial_balance_mv`, `aging_report_mv`, summary tables
   *Deliverables*: P\&L (basic), Balance Sheet (basic), Aging, materialized view refresh hooks.

8. [ ] **API v1 & Mobile Sync**
   *Deliverables*: compact media type, sparse fields, `/sync?updated_since=...`, rate limits, structured error codes.

9. [ ] **Internationalization & Localization**
   *Deliverables*: per‑company `base_currency|language|locale`, RTL support, localized PDFs, date/number formats.

10. [ ] **Module Registry & Extensibility**
    *Deliverables*: `config/modules.php`, domain events, sample **Visitor Management** stub posting a fee to ledger.

11. [ ] **Observability & Health**
    *Deliverables*: Sentry, p95/p99, queue depth alerts, slow query logs, `/health` with ledger read + Redis + build SHA.

12. [ ] **Backups & DR Automation**
    *Deliverables*: nightly encrypted `pg_dump`, checksum upload, weekly restore drill script with trial balance verification.

13. [ ] **Onboarding Wizard & SaaS Subscription (Manual)**
    *Deliverables*: company creation wizard, COA seed, manual subscription invoice + receipt approval, activation job.

---

### Optional Post‑MVP Modules (Weeks 9–12)

* [ ] **Online Payments** (Stripe/Paddle driver layer)
* [ ] **Advanced Reporting** (cash flow, custom KPIs, dashboards)
* [ ] **Importers** (QuickBooks/Xero CSV, opening balances, contacts)
* [ ] **Notifications** (email templates, async digest, webhooks)

---

### Quick Totals

* **Modules in v1**: 13
* **Post‑MVP candidates**: 4
  Document sub‑tasks per module using the standard loop to keep scope tight.
