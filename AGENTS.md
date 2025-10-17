# Haasib Development Guidelines

Auto-generated from all feature plans. Last updated: 2025-10-16

## Active Technologies
- PHP 8.2 (Laravel 12) + Vue 3 (Inertia.js v2) with PrimeVue 4.3.9 UI for ledger period close workflows; adds `Modules\Ledger\Services\PeriodCloseService`, `ledger.period_closes*` tables, command-bus actions (`period-close.*`), and Inertia checklist/validation pages under `stack/resources/js/Pages/Ledger`. (008-period-close-monthly)
- PHP 8.2 / Laravel 12 back-end within `stack/`, Vue 3 + Inertia.js v2 front-end, PrimeVue 4.3.9 UI; no deviations planned. + `Modules\Accounting` domain/services (e.g., `Services/PaymentService.php`, `Domain/Payments`), shared `App\Services\PaymentAllocationService`, new payment command-bus actions to register in `stack/config/command-bus.php`, and Inertia pages under `stack/resources/js/Pages/Invoicing`. (005-payment-processing-receipt)
- PHP 8.2 (Laravel 12) within the monolithic `stack/` workspace. Front end delivered via Vue 3 + Inertia.js v2 single-page flows compiled by Vite. + PrimeVue 4.3.9 UI library, Tailwind CSS for layout utilities, Postgres `ILIKE` search (Laravel Scout not installed), Spatie Permission for RBAC, command bus infrastructure under `stack/config/command-bus.php`, and new customer services (statement/aging) instead of reusing `App\Services\PaymentAllocationService`. (006-customer-management-customer-work)
- PostgreSQL 16 with canonical `invoicing.customers` table plus planned tables `invoicing.customer_contacts`, `invoicing.customer_addresses`, `invoicing.customer_credit_limits`, and `invoicing.customer_statements`; update `App\Models\Customer` and downstream queries to target the `invoicing` schema. (006-customer-management-customer-work)
- PHP 8.2 (Laravel 12) and TypeScript/Vue 3 + Laravel framework, Inertia.js v2, PrimeVue 4.3.9, Tailwind CSS, Spatie Permission (007-journal-entries-manual)
- PostgreSQL 16 (`invoicing` schema) (007-journal-entries-manual)
- PHP 8.2 (Laravel 12), TypeScript/Vue 3 via Inertia.js v2 + Laravel command bus, Inertia.js, PrimeVue 4.3.9, Tailwind CSS, Spatie Permission (009-bank-reconciliation-statement)
- PostgreSQL 16 (new `ops.bank_statements`/`ops.bank_statement_lines` staging tables referencing `ledger` transactions) (009-bank-reconciliation-statement)
- PHP 8.2 (Laravel 12), TypeScript (Vue 3 + Inertia.js v2) + PrimeVue 4.3.9 (Chart.js), Tailwind CSS, Laravel command bus, Spatie Permission (010-reporting-dashboard-financial)
- PostgreSQL 16 across `ledger`, `acct`, `ops`, plus `rpt` reporting schema with materialized snapshots and short-lived cache store (010-reporting-dashboard-financial)

## Project Structure
```
backend/
frontend/
tests/
```

## Commands
- Command bus `period-close:*` actions (start, validate, lock, complete, reopen) exposed via CLI and HTTP for monthly closing.
- Journal entry CLI/API (`journal:*`) for period adjustment support (reuse from ledger module).

## Code Style
PHP 8.2 / Laravel 12 back-end within `stack/`, Vue 3 + Inertia.js v2 front-end, PrimeVue 4.3.9 UI; no deviations planned.: Follow standard conventions

## Recent Changes
- 010-reporting-dashboard-financial: Added PHP 8.2 (Laravel 12), TypeScript (Vue 3 + Inertia.js v2) + PrimeVue 4.3.9 (Chart.js), Tailwind CSS, Laravel command bus, Spatie Permission
- 009-bank-reconciliation-statement: Added PHP 8.2 (Laravel 12), TypeScript/Vue 3 via Inertia.js v2 + Laravel command bus, Inertia.js, PrimeVue 4.3.9, Tailwind CSS, Spatie Permission
- 008-period-close-monthly: Added ledger period close service, checklist/task templates, command-bus actions, and Inertia dashboard with PrimeVue Steps.

<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
