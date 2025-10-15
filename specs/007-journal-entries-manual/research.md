# Phase 0 Research — Journal Entries (Manual & Automatic)

## Testing Stack Confirmation
- Decision: Standardize on Pest (PHPUnit runner) for Laravel unit/feature/CLI coverage and Playwright for browser automation exercises around the new journal entry flows.
- Rationale: `stack/tests/Pest.php` binds Pest across Feature/Unit/CLI suites, and `stack/tests/Browser/customers.contacts.spec.ts` already seeds Playwright specs; aligning journal tests with these harnesses keeps parity with existing automation and avoids introducing a different runner.
- Alternatives considered: Reverting to raw PHPUnit test cases without Pest helpers (loses shared setup macros) or adopting Cypress/WebdriverIO for UI (would expand tooling footprint and duplicate Playwright coverage patterns).

## Performance & Throughput Targets
- Decision: Design manual/automatic posting to keep p95 journal entry create/post latency under 1.5 seconds for entries up to 20 lines and support at least 10,000 journal lines per day per tenant without manual intervention.
- Rationale: `docs/briefs/story-of-accounting.md` frames GL as the backbone for every AR/AP/Bank action, so journal persistence must keep pace with payment/invoice flows that currently target <2s latency (`docs/ServiceContext-Metrics.md`). Capping at 20 lines matches common adjustment journals while the 10k/day ceiling covers high-volume tenants without over-optimizing.
- Alternatives considered: Using looser 3–4 second targets (risking UI lag and period-close batching delays) or optimizing for >50 line entries by default (adds premature denormalization complexity).

## Compliance & Operational Constraints
- Decision: Enforce closed-period guards, double-entry validation, and source document traceability by default, reusing posting protections described in `docs/briefs/story-of-accounting.md` and existing exceptions in `Modules\Accounting\Services\LedgerService`.
- Rationale: The specification highlights audit trails and prevention of posting into closed periods; the current ledger service already validates balance and locks periods. Carrying those constraints into manual workflows maintains audit compliance and matches manual test expectations in `docs/manual_test.md §7.2–7.3`.
- Alternatives considered: Allowing manual overrides for closed periods (would breach audit trail requirements) or skipping source linkage on manual entries (breaks traceability and monitoring).

## Expected Scale & Scope
- Decision: Scope v1 to accountants and controllers (roles with `ledger.postJournal`), covering manual adjustments, recurring templates, reversals, and trial balance reporting for up to 50 concurrent tenants.
- Rationale: Existing permissions in `docs/briefs/haasib-technical-brief-and-progress_v2.1_2025-08-22.md` enumerate ledger-specific abilities; limiting initial release to finance roles keeps RBAC tight while 50-tenant concurrency aligns with current multi-company roadmap.
- Alternatives considered: Broadening access to any user with accounting menu visibility (undermines segregation of duties) or constraining to a single tenant (ignores multi-company commitments).

## Laravel Ledger Module Alignment
- Decision: Build manual journal flows on top of `Modules\Accounting\Services\LedgerService` and `Domain\Ledgers\Actions\CreateJournalEntryAction`, extending them to support batching, templates, and reversals instead of recreating ledger logic elsewhere.
- Rationale: Existing services already encapsulate balance validation, transaction creation, and event emission; augmenting them ensures automatic and manual entries share the same audit hooks (`Modules\Accounting\Domain\Ledgers\Events\LedgerEntryCreated`), simplifying reporting and tracing.
- Alternatives considered: Crafting a parallel manual journal service (duplicates logic, risks divergent validation) or routing everything through raw Eloquent calls in controllers (bypasses domain events and command bus middleware).

## Command Bus & Action Registration
- Decision: Register `journal.*` actions in `stack/config/command-bus.php`, implemented under `Modules\Accounting\Domain\Ledgers\Actions`, and expose matching CLI/Palette commands so manual entry operations traverse the same audited bus path as payments/customers.
- Rationale: Command bus supremacy is reiterated across docs (`docs/modules-architecture.md`, `docs/dosdonts/command-bus-best-practices.md`); payments already dispatch via `LedgerService` actions. Extending the bus maintains parity across HTTP/CLI/UI and keeps idempotency/audit middleware consistent.
- Alternatives considered: Invoking services directly from controllers or Artisan commands (breaks parity and guardrails) or batching via queued jobs without synchronous bus dispatch (complicates UX feedback).

## Front-end Patterns with Inertia & PrimeVue
- Decision: Implement manual journal UI as Inertia pages under `stack/resources/js/Pages/Accounting/JournalEntries`, composing PrimeVue `DataTable`, `Dialog`, `InputNumber`, `Dropdown`, and future `JournalEntryForm`/`LinesTable` components described in `docs/components/Ledger-Components.md`.
- Rationale: Existing guidelines (`docs/development-guide.md`) prescribe a reusable `JournalEntryForm` pattern with balance validation and permission-aware controls. PrimeVue inventory in `docs/primevue-inventory.md` keeps component imports standardized, while Inertia pages ensure SPA parity with the rest of Accounting.
- Alternatives considered: Building bespoke Tailwind-only forms (would violate accessibility guidance) or introducing a separate Vue router module (breaks Inertia navigation conventions).

## PostgreSQL Schema & RLS Considerations
- Decision: Store journal headers in `acct.journal_entries` and normalized lines in `acct.journal_transactions`, enforcing RLS on `company_id`, period status checks, and supporting indexes for trial balance queries as outlined in `docs/schemas/10_accounting.sql`.
- Rationale: Existing migrations (e.g., `stack/modules/Accounting/Database/Migrations/2025_10_05_130003_create_journal_entries_and_transactions.php`) already implement double-entry triggers and RLS patterns; extending them for manual features keeps data co-located and compliant with multi-schema governance.
- Alternatives considered: Creating new `manual_journals` tables (adds duplication) or relaxing RLS to rely on application filters (risks tenant leakage).

## Constitution Status
- Decision: Treat `.specify/memory/constitution.md` as currently unratified placeholder guidance and fall back to `AGENTS.md`/repository docs for guardrails while awaiting governance updates.
- Rationale: The constitution file contains template markers only; repository history lacks any populated version. Documenting this maintains transparency while letting planning continue under documented guidelines.
- Alternatives considered: Blocking planning entirely (halts feature progress without a path forward) or fabricating principles (risks misalignment with future governance).
