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
  - `is_contra` boolean not null default false. True for contra accounts. Contra accounts have `normal_balance` opposite to their type category (e.g., contra-asset has credit normal_balance). Used for UI/reporting grouping.
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
  - `$fillable = ['company_id','parent_id','code','name','type','subtype','normal_balance','currency','is_contra','is_active','is_system','description','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','parent_id'=>'string','is_contra'=>'boolean','is_active'=>'boolean','is_system'=>'boolean','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Validation:
  - `code`: required|string|max:50; unique per company (soft-delete aware).
  - `name`: required|string|max:255.
  - `type`: required|in:asset,liability,equity,revenue,expense,cogs,other_income,other_expense.
  - `subtype`: required|string|max:50; must align with `type` per mapping above.
  - `normal_balance`: required|in:debit,credit. Standard accounts follow type (assets/expenses→debit); contra accounts have opposite.
  - `currency`: nullable|string|size:3|uppercase; must be enabled for company; must be NULL for base-only types; required/allowed only for foreign-capable subtypes.
  - `is_contra`: boolean (default false).
  - `is_active`: boolean.
  - `is_system`: boolean.
  - `parent_id`: nullable|uuid|exists:acct.accounts,id (same company).
- Business rules:
  - Code unique per company, soft-delete aware.
  - Account currency immutable after postings; base-only types cannot use foreign currency.
  - Prevent deleting system accounts or accounts with posted journal lines.
  - Parent must belong to same company.
  - Default `normal_balance`: debit for asset/expense/cogs; credit for liability/equity/revenue/other_income/other_expense.

### acct.account_templates (global catalog)
- Purpose: global, company-agnostic templates that populate the account creation dropdowns; selected templates are copied into `acct.accounts` for each company.
- Columns:
  - `id` uuid PK.
  - `code` varchar(50) not null; global unique.
  - `name` varchar(255) not null.
  - `type` varchar(30) not null (allowed: asset, liability, equity, revenue, expense, cogs, other_income, other_expense).
  - `subtype` varchar(50) not null (must align with `type` per mapping above).
  - `normal_balance` varchar(6) not null (debit|credit) derived from type.
  - `is_contra` boolean not null default false. True for contra accounts. Contra accounts have `normal_balance` opposite to their type category (e.g., contra-asset has credit normal_balance). Used for UI/reporting grouping.
  - `is_active` boolean not null default true.
  - `description` text nullable.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique `code`.
  - Index: (`type`, `subtype`); (`is_active`).
  - Checks: `type` allowed list; `normal_balance` in (debit, credit).
  - Note: No type→normal_balance constraint. Contra accounts (`is_contra=true`) have opposite normal_balance to their type category (e.g., contra-asset has credit normal_balance).
- RLS: not required (global template data, no `company_id`).
- Model (canonical):
  - `$connection = 'pgsql'; $table = 'acct.account_templates'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['code','name','type','subtype','normal_balance','is_contra','is_active','description'];`
  - `$casts = ['is_contra'=>'boolean','is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Validation:
  - `code`: required|string|max:50|unique:acct.account_templates,code.
  - `name`: required|string|max:255.
  - `type`: required|in:asset,liability,equity,revenue,expense,cogs,other_income,other_expense.
  - `subtype`: required|string|max:50; must align with type.
  - `normal_balance`: required|in:debit,credit. Standard accounts follow type (assets/expenses→debit); contra accounts have opposite.
  - `is_contra`: boolean (default false).
  - `is_active`: boolean.
- Business rules:
  - Used to seed/selectable options across companies; selections instantiate company accounts with matching code/name/type/subtype/normal_balance/is_contra.
  - Keep codes non-overlapping and descriptive to avoid collisions when copied into tenant charts.
  - **normal_balance** = "which side increases this account". Standard accounts follow type (assets/expenses→debit, liabilities/equity/revenue→credit). Contra accounts have the opposite.
  - **is_contra** = UI/reporting flag indicating the account should be displayed under its type but with opposite sign (e.g., Accumulated Depreciation under Assets but subtracted).

---

## Integration Points

### What Links to Accounts

The Chart of Accounts is the central reference for all financial postings. These tables reference `acct.accounts.id`:

| Table | Column | Account Constraint | Purpose |
|-------|--------|-------------------|---------|
| `acct.customers` | `ar_account_id` | subtype = 'accounts_receivable' | Customer's AR account |
| `acct.vendors` | `ap_account_id` | subtype = 'accounts_payable' | Vendor's AP account |
| `acct.payments` | `deposit_account_id` | subtype IN ('bank','cash') | Where payment is deposited |
| `acct.bill_payments` | `payment_account_id` | subtype IN ('bank','cash','credit_card') | Where payment comes from |
| `acct.invoice_line_items` | `income_account_id` | type = 'revenue' | Revenue recognition |
| `acct.bill_line_items` | `expense_account_id` | type IN ('expense','cogs','asset') | Expense/asset recognition |
| `acct.journal_entries` | `account_id` | Any active account | GL posting lines |
| `bank.company_bank_accounts` | `gl_account_id` | subtype IN ('bank','cash') | Bank ↔ GL link |
| `inv.items` | `income_account_id` | type = 'revenue' | Product sales revenue |
| `inv.items` | `expense_account_id` | type IN ('expense','cogs') | Product COGS |
| `inv.items` | `asset_account_id` | subtype = 'inventory' | Inventory asset |

### Account Selectors by Use Case

Each dropdown in the UI needs specific filtering:

```typescript
// Account selector configurations
const ACCOUNT_FILTERS = {
  // AR/AP accounts
  ar_account: { subtype: 'accounts_receivable' },
  ap_account: { subtype: 'accounts_payable' },

  // Payment accounts
  deposit_to: { subtype: ['bank', 'cash'] },
  pay_from: { subtype: ['bank', 'cash', 'credit_card'] },

  // Income/Expense
  income: { type: 'revenue' },
  expense: { type: ['expense', 'cogs', 'asset'] },

  // Inventory
  inventory_asset: { subtype: 'inventory' },
  cogs: { type: 'cogs' },

  // Year-end
  retained_earnings: { subtype: 'retained_earnings' },

  // All postable
  all: { is_active: true }
};
```

### Default Accounts

Company settings should store default account IDs:

| Setting | Subtype/Type | Used By |
|---------|--------------|---------|
| `default_ar_account_id` | accounts_receivable | Customers without specific AR |
| `default_ap_account_id` | accounts_payable | Vendors without specific AP |
| `default_income_account_id` | revenue | Invoice lines without specific income account |
| `default_expense_account_id` | expense | Bill lines without specific expense account |
| `default_bank_account_id` | bank | Payments without specific bank |
| `retained_earnings_account_id` | retained_earnings | Year-end close |
| `sales_tax_payable_account_id` | other_current_liability | Output tax |
| `purchase_tax_receivable_account_id` | other_current_asset | Input tax |

### Account Type → Normal Balance Mapping

Enforced by database check constraint:

| Type | Normal Balance | Debit Increases | Credit Increases |
|------|----------------|-----------------|------------------|
| asset | debit | ✅ | |
| expense | debit | ✅ | |
| cogs | debit | ✅ | |
| other_expense | debit | ✅ | |
| liability | credit | | ✅ |
| equity | credit | | ✅ |
| revenue | credit | | ✅ |
| other_income | credit | | ✅ |

### Subtype → Type Mapping

Valid combinations:

| Subtype | Valid Types |
|---------|-------------|
| bank, cash, accounts_receivable, other_current_asset, inventory | asset |
| fixed_asset, other_asset | asset |
| accounts_payable, credit_card, other_current_liability | liability |
| other_liability, loan_payable | liability |
| equity, retained_earnings | equity |
| revenue | revenue |
| cogs | cogs |
| expense | expense |
| other_income | other_income |
| other_expense | other_expense |

### System Accounts (Seeded on Company Creation)

These accounts are created automatically and marked `is_system = true`:

| Code | Name | Type | Subtype |
|------|------|------|---------|
| 1100 | Accounts Receivable | asset | accounts_receivable |
| 2100 | Accounts Payable | liability | accounts_payable |
| 3100 | Retained Earnings | equity | retained_earnings |
| 4100 | Sales Revenue | revenue | revenue |
| 5100 | Cost of Goods Sold | cogs | cogs |
| 6100 | General Expense | expense | expense |

### Query Helpers

```php
// Account.php - Scopes for filtering
public function scopeBankAccounts($query)
{
    return $query->whereIn('subtype', ['bank', 'cash']);
}

public function scopeRevenueAccounts($query)
{
    return $query->where('type', 'revenue');
}

public function scopeExpenseAccounts($query)
{
    return $query->whereIn('type', ['expense', 'cogs', 'asset']);
}

public function scopeReceivableAccounts($query)
{
    return $query->where('subtype', 'accounts_receivable');
}

public function scopePayableAccounts($query)
{
    return $query->where('subtype', 'accounts_payable');
}
```

### Account Immutability Rules

| Property | Mutable Before Postings | Mutable After Postings |
|----------|------------------------|------------------------|
| code | ✅ | ❌ |
| name | ✅ | ✅ |
| type | ✅ | ❌ |
| subtype | ✅ | ❌ |
| currency | ✅ | ❌ |
| is_active | ✅ | ✅ (but cannot deactivate with balance) |
| is_system | ❌ | ❌ |
| description | ✅ | ✅ |

### Migration Dependency

COA depends on:
1. `auth.companies` (company_id FK)
2. `public.currencies` (currency FK)
3. `auth.users` (created_by, updated_by FK)

COA is required by:
1. `acct.transactions` / `acct.journal_entries` (GL)
2. `acct.customers` / `acct.vendors` (AR/AP)
3. `acct.payments` / `acct.bill_payments` (Payments)
4. `bank.company_bank_accounts` (Banking)
5. `inv.items` (Inventory)

**Order**: COA migration runs after auth, before GL/AR/AP/Banking/Inventory.

---

## Extending
- Add new type/subtype values here first.
- Consider `acct.account_templates` for chart of accounts templates.
- Multi-company consolidation would need mapping tables.
