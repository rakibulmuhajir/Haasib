# Research – Reporting Dashboard (Financial & KPI)

- Decision: Standardize dashboards on PrimeVue’s `Chart` component (Chart.js) already used in `stack/resources/js/Pages/Dashboard/Index.vue` and `stack/resources/js/Pages/Accounting/Customers/Tabs/AgingTab.vue`.
  Rationale: Reusing the existing PrimeVue/Chart.js stack keeps theming consistent and avoids adding new chart dependencies while supporting the required KPIs and trend visualizations.
  Alternatives considered: Introducing ECharts or D3 for bespoke visuals (rejected due to new dependency cost and lack of PrimeVue integration), rolling custom SVG charts (rejected for time-to-market and accessibility debt).

- Decision: Persist financial statement snapshots and generated reports in the planned `rpt` schema (per `docs/schemas/30_reporting.sql` and `docs/briefs/story-of-accounting.md`) with materialized views refreshed via command-bus jobs.
  Rationale: Aligns with the Multi-Schema Domain Separation gate, keeps reporting storage isolated from `acct`/`ledger`, and provides auditable history while enabling <10s regeneration by pre-aggregating totals.
  Alternatives considered: Querying ledger tables live on every request (rejected: slow for unlimited retention and burdens RLS), exporting to external warehouse (rejected: out of scope and violates constitution without documented exception).

- Decision: Use a dual-layer caching policy—`Cache::remember` entries (database/Redis store per `config/cache.php`) with 5s TTL for live dashboards and persisted `rpt.reports` snapshots refreshed on schedule/on-demand.
  Rationale: Meets “real-time (<5s)” freshness by limiting cache staleness while avoiding repeated heavy queries; scheduled jobs can warm snapshots for exports and trial balance runs.
  Alternatives considered: No caching (rejected: violates FR-013 and risks missing performance targets), long-lived caches (rejected: conflicts with live data requirement), client-side polling only (rejected: still requires backend throttling).

- Decision: Size concurrency targets to 100 concurrent dashboard viewers and ≥10 simultaneous heavy report generations, matching the load guidance in `docs/api/payment-audit-reporting.md` and monitoring playbooks.
  Rationale: Reusing proven load envelopes ensures the design covers enterprise tenants and keeps us within existing observability thresholds.
  Alternatives considered: Deferring concurrency sizing (rejected: leaves Scale/Scope unresolved), assuming lower loads (rejected: conflicts with compliance expectations documented in monitoring guides).

- Decision: Apply Tailwind/PrimeVue layout patterns documented in `docs/frontend-architecture.md`—reuse existing summary cards, data tables, and KPI tiles while keeping business logic in composables/services.
  Rationale: Maintains UI consistency, accelerates development through reusable components, and keeps Vue pages slim as recommended by the frontend architecture plan.
  Alternatives considered: Building bespoke CSS per page (rejected: inconsistent UX), migrating to another utility framework (rejected: contradicts active tech stack).

- Decision: Register new dashboard/reporting command-bus actions using the practices in `docs/dosdonts/command-bus-best-practices.md` and existing `stack/config/command-bus.php`.
  Rationale: Ensures commands are container-resolved, transactional, and testable while allowing CLI, HTTP, and scheduled jobs to share the same handlers.
  Alternatives considered: Direct service invocation from controllers without bus registration (rejected: bypasses middleware/audit pipeline), chaining commands manually (rejected: risks nested transactions).

- Decision: Enforce report permissions with Spatie Laravel Permission per `docs/dosdonts/models-best-practices.md` and `docs/briefs/rbac_implementation_brief.md`, mapping Owner/Accountant/Viewer roles to granular abilities (e.g., `reporting.dashboard.view`, `reporting.reports.export`).
  Rationale: Aligns with Security-First Bookkeeping by guaranteeing role-scoped access and leveraging existing caching/traits.
  Alternatives considered: Hard-coded role checks (rejected: brittle and duplicates policy logic), new custom permission system (rejected: unnecessary and risks divergence from constitution).
