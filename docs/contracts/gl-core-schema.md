# Schema Contract — General Ledger Core (acct)

Single source of truth for fiscal years, accounting periods, transactions, and journal entries. This is the backbone of double-entry accounting. Read this before touching migrations, models, or services. Do not invent columns; update this contract first.

## Guardrails
- Schema: `acct` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Soft deletes via `deleted_at` on transactions only; journal entries cascade with parent.
- RLS required with company isolation + super-admin override.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`.
- Money precision: `debit_amount`/`credit_amount` use `numeric(15,2)`; journals MUST balance at this precision.
- Foreign currency amounts use `numeric(18,6)` with `exchange_rate numeric(18,8)`.
- All transactions must have `total_debit = total_credit` (enforced by DB constraint).

## Tables

### acct.fiscal_years
- Purpose: define company fiscal years for period management and reporting.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `name` varchar(100) not null (e.g., "FY 2025").
  - `start_date` date not null.
  - `end_date` date not null; check (end_date > start_date).
  - `is_current` boolean not null default false.
  - `is_closed` boolean not null default false.
  - `status` varchar(20) not null default 'open'. Enum: open, closing, closed.
  - `closed_at` timestamp nullable.
  - `closed_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `retained_earnings_account_id` uuid nullable FK → `acct.accounts.id` (SET NULL/CASCADE).
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `name`).
  - Unique (`company_id`, `start_date`).
  - Index: `company_id`; (`company_id`, `is_current`) where is_current = true.
  - Check: only one `is_current = true` per company (enforce via trigger or app).
- RLS:
  ```sql
  alter table acct.fiscal_years enable row level security;
  create policy fiscal_years_policy on acct.fiscal_years
    for all using (
      company_id = current_setting('app.current_company_id', true)::uuid
      or current_setting('app.is_super_admin', true)::boolean = true
    );
  ```
- Model:
  - `$connection = 'pgsql'; $table = 'acct.fiscal_years'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','name','start_date','end_date','is_current','is_closed','status','closed_at','closed_by_user_id','retained_earnings_account_id','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','start_date'=>'date','end_date'=>'date','is_current'=>'boolean','is_closed'=>'boolean','closed_at'=>'datetime','closed_by_user_id'=>'string','retained_earnings_account_id'=>'string','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; hasMany AccountingPeriod; hasMany Transaction.
- Validation:
  - `name`: required|string|max:100; unique per company.
  - `start_date`: required|date.
  - `end_date`: required|date|after:start_date.
  - `is_current`: boolean.
  - `is_closed`: boolean.
  - `status`: in:open,closing,closed.
  - `retained_earnings_account_id`: nullable|uuid|exists:acct.accounts,id (must be equity type).
- Business rules:
  - Only one fiscal year can be `is_current = true` per company.
  - Cannot delete fiscal year with posted transactions.
  - Closing a year transfers P&L balances to retained earnings account.
  - Date ranges cannot overlap within a company.
  - Year-end close creates opening balances for next year.

### acct.accounting_periods
- Purpose: sub-periods within fiscal year (monthly, quarterly, or custom).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `fiscal_year_id` uuid not null FK → `acct.fiscal_years.id` (CASCADE/CASCADE).
  - `name` varchar(100) not null (e.g., "January 2025", "Q1 2025").
  - `period_number` integer not null (1-based within fiscal year).
  - `start_date` date not null.
  - `end_date` date not null; check (end_date > start_date).
  - `period_type` varchar(20) not null default 'monthly'. Enum: monthly, quarterly, custom.
  - `is_closed` boolean not null default false.
  - `is_adjustment` boolean not null default false (for year-end adjustments).
  - `closed_at` timestamp nullable.
  - `closed_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`fiscal_year_id`, `period_number`).
  - Unique (`company_id`, `start_date`).
  - Index: `company_id`; `fiscal_year_id`; (`company_id`, `is_closed`).
- RLS: same pattern with company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.accounting_periods'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','fiscal_year_id','name','period_number','start_date','end_date','period_type','is_closed','is_adjustment','closed_at','closed_by_user_id','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','fiscal_year_id'=>'string','period_number'=>'integer','start_date'=>'date','end_date'=>'date','is_closed'=>'boolean','is_adjustment'=>'boolean','closed_at'=>'datetime','closed_by_user_id'=>'string','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo FiscalYear; hasMany Transaction.
- Validation:
  - `fiscal_year_id`: required|uuid|exists:acct.fiscal_years,id.
  - `name`: required|string|max:100.
  - `period_number`: required|integer|min:1|max:13.
  - `start_date`: required|date.
  - `end_date`: required|date|after:start_date.
  - `period_type`: in:monthly,quarterly,custom.
  - `is_adjustment`: boolean.
- Business rules:
  - Period dates must fall within parent fiscal year.
  - Periods cannot overlap within a fiscal year.
  - Cannot post to closed period (enforced by trigger).
  - Adjustment period (period 13) for year-end entries.
  - Close periods sequentially; cannot close period N+1 before N.

### acct.transactions
- Purpose: journal entry header; groups related debits/credits.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `transaction_number` varchar(50) not null; unique per company (soft-delete aware).
  - `transaction_type` varchar(30) not null. Enum: manual, invoice, bill, payment, receipt, credit_note, vendor_credit, transfer, adjustment, opening, closing.
  - `reference_type` varchar(100) nullable (e.g., 'acct.invoices', 'acct.bills').
  - `reference_id` uuid nullable (polymorphic link to source document).
  - `transaction_date` date not null.
  - `posting_date` date not null default current_date.
  - `fiscal_year_id` uuid not null FK → `acct.fiscal_years.id` (RESTRICT/CASCADE).
  - `period_id` uuid not null FK → `acct.accounting_periods.id` (RESTRICT/CASCADE).
  - `description` text nullable.
  - `currency` char(3) not null FK → `public.currencies.code` (transaction currency).
  - `base_currency` char(3) not null FK → `public.currencies.code` (company base).
  - `exchange_rate` numeric(18,8) nullable (required if currency != base_currency).
  - `total_debit` numeric(15,2) not null default 0.00.
  - `total_credit` numeric(15,2) not null default 0.00.
  - `status` varchar(20) not null default 'draft'. Enum: draft, posted, reversed, void.
  - `reversal_of_id` uuid nullable FK → `acct.transactions.id` (SET NULL/CASCADE).
  - `reversed_by_id` uuid nullable FK → `acct.transactions.id` (SET NULL/CASCADE).
  - `posted_at` timestamp nullable.
  - `posted_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `voided_at` timestamp nullable.
  - `voided_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `void_reason` varchar(255) nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `transaction_number`) where deleted_at is null.
  - Index: `company_id`; (`company_id`, `transaction_date`); (`company_id`, `status`); (`reference_type`, `reference_id`); `fiscal_year_id`; `period_id`.
  - Check: `total_debit = total_credit` (balanced entry).
  - Check: `total_debit >= 0` and `total_credit >= 0`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.transactions'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','transaction_number','transaction_type','reference_type','reference_id','transaction_date','posting_date','fiscal_year_id','period_id','description','currency','base_currency','exchange_rate','total_debit','total_credit','status','reversal_of_id','reversed_by_id','posted_at','posted_by_user_id','voided_at','voided_by_user_id','void_reason','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','reference_id'=>'string','transaction_date'=>'date','posting_date'=>'date','fiscal_year_id'=>'string','period_id'=>'string','exchange_rate'=>'decimal:8','total_debit'=>'decimal:2','total_credit'=>'decimal:2','reversal_of_id'=>'string','reversed_by_id'=>'string','posted_at'=>'datetime','posted_by_user_id'=>'string','voided_at'=>'datetime','voided_by_user_id'=>'string','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo FiscalYear; belongsTo AccountingPeriod; hasMany JournalEntry; belongsTo ReversalOf (self); hasOne ReversedBy (self).
- Validation:
  - `transaction_number`: required|string|max:50; unique per company (soft-delete aware).
  - `transaction_type`: required|in:manual,invoice,bill,payment,receipt,credit_note,vendor_credit,transfer,adjustment,opening,closing.
  - `transaction_date`: required|date.
  - `posting_date`: required|date.
  - `fiscal_year_id`: required|uuid|exists:acct.fiscal_years,id.
  - `period_id`: required|uuid|exists:acct.accounting_periods,id.
  - `currency`: required|string|size:3|uppercase.
  - `base_currency`: required|string|size:3|uppercase.
  - `exchange_rate`: nullable|numeric|min:0.00000001 (required if currency != base_currency).
  - `status`: in:draft,posted,reversed,void.
  - `entries`: required|array|min:2 (at least one debit and one credit).
- Business rules:
  - Transaction MUST balance: total_debit = total_credit.
  - Cannot post to closed period.
  - Posted transactions cannot be edited; must reverse and re-enter.
  - Reversal creates new transaction with opposite entries.
  - Void marks transaction as void but keeps for audit trail.
  - Auto-assign period based on transaction_date if not specified.

### acct.journal_entries
- Purpose: individual debit/credit lines within a transaction.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `transaction_id` uuid not null FK → `acct.transactions.id` (CASCADE/CASCADE).
  - `account_id` uuid not null FK → `acct.accounts.id` (RESTRICT/CASCADE).
  - `line_number` integer not null.
  - `description` text nullable.
  - `debit_amount` numeric(15,2) not null default 0.00; check >= 0.
  - `credit_amount` numeric(15,2) not null default 0.00; check >= 0.
  - `currency_debit` numeric(18,6) nullable (foreign currency amount).
  - `currency_credit` numeric(18,6) nullable (foreign currency amount).
  - `exchange_rate` numeric(18,8) nullable (line-level rate if different from header).
  - `reference_type` varchar(100) nullable (line-level reference).
  - `reference_id` uuid nullable.
  - `dimension_1` varchar(100) nullable (cost center/department).
  - `dimension_2` varchar(100) nullable (project/job).
  - `dimension_3` varchar(100) nullable (location/branch).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`transaction_id`, `line_number`).
  - Index: `company_id`; `transaction_id`; `account_id`; (`company_id`, `account_id`).
  - Check: `(debit_amount > 0 AND credit_amount = 0) OR (credit_amount > 0 AND debit_amount = 0)` — each line is either debit or credit, not both.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.journal_entries'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','transaction_id','account_id','line_number','description','debit_amount','credit_amount','currency_debit','currency_credit','exchange_rate','reference_type','reference_id','dimension_1','dimension_2','dimension_3'];`
  - `$casts = ['company_id'=>'string','transaction_id'=>'string','account_id'=>'string','line_number'=>'integer','debit_amount'=>'decimal:2','credit_amount'=>'decimal:2','currency_debit'=>'decimal:6','currency_credit'=>'decimal:6','exchange_rate'=>'decimal:8','reference_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Transaction; belongsTo Account.
- Validation:
  - `transaction_id`: required|uuid|exists:acct.transactions,id.
  - `account_id`: required|uuid|exists:acct.accounts,id.
  - `line_number`: required|integer|min:1.
  - `debit_amount`: numeric|min:0.
  - `credit_amount`: numeric|min:0.
  - XOR: exactly one of debit_amount or credit_amount must be > 0.
- Business rules:
  - Each line is either debit OR credit, never both.
  - Line numbers sequential within transaction.
  - Sum of all debit_amount must equal sum of all credit_amount (enforced at transaction level).
  - Trigger recomputes transaction totals on insert/update/delete.
  - Cannot modify lines of posted transaction.

## Database Triggers

### trg_journal_entries_balance
```sql
-- After insert/update/delete on journal_entries, recompute transaction totals
create or replace function acct.recompute_transaction_totals()
returns trigger as $$
begin
  update acct.transactions t
  set total_debit = coalesce(s.sum_debit, 0),
      total_credit = coalesce(s.sum_credit, 0)
  from (
    select transaction_id,
           sum(debit_amount) as sum_debit,
           sum(credit_amount) as sum_credit
    from acct.journal_entries
    where transaction_id = coalesce(new.transaction_id, old.transaction_id)
    group by transaction_id
  ) s
  where t.id = coalesce(new.transaction_id, old.transaction_id);
  return null;
end;
$$ language plpgsql;

create trigger journal_entries_aiud
after insert or update or delete on acct.journal_entries
for each row execute function acct.recompute_transaction_totals();
```

### trg_check_period_open
```sql
-- Before insert/update on transactions, verify period is open
create or replace function acct.check_period_open()
returns trigger as $$
declare v_closed boolean;
begin
  select is_closed into v_closed
  from acct.accounting_periods
  where id = new.period_id;

  if v_closed then
    raise exception 'Cannot post to closed period %', new.period_id;
  end if;
  return new;
end;
$$ language plpgsql;

create trigger transactions_biu_period
before insert or update on acct.transactions
for each row execute function acct.check_period_open();
```

## Enums Reference

### Transaction Type
| Type | Description | Auto-Generated? |
|------|-------------|-----------------|
| manual | Manual journal entry | No |
| invoice | From AR invoice posting | Yes |
| bill | From AP bill posting | Yes |
| payment | From AR payment | Yes |
| receipt | From AR receipt | Yes |
| credit_note | From credit note | Yes |
| vendor_credit | From vendor credit | Yes |
| transfer | Bank transfer | Manual |
| adjustment | Period adjustment | Manual |
| opening | Opening balances | Auto |
| closing | Year-end close | Auto |

### Transaction Status
| Status | Description | Editable? |
|--------|-------------|-----------|
| draft | Not yet posted | Yes |
| posted | Posted to GL | No |
| reversed | Reversed by another entry | No |
| void | Voided (audit trail kept) | No |

## Form Behaviors

### Manual Journal Entry Form
- Fields: transaction_date, description, entries[]
- Each entry: account_id, description, debit_amount OR credit_amount
- Running totals displayed; submit disabled until balanced
- Account picker filtered by company, shows code + name
- Default posting_date = transaction_date
- Auto-assign period based on transaction_date

### Period Close Form
- Select period to close
- Shows unposted transactions count (must be 0)
- Shows trial balance for period
- Confirm button with warning
- Sets is_closed = true, closed_at = now(), closed_by_user_id

### Year-End Close Form
- Select fiscal year
- Shows all periods (must all be closed)
- Specify retained earnings account
- Preview closing entries (revenue/expense → retained earnings)
- Confirm creates closing transaction + opens next year

## Out of Scope (v1)
- Multi-company consolidation.
- Intercompany eliminations.
- Sub-ledger reconciliation views.
- Dimension hierarchies (flat dimensions only).
- Budgets and forecasts.

## Extending
- Add new transaction_type values here first.
- Dimension fields (1-3) can be used for cost centers, projects, locations.
- Consider adding `acct.dimension_values` table for controlled values in future.
