# Phase 0 Research — Payment Processing · Receipt & Allocation

## Command Bus Alignment for Payments
- **Decision**: Register dedicated payment actions under `stack/modules/Accounting/Domain/Payments/Actions` and wire them into `stack/config/command-bus.php` plus module-level action registries, ensuring all HTTP/CLI/Jobs dispatch through the bus.
- **Rationale**: Constitution principle II mandates command-bus mediated writes. Current config lacks any `payment.*` entries (`stack/config/command-bus.php`), while `docs/api-allocation-guide.md` expects allocations to flow through bus-dispatched actions. Central actions also simplify audit/idempotency hooks shared across controllers and CLI.
- **Alternatives considered**: Keeping controllers/commands calling `App\Services\PaymentService` directly was rejected because it keeps duplicate logic scattered and violates the command-bus guardrail.

## Receipt Batch & Cash Application Data Model
- **Decision**: Introduce an `invoicing.payment_receipt_batches` table with aggregate fields (company, batch number, receipt_count, total_amount, status, processing timestamps) referencing individual payments by `batch_id`, plus supporting enums for batch states and reconciliation linkage to ledger journals.
- **Rationale**: Spec FR-011 requires batch processing, and no batch table exists in migrations or schema docs. Adding explicit batch storage aligns with seeder-driven volumes (`stack/database/seeders/*CompanySeeder.php`) and permits audit trails across grouped receipts.
- **Alternatives considered**: Reusing the existing `invoicing.payments` table with a JSON metadata column was rejected because it complicates querying/reporting and makes RLS enforcement harder.

## CLI ↔ GUI Parity Plan
- **Decision**: Consolidate CLI flows by replacing the legacy `Modules\Accounting\CLI\Commands\PaymentRecord` (hard-coded `acct.*` tables) with bus-backed commands mirroring GUI capabilities (`create`, `allocate`, `reverse`, `report`) and expose natural-language aliases per `docs/cli.md`.
- **Rationale**: Constitution principle III requires parity. GUI endpoints are stubbed in `stack/modules/Accounting/Http/Controllers/PaymentController.php`, and existing CLI commands in `stack/app/Console/Commands/PaymentAllocate.php` already support `--json`. Aligning both via shared actions keeps behavior consistent and preserves idempotency middleware.
- **Alternatives considered**: Maintaining separate CLI logic in `stack/app/Console/Commands` was rejected because it duplicates validation rules and blocks parity updates.

## RBAC & Permission Scope
- **Decision**: Use the Spatie permissions seeded in `stack/database/seeders/PermissionSeeder.php` (`accounting.payments.view|create|update|delete|refund`) and add `accounting.payments.allocate` and `accounting.payments.reverse` to cover allocation/reversal paths, updating role assignments accordingly.
- **Rationale**: Principle V demands explicit permissions with negative tests. Existing seeds stop at refund; allocation/reversal are required for FR-002/FR-008. Extending seeds preserves centralized permission governance.
- **Alternatives considered**: Reusing `accounting.payments.update` for allocation/reversal was rejected because it obscures audit trails and complicates least-privilege reviews.

## Performance & Scale Targets
- **Decision**: Target p95 receipt recording latency <2s and allocation completion <3s, supporting ~250 receipts/day per tenant with up to 10 allocations each; schedule batch ingestion at <=5 batches/hour with optimistic locks for concurrency.
- **Rationale**: `docs/ServiceContext-Metrics.md` sets payment processing <2s and highlights allocation success >99%. Seeders create ~70 payments/month per tenant today, but batch mode and SME growth warrant higher ceiling; designing for 250/day keeps headroom without over-optimizing.
- **Alternatives considered**: Using seeder throughput (≈70/month) as ceiling was rejected because it ignores production growth and FR-011 batch scenarios.

## Observability, Audit, & Idempotency
- **Decision**: Emit structured audit events via existing `App\Models\AuditEntry` hooks, include idempotency enforcement on every POST/command (per `docs/idempotency.md`), and add metrics counters (payment_created_total, allocation_applied_total, allocation_failure_total) exported to existing dashboards defined in `docs/ServiceContext-Monitoring.md`.
- **Rationale**: Constitution principle X requires audit + telemetry. Centralized actions make it straightforward to log context (`user_id`, `company_id`, `payment_id`) and increment counters. Idempotency middleware already exists and should be enforced for new routes.
- **Alternatives considered**: Relying solely on database triggers for audit was rejected because they cannot capture CLI invocations or detailed context metadata.

## Tenancy & RLS Guarantees
- **Decision**: Require all queries to filter by `company_id`, ensure migrations attach foreign keys to `auth.companies`, and set `app.current_company_id` through `App\Services\ContextService` for CLI/HTTP flows; include fail-first tests that simulate cross-company access denial.
- **Rationale**: Principle IV demands RLS. Existing migrations (`stack/database/migrations/2025_10_13_125002_create_payment_allocations_table.php`) already follow this pattern; ensuring new batch tables and services do the same prevents leakage.
- **Alternatives considered**: Using application-level guards without database policies was rejected; it would not satisfy RLS enforcement.

## Translation, Accessibility & PrimeVue Usage
- **Decision**: Build new payment pages with PrimeVue `DataTable`, `Calendar`, `Dialog`, and `InputNumber`, internationalize strings via `stack/resources/js/locales` following `docs/vue-i18n-implementation-guide.md`, and add ARIA labels/keyboard traps per constitution principle VI.
- **Rationale**: No dedicated payment UI exists yet (`rg "Payment" stack/resources/js/Pages`), so starting with compliant components prevents divergence and leverages inventory from `docs/primevue-inventory.md`.
- **Alternatives considered**: Custom Tailwind components were rejected for accessibility and maintenance overhead.

## Testing & Automation Strategy
- **Decision**: Author fail-first PHPUnit tests under `stack/modules/Accounting/Tests/{Unit,Feature}` for command bus actions and RLS checks, extend `tests/Console` for CLI JSON parity, and add Playwright scenarios exercising receipt creation/allocation once UI ships (placeholder spec under `tools/cli_suite.py` for CLI probes).
- **Rationale**: Principle IX mandates tests before implementation. Current module test folders are empty; filling them ensures early detection. CLI probes already exist and can be expanded.
- **Alternatives considered**: Delaying UI automation until after release was rejected because parity must be validated pre-ship.

## Documentation & Single Source Updates
- **Decision**: Cite `docs/api-allocation-guide.md` as canonical reference, update `docs/TEAM_MEMORY.md` with new allocation strategies and batch workflow notes, and link generated OpenAPI contracts under `/specs/005-payment-processing-receipt/contracts`.
- **Rationale**: Principle I requires canonical documentation. Aligning plan/tasks with existing docs prevents drift and supports quarterly compliance evidence.
- **Alternatives considered**: Treating the feature plan as the only documentation was rejected; constitution demands cross-referenced sources.
