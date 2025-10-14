# Specification Source Catalog (2025-10-14)

Authoritative inputs to consult when drafting or updating feature specs, plans, and tasks for the `stack/` workspace:

## Governance & Priorities
- `.specify/memory/constitution.md` — Haasib Constitution v2.2.0 (principles, guardrails, workflow gates)
- `docs/TEAM_MEMORY.md` — project-wide priorities, UI decisions, and working agreements

## API & Backend References
- `docs/api/company-endpoints.md` — current company management endpoints, context requirements, and idempotency usage
- `docs/api-allocation-guide.md` — payment allocation quick reference
- `docs/idempotency.md` — header semantics, retry behaviour, error codes

## CLI & Command Palette
- `docs/cli/company-commands.md` — core CLI verbs and execution conventions

## Frontend Toolkit
- `docs/mig-to-prime.md` — PrimeVue migration plan (paths updated for `stack/resources/js`)
- `docs/primevue-inventory.md` — offline component import catalog
- `docs/primevue-theming.md` — theming guidance and token strategy
- `docs/manual_test.md` — manual QA checklist (with CLI/idempotency reminder)

## Quality & Testing
- `docs/manual_test.md` — canonical manual regression scenarios
- Automation hooks noted in `docs/TEAM_MEMORY.md`: `tools/cli_suite.py`, `tools/gui_suite.py`

## Archived / Historical (read-only)
- Any document marked with an **ARCHIVE NOTICE** banner (ServiceContext era, legacy scaffolding). Use them only for historical context; base new specs on the sources above.
