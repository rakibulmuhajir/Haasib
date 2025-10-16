# Team Memory — Working Principles (MVP Phase)

Last updated: 2025-10-14

These notes capture persistent decisions and constraints to keep delivery fast and consistent.

- MVP Priority: Deliver the MVP ASAP. Optimize for speed over breadth; defer nice-to-haves.
- PrimeVue First: Use PrimeVue as the single Vue UI/components library for all new and refactored UI. Avoid mixing multiple UI libraries; exceptions require an explicit note in this file.
- CLI Parity: Every guided palette feature must have a freeform counterpart (parseable via the input) with sensible synonyms/flags.

Context & Authority
- Solo Owner/PM: This project has a single owner who is both the lead developer and the project manager. The company relies on this role for delivery and technical direction.
- CEO Mandate: The CEO has expressed high confidence and granted full responsibility and decision authority to develop the SME accounting app (Laravel + Vue + Inertia).

Principles
- Don’t Reinvent the Wheel: Prefer proven, well-maintained libraries and patterns over bespoke implementations when quality is comparable.
- DRY: Centralize shared logic and UI patterns; compose rather than duplicate.

Frontend Library Decision
- Chosen Library: PrimeVue (deep expertise; “inside out” familiarity). Suitable for data-heavy enterprise UIs with robust DataTable/TreeTable, form controls, overlays, and accessibility.
- Theming: Use PrimeVue themes/presets and tokens; pair with Tailwind for layout/spacing utilities. Avoid re-styling components from scratch.
- Usage Rule: New UI should leverage PrimeVue components. If a needed primitive is missing, favor small, headless utilities with Tailwind, and document the exception here.

Migration SOP
- Plan: See `docs/mig-to-prime.md` for the end-to-end PrimeVue migration order (root → leaves), acceptance criteria per phase, and verification checklist.
- Dark Mode: Use PrimeVue's built-in dark mode theming system. Configure through PrimeVue theme configuration rather than Tailwind dark classes.

PrimeVue Guardrails
- Local Docs: Use `docs/primevue-inventory.md` and `stack/node_modules/primevue/README.md` as the API source of truth for v4.3.9.
- Before Changes: For any new PrimeVue component/service/directive:
 1) Verify the import path exists in `stack/node_modules/primevue/*`.
  2) If service/composable, confirm exported name (e.g., `confirmationservice`, `usetoast`).
  3) Add a minimal usage snippet in the PR description to lock in the API.
- Disallowed: Do not use undocumented paths (e.g., `confirmdialogservice`); grep the inventory first.
- Review Checklist: Ensure imports match inventory; avoid mixing Reka and PrimeVue in a single component.

PrimeVue Docs Sources
- Offline Showcase: `docs/vendor/primevue-docs/apps/showcase/doc/`
  - Each component has variant docs (e.g., Dialog: `.../dialog/WithoutModalDoc.vue`).
  - Use these as canonical examples before adding/updating components.
- Types as Spec: `app/node_modules/primevue/<component>/index.d.ts` and `app/node_modules/primevue/index.d.ts` define props/events/slots.
- Quick Inventory: `docs/primevue-inventory.md` lists valid import paths and services.

Example — Non‑modal Dialog
- Import: `import Dialog from 'primevue/dialog'`
- Usage: `<Dialog v-model:visible="open" :modal="false" :blockScroll="false" />`
- Reference: `docs/vendor/primevue-docs/apps/showcase/doc/dialog/WithoutModalDoc.vue`

Practical implications
- Stick to one feedback surface for validation (PrimeVue Toast). Avoid adding duplicate inline validation UIs unless explicitly requested.
- When adding new CLI verbs, include synonyms in `entities.ts` and extend the freeform parser for parity.
- Keep implementation surgical; avoid introducing overlapping widgets or redundant flows that increase maintenance burden.

Ownership
- Command Palette + Parser: Shared between frontend and backend. Keep responses structured for clear errors and previews when needed.
- Tests: Prefer lightweight Python probes/suites (tools/cli_probe.py, tools/cli_suite.py) and Playwright-based GUI checks (tools/gui_suite.py).

## Payment Processing Feature (005-payment-processing-receipt)
**Added**: 2025-10-14
**Key Decisions**:
- **Command Bus Usage**: All payment operations (create, allocate, reverse) dispatch through dedicated actions in `stack/modules/Accounting/Domain/Payments/Actions/` registered in `stack/config/command-bus.php`
- **New Tables**: `invoicing.payment_receipt_batches` for batch processing (FR-011), plus enhanced `invoicing.payments` and `invoicing.payment_allocations` with RLS
- **Telemetry**: Metrics counters for payment_created_total, allocation_applied_total, allocation_failure_total added to monitoring dashboards
- **Permissions**: Extended Spatie permissions with `accounting.payments.*` permissions (`view`, `create`, `update`, `delete`, `allocate`, `reverse`, `reconcile`) plus `accounting.batches.*` and `accounting.audit.view` for fine-grained access control. Updated `RbacSeeder.php` to assign these permissions to owner, manager, and accountant roles.
- **CLI ↔ GUI Parity**: Consolidated CLI flows using shared bus actions, maintaining JSON output format for automation
- **Allocation Strategies**: Support for fifo, proportional, overdue_first, largest_first, percentage_based, and custom_priority strategies
- **Audit Trail**: Structured audit events via `App\Models\AuditEntry` hooks for all payment operations
- **Performance Targets**: p95 receipt recording <2s, allocation completion <3s, supporting ~250 receipts/day/tenant

## Customer Management Lifecycle Feature (006-customer-management-customer-work)
**Added**: 2025-10-15
**Key Decisions**:
- **Domain Architecture**: Customer lifecycle logic centralized in `Modules\Accounting\Domain\Customers\` namespace with dedicated Actions, Services, Models, and Telemetry subdirectories
- **Command Bus Integration**: All customer operations (create, update, delete, status change, credit adjustment, statement generation, import/export) dispatch through registered bus actions with full CLI parity
- **Comprehensive Data Model**: 8 new tables supporting complete customer lifecycle:
  - `invoicing.customers` (enhanced with lifecycle fields)
  - `invoicing.customer_contacts` with primary contact enforcement
  - `invoicing.customer_addresses` with default designation
  - `invoicing.customer_groups` + `customer_group_members` for segmentation
  - `invoicing.customer_communications` for interaction history
  - `invoicing.customer_credit_limits` with effective/expiry dates
  - `invoicing.customer_statements` with document generation
  - `invoicing.customer_aging_snapshots` for risk assessment
- **Multi-Tenant Security**: Comprehensive RLS policies on all customer tables with `company_id` filtering and audit triggers
- **Granular RBAC**: Extended permission set with 12 specific customer permissions covering view, create, update, delete, contact management, credit management, statement generation, and import/export operations
- **Credit Enforcement**: Real-time credit limit checking during invoice creation with override support and audit logging
- **Aging & Statements**: Automated aging calculations with nightly scheduler, on-demand refresh capability, and PDF/CSV statement generation
- **Import/Export**: Bulk data operations supporting CSV, JSON, XLSX formats with validation, preview, and idempotency protection
- **Advanced UI**: 8 Vue components using PrimeVue with tabbed interface, interactive charts, real-time updates, and responsive design
- **Performance Optimization**: Database indexes for p95 <1.2s customer list queries, efficient aging calculations, and queue-based processing
- **Observability**: Comprehensive audit logging, Prometheus metrics, and structured logging for all customer operations

**Technical Notes**:
- Customer numbers auto-generated with tenant-specific sequences (e.g., CUST-0001)
- Credit limit adjustments support approval workflows and conflict resolution
- Communication logging supports multiple channels (email, phone, meeting, note) with timeline view
- Risk assessment with automated health scoring and collection recommendations
- Statement generation includes watermarking, checksum verification, and delivery tracking
- All CLI commands support `--json` output and `--dry-run` modes for testing

**Future Considerations**:
- Customer self-service portal for viewing statements and updating contact information
- Advanced credit scoring with payment history integration
- Automated collection workflows with configurable escalation rules
- Integration with accounting systems for automatic payment application

## Journal Entries & Ledger Processing Feature (007-journal-entries-manual)
**Added**: 2025-10-15
**Key Decisions**:
- **Queue Architecture**: Dedicated `journal` and `ledger` queues with separate worker configurations for manual entry processing vs automatic ledger posting
- **Worker Configuration**: 
  - `journal` worker handles manual journal entry processing, approvals, and posting with 3 retry attempts and 90s timeout
  - `ledger` worker processes automatic ledger posting from source documents with 5 retry attempts and 120s timeout
  - Supervisor configurations included for high-availability deployment with 2 journal workers and 3 ledger workers
- **Priority Separation**: Journal queues (`journal`, `journal_approval`, `journal_posting`) prioritize user-initiated actions, while ledger queues (`ledger`, `ledger_auto_post`, `ledger_reconciliation`) handle system-generated postings
- **Balance Validation**: All journal processing must enforce double-entry balance validation before posting, with rollback on failure
- **Audit Trail Integration**: Queue jobs automatically populate `acct.journal_audit_log` events for state changes, approvals, postings, and reversals
- **Performance Targets**: p95 journal entry create/post latency <1.5s for ≤20 lines, support ≥10k journal lines/day per tenant
- **Error Handling**: Configurable backoff strategies with journal operations using [5, 15, 30]s intervals and ledger operations using [10, 30, 60]s intervals

**Queue Worker Requirements**:
- **Journal Worker**: Lower memory footprint (256MB), faster processing (2s sleep), suitable for user-interactive operations requiring immediate feedback
- **Ledger Worker**: Higher memory footprint (512MB), faster processing (1s sleep), optimized for high-throughput automatic posting from invoices/payments
- **Isolation**: Separate queues prevent manual journal operations from being blocked by bulk ledger processing and vice versa
- **Monitoring**: Dedicated log files for each worker type (`supervisor-accounting-journal.log`, `supervisor-accounting-ledger.log`) for debugging and performance analysis

**Operational Notes**:
- Journal workers should run continuously during business hours for responsive user experience
- Ledger workers can handle burst loads from automated invoice/payment processing
- Queue workers support graceful shutdown and can be restarted without data loss
- Failed jobs are automatically retried with exponential backoff before being moved to failed_jobs table

See also
- PR review checklist: `.github/pull_request_template.md`
