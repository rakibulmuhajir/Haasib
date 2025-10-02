Migrations Strategy: Baseline + Patch Table

Goal
- Keep existing migrations as your baseline history.
- Apply all future changes as "patch" migrations tracked separately in `migrations_patches`.

What was added
- Config: `config/database.php` now allows `DB_MIGRATIONS_TABLE` override. Default remains `migrations`.
- Commands:
  - `php artisan make:patch NameOfMigration` → creates a migration under `database/migrations_patches`.
  - `php artisan migrate:patch` → applies patch migrations from `database/migrations_patches`, tracking them in `migrations_patches` table.
  - `php artisan migrate:patch rollback --step=1` → rolls back recent patch steps.
  - `php artisan migrate:patch status` → shows patch migration status.

Workflow
1) Baseline (optional but recommended once stable)
   - Ensure your database reflects the current baseline (run `php artisan migrate`).
   - Optionally create a schema dump: `php artisan schema:dump`.
2) Add a patch migration
   - `php artisan make:patch add_widget_table --create=widgets`
   - Edit the generated file in `database/migrations_patches/`.
3) Apply patches
   - `php artisan migrate:patch` (uses `migrations_patches` repository table)
4) Check status
   - `php artisan migrate:patch status`
5) Rollback (patch-only)
   - `php artisan migrate:patch rollback --step=1`

Notes
- Patch migrations are isolated from the baseline and won't pollute the default `migrations` table.
- You can still use all standard migration commands for the baseline.
- Use `--database` on `migrate:patch` if you need a non-default connection.

## Database Schema Structure

The database is organized into multiple schemas to provide clear separation of concerns:

### **auth schema**
Authentication, authorization, and multi-tenant core tables:
- `users` - User accounts
- `companies` - Multi-tenant company data
- `company_user` - Company-user relationships
- `company_secondary_currencies` - Multi-currency support per company
- `sessions` - User sessions
- `password_reset_tokens` - Password reset tokens
- Permission/RBAC tables (roles, permissions, model_has_permissions, etc.)

### **public schema**
General reference data and system infrastructure:
- `languages` - System languages (ISO 639 codes)
- `currencies` - System currencies (ISO 4217 codes)
- `locales` - Locale combinations (en_US, en_AE, etc.)
- `countries` - Country data
- System infrastructure: `cache`, `jobs`, `failed_jobs`
- `idempotency_keys` - API idempotency handling

### **hrm schema**
Human Resource Management - business entities:
- `customers` - Customer records
- `contacts` - Customer/vendor contacts
- `vendors` - Vendor/supplier records
- Related HRM entities

### **acct schema**
Accounting and financial data:
- `ledger_accounts` - Chart of accounts
- `journal_entries` - Journal entries
- `journal_lines` - Journal entry line items
- `transactions` - Financial transactions
- `invoices` - Sales invoices
- `invoice_items` - Invoice line items
- `payments` - Payment records
- `payment_allocations` - Payment-to-invoice allocations
- `bills` - Vendor bills
- `bill_items` - Bill line items
- `bill_payments` - Bill payments
- `accounts_receivable` - AR ledger
- `accounts_payable` - AP ledger
- `fiscal_years` - Fiscal year definitions
- `accounting_periods` - Accounting periods
- `audit_logs` - Audit trail

### Benefits of Multi-Schema Design
1. **Clear domain separation** - Each schema represents a business domain
2. **Easier permissions management** - Grant schema-level permissions by role
3. **Better organization** - Related tables are grouped together
4. **Improved security** - Isolate sensitive data (e.g., auth) from business data
5. **Scalability** - Each domain can evolve independently
6. **Cross-schema joins** - PostgreSQL supports foreign keys and joins across schemas

