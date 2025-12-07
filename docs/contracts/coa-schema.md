# Schema Contract — Chart of Accounts (acct)

Single source of truth for the company chart of accounts used by AP/AR/GL. Update this before touching migrations, models, requests, or services. No columns or enums may be added without updating this contract first.

## Guardrails
- Schema: `acct` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` defaults where applicable.
- Currency: `char(3)` referencing `public.currencies(code)`; account currency follows multi-currency rules (see `docs/contracts/multicurrency-rules.md`).
- Soft deletes via `deleted_at`; uniqueness filtered on `deleted_at IS NULL`.
- RLS required with company isolation + super-admin override, using safe `current_setting(..., true)`.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`, `$keyType = 'string'`, `$incrementing = false`.

## Tables

### acct.accounts
- Purpose: canonical chart of accounts per company; referenced by AP/AR/GL.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `parent_id` uuid nullable FK → `acct.accounts.id` (SET NULL/CASCADE).
  - `code` varchar(50) not null; unique per company (soft-delete aware).
  - `name` varchar(255) not null.
  - `type` varchar(30) not null. Allowed: asset, liability, equity, revenue, expense, cogs, other_income, other_expense.
  - `subtype` varchar(50) not null. Allowed examples (must map to type): bank, cash, accounts_receivable, accounts_payable, credit_card, other_current_asset, other_asset, inventory, fixed_asset, other_current_liability, other_liability, loan_payable, equity, retained_earnings, revenue, cogs, expense, other_income, other_expense.
  - `normal_balance` varchar(6) not null (debit|credit).
  - `currency` char(3) nullable FK → `public.currencies.code`. Base-only types (revenue, cogs, expense, other_income, other_expense, equity) must be NULL. Foreign currency allowed only for: bank, cash, accounts_receivable, accounts_payable, credit_card, other_current_asset, other_asset, other_current_liability, other_liability.
  - `is_active` boolean not null default true.
  - `is_system` boolean not null default false (lock from edits/deletion).
  - `description` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps; `deleted_at` soft delete.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `code`) where `deleted_at is null`.
  - Indexes: `company_id`; (`company_id`, `type`, `subtype`); (`company_id`, `is_active`).
  - Check: `normal_balance` in (debit, credit).
  - Check: currency allowed only for the specified subtypes; base-only types force currency NULL or company base.
- RLS:
  ```sql
  alter table acct.accounts enable row level security;
  create policy accounts_policy on acct.accounts
    for all
    using (
      company_id = current_setting('app.current_company_id', true)::uuid
      or current_setting('app.is_super_admin', true)::boolean = true
    );
  ```
- Model (canonical):
  - `$connection = 'pgsql'; $table = 'acct.accounts'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','parent_id','code','name','type','subtype','normal_balance','currency','is_active','is_system','description','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','parent_id'=>'string','is_active'=>'boolean','is_system'=>'boolean','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Validation:
  - `code`: required|string|max:50; unique per company (soft-delete aware).
  - `name`: required|string|max:255.
  - `type`: required|in:asset,liability,equity,revenue,expense,cogs,other_income,other_expense.
  - `subtype`: required|string|max:50; must align with `type` per mapping above.
  - `normal_balance`: required|in:debit,credit; must align with type (assets/expenses/cogs → debit; liabilities/equity/revenue/other_income/other_expense → credit).
  - `currency`: nullable|string|size:3|uppercase; must be enabled for company; must be NULL for base-only types; required/allowed only for foreign-capable subtypes.
  - `is_active`: boolean.
  - `is_system`: boolean.
  - `parent_id`: nullable|uuid|exists:acct.accounts,id (same company).
- Business rules:
  - Code unique per company, soft-delete aware.
  - Account currency immutable after postings; base-only types cannot use foreign currency.
  - Prevent deleting system accounts or accounts with posted journal lines.
  - Parent must belong to same company.
  - Default `normal_balance`: debit for asset/expense/cogs; credit for liability/equity/revenue/other_income/other_expense.
