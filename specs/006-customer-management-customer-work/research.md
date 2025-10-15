# Research — Customer Management Lifecycle

## Technical Approach & Directory Discipline
Decision: Centralize customer lifecycle logic under `stack/modules/Accounting/Domain/Customers` with shared services and command actions, expose HTTP endpoints through thin controllers in `stack/app/Http/Controllers/Invoicing`, and deliver UI via new Inertia pages under `stack/resources/js/Pages/Accounting/Customers` using existing `Components/Layout/LayoutShell.vue`.
Rationale: Keeps customer processes within the Accounting module (matching `config/modules.php`), satisfies directory discipline guardrails, and allows shared orchestration (command bus, CLI) without duplicating core controllers.
Alternatives considered: Leaving lifecycle code inside `App\Http\Controllers` with direct Eloquent calls (breaks module governance and command-bus supremacy); creating a standalone Customers module (would fragment Accounting and complicate feature toggles).

## Canonical Data Source & Schema Alignment
Decision: Make `invoicing.customers` the canonical table for customer records, update `App\Models\Customer` (and referencing queries) to target this schema, and phase out `hrm.customers` references.
Rationale: Migration `2025_10_12_081945_create_customers_table.php` provisions `invoicing.customers` with RLS and credit/payment columns; continuing to reference the non-existent `hrm` table yields runtime failures and blocks tenancy safety.
Alternatives considered: Re-creating `hrm.customers` to match the model (adds duplicate data/RLS policies); introducing a new schema (adds unnecessary complexity and violates single-source doctrine).

## Supporting Entities & Storage Expansion
Decision: Add normalized tables `invoicing.customer_contacts`, `invoicing.customer_addresses`, `invoicing.customer_credit_limits` (history + approvals), and `invoicing.customer_statements` (snapshot metadata) with foreign keys to `invoicing.customers`; maintain JSON-free models for auditability.
Rationale: Requirements call for multiple contacts, address variants, credit enforcement, and statement tracking; normalized tables simplify RBAC, auditing, and reporting while fitting existing Postgres schema practices.
Alternatives considered: Embedding contacts/addresses as JSON columns on `customers` (hurts queryability and RLS enforcement); placing them in `app` schema (breaks module cohesion and tenancy policies).

## Aging & Statement Services
Decision: Introduce `CustomerStatementService` and `CustomerAgingService` under `Modules\Accounting\Domain\Customers\Services`, reusing `App\Services\BalanceTrackingService` query patterns, and back scheduled updates via a queued job dispatched from a new `ar:update-aging` artisan command.
Rationale: Dedicated services encapsulate cross-cutting calculations (aging buckets, balance rollups) while leveraging existing balance tracking logic; scheduling through a command keeps parity with the bootstrap schedule hook.
Alternatives considered: Computing statements inline per request (risks blocking UI with heavy joins); repurposing `PaymentAllocationService` (focused on payment allocations, not lifecycle summaries).

## Command Bus & CLI Parity
Decision: Define customer actions (`customer.create`, `customer.update`, `customer.deactivate`, `customer.contact.add`, `customer.credit.adjust`, `customer.statement.generate`, etc.) under `Modules\Accounting\Domain\Customers\Actions`, register them through the module provider and `stack/config/command-bus.php`, and ship matching artisan commands under `Modules\Accounting\CLI\Commands`.
Rationale: Aligns with Constitution Command-Bus Supremacy, keeps HTTP/CLI/Palette in sync, and reuses the proven payment action pattern (`Modules\Accounting\Domain\Payments\Actions\registry.php`).
Alternatives considered: Invoking Eloquent directly from controllers/CLI (breaks parity and audit hooks); reusing placeholder `App\Actions\DevOps\Customer*` classes (they do not exist and would sit outside module boundaries).

## RBAC & Permissions
Decision: Extend Spatie permissions with granular entries: `accounting.customers.manage_contacts`, `accounting.customers.manage_credit`, `accounting.customers.generate_statements`, and `accounting.customers.export`; map them to accountant/owner roles in `PermissionSeeder` and surface checks in controllers and CLI.
Rationale: Existing permissions only cover CRUD; new flows (contacts, credit, statements, exports) need explicit gating to satisfy RBAC Integrity and negative-test expectations.
Alternatives considered: Reusing `accounting.customers.update` for all operations (no differentiation, harder to audit); introducing generic `accounting.advanced` permissions (overly broad).

## Tenancy & RLS Safety
Decision: Mirror the RLS pattern used for `invoicing.customers` on all new tables (contacts, addresses, credit limits, statements) using `company_id` filters tied to `current_setting('app.current_company_id')`; update onboarding scripts to seed baseline contacts/addresses when demo data is generated.
Rationale: Maintains multi-tenant isolation and satisfies guardrails; onboarding consistency ensures demo tenants remain functional without manual setup.
Alternatives considered: Enforcing tenancy in application code only (risk of leaks); sharing tables across companies without `company_id` (blocks RLS).

## UI Components & PrimeVue Compliance
Decision: Build customer screens with PrimeVue components already inventoried in `docs/primevue-inventory.md`—`DataTable` for listings, `Dialog` + `DynamicDialog` for contacts/credit modals, `Tabs` for overview/aging/communication, `Accordion` or `Timeline` for history—and apply Tailwind for layout only.
Rationale: Matches PrimeVue-first mandate and leverages components proven in `Pages/Accounting/Payments/*`; keeps styling consistent with existing theme tokens.
Alternatives considered: Adding third-party grids (violates Active Technologies guardrail); hand-rolling tables with Tailwind (loss of accessibility and filtering features).

## Translation & Accessibility Strategy
Decision: Follow `docs/vue-i18n-implementation-guide.md` to place copy under `stack/resources/js/locales/en-US/customers.json`, wire components with `useI18n`, and ensure keyboard/ARIA coverage for dialogs, data tables, and action menus (e.g., focus traps, `aria-label` on buttons).
Rationale: Satisfies Constitution principle VI and existing localization architecture; ensures customer management is accessible and translatable from day one.
Alternatives considered: Deferring i18n setup (introduces backlog debt and fails parity); embedding hard-coded strings (blocks future locales).

## Testing Plan (Fail-First)
Decision: Author red-first PHPUnit feature tests for command bus actions (create/update/deactivate, credit limit enforcement, statement generation), module unit tests for services, CLI probe tests for artisan commands, and Playwright specs validating UI flows and keyboard navigation.
Rationale: Addresses Constitution principle IX and keeps parity with payment feature coverage; fail-first ensures regressions are caught before implementation.
Alternatives considered: Manual QA only (insufficient evidence); relying on a single integration test (limited feedback).

## Audit, Idempotency & Observability
Decision: Emit structured audit events (`customer.created`, `customer.updated`, `customer.credit_limit.changed`, `customer.statement.generated`) via `App\Models\AuditEntry`, require idempotency keys for imports/CLI bulk operations, and add metrics counters (e.g., `customer_created_total`, `customer_credit_breach_total`) to the observability pipeline.
Rationale: Aligns with Constitution principle X and mirrors the payment processing telemetry approach documented in `docs/TEAM_MEMORY.md`.
Alternatives considered: Logging ad-hoc messages (hard to trace); skipping metrics (no visibility into adoption or limits enforcement).

## Performance & Scale Targets
Decision: Target p95 <1.2s for customer list retrieval at 5k records/tenant with filters applied, <2.0s for statement generation covering up to 12 months of invoices, and <1.0s for credit limit enforcement checks during invoice creation; support concurrency of 10 parallel accountants per tenant.
Rationale: Provides measurable goals tied to SME workloads and ensures database indexes (customer number, email, status) are justified.
Alternatives considered: Deferring performance targets (violates guardrail expectations); adopting enterprise-scale metrics (>50k customers) (overbuild for MVP scope).

## Async Processing & Schedulers
Decision: Implement `ar:update-aging` as a real artisan command under `Modules\Accounting\CLI\Commands`, dispatching queued jobs to refresh `customer_aging_snapshots` nightly and on-demand when statements are generated.
Rationale: The schedule hook (`bootstrap/app.php`) already references this command; wiring it prevents silent failures and keeps aging data fresh without blocking UI.
Alternatives considered: Polling aging data on each request (unbounded latency); removing the schedule (loses automated updates).

## Dependency Best Practices
Decision: 
- PrimeVue & Tailwind: Mirror component import patterns from `Pages/Accounting/Payments`, referencing `docs/primevue-inventory.md` before introducing new widgets.
- Spatie Permission: Use guard `web`, seed via `PermissionSeeder`, and reflect new permissions in `CompanyDemoSeeder` role assignments.
- Command Bus: Extend module `registry.php` pattern and keep `config/command-bus.php` as a thin alias map to module actions.
- PaymentAllocationService: Do not reuse for statements; instead, expose customer allocation summaries via dedicated query service to avoid unintended side effects.
Rationale: Ensures each dependency is applied consistently with existing code and avoids cross-cutting coupling that would be hard to maintain.
Alternatives considered: Importing components ad-hoc (risk mismatched versions); sharing payment allocation logic for statements (mixes responsibilities and can mutate payment state).

## Documentation & Compliance Evidence
Decision: Update `docs/TEAM_MEMORY.md` with customer lifecycle decisions post-implementation, add a feature quickstart at `specs/006-customer-management-customer-work/quickstart.md`, and record compliance evidence (RBAC mapping, audit events, performance baselines) in a new appendix under `docs/PAYMENT-BATCH-COMPLIANCE-EVIDENCE.md` or a sibling `CUSTOMER-LIFECYCLE-COMPLIANCE.md`.
Rationale: Keeps canonical docs aligned (Single Source Doctrine) and satisfies compliance evidence retention expectations highlighted by existing payment feature documentation.
Alternatives considered: Leaving docs untouched (knowledge drift); scattering notes across ad-hoc files (hard to audit).
