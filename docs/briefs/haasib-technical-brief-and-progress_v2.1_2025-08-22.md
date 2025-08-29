# Haasib Engineering Handbook (v3.0)

**Date:** 2025-08-23
**Owner:** banna
**Scope:** Accounting-first, multi-company SaaS with extensible business modules.
**Status:** Active build

> This is the canonical reference for what we’re building, why, how, and in what order. Keep it updated as we learn.

---

## 0) Product North Star

* **Outcome:** Reliable system of record for SMEs with fast UI, strict data integrity, and clean extensibility.
* **Initial markets:** International, starting with UAE (AED) and English/Arabic.
* **Pillars:** Double-entry accuracy, hard multi-tenancy (RLS), manual-first payments + reconciliation, module extensibility, offline-friendly API patterns.
* **Non-goals (v1):** Bank API integrations, payroll, local payment gateways, microservices.

---

## 1) Architecture Overview

* **Pattern:** Modular monolith on **Laravel 11 / PHP 8.3**.
* **Runtime:** **Octane + Swoole** (Linux/WSL), Redis (cache/queues), Horizon.
* **Frontend:** Inertia + Vue 3 + Vite (SSR + hydration).
* **Database:** PostgreSQL 16, single DB, **schema-per-module**: `auth`, `ledger`, `billing`, `ops`, `crm`, `audit`.
* **API:** `/api/v1` with **Sanctum**; idempotent writes; compact/sparse/delta endpoints.
* **Edge:** Cloudflare (Brotli, HTTP/3, WAF, cache rules for `/build/*`).
* **Storage:** S3-compatible object storage for documents and receipts.

**Why this stack**

* Laravel for velocity + ecosystem; Octane/Swoole for latency; Postgres RLS for real isolation; Inertia for SPA feel without API duplication.

---

## 2) Tenancy, Security & Access

* **Multi-company model:** `auth.users`, `auth.companies`, `auth.company_user(company_id,user_id,role)`.
* **RLS:** All tenant tables include `company_id uuid not null`. Policies match `company_id = current_setting('app.current_company', true)::uuid`.
* **Context:** `SetTenantContext` middleware sets `app.current_company` from session/header/token; jobs/CLI accept `--company` and set context.
* **RBAC:** `spatie/laravel-permission` roles `owner|admin|accountant|member`; permissions by capability (`invoice.view`, `ledger.post`, `report.export`, etc.).
* **Auth:** `auth` (web) + `auth:sanctum` (API). For API, either one token per company or header `X-Company-Id` with membership check.
* **Auditing:** Append-only audit entries for all financial mutations; export per company.

**Why**: DB-level isolation beats app-level checks; RBAC and policies gate actions; logs make accountants and auditors trust us.

---

## 3) Internationalization & Currency

* **Company profile:** `base_currency`, `language`, `locale` (e.g., `en_AE`, `ar_AE`).
* **Rates:** `ops.currencies`, `ops.exchange_rates(base, quote, rate_ppm, as_of)`; **CurrencyService** uses `brick/money` and minor units.
* **i18n:** JSON translation files, RTL toggle, localized PDFs/exports.

**Why**: International-first, any base currency per company, proper formatting, and safe arithmetic.

---

## 4) Data & API Conventions

* **IDs:** UUID (strings).
* **Columns:** `company_id`, timestamps, soft deletes where needed; composite indexes like `(company_id, created_at)`.
* **Constraints:** **CHECK** amounts ≥ 0, whitelist statuses; FKs everywhere; no orphan rows.
* **API v1:** path versioning; snake\_case; ISO-8601 UTC; envelope `{ data, meta, links }`; filters `?filter[...]`; sorting `?sort=-created_at`.
* **Writes:** `Idempotency-Key` required; service-layer `DB::transaction(...)` wraps monetary changes.
* **Mobile ergonomics:** content negotiation for compact JSON; **sparse fields** `?fields[entity]=id,name`; **delta sync** `/sync?updated_since=`.
* **Deprecation:** headers + 90-day grace; structured error codes.

**Why**: consistency, safe retried writes, and bandwidth-aware clients.

---

## 5) Coding Standards

**PHP/Laravel**

* Style: **PSR-12** via Pint.
* Static analysis: Larastan (max level feasible).
* Tests: **Pest** for unit/feature; dedicated RLS tests; balance tests for ledger.
* Services over fat controllers; thin Eloquent models; avoid hidden globals.

**Vue/TS**

* **Composition API** with `<script setup lang="ts">`.
* State minimal; prefer server-driven pagination/filtering.
* Components under `resources/js/Components`; pages under `resources/js/Pages/<Module>`.

**Commits & Branching**

* Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`...).
* Branches: `main` (prod), `develop` (staging), feature branches `feat/<module>-<slug>`.

---

## 6) Environments, CI/CD, Backups

* **Local:** WSL Ubuntu 24.04; Swoole; Postgres/Redis system services.
* **Staging/Prod regions:** ap-south-1 or ap-southeast-1 behind Cloudflare.
* **CI (GitHub Actions):** install PHP/Node, run Pint/Larastan/Pest, build assets, run migrations.
* **Deploy:** pre-deploy `pg_dump` (encrypted + checksum), two-phase migrations, zero-downtime symlink switch, Horizon supervised.
* **Backups & DR:** nightly encrypted backups, weekly restore drill verifies RLS and trial balance.

**Why**: repeatable deploys with guardrails against data loss.

---

## 7) Observability & Health

* **Errors:** Sentry (errors + traces).
* **Metrics:** API p95/p99, queue depth/latency, DB slow query log.
* **Health:** `/health` performs read-only query against `ledger`, checks Redis + queue + build SHA.

---

## 8) Module Order & Why

1. **Foundations: Auth + Multi-company + RBAC** → everything depends on it.
2. **Ledger Core** → source of truth; all money posts here.
3. **Invoicing (Sales)** → early business value, simple flow to validate posting.
4. **Payments (Manual Receipts)** → cash in, approval workflow, ledger posting.
5. **Bank Reconciliation (CSV)** → trust; match bank statements.
6. **Taxes (Calculator + presets)** → compliance basics for AE/PK.
7. **Reporting v1** → trial balance, aging, P\&L/BS.
8. **API v1 & Mobile Sync** → compact/sparse/delta.
9. **Internationalization & Localization** → polish for first international users.
10. **Module Registry & Extensibility** → enable custom vertical modules.
11. **Observability & Health** → alerts dialed-in.
12. **Backups & DR Automation** → institutionalize the weekly restore drill.
13. **Onboarding Wizard & SaaS Subscription (Manual)** → create company, COA seed, manual sub invoice + activation.

---

## 9) Detailed Module Plans (What/Why/How)

### 9.1 Foundations: Auth + Multi-company + RBAC

**What:** `auth` schema, `companies`, `company_user`, roles/permissions, company switcher.
**Why:** multi-company accountants; secure access; per-request tenant context.
**How:**

* Migrations create schema + tables; RLS on any tenant table.
* Middleware: `SetTenantContext` + `TransactionPerRequest` registered in `bootstrap/app.php`.
* Policies guard sensitive actions; seed roles/permissions.
* Endpoints: `GET /me/companies`, `POST /me/companies/switch`.
* DoD: RLS tests, policy tests, basic UI switcher.

### 9.2 Ledger Core

**What:** `ledger.ledger_accounts`, `ledger.journal_entries`, `ledger.journal_lines`; balanced posting service; reversing & credit notes.
**Why:** accounting engine; everything posts here.
**How:**

* Constraints: lines must net to zero per entry; CHECK amounts ≥ 0; FK to accounts.
* Services: `LedgerService::post($entry)` throws `UnbalancedJournal` if not balanced.
* Tests: balance enforced; RLS; performance on common queries.
* Reports base: materialized views refreshed after posting batch.

### 9.3 Invoicing (Sales)

**What:** `billing.invoices`, `billing.invoice_items`, statuses (`draft|sent|paid|void`).
**Why:** quick path to revenue and posting flows.
**How:**

* Create/Send PDF; post AR + revenue + tax lines on `paid`.
* API + UI with idempotent writes and server validation.
* DoD: OpenAPI docs; audit trail; unit + feature tests.

### 9.4 Payments (Manual Receipts)

**What:** `billing.payments` with method/reference/receipt attachment; approval posts to ledger.
**Why:** manual bank/wire first; no gateways.
**How:**

* Upload + approve workflow; idempotency to avoid duplicates.
* Reconciliation references link to bank CSV lines where matched.

### 9.5 Bank Reconciliation (CSV)

**What:** `ops.bank_accounts`, `ops.bank_transactions`; CSV import; fuzzy matching; unmatched queue; reconciliation report.
**How:**

* Matching rules: exact amount+date window; then amount+reference; manual override.
* Audit every match/unmatch.

### 9.6 Taxes

**What:** `ops.tax_rates` per company; `TaxCalculator` with `AE-VAT` and `PK-GST` presets.
**How:**

* Hook into posting pipeline; liability accounts updated on invoice/payment events.

### 9.7 Reporting v1

**What:** Materialized views: `trial_balance_mv`, `aging_report_mv`; lightweight P\&L/BS.
**How:** refresh concurrently after posting; scheduled nightly refresh; indexed columns.

### 9.8 API v1 & Mobile Sync

**What:** compact JSON, sparse fields, delta `/sync?updated_since=`.
**How:** ETags/If-None-Match for list endpoints; enforce rate limits; structured error codes.

### 9.9 Internationalization & Localization

**What:** per-company locale/currency; Arabic RTL; localized PDFs.
**How:** translation JSON; date/number via Intl; currency formatting via Money.

### 9.10 Module Registry & Extensibility

**What:** `config/modules.php`; domain events; sample Visitor module posting fees.
**How:** Service providers register routes/policies/migrations when enabled.

### 9.11 Observability & Health

**What:** Sentry, metrics dashboards, `/health` with ledger read + Redis + queue.

### 9.12 Backups & DR Automation

**What:** nightly encrypted `pg_dump` + checksum; weekly restore + trial balance verification.

### 9.13 Onboarding Wizard & SaaS Subscription (Manual)

**What:** create company, set base currency/locale, seed COA; create subscription invoice; receipt upload; activation job.

---

## 10) Tooling — Now & Later

**Now:** PHP 8.3, Composer, Node 20, Postgres 16, Redis 7, Swoole, Horizon, Sanctum, Pint, Pest, Larastan, Telescope, Scribe.
**Later (optional):** Stripe/Paddle (hosted billing), Meilisearch/Algolia (search), Laravel WebSockets (realtime), Envoy/Deployer (releases).

---

## 11) Performance Budgets

* P50 HTML render < 100 ms server time for common pages.
* P95 API endpoints < 250 ms under expected load.
* Index all list queries; no N+1; paginate everywhere.

---

## 12) Risk Register (active)

* **Tax complexity** higher than expected → mitigate with pluggable calculators and feature flags.
* **Manual payments** require strong reconciliation UX → invest in unmatched queue and audit logs.
* **i18n RTL edge cases** in PDFs → add visual tests and Arabic QA pass.

---

## 13) Engineering Log (why/what/how) — running entries

> Add a dated entry for any architectural choice, migration, or prod change.

### 2025-08-23 — Base setup & DB auth

* **Why:** needed a fast local stack with Octane/Swoole and Postgres over TCP.
* **What:** WSL Ubuntu 24.04; PHP 8.3; Node 20; Redis; Postgres 16; Swoole via PECL (no threads, no pgsql ext inside Swoole).
* **How:** installed packages; set `pg_hba.conf` localhost to `scram-sha-256`; created role `superadmin`, db `acctdb`; `.env` switched to pgsql; ran base migrations.

### 2025-08-23 — Packages & scaffolding

* **Why:** establish DX and runtime tooling.
* **What:** Sanctum, Octane, Horizon, spatie/permission, spatie/activitylog, brick/money; dev: Pint, Pest, Larastan, Telescope, Scribe.
* **How:** `composer require ...`; installed stubs; verified Octane Swoole; fixed Vite/chokidar.

### 2025-08-23 — Multi-company schema start

* **Why:** enable per-company isolation and accountant access across companies.
* **What:** `auth` schema, `auth.companies`, `auth.company_user` migrations; added schema creation prior to tables.
* **How:** `DB::statement('CREATE SCHEMA IF NOT EXISTS auth')`; created tables with UUIDs; prepared RLS policies for tenant tables.

### 2025-08-23 — Middleware bootstrapped & route groups wired
**Why:** Centralize tenancy and DB transaction handling so every request has correct tenant context and safe per-request transactions.
**What:** Registered SetTenantContext + TransactionPerRequest in bootstrap/app.php with aliases; appended both to 'web' and 'api' groups; standardized route usage (Option A); excluded /health from tenant stack.
**How:** Edited bootstrap/app.php to add $middleware->alias(...) and ->appendToGroup('web'|'api', ...); verified with `route:list` and `app('router')->getMiddlewareGroups()`; restarted Octane.


### 2025-08-24 — Foundations: Tenancy & Transactions wired
**Why:** Guarantee every request runs inside a DB transaction and sees only its company’s data.
**What:** Implemented SetTenantContext (session for web, X-Company-Id for API) with membership check and `set local app.current_company_id`; added TransactionPerRequest; created /api/v1/me/companies and /api/v1/me/companies/switch; enabled Postgres RLS function `app.company_match(company_id)` and policy template; added feature tests.
**How:** Aliased and appended middleware in bootstrap/app.php (Option A); defined controller actions; added migration for RLS function and policy scaffolding; verified via `route:list` and tests; restarted Octane.


### 2025-08-24 — Added Health & Home endpoints for Foundations
**Why:** Provide unauthenticated liveness endpoint independent of tenant stack; define a default authenticated landing route for web.
**What:** Implemented invokable HealthController (checks DB + cache, returns JSON) and HomeController (Inertia "Home" component or Blade fallback). Wired routes with Option A groups; kept /health outside tenant/txn.
**How:** Created two controllers in app/Http/Controllers; updated routes/web.php; verified with `php artisan route:list`; restarted Octane.


### 2025-08-24 — App schema created for RLS namespace
**Why:** Namespaces RLS helpers (functions, views) away from public to avoid collisions.
**What:** Created PostgreSQL schema 'app'; set owner to current role; kept function references schema-qualified.
**How:** Added migration `create_app_schema`; verified with information_schema; optional search_path set to 'public,app'.

### 2025-08-25 — Foundations milestone: auth + tenancy bootstrapped and verified
**Why:** Establish secure request context and stable developer ergonomics before Ledger features.
**What:**
- Installed Breeze (Vue + Inertia) and Sanctum; added full auth flow (/login, /register, resets).
- Created PostgreSQL schema 'app'; added RLS helper `app.company_match(company uuid)`.
- Implemented middleware: SetTenantContext (session for web, X-Company-Id for API) and TransactionPerRequest.
- Adopted Option A routing: appended tenant/txn to 'web' & 'api' groups in bootstrap/app.php.
- Added endpoints: GET /api/v1/me/companies and POST /api/v1/me/companies/switch.
- Added HealthController and HomeController; kept /health outside tenant/txn.
**Proof:**
- `php artisan test` — 27 tests passing (Auth suite, Profile suite, Tenancy: lists companies; blocks cross-company switch).
**How:**
- Migrations: create_app_schema; add RLS function migration.
- Code: middleware classes, MeController, HealthController, HomeController.
- Commands: composer require breeze --dev; breeze:install vue --ssr; composer require laravel/sanctum; migrate; npm run build; Octane restart.

### 2025-08-26 — Foundations Complete: Auth, Tenancy, RBAC, Switcher
**Why:** Lock a secure, company-scoped base so ledger features don’t devolve into permissions whack-a-mole.
**What:**
- Breeze (Vue + Inertia) auth + Sanctum; `/login` et al working.
- PostgreSQL `app` schema + RLS helper `app.company_match(company uuid)`; tenancy middleware sets `set local app.current_company_id`.
- Middleware: `SetTenantContext` (session for web, `X-Company-Id` for API) and `TransactionPerRequest`.
- Routing Option A: appended tenant/txn to `web` and `api` groups in `bootstrap/app.php`; `/health` excluded.
- Endpoints: `GET /api/v1/me/companies`, `POST /api/v1/me/companies/switch`; Inertia `HandleInertiaRequests` shares `auth.user` and `auth.companyId`.
- UI: `CompanySwitcher` in `AuthenticatedLayout` (desktop + mobile); Axios interceptor adds `X-Company-Id`.
- RBAC: pivot roles (owner/admin/accountant/viewer); gates `company.manageMembers`, `ledger.view`, `ledger.postJournal`.
**Proof:** `php artisan test` → 35 passed (85 assertions), including `InertiaShareTest`, `RbacTest`, and tenancy tests.
**How:** Migrations for `app` schema + RLS function; middleware, controller, and layout updates; Sanctum/CORS settings; asset build and Octane restart.

### 2025-08-26 — Foundations: implementation notes & testing tweaks
**Test helpers**
- `Tests\TestCase::setTenant($companyId)` for Gate checks; feature tests use `->withHeader('X-Company-Id', $id)` (API) and `->withSession(['current_company_id' => $id])` (web/Inertia).

**Middleware safety**
- `SetTenantContext` skips `/api/v1/me/companies*` and unauth routes (e.g. `/up`), and only touches session when `$request->hasSession()` to avoid “Session store not set”.

**Membership check (schema-qualified)**
- All membership lookups target `auth.company_user`. Eloquent `belongsToMany` uses `'auth.company_user'` explicitly.

**RLS mechanics**
- Per-request `set local app.current_company_id = :uuid`, guarded in `try/catch` so non-PG drivers noop.
- Policies match `company_id = current_setting('app.current_company_id', true)::uuid`.

**RBAC gates**
- `Gate::before` grants owner/admin full access.
- Explicit abilities: `company.manageMembers`, `ledger.view`, `ledger.postJournal` (owner/admin/accountant).

**Seeds for demo/user flows**
- `founder@example.com` owns **Acme**, viewer in **BetaCo**.
- Pivot roles: `owner | admin | accountant | viewer`.

**API/Web consistency**
- Axios interceptor sets `X-Company-Id`.
- Inertia share exposes `{ auth: { user, companyId } }` so SPA state and backend agree.

**Per-request transaction scope**
- `TransactionPerRequest` wraps **POST/PUT/PATCH/DELETE** only, avoiding “current transaction is aborted” noise on reads.

**Failure modes (documented)**
- Missing context ⇒ **422**; not a member ⇒ **403**. Both covered by tests.

**Caching in tests**
- `CACHE_STORE=array` in `testing` to keep cache ops from poking the DB.

**Octane note**
- Safe because `set local` is request-scoped; middleware resets GUCs so connections aren’t “haunted” across requests.

**DX footnotes**
- Local debug header `Tenant-Company` echoes resolved company.
- `php artisan app:whoami` prints `{user, company}` for CLI sanity checks.

**Next up (immediately actionable)**
- Ledger schema (accounts, journals, lines) + policies, then UI for posting/browsing entries.
- Members UI (promote/demote/remove) using `company.manageMembers`.
- Audit log on role changes and journal posts.
- Happy-path E2E (PHPUnit + Dusk or Playwright via `/api/v1` + Inertia flows).

### 2025-08-26 — Decision: build CLI envelope in Foundations (CLI-F1)
**Why:** Hybrid UX is a core differentiator; admin verbs speed setup/testing; interface now avoids rework later.
**What:** Browser + command bus/palette with unique-verb parser; commands: setup/company/users/switch/assign/unassign/bootstrap:demo; all wired to services; tenant context applied before ops; audit + idempotency + structured errors.
**Defers:** Financial/reporting commands (invoice, bill, payment, reconcile, P&L/BS) to Ledger Core (CLI-L1) once posting and ledger schemas are in place.
**References:** Plan: CLI in same codebase via command bus/palette; bus per-module in DoD; module loop adds CLI after services.


> Use this template for new entries:
>
> * **Date — Change title**
>   **Why:**
>   **What:**
>   **How:**

---

## 14) Definition of Done (module)

* Schema + RLS + CHECK/FK + indexes; services with transactions; API v1 + OpenAPI; RBAC policies + tests; audit trail; caching/invalidations; reporting refresh; health/metrics updated; backups include new tables; idempotency enforced on writes.

---

## 15) How to Contribute (internal)

* Keep PRs < 300 LOC; include tests and docs updates.
* Update this handbook with any decision that affects architecture, data, or API.

---

## 16) Open Questions

* Which first customer vertical post-MVP?
* Which reporting KPIs matter most for v1 dashboards?
* What minimum locales to ship at GA (en, ar)?

---

## 17) Quick Start Commands (local)

```bash
npm run dev &
php artisan octane:start --server=swoole --watch
```

---

## 18) Links

* Docs index: `/docs` in repo (ADR, API, DB, runbooks).
* Brief snapshot: `docs/briefs/haasib-technical-brief-and-progress_v2.1_2025-08-22.md`.
