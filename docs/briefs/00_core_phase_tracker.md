# Core Phase Database Tables Tracker

**Created:** 2025-09-20
**Module:** Core Foundations (Tenancy, References, FX) — underpinning Ledger Core
**Schema File:** `docs/schemas/00_core.sql`
**Status:** Development Phase

## Tables to Create for Core Phase

### Core Reference Tables
- [x] `currencies` — Master currency catalog (code, name, symbol, decimals)
- [x] `countries` — ISO-3166 countries (alpha-2, alpha-3, metadata)

### Tenancy & Users
- [x] `auth.companies` — Tenant companies and org profile (managed by app migrations)
- [x] `user_accounts` — Per-company users (public schema)

### Finance Support
- [x] `exchange_rates` — Daily FX rates (base_id, target_id, rate, effective_date)

### Indexes (non-concurrent in SQL file)
- [x] `idx_countries_alpha3` on `countries(alpha3)`
- [x] `idx_rates_pair_date` on `(base_currency_id, target_currency_id, effective_date)`

## Dependencies (Must exist first)

- [x] PostgreSQL 16+ with `plpgsql` enabled
- [x] Ability to create schemas and run transactions
- [ ] Application GUCs for tenancy (e.g., `app.current_company`) set by middleware
- [ ] Seed data for currencies and countries

## Implementation Notes

### Key Features to Implement
1. **Multi-company tenants** — `auth.companies` as system of record; `user_accounts.company_id` links users to a company.
2. **Role & permissions seed** — bootstrap roles (`owner|admin|accountant|member`) and default permissions structure using Spatie's laravel-permission package with dedicated tables (`permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`) and team-aware scoping for company-level permissions.
3. **Currency catalog + formatting** — `currencies` drives money precision and display; integrate with Money library.
4. **Exchange rates** — `exchange_rates` used by services; unique per `(base, target, effective_date)`; source tagging and audit columns present.
5. **Audit trail hooks** — `created_by/updated_by` FKs and timestamps present across tables; wire from authenticated user.
6. **Soft deletes (where applicable)** — `companies.deleted_at`, `user_accounts.deleted_at` support recoverability.

### Row Level Security (RLS) Strategy
- Apply RLS to tenant-scoped module tables (e.g., `acct.*`, `billing.*`, `crm.*`).
- For reference tables in public schema, rely on app policies; RLS is applied on tenant-scoped module tables.

### Foreign Key Relationships
- `user_accounts.company_id` → `auth.companies.id`
- `exchange_rates.base_currency_id` → `currencies.id`
- `exchange_rates.target_currency_id` → `currencies.id`

### Business Logic (Core)
- Company lifecycle: create → configure (currency/locale/fiscal month) → activate/deactivate → delete (soft).
- Unique constraints: `companies.schema_name` unique; `user_accounts` unique per-company `(company_id, username)` and global unique `email`.
- FX rules: enforce `base != target`; positive rates; one active rate per day per pair; allow historical updates with audit.
- Timezones & locales: default to `UTC` and `en`; per-user overrides in `user_accounts`.

### RBAC Permissions (baseline)
- `core.company.view`, `core.company.create`, `core.company.update`, `core.company.deactivate`
- `core.user.invite`, `core.user.update`, `core.user.deactivate`, `core.user.reset_password`
- `core.currency.view`, `core.currency.manage`
- `core.fx.view`, `core.fx.update`, `core.fx.sync`

## Progress Tracking

### Completed
- [x] Core schema SQL authored: `docs/schemas/00_core.sql`
- [x] Tables created in SQL definition (3 tables)
- [x] Helpful indexes added
- [x] Models present: `Currency`, `Country`, `Company`, `ExchangeRate`
- [x] Currency API endpoints implemented (listing, convert, rate, history)

### In Progress / Pending
- [x] Seeders: ISO currencies, countries (implemented)
- [ ] Apply seeds to environments (`php artisan core:seed-reference`)
- [ ] Tenant context: `SetTenantContext` middleware sets `app.current_company`
- [ ] RLS: evaluate/apply to `core.user_accounts` per tenancy policy
- [x] FX sync job scaffold + console command (`fx:sync {provider}`)
- [x] FX provider integration: ECB daily rates (no key)
- [ ] Idempotency on FX rate upserts
- [ ] Basic admin UI for companies and users

### Migration Status Summary
- **Core System**: 5 tables ✅
- **Dependencies**: none (root schema) ✅
- **Indexes/Constraints**: created ✅

---

## 📋 Development Roadmap

Based on dev-plan (Architecture 1A, 4/4A) and Technical Brief (Sections 1–4, 9.1–9.2 prerequisites)

### 🎯 Phase 1: Database & Seeds
**Priority: CRITICAL** — unblock ledger and all modules

- [x] Author core reference DDL (`00_core.sql`)
- [x] Seed `currencies` (ISO 4217; decimals, symbol) — seeder added
- [x] Seed `countries` (ISO-3166-1, metadata) — seeder added
- [x] Add CLI to upsert seeds idempotently (`core:seed-reference`)
- [ ] Validate constraints with sample data (FKs, uniques, checks)

### 🎯 Phase 2: Tenancy & Context
- [ ] Implement `SetTenantContext` to set `app.current_company` per request/job
- [ ] Add per-request transaction middleware for mutating requests
- [ ] Company switcher endpoints: `GET /me/companies`, `POST /me/companies/switch`
- [ ] Policies to gate company/user management
- [ ] Optional: RLS on `core.user_accounts` using `app.current_company`

### 🎯 Phase 3: Currency & FX Services
- [ ] `CurrencyService` read-paths: catalog, symbols, formatting via Money
- [ ] `ExchangeRateService` upsert with idempotency; effective-date lookup
- [x] Scheduled sync job scaffold (`App/Jobs/SyncExchangeRates`) + manual command
- [ ] API endpoints: list currencies, latest rates, convert, rate history, upsert rate
- [ ] Caching strategy for hot FX pairs (Redis, tags)

### 🎯 Phase 4: Admin UI & Onboarding
- [ ] Company creation wizard (name, country, primary currency, fiscal month)
- [ ] Invite users; assign roles; verify email
- [ ] Basic admin pages for currencies and FX management
- [ ] Audit log surfacing for core changes

### 🎯 Phase 5: Hardening & Ops
- [ ] Pint + PHPStan passes for new code
- [ ] Sentry + health endpoint includes DB and Redis checks
- [ ] Backups include `core` data; weekly restore drill doc
- [ ] OpenAPI docs for core endpoints (Scribe/Swagger), CI publish

---

## 📊 Timeline & Milestones

### Milestone 1: Core DB + Seeds (1 week)
- ✅ `00_core.sql` authored
- ⏳ ISO currency & country seeders
- ⏳ Validation of constraints with datasets

### Milestone 2: Tenancy Context (0.5–1 week)
- ⏳ `SetTenantContext` + per-request transactions
- ⏳ Company switcher endpoints + policies

### Milestone 3: Currency & FX APIs (1 week)
- ⏳ Endpoints (rate, convert, history, latest)
- ⏳ FX sync job + provider abstraction
- ⏳ Idempotent upsert + caching

### Milestone 4: Admin UX + Docs (0.5–1 week)
- ⏳ Minimal admin pages (companies/users/FX)
- ⏳ OpenAPI docs + CI artifact

---

## ✅ Detailed Delivery Checklist (Phase Matrix)

- [x] `currencies` table with checks and timestamps
- [x] `countries` with alpha-2/alpha-3 + metadata
- [x] `exchange_rates` with pair/date unique + checks
- [x] Helpful indexes created
- [x] Seeds for currencies and countries (seeders + CLI)

### Models & Policies
- [x] Eloquent models: `Currency`, `Country`, `Company`, `ExchangeRate`
- [ ] Policies for `Company` and `User` management
- [ ] Gates for `core.currency.manage` and `core.fx.update`

### Services
- [~] `CurrencyService` (formatting, balances, conversions) — present, to be validated against schema
- [ ] `ExchangeRateService` (lookup, upsert, provider sync)
- [ ] Scheduled job + driver interface (`fixer|oxr|ecb`)

### API Layer
- [x] Currency API: list, rate, convert, history, latest, enable/disable per company
- [x] Console commands: `core:seed-reference`, `fx:sync {provider}`
- [ ] FX upsert endpoint with idempotency
- [ ] Company & user admin APIs (invite, deactivate)
- [ ] Error envelope + structured codes for core endpoints

### Security & Multi-tenant
- [ ] `SetTenantContext` GUC set for every request/job
- [ ] Optional RLS on `core.user_accounts` (policy-defined)
- [ ] Policies + Gates enforced on admin endpoints

### Tests
- [ ] Seed validation tests (counts, constraints)
- [ ] Currency formatting + conversion correctness
- [ ] FX lookup precedence (custom rate > dated rate > latest)
- [ ] Tenancy context isolation for user listing/edit

### Docs & Ops
- [ ] Admin runbook: seeding, company onboarding, FX procedures
- [ ] OpenAPI for core endpoints (`docs/openapi/core.yaml`)
- [ ] CI job to publish OpenAPI JSON artifact

---

## Notes for Ledger Core Handoff

- Ledger relies on: `auth.companies`, `currencies`, `exchange_rates`, and `user_accounts` for auditing.
- Ensure: Money precision consistent with `minor_unit`; conversion rules finalized before posting goes live.
- Next schema step: apply `docs/schemas/10_accounting.sql` for `acct.*` (periods, chart_of_accounts, transactions, journal_entries) with RLS.
