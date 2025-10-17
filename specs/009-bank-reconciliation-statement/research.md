# Research Findings

## Decision: Stage bank statement ingestion in the `ops` schema and persist reconciliations alongside ledger references
- **Rationale**: The constitution mandates deliberate schema ownership and notes that `ops` covers operational support data such as bank imports. Placing `ops.bank_statements`/`ops.bank_statement_lines` keeps raw files isolated while reconciliation status rows can reference `ledger` journal entities for posting integrity. This avoids leaking staging data into `acct` while aligning with the period-close/ledger touchpoints described in existing specs.
- **Alternatives considered**:
  - Introduce a dedicated `bank` schema (suggested in `docs/briefs/target-mvp.md`), but doing so would require constitution updates and additional governance right now.
  - Store statements directly in `acct` tables, which would mingle external raw data with customer-facing AR/AP records and violate separation-of-concerns guidance.

## Decision: Emit reconciliation activity to the audit subsystem via `audit_log()` and broadcast `bank.reconciliation` events
- **Rationale**: The constitution’s security principle requires audit coverage for money-affecting work. Existing payment audit documentation (`docs/api/payment-audit-reporting.md`) already defines the `bank.reconciliation` channel. Leveraging `audit_log()` keeps parity with other financial workflows while the broadcast ensures UI consumers (e.g., dashboards) stay in sync.
- **Alternatives considered**:
  - Create bespoke reconciliation logs outside the audit schema—rejected because it fragments governance and complicates compliance reporting.
  - Rely solely on WebSocket events without persisted audit data—rejected; events are ephemeral and fail compliance requirements.

## Decision: Target up to 2,000 statement lines per import with <5s ingestion and <2s auto-match runs
- **Rationale**: The PRD focuses on SMEs, where monthly statements typically carry a few hundred to low thousands of lines; engineering for 2,000 lines keeps headroom while remaining realistic for Laravel queue workloads. Sub-5s ingestion lets accountants stay in flow, and a <2s auto-match budget aligns with PrimeVue table interactions without needing aggressive pagination.
- **Alternatives considered**:
  - Design for 10k+ lines immediately—deferred until volume data justifies optimizing storage/indexing and possibly background processing.
  - Accept slower processing (>10s)—rejected to maintain responsive UX expectations from the CLI/GUI hybrid vision.

## Decision: Enforce constraints—idempotent imports, 10 MB file cap, foreign-currency tagging, and duplicate safeguards
- **Rationale**: Edge cases in the spec call out duplicate imports and unsupported formats. Hashing the uploaded file plus statement metadata gives idempotency. A 10 MB cap fits CSV/OFX/QFX volumes within the SME scale and protects queue workers. Tagging currency on statements/lines ensures reconciliation honors multi-currency scenarios highlighted in the accounting briefs.
- **Alternatives considered**:
  - Allow unlimited uploads—rejected due to risk of worker exhaustion and denial-of-service.
  - Skip duplicate detection—would let the same statement create conflicting reconciliations, undermining trust.

## Decision: Scope initial release for up to 10 active bank accounts per company with monthly reconciliation cadence
- **Rationale**: Story and MVP briefs emphasise midsized hospitality operations with a small set of bank accounts. Setting expectations at ~10 accounts aligns with permission sets (e.g., `accounting.payments.reconcile`) and keeps UI manageable. Monthly cadence matches standard accounting close cycles and complements the period-close module added in feature 008.
- **Alternatives considered**:
  - Unlimited accounts with daily reconciliations—postpone until customer demand arises; would require batching, custom dashboards, and heavier automation.
  - Limiting to a single account—too restrictive for even modest SMEs and conflicts with FR-011 (multiple bank accounts per company).

## Decision: Use Laravel command-bus actions for ingestion (`bank.reconciliation.import`) and matching (`bank.reconciliation.match`)
- **Rationale**: Command-bus patterns are already established (see period close module) and provide consistent queuing, validation, and audit hooks. Separate actions keep ingestion concerns (file parsing, staging writes) apart from matching logic, easing retries and observability.
- **Alternatives considered**:
  - Handle everything in controller methods—rejected; harder to test and conflicts with command-bus usage across new modules.
  - Build dedicated Artisan-only commands—less useful for the Inertia UI and would duplicate validation logic.

## Decision: Render reconciliation UI with PrimeVue `DataTable` (virtual scroll) and drawer-based manual matching
- **Rationale**: Matching requires side-by-side views of statement lines and internal transactions. PrimeVue’s `DataTable` supports large datasets with virtual scrolling, while a drawer/modal interaction aligns with existing ledger UX patterns for detail editing. Inertia keeps SPA interactions smooth.
- **Alternatives considered**:
  - Custom canvas-based table—overkill versus leveraging PrimeVue components already in use.
  - Separate pages for statement and ledger items—breaks the reconciliation workflow and slows manual matching.

## Decision: Parse CSV internally and delegate OFX/QFX parsing to dedicated PHP libraries wrapped behind a common adapter
- **Rationale**: CSV parsing is trivial with native PHP/Spl. Reliable OFX/QFX parsing benefits from community-tested packages (`ofxparser/ofxparser`, `asgrim/ofxparser`). Wrapping them behind an adapter yields a normalized statement-line DTO that feeds both auto- and manual-matching paths.
- **Alternatives considered**:
  - Implement OFX/QFX parsing from scratch—too error-prone and time-consuming.
  - Restrict MVP to CSV—violates FR-001 requiring multiple formats.
