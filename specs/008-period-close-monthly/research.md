# Phase 0 Research — Period Close (Monthly)

## Monthly Close Performance Targets
- Decision: Keep p95 latency for close validation steps (trial balance, open-item checks) under 2 seconds and complete the end-to-end close or reopen workflow within 5 minutes for periods containing up to 10k journal lines.
- Rationale: `docs/ServiceContext-Metrics.md` sets sub-2s expectations for accounting-critical requests, and `docs/briefs/target-mvp.md` lists period close as a core flow that must keep pace with invoice/payment throughput; anchoring on 10k lines matches Ledger load assumptions already in `Modules\Ledger\Services\LedgerService.php`.
- Alternatives considered: Allowing 3–5 second validation latency (creates sluggish UX and risks timeouts for checklist gating) or optimizing for extreme >50k line periods (adds premature parallelization complexity).

## Operational Scale & Scope
- Decision: Target controllers and senior accountants with `accounting_periods.close` permission, supporting 50 concurrent tenant companies, 60 historical periods per tenant, and multi-user review per period.
- Rationale: Permission seeds in `stack/database/seeders/CompanyPermissionsSeeder.php` already carve out close capability; `docs/briefs/target-mvp.md` emphasizes multi-company readiness with period close included in core flows, while 60 periods covers five fiscal years of monthlies plus adjustments.
- Alternatives considered: Broadening access to all accounting users (weakens segregation of duties) or trimming history to 24 periods (breaks audit retention requirements noted in `docs/briefs/story-of-accounting.md`).

## Laravel Domain & Transactions Pattern
- Decision: Implement a dedicated `Modules\Ledger\Services\PeriodCloseService` that wraps closing logic in database transactions, reuses `audit_log()` hooks, and coordinates with existing `LedgerService` balance checks before mutating `acct.accounting_periods`.
- Rationale: `Modules\Ledger\Services\LedgerService.php` demonstrates transaction-scoped validation and audit logging; extending the module keeps ledger invariants centralized and honors constitution mandates for security-first bookkeeping.
- Alternatives considered: Embedding closing logic directly in controllers (risks bypassing transactions/audit) or creating a separate Artisan-only flow (blocks UI parity and Inertia integration).

## Command Bus Registration Strategy
- Decision: Expose `period-close.*` actions through the command bus (e.g., `period-close.validate`, `period-close.lock`, `period-close.reopen`) and register them in `stack/config/command-bus.php` alongside journal actions.
- Rationale: Existing entries for `journal.*` actions in `stack/config/command-bus.php` show the precedent for ledger workflows; command bus middleware already enforces idempotency and authorization, satisfying consistency and audit gates.
- Alternatives considered: Calling services directly from controllers (skips middleware/audit) or orchestrating via queued jobs without synchronous feedback (slows user confirmation during close).

## PostgreSQL Schema Strategy
- Decision: Store status, audit, and checklist metadata in `ledger.period_closes` referencing `acct.accounting_periods`, while continuing to use `acct.*` tables for journal data; enforce RLS via `company_id` and add closing constraints by trigger.
- Rationale: Migrations such as `stack/modules/Accounting/Database/Migrations/2025_10_05_130002_create_fiscal_years_and_periods.php` already model periods with RLS and status hooks; introducing a ledger-side table keeps close metadata alongside other ledger governance while respecting multi-schema separation.
- Alternatives considered: Extending `acct.accounting_periods` with numerous new columns (risks cross-module coupling) or storing close state in application caches (breaks auditability).

## Spatie Permission Enforcement
- Decision: Introduce granular abilities `period-close.view`, `period-close.close`, and `period-close.reopen`, mapped to controllers and command bus middleware via Spatie Permission.
- Rationale: Seeder entries in `stack/database/seeders/CompanyPermissionsSeeder.php` show modular permission patterns; mirroring that keeps compliance with constitution rule II (security-first bookkeeping) by scoping close authority.
- Alternatives considered: Reusing the broader `accounting_periods.update` permission (too permissive) or introducing ad-hoc role checks in controllers (duplicates Spatie logic and complicates audits).

## Inertia.js & PrimeVue UI Pattern
- Decision: Build the monthly close dashboard as Inertia pages under `stack/resources/js/Pages/Ledger/PeriodClose`, using PrimeVue `Steps`, `DataTable`, `Dialog`, and `Message` components following patterns seen in `stack/resources/js/Pages/Invoicing/InvoiceList.vue`.
- Rationale: Existing pages use composition API, PrimeVue tables, and Inertia routing for async refresh; reusing these patterns ensures consistent UX and leverages the established component inventory (`docs/primevue-inventory.md`).
- Alternatives considered: Building a standalone Vue router module (breaks Inertia SPA cohesion) or crafting plain HTML/Tailwind tables without PrimeVue (loses accessibility and built-in async features).

## Tailwind Layout Guidance
- Decision: Apply Tailwind utility classes for responsive grid layout, progress banners, and status chips while leaving complex interactivity to PrimeVue components.
- Rationale: `docs/development-guide.md` and existing Inertia views mix Tailwind spacing (`flex`, `gap`, `grid`) with PrimeVue widgets; continuing this keeps design tokens consistent and simplifies theming via `docs/primevue-theming.md`.
- Alternatives considered: Embedding custom CSS modules (adds maintenance overhead) or over-relying on PrimeVue layout components (harder to match Haasib design system spacing).

## Ledger Service Extension
- Decision: Add domain methods for `prepareClose`, `finalizeClose`, and `reopen` inside the Ledger module, coordinating with new command-bus actions and deferring adjusting JE generation to existing `LedgerService`.
- Rationale: Ledger services already encapsulate balance validation and AuditLogging (see `Modules\Ledger\Services\LedgerService.php`), so extending the module maintains single responsibility and reuse of audit helpers.
- Alternatives considered: Spreading logic across Accounting services (dilutes ledger ownership) or creating static helper classes (loses dependency injection and service context support).

## Ledger ↔ Accounting Integration Pattern
- Decision: Query outstanding AR/AP items and unposted journals through existing Accounting services (e.g., `Modules\Accounting\Domain\Payments\Services\PaymentQueryService`) and expose results to the close checklist via domain adapters.
- Rationale: Accounting services already respect schema boundaries and company scoping; leveraging them avoids duplicating SQL and keeps close validations aligned with payment, invoice, and customer domains.
- Alternatives considered: Directly querying tables from the close service (risks bypassing future caching and shared filters) or forcing manual checklist input (reduces automation benefits).

## Audit Logging & Observability
- Decision: Emit `ledger.period.close.started|completed|reopened` events through `audit_log()` with payloads capturing period ID, user, outstanding exceptions, and timestamp.
- Rationale: Constitution principle IV requires traceability, and existing audit logging hooks in `Modules\Accounting\Domain\Customers\Actions\CreateCustomerContactAction.php` show how domain events leverage `audit_log()`.
- Alternatives considered: Relying solely on database timestamps (insufficient for forensic detail) or emitting generic `system.event` logs (too coarse for compliance).

## RLS & Company Scoping
- Decision: Ensure all close mutations run with `current_setting('app.current_company_id')` set, and add RLS policies to any new tables mirroring the pattern in `2025_10_11_110306_enhance_company_rls_policies.php`.
- Rationale: Constitution rule II mandates RLS for money-affecting data; the referenced migration demonstrates how Haasib layers RLS and policies on `acct.accounting_periods`.
- Alternatives considered: Handling scoping in application code alone (fragile and non-compliant) or granting superuser bypass for close operations (breaks tenancy isolation).
