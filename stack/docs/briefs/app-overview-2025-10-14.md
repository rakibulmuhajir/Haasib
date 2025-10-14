# Haasib Accounting Platform — Executive Brief (2025-10-14)

**Owner:** banna
**Audience:** Engineering, product, and delivery contributors
**Purpose:** Summarize the business problem, target users, and guiding principles so teammates stay aligned on what we are building and why it matters.

---

## 1. Problem & Opportunity

Small and mid-sized enterprises (SMEs) struggle to keep books accurate when they juggle multiple legal entities, currencies, and fast-moving operations. Existing software is slow, opaque, or too rigid to adapt. Haasib addresses this by delivering a modular accounting workspace that keeps data trustworthy while letting finance operators move quickly.

---

## 2. Target Users & Jobs

| User | Primary Jobs | Pain Points Today |
|------|--------------|-------------------|
| Company owner / finance lead | Maintain reliable books, approve closes, monitor cash | Manual reconciliation, poor visibility, stale reports |
| Accountant / finance operator | Post invoices, receipts, journals; reconcile accounts | Tedious workflows, duplicated effort, weak multi-company separation |
| Executive stakeholder | Monitor compliance and delivery risk | Limited insight into review coverage and outstanding issues |

All features must respect strict tenant boundaries, enforce the company’s permission model, and provide both guided UI flows and command/CLI access for power users.

---

## 3. Product Vision

Deliver an accounting hub that:

1. **Protects financial accuracy** — every transaction posts through double-entry rules with full audit history.
2. **Keeps operators fast** — any action available in the UI is also reachable through the command palette or CLI for bulk work and automation.
3. **Grows by module** — core domains (Foundations, Ledger, Invoicing, Payments, Reporting) can launch independently while sharing governance.
4. **Supports global teams** — multilingual interfaces, multiple currencies per company, and compliance-friendly exports.

---

## 4. Scope Snapshot (Current Build)

- **Foundations**: Company onboarding, role/permission enforcement, tenant context switching, safe retryable writes.
- **Active modules**: General ledger, sales invoicing, incoming payments, and the command palette experience.
- **Quality tooling**: Specification/plan/task review workflows that ensure every deliverable satisfies the Haasib constitution.
- **Operational insights**: Audit trails, health dashboards, and review coverage reporting for leadership.

Out of scope right now: payroll, automated bank feeds, payment gateways, deep analytics beyond trial balance and AR/AP aging.

---

## 5. Success Criteria

| Pillar | Indicator | Target |
|--------|-----------|--------|
| Financial integrity | Balance and audit checks per module | 100% pass before release |
| Tenant safety | Policy and permission regression tests | Zero breaches per release |
| Operator speed | CLI/command palette coverage vs. UI | ≥ 95% parity |
| Delivery cadence | Spec → plan → tasks turnaround | ≤ 48 hours for priority features |
| Visibility | Review coverage dashboard freshness | Updates within 2 minutes |

---

## 6. Guiding Principles

- Obey `.specify/memory/constitution.md` v2.2.0 — CLI/GUI parity, idempotent operations, audit trails, and tests-before-code are non-negotiable.
- Document assumptions, dependencies, and tests in specs and plans before work begins.
- Prefer clear, reusable business modules over ad-hoc solutions; keep finance workflows transparent and explainable.
- Make review outcomes, findings, and ownership visible so leaders can intervene early.

---

## 7. Risks & Dependencies

| Area | Risk | Mitigation |
|------|------|------------|
| Tenant isolation | Incorrect company context during operations | Keep automated RLS/RBAC tests up to date; audit new routes |
| Review discipline | Deliverables bypass quality gates | Enforce `/speckit.specify` workflow and checklist sign-off |
| CLI adoption | Command palette lags UI changes | Track parity in plans and PR reviews |
| Localization | Currency/locale data drifts | Maintain translation and FX update routines |

---

## 8. Immediate Priorities

1. Finish the quality review tooling (feature 011) and surface leadership dashboards.
2. Ship ledger and invoicing modules with full audit coverage.
3. Expand command palette coverage to high-volume flows (payments, reconciliation).
4. Describe the manual SaaS onboarding journey (company creation, subscription activation).
5. Institutionalize the weekly restore drill and record outcomes in team docs.

---

## 9. Further Reading

- `docs/TEAM_MEMORY.md` — working principles and decision log.
- `docs/spec-reference.md` — authoritative list of specs, plans, and templates.
- `docs/briefs/00_core_phase_tracker.md`, `docs/briefs/01_invoicing_phase_tracker.md` — module status.
- `stack/docs/cli-commands-guide.md` — how reviewers and operators use the command palette/CLI.

Update this brief as priorities shift or modules launch.
