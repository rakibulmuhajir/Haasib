# Haasib Development Guidelines

Auto-generated from all feature plans. Last updated: 2025-10-14

## Active Technologies
- PHP 8.2 / Laravel 12 back-end within `stack/`, Vue 3 + Inertia.js v2 front-end, PrimeVue 4.3.9 UI; no deviations planned. + `Modules\Accounting` domain/services (e.g., `Services/PaymentService.php`, `Domain/Payments`), shared `App\Services\PaymentAllocationService`, new payment command-bus actions to register in `stack/config/command-bus.php`, and Inertia pages under `stack/resources/js/Pages/Invoicing`. (005-payment-processing-receipt)
- PHP 8.2 (Laravel 12) within the monolithic `stack/` workspace. Front end delivered via Vue 3 + Inertia.js v2 single-page flows compiled by Vite. + PrimeVue 4.3.9 UI library, Tailwind CSS for layout utilities, Postgres `ILIKE` search (Laravel Scout not installed), Spatie Permission for RBAC, command bus infrastructure under `stack/config/command-bus.php`, and new customer services (statement/aging) instead of reusing `App\Services\PaymentAllocationService`. (006-customer-management-customer-work)
- PostgreSQL 16 with canonical `invoicing.customers` table plus planned tables `invoicing.customer_contacts`, `invoicing.customer_addresses`, `invoicing.customer_credit_limits`, and `invoicing.customer_statements`; update `App\Models\Customer` and downstream queries to target the `invoicing` schema. (006-customer-management-customer-work)

## Project Structure
```
backend/
frontend/
tests/
```

## Commands
# Add commands for PHP 8.2 / Laravel 12 back-end within `stack/`, Vue 3 + Inertia.js v2 front-end, PrimeVue 4.3.9 UI; no deviations planned.

## Code Style
PHP 8.2 / Laravel 12 back-end within `stack/`, Vue 3 + Inertia.js v2 front-end, PrimeVue 4.3.9 UI; no deviations planned.: Follow standard conventions

## Recent Changes
- 006-customer-management-customer-work: Added PHP 8.2 (Laravel 12) within the monolithic `stack/` workspace. Front end delivered via Vue 3 + Inertia.js v2 single-page flows compiled by Vite. + PrimeVue 4.3.9 UI library, Tailwind CSS for layout utilities, Postgres `ILIKE` search (Laravel Scout not installed), Spatie Permission for RBAC, command bus infrastructure under `stack/config/command-bus.php`, and new customer services (statement/aging) instead of reusing `App\Services\PaymentAllocationService`.
- 005-payment-processing-receipt: Added PHP 8.2 / Laravel 12 back-end within `stack/`, Vue 3 + Inertia.js v2 front-end, PrimeVue 4.3.9 UI; no deviations planned. + `Modules\Accounting` domain/services (e.g., `Services/PaymentService.php`, `Domain/Payments`), shared `App\Services\PaymentAllocationService`, new payment command-bus actions to register in `stack/config/command-bus.php`, and Inertia pages under `stack/resources/js/Pages/Invoicing`.

<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
