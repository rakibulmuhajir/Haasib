# Haasib Lightbearer Playbook for AI Contributors

## 1. Purpose & Principles
- **Mission**: Rebuild the Haasib modular monolith from a clean foundation, re-introducing features intentionally with proper RBAC, tenancy, and internationalization.
- **Bias for creation**: Default to generating fresh code guided by briefs instead of patching legacy files. Use existing code only as reference material housed in `backup-2`.
- **Explicitness wins**: Every task should state inputs, desired outputs, affected layers, and acceptance tests. Ambiguity leads to misaligned generations.
- **Tenant first, ledger safe**: All work must protect multi-company isolation, double-entry integrity, and audit trails.
- **Small, verifiable steps**: Deliver features in thin vertical slices; each slice should compile, migrate, and pass targeted tests before moving on.

### Why This Product Can Win
- **Trust-first architecture**: Command-bus writes, audit logging, and strict RBAC give accountants the reliability they require before adopting new software.
- **Operator speed**: Shared CLI/palette parity turns repetitive flows into keyboard-native actions, helping SMEs close books faster than form-heavy competitors.
- **International readiness**: Multi-currency, RTL, and localization support open markets where incumbent products lag.
- **Modular extensibility**: Clean seams let you bolt on CRM, VMS, or POS modules without destabilizing the accounting core, enabling upsell paths.
- **Execution discipline**: The playbook enforces scoped briefs, tests, and documentation so the build stays aligned with real customer pain instead of pet features.

## 2. Canonical References
Consult these documents before coding:
- **Technical North Star**: `docs/briefs/haasib-technical-brief-and-progress_v2.1_2025-08-22.md`
- **Execution Roadmap**: `docs/dev-plan.md`, `docs/tasks.md`
- **RBAC Blueprint**: `docs/briefs/rbac_implementation_brief.md`, `app/docs/briefs/rbac_implementation_brief.md`
- **Service Context & Audit**: `docs/ServiceContext-Guide.md`, `app/docs/user-context-refactoring-plan.md`
- **Module Trackers**: `docs/briefs/00_core_phase_tracker.md`, `docs/briefs/01_invoicing_phase_tracker.md`, `docs/briefs/02_payments_phase_tracker.md`
- **Narrative QA**: `docs/briefs/target-mvp.md`
- **Frontend Patterns**: `docs/development-guide.md`, `docs/components/*.md`, `docs/specs/tables/*`

## 3. Operating Rhythm for Each AI Session
1. **Environment prep**
   - Confirm branch (`git status`) and working directory.
   - Run `php artisan test --testsuite=Smoke` (or agreed subset) to capture baseline failures.
   - Snapshot key config/migration files if restructuring (`cp -r app backup-2/YYYYMMDD` as needed).
2. **Task brief**
   - Draft a micro-brief containing: goal, scope, touchpoints (models/routes/views), dependencies, success criteria, rollback plan.
   - Link supporting docs so the AI can cite policies (e.g., RLS requirements, permission names).
3. **Implementation loop**
   - Generate migrations/services/controllers in one module at a time.
   - Enforce `ServiceContext` parameters, `company_id` checks, and RBAC middleware.
   - After each logical unit: run targeted tests (`php artisan test tests/Feature/...`) and lint (`./vendor/bin/pint`).
4. **Review & handoff**
   - Document changes in `docs/engineering-log.md` (create if missing) with date, summary, tests run.
   - Update relevant tracker checkboxes in `/docs/briefs/*` when milestones flip.

## 4. Baseline System Requirements
- **Architecture**: Laravel 11 + Octane (Swoole), Postgres 16 with a minimal schema set (`public`, `auth`, `hrm`, `acct` — rename `acct` to a less guessable alias before launch), Inertia + Vue 3 frontend, Sanctum API.
- **Tenancy**: Every tenant table carries `company_id UUID NOT NULL`; RLS policies compare against `current_setting('app.current_company', true)`.
- **RBAC**: `spatie/laravel-permission` with team support. Roles include `super_admin`, `systemadmin`, `owner`, `admin`, `manager`, `accountant`, `employee`, `viewer`. System roles use `team_id = null`, tenant roles scope to company UUIDs.
- **Context**: All services accept `ServiceContext` for user/company/idempotency data. Controllers derive it via `ServiceContextHelper::fromRequest($request)`.
- **Audit & Idempotency**: Financial mutations must log via `AuditLogging` trait and guard against duplicates with company-scoped idempotency keys.
- **Command Bus**: Every write path (web, CLI, future mobile) dispatches an action registered in `app/config/command-bus.php`, keeping controllers thin and reuseable. Keep schema proliferation in check while wiring new actions: default to `public` (shared references), `auth` (tenancy/security), `hrm` (people ops), and `acct` (financial ledger—give it an obfuscated alias before go-live) unless a brief explicitly approves a new schema.
- **API Contract**: `/api/v1`, snake_case payloads, envelope `{ data, meta, links }`, idempotent writes, filters DSL backed by `FilterBuilder`.
- **Internationalization**: JSON translations, locale-aware formatting, RTL-ready views, multi-currency via `brick/money` and exchange rate services.

## 5. Fresh Start Checklist (when rebuilding modules)
1. **Create staging directory**: move legacy implementations to `backup-2/<module>`; leave Laravel’s own `app/` intact for framework internals.
2. **Define PSR-4 namespace**: choose new domain root (e.g., `AppDomain\`) and update `composer.json` autoload → run `composer dump-autoload`.
3. **Bootstrap scaffolding**:
   - Folders: `domain/<Module>/Models`, `domain/<Module>/Actions`, `domain/<Module>/Services`, `domain/<Module>/Policies`.
   - Service providers to register routes, policies, migrations per module.
4. **Migration strategy**: mirror SQL from `/docs/schemas/*.sql`; limit new tables to the approved schemas (`public`, `auth`, `hrm`, obfuscated `acct`); enable schemas via `DB::statement('create schema if not exists ...')`; add RLS policies and `CHECK` constraints per brief.
5. **Testing baseline**: stub Pest tests for migrations, services, and HTTP flows before writing implementations to lock expectations.

## 6. Phase Roadmap
### Phase 0 — Core Foundations
- Recreate foundational tables in the shared schemas (`auth` + `public`) for companies, currencies, and exchange rates with seeds.
- Reinstate `SetTenantContext` middleware, transaction-per-request, health checks.
- Deliver Company + User onboarding UI with RBAC gating.

### Phase 1 — Ledger Core
- Implement ledger schemas, models, and services with balance enforcement.
- Build state machine for journal entries, queue posting jobs, audit logs.
- Integrate permission checks (`ledger.entries.*`) and ServiceContext-aware services.

### Phase 2 — Invoicing (Accounts Receivable)
- Rebuild invoice, invoice_item, invoice_item_tax tables plus status machine.
- Actions layer (`AppDomain\Invoicing\Actions\InvoiceCreate`) delegates to services.
- Expose Inertia pages using `DataTablePro` filters DSL; guard routes with `invoices.*` permissions.
- Ensure idempotent invoice creation and ledger posting sync.

### Phase 3 — Payments (AR Receipts)
- Payments + allocations migrations; `PaymentService` with currency conversion.
- Permission set `payments.view|create|allocate|refund`; enforce via middleware and policies.
- File upload hooks for receipts; audit logging for allocations and voids.

### Phase 4 — Payables (Accounts Payable)
- Build vendor, bills, bill_items, bill_payments structures as per `docs/briefs/02_payments_phase_tracker.md`.
- Command facade pattern mirrored from invoicing; include approval workflow and ledger integration.
- Prepare for recurring bills and vendor credits (flag as stretch goals).

### Phase 5 — Supporting Systems
- Bank reconciliation pipelines, tax services, reporting materialized views.
- OpenAPI specs generation, observability (p95 metrics, Sentry instrumentation), backups automation.

Progression to later phases must only start once earlier acceptance criteria and tests are green.

## 7. Feature Delivery Template
```
Feature: <short name>
Module: <e.g., Invoicing>
Intent: <business problem solved>
Artifacts: migrations | models | policies | Vue components | API endpoints
Constraints:
  - Tenancy (company_id, RLS policy name)
  - Permissions (list required slugs)
  - ServiceContext usage expectations
  - Idempotency key requirements
Acceptance:
  - Tests to add/run (unit + feature)
  - Manual QA steps (e.g., create invoice, allocate payment)
Dependencies: <linked docs/tasks>
```

### 7.1 Standard To-Do Checklist (run before and after generation)
- **Translations**: Add or update locale strings (English + Arabic baseline) in `resources/lang/{locale}/` or JSON files; ensure new copy is translatable and documented.
- **RBAC**: Define permission slugs in seeders, sync roles, add policy/gate coverage, and wire middleware or `$this->authorize()` checks; update Vue permission guards.
- **Routes & Controllers**: Register REST + API routes, apply middleware stacks, and document route names for Inertia usage.
- **Service Layer**: Implement ServiceContext-aware actions/services with audit logging and idempotency; avoid direct `auth()` calls.
- **Data Shape**: Ensure migrations include `company_id`, UUID PKs, RLS policies, CHECK constraints, and seed data when required.
- **Frontend**: Place components/pages in the correct module folder, enforce permission-driven rendering, and add i18n-ready labels/placeholders.
- **Documentation**: Update relevant briefs, trackers, and runbooks; append engineering log entry with summary + tests.
- **Regression Hooks**: Note prior features impacted and plan smoke checks (e.g., invoices still create, ledger posts remain balanced).
- **CLI & CommandBus**: Expose the same write path through the command bus—register/extend the action in `app/config/command-bus.php`, update palette metadata and parser synonyms, verify the command executes the ServiceContext-aware action, and document usage in the CLI spec.

### 7.2 Test & Verification Matrix
- **Automated Tests**
  - Unit: coverage for services, policies, value objects introduced by the feature.
  - Feature/API: happy path + forbidden path due to missing permission/company mismatch.
  - RLS: cross-company access attempts return 403/not found.
  - Frontend: component/unit tests or snapshot updates where applicable.
- **Regression Suites**
  - Re-run existing module suites (e.g., `pest --group=invoice`) and smoke tests touched by the feature.
  - Trigger integration tests for ledger posting if financial data is involved.
- **Manual QA**
  - Execute scripted steps (form submit, translation switch, permission denial) and record in `docs/manual_test.md` with timestamp.
  - Verify UI labels appear in all supported locales and permission-based controls hide/show correctly.
  - Exercise the paired CLI command (palette or terminal probe) to confirm parity with the GUI operation.

### 7.3 CommandBus + CLI Parity Expectations
- All mutating work must flow through command actions (`App\Actions\<Domain>\*`) invoked by both controllers and the CLI palette; avoid bespoke service calls in controllers.
- Register each action in `app/config/command-bus.php`, ensuring permissions map to the correct abilities (e.g., `invoice.create`, `ledger.post`).
- Update the palette registry (`resources/js/palette/entities.ts`) and freeform parser (`resources/js/palette/parser.ts`) so the new feature is discoverable and runnable via keyboard only.
- Follow the CLI functional specs (`docs/cli.md`, `docs/clie-v2.md`, `docs/briefs/CLI-target.md`) for grammar, prompts, and idempotency; surface human-readable success messages and record entries in the audit log.
- Add CLI contract tests (parser golden cases, command executor feature tests, optional Playwright probe) to keep GUI/CLI behaviour in sync.

## 8. RBAC & Permissions Guardrails
- Seed roles exactly as defined in `RbacSeeder` with deterministic UUIDs for system roles.
- Store permission slugs using `{resource}.{action}.{scope}` convention.
- Update `docs/components` and Vue layouts to hide controls based on hydrated permissions arrays.
- Add Pest tests per protected route: one allow case, one deny case, verifying HTTP 403 rather than 500.
- Log permission changes and access denials via audit logs; include user UUID and team context.

## 9. Testing & QA Cadence
- Run `./vendor/bin/pest --filter=<feature>` after each major change; add datasets to cover multi-company and locale variations.
- Execute schema-specific integration tests for RLS, ensuring cross-company access fails.
- Maintain fake data seeds referencing ISO currency and exchange rate tables to validate multi-currency math.
- Document results and outstanding failures in `docs/manual_test.md` with timestamps.

## 10. Prompt Crafting Tips for Future AI Sessions
- State the target files and line numbers when requesting edits.
- Provide diff-friendly instructions ("add method X under Y", "rename namespace A→B").
- Call out non-negotiables: `ServiceContext` usage, permission names, `company_id` assignments, auditing.
- When generating Vue code, specify component folder, composition API usage, typed props, and permission-driven rendering.
- Ask for accompanying tests and translations whenever new user-facing text or behavior appears.

## 11. Observability, Ops, and Documentation Duties
- Extend `/health` to surface DB, Redis, queue, build SHA per `docs/ServiceContext-Metrics.md` (once rebuilt).
- Refresh tracker checkboxes and note decisions in `docs/briefs/*.md` after completing tasks.
- Keep `docs/prd.md` and `docs/onboarding-checklist.md` aligned with new flows.
- Schedule weekly backup + restore simulations; record outcomes in `docs/ServiceContext-Error-Budget-Review.md` when available.

## 12. Ready-To-Ship Checklist (per module)
- Migrations applied and reversible.
- Services/controllers fully typed, return DTOs/resources, use ServiceContext only.
- Policies and routes protected with correct permissions.
- Vue pages wired with DataTablePro filters DSL where applicable.
- Feature, unit, and RLS tests green in CI.
- Docs updated (tracker, runbooks, API specs).
- Manual QA steps documented with screenshots if needed (store paths in `storage/docs` or external repo).

---

_This playbook is the guiding lantern for future AI collaborators. Follow it, keep it updated, and Haasib’s rebuild will stay deliberate, testable, and audit-ready._
