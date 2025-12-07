# Schema Contract — Banking & Cash Management (bank)

Single source of truth for bank accounts, transactions, and reconciliations. Read this before touching migrations, models, or services.

## Guardrails
- Schema: `bank` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Soft deletes via `deleted_at` on bank accounts and transactions.
- RLS required with company isolation + super-admin override.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`.
- Money precision: `numeric(15,2)` for balances; `numeric(18,6)` for transaction amounts.
- Bank account currency is immutable after transactions exist.
- GL account linkage required for posting to general ledger.

## Tables

### bank.banks
- Purpose: reference table for bank institutions (optional, for dropdown selection).
- Columns:
  - `id` uuid PK.
  - `name` varchar(255) not null.
  - `swift_code` varchar(11) nullable.
  - `country_code` char(2) nullable FK → `public.countries.code`.
  - `logo_url` varchar(500) nullable.
  - `website` varchar(255) nullable.
  - `is_active` boolean not null default true.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `swift_code`; `country_code`.
- RLS: None (public reference data).
- Model:
  - `$connection = 'pgsql'; $table = 'bank.banks'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['name','swift_code','country_code','logo_url','website','is_active'];`
  - `$casts = ['is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Validation:
  - `name`: required|string|max:255.
  - `swift_code`: nullable|string|max:11|regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/.
  - `country_code`: nullable|string|size:2|exists:public.countries,code.
- Business rules:
  - Seed with common banks; users can add custom entries.
  - Not tenant-scoped; shared across all companies.

### bank.company_bank_accounts
- Purpose: company's bank accounts for receiving/making payments.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `bank_id` uuid nullable FK → `bank.banks.id` (SET NULL/CASCADE).
  - `gl_account_id` uuid nullable FK → `acct.accounts.id` (RESTRICT/CASCADE).
  - `account_name` varchar(255) not null (display name, e.g., "Main Operating Account").
  - `account_number` varchar(100) not null.
  - `account_type` varchar(30) not null default 'checking'. Enum: checking, savings, credit_card, cash, other.
  - `currency` char(3) not null FK → `public.currencies.code`.
  - `iban` varchar(34) nullable.
  - `swift_code` varchar(11) nullable.
  - `routing_number` varchar(50) nullable.
  - `branch_name` varchar(255) nullable.
  - `branch_address` text nullable.
  - `opening_balance` numeric(15,2) not null default 0.00.
  - `opening_balance_date` date nullable.
  - `current_balance` numeric(15,2) not null default 0.00.
  - `last_reconciled_balance` numeric(15,2) nullable.
  - `last_reconciled_date` date nullable.
  - `is_primary` boolean not null default false.
  - `is_active` boolean not null default true.
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `account_number`) where deleted_at is null.
  - Index: `company_id`; (`company_id`, `is_active`); (`company_id`, `is_primary`).
- RLS:
  ```sql
  alter table bank.company_bank_accounts enable row level security;
  create policy company_bank_accounts_policy on bank.company_bank_accounts
    for all using (
      company_id = current_setting('app.current_company_id', true)::uuid
      or current_setting('app.is_super_admin', true)::boolean = true
    );
  ```
- Model:
  - `$connection = 'pgsql'; $table = 'bank.company_bank_accounts'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','bank_id','gl_account_id','account_name','account_number','account_type','currency','iban','swift_code','routing_number','branch_name','branch_address','opening_balance','opening_balance_date','current_balance','last_reconciled_balance','last_reconciled_date','is_primary','is_active','notes','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','bank_id'=>'string','gl_account_id'=>'string','opening_balance'=>'decimal:2','opening_balance_date'=>'date','current_balance'=>'decimal:2','last_reconciled_balance'=>'decimal:2','last_reconciled_date'=>'date','is_primary'=>'boolean','is_active'=>'boolean','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Bank; belongsTo GlAccount (Account); hasMany BankTransaction; hasMany BankReconciliation.
- Validation:
  - `account_name`: required|string|max:255.
  - `account_number`: required|string|max:100; unique per company (soft-delete aware).
  - `account_type`: required|in:checking,savings,credit_card,cash,other.
  - `currency`: required|string|size:3|uppercase; must be enabled for company.
  - `iban`: nullable|string|max:34|regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/.
  - `swift_code`: nullable|string|max:11.
  - `routing_number`: nullable|string|max:50.
  - `opening_balance`: numeric.
  - `opening_balance_date`: nullable|date.
  - `gl_account_id`: nullable|uuid|exists:acct.accounts,id (must be bank/cash subtype).
  - `is_primary`: boolean.
  - `is_active`: boolean.
- Business rules:
  - Only one `is_primary = true` per company (enforce via app/trigger).
  - Currency immutable after transactions exist.
  - GL account must be bank or cash subtype.
  - current_balance updated by triggers on bank_transactions.
  - Cannot delete account with unreconciled transactions.

### bank.bank_transactions
- Purpose: individual transactions (deposits, withdrawals, transfers, fees).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `bank_account_id` uuid not null FK → `bank.company_bank_accounts.id` (CASCADE/CASCADE).
  - `reconciliation_id` uuid nullable FK → `bank.bank_reconciliations.id` (SET NULL/CASCADE).
  - `transaction_date` date not null.
  - `value_date` date nullable (settlement date).
  - `description` text not null.
  - `reference_number` varchar(100) nullable.
  - `transaction_type` varchar(30) not null. Enum: deposit, withdrawal, transfer_in, transfer_out, fee, interest, adjustment, opening.
  - `amount` numeric(18,6) not null. Positive for inflows, negative for outflows.
  - `balance_after` numeric(15,2) nullable (running balance).
  - `payee_name` varchar(255) nullable.
  - `category` varchar(100) nullable (user classification).
  - `is_reconciled` boolean not null default false.
  - `reconciled_date` date nullable.
  - `reconciled_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `matched_payment_id` uuid nullable FK → `acct.payments.id` (SET NULL/CASCADE).
  - `matched_bill_payment_id` uuid nullable FK → `acct.bill_payments.id` (SET NULL/CASCADE).
  - `gl_transaction_id` uuid nullable FK → `acct.transactions.id` (SET NULL/CASCADE).
  - `source` varchar(30) not null default 'manual'. Enum: manual, import, feed, system.
  - `external_id` varchar(255) nullable (bank feed reference).
  - `raw_data` jsonb nullable (original import data).
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `bank_account_id`; (`bank_account_id`, `transaction_date`); (`company_id`, `is_reconciled`); `external_id`.
  - Check: NOT (`matched_payment_id` IS NOT NULL AND `matched_bill_payment_id` IS NOT NULL) — can match to AR or AP, not both.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'bank.bank_transactions'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','bank_account_id','reconciliation_id','transaction_date','value_date','description','reference_number','transaction_type','amount','balance_after','payee_name','category','is_reconciled','reconciled_date','reconciled_by_user_id','matched_payment_id','matched_bill_payment_id','gl_transaction_id','source','external_id','raw_data','notes','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','bank_account_id'=>'string','reconciliation_id'=>'string','transaction_date'=>'date','value_date'=>'date','amount'=>'decimal:6','balance_after'=>'decimal:2','is_reconciled'=>'boolean','reconciled_date'=>'date','reconciled_by_user_id'=>'string','matched_payment_id'=>'string','matched_bill_payment_id'=>'string','gl_transaction_id'=>'string','raw_data'=>'array','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo BankAccount; belongsTo Reconciliation; belongsTo Payment (AR); belongsTo BillPayment (AP); belongsTo GlTransaction (Transaction).
- Validation:
  - `bank_account_id`: required|uuid|exists:bank.company_bank_accounts,id.
  - `transaction_date`: required|date.
  - `description`: required|string.
  - `transaction_type`: required|in:deposit,withdrawal,transfer_in,transfer_out,fee,interest,adjustment,opening.
  - `amount`: required|numeric (positive for inflows, negative for outflows).
  - `source`: in:manual,import,feed,system.
- Business rules:
  - Amount sign convention: positive = money in, negative = money out.
  - Trigger updates bank account current_balance on insert/update/delete.
  - Cannot delete reconciled transaction.
  - Matching to AR payment or AP bill payment creates GL posting.
  - external_id used for deduplication on imports.

### bank.bank_reconciliations
- Purpose: reconciliation header for matching bank statements.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `bank_account_id` uuid not null FK → `bank.company_bank_accounts.id` (CASCADE/CASCADE).
  - `statement_date` date not null.
  - `statement_ending_balance` numeric(15,2) not null.
  - `book_balance` numeric(15,2) not null (system balance at statement date).
  - `reconciled_balance` numeric(15,2) not null default 0.00.
  - `difference` numeric(15,2) not null default 0.00.
  - `status` varchar(20) not null default 'in_progress'. Enum: in_progress, completed, cancelled.
  - `started_at` timestamp not null default now().
  - `completed_at` timestamp nullable.
  - `completed_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`bank_account_id`, `statement_date`).
  - Index: `company_id`; `bank_account_id`; (`company_id`, `status`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'bank.bank_reconciliations'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','bank_account_id','statement_date','statement_ending_balance','book_balance','reconciled_balance','difference','status','started_at','completed_at','completed_by_user_id','notes','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','bank_account_id'=>'string','statement_date'=>'date','statement_ending_balance'=>'decimal:2','book_balance'=>'decimal:2','reconciled_balance'=>'decimal:2','difference'=>'decimal:2','started_at'=>'datetime','completed_at'=>'datetime','completed_by_user_id'=>'string','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo BankAccount; hasMany BankTransaction (reconciled items).
- Validation:
  - `bank_account_id`: required|uuid|exists:bank.company_bank_accounts,id.
  - `statement_date`: required|date; unique per bank account.
  - `statement_ending_balance`: required|numeric.
  - `status`: in:in_progress,completed,cancelled.
- Business rules:
  - difference = statement_ending_balance - reconciled_balance.
  - Can only complete when difference = 0.
  - Completing updates bank account last_reconciled_date and last_reconciled_balance.
  - Linked transactions marked as reconciled.
  - Cannot complete reconciliation before previous period.

### bank.bank_rules
- Purpose: auto-categorization rules for imported transactions.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `bank_account_id` uuid nullable FK → `bank.company_bank_accounts.id` (SET NULL/CASCADE).
  - `name` varchar(255) not null.
  - `priority` integer not null default 0.
  - `conditions` jsonb not null. Keys: field (description, payee_name, amount, reference_number), operator (contains, equals, starts_with, ends_with, regex, gt, lt, between), value.
  - `actions` jsonb not null. Keys: set_category, set_payee, set_gl_account_id, set_transaction_type, auto_match_customer, auto_match_vendor.
  - `is_active` boolean not null default true.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; (`company_id`, `is_active`, `priority`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'bank.bank_rules'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','bank_account_id','name','priority','conditions','actions','is_active','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','bank_account_id'=>'string','priority'=>'integer','conditions'=>'array','actions'=>'array','is_active'=>'boolean','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Validation:
  - `name`: required|string|max:255.
  - `priority`: integer|min:0.
  - `conditions`: required|array.
  - `actions`: required|array.
  - `is_active`: boolean.
- Business rules:
  - Rules processed in priority order (lower number = higher priority).
  - First matching rule wins.
  - bank_account_id null means applies to all accounts.
  - Applied on import/feed; manual transactions can bypass.

## Database Triggers

### trg_update_bank_balance
```sql
-- After insert/update/delete on bank_transactions, update bank account balance
create or replace function bank.update_account_balance()
returns trigger as $$
declare
  v_account_id uuid;
  v_new_balance numeric(15,2);
begin
  v_account_id := coalesce(new.bank_account_id, old.bank_account_id);

  select coalesce(sum(amount), 0) + opening_balance
  into v_new_balance
  from bank.company_bank_accounts a
  left join bank.bank_transactions t on t.bank_account_id = a.id and t.deleted_at is null
  where a.id = v_account_id
  group by a.opening_balance;

  update bank.company_bank_accounts
  set current_balance = v_new_balance
  where id = v_account_id;

  return null;
end;
$$ language plpgsql;

create trigger bank_transactions_aiud
after insert or update or delete on bank.bank_transactions
for each row execute function bank.update_account_balance();
```

## Enums Reference

### Account Type
| Type | Description |
|------|-------------|
| checking | Standard checking/current account |
| savings | Savings account |
| credit_card | Credit card account (balance typically negative) |
| cash | Petty cash / cash drawer |
| other | Other types |

### Transaction Type
| Type | Description | Amount Sign |
|------|-------------|-------------|
| deposit | Money received | Positive |
| withdrawal | Money paid out | Negative |
| transfer_in | Transfer from another account | Positive |
| transfer_out | Transfer to another account | Negative |
| fee | Bank fees | Negative |
| interest | Interest earned | Positive |
| adjustment | Manual adjustment | Either |
| opening | Opening balance entry | Either |

### Reconciliation Status
| Status | Description |
|--------|-------------|
| in_progress | Currently being reconciled |
| completed | Successfully reconciled (difference = 0) |
| cancelled | Abandoned without completion |

## Form Behaviors

### Bank Account Form
- Fields: account_name, account_number, account_type, currency, bank_id (dropdown), gl_account_id, opening_balance, opening_balance_date, is_primary, is_active
- Currency dropdown filtered to company-enabled currencies
- GL account dropdown filtered to bank/cash subtypes
- Warning if making non-primary account primary (will unset others)

### Bank Transaction Form
- Fields: transaction_date, transaction_type, amount, description, reference_number, payee_name, category
- Amount input: absolute value; sign determined by transaction_type
- Quick category suggestions based on bank_rules
- Option to create/match to AR payment or AP bill payment

### Reconciliation Form
- Start: select bank account, enter statement_date and statement_ending_balance
- Shows unreconciled transactions
- Check/uncheck to mark as reconciled
- Running difference displayed
- Complete button enabled only when difference = 0
- Option to create adjustment transaction for small differences

### Import Transactions Form
- Upload CSV/OFX/QIF file
- Preview with column mapping
- Duplicate detection via external_id or date+amount+description
- Apply bank rules for categorization
- Review and confirm import

## Out of Scope (v1)
- Direct bank feed integration (Plaid, Yodlee).
- Multi-currency bank accounts (single currency per account).
- Automatic payment matching AI.
- Bank statement PDF parsing.
- Positive pay / check fraud prevention.

## Extending
- Add new transaction_type values here first.
- Bank feed integration would add `bank.bank_feeds` and `bank.feed_connections` tables.
- Consider `bank.transfers` table for explicit internal transfers.
