# Demo Tenant Rollout Plan

A practical guide for creating and maintaining a repeatable demo tenant inside the production-grade app so evaluators can explore realistic data without risking live companies.

---

## 0) Objectives

* Give prospects and testers instant access to a rich, realistic dataset.
* Keep demo activity isolated from real tenants while reusing the main stack.
* Ensure the demo resets reliably so every session starts from a known state.
* Capture insights from demo sessions without exposing confidential data.

---

## 1) Tenant Provisioning

* **Company record**: create `core.companies` entry flagged `is_demo=true` with a memorable name (e.g., "Demo Travel Co").
* **Users**: seed at least two demo identities (owner + accountant) with descriptive emails (e.g., `owner.demo@example.test`).
* **Roles & permissions**: assign broad but reasonable permissions; avoid super-admin features that expose other tenants.
* **API tokens**: generate one long-lived token per persona for Postman collections.

---

## 2) Environment Configuration

* **Feature flags**: gate any unstable or unreleased modules so demo users only see vetted features.
* **Email**: route notifications to MailHog or a demo mailbox; never deliver to real customers.
* **Background jobs**: tag demo jobs so they can be purged or replayed separately from production work queues.
* **Access**: protect demo credentials in your vault; rotate monthly or whenever you refresh the seed data.

---

## 3) Data Seeding Strategy

* **Coverage**: include AR invoices, AP bills, payments (full/partial/refunds), bank reconciliations, inventory movements, payroll runs, tax rates, and reporting periods so every module looks lived-in.
* **Chronology**: seed data across 12–18 months so dashboards and reports show trends and aged balances.
* **Anonymisation**: use fake-but-human data (Faker, Mockaroo) to avoid any overlap with live customer information.
* **Automation**: store the seeding logic as replayable scripts (Laravel seeders + SQL fixtures) versioned in `database/seeders` or `docs/demo-seed/`.

---

## 4) Reset & Refresh Workflow

* **Snapshot**: keep a sanitized base database dump (`storage/demo/base-demo.sql`) that reflects the desired starting point.
* **Reset command**: add an Artisan/CLI command (`app:demo:reset`) that drops current demo company data, reloads the snapshot, and reissues credentials.
* **Schedule**: run the reset nightly via cron and expose a manual trigger for the success team.
* **Audit**: log each reset with timestamp and operator for traceability.

---

## 5) Synthetic Activity Loop

* **Recurring tasks**: schedule a job that posts a handful of transactions weekly (e.g., new invoices, expenses, payments) so the tenant evolves between resets.
* **Scenario scripts**: bundle optional scripts ("run quarter close", "simulate cash crunch") the sales team can execute before a call.
* **Time travel**: maintain fixtures that push the books forward (e.g., +6 months) to demo future states without needing a dual-database test mode.

---

## 6) Observability & Feedback

* **Logging**: tag all requests made by demo users (`X-Demo-Tenant: true`) to segment analytics and error rates.
* **Session capture**: enable privacy-respecting session replay (e.g., OpenReplay) on the demo tenant only.
* **Feedback loop**: embed a feedback form or link to capture tester questions, and route alerts to the product Slack channel.

---

## 7) Security & Guardrails

* **Rate limits**: enforce sane API/web limits to prevent automated scraping.
* **Data leaks**: hard-code tenant scoping to the demo company so users cannot pivot to other companies via ID manipulation.
* **Cleanup**: purge any files/uploads older than 48 hours to keep storage light.
* **Compliance**: ensure demo fixtures avoid trademarked names or real vendor/customer info.

---

## 8) Enablement Assets

* **Runbook**: store the reset process, credentials, and troubleshooting tips in `docs/runbooks/demo-tenant.md`.
* **Walkthrough**: record a 5-minute Loom that shows the main flows testers should exercise.
* **Checklists**: point evaluators to `docs/manual_test.md` for systematic validation and provide a tailored demo checklist.
* **FAQ**: maintain a short Q&A (common errors, how to trigger reports) for the success/support team.

---

## 9) Future Enhancements

* Add a self-service "Reset demo data" button gated to internal staff.
* Layer in feature-flagged experiments so demo users pilot upcoming modules before production customers.
* Consider exporting demo telemetry to a BI dashboard to see which modules resonate (page hits, feature usage).
* Evaluate whether to offer a downloadable Postgres dump for on-premise prospects once the SaaS demo process stabilises.

