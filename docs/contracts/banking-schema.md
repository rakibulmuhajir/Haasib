# Schema Contract — Banking & Cash Management

Single source of truth for bank accounts, transactions, and reconciliations. Read this before touching migrations, models, or services.

**Note**: All banking tables reside in the `acct` schema alongside other accounting tables, not a separate `bank` schema. This consolidates all financial data under one schema for simpler RLS policies and joins.

## Guardrails
- Schema: `acct` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Soft deletes via `deleted_at` on bank accounts and transactions.
- RLS required with company isolation + super-admin override.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`.
- Money precision: `numeric(15,2)` for balances; `numeric(18,6)` for transaction amounts.
- Bank account currency is immutable after transactions exist.
- GL account linkage required for posting to general ledger.
- **Module**: All models live in `App\Modules\Accounting\Models`.

## Tables

### acct.banks
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
  - `$connection = 'pgsql'; $table = 'acct.banks'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['name','swift_code','country_code','logo_url','website','is_active'];`
  - `$casts = ['is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Validation:
  - `name`: required|string|max:255.
  - `swift_code`: nullable|string|max:11|regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/.
  - `country_code`: nullable|string|size:2|exists:public.countries,code.
- Business rules:
  - Seed with common banks; users can add custom entries.
  - Not tenant-scoped; shared across all companies.

### acct.company_bank_accounts
- Purpose: company's bank accounts for receiving/making payments.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `bank_id` uuid nullable FK → `acct.banks.id` (SET NULL/CASCADE).
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
  alter table acct.company_bank_accounts enable row level security;
  create policy company_bank_accounts_policy on acct.company_bank_accounts
    for all using (
      company_id = current_setting('app.current_company_id', true)::uuid
      or current_setting('app.is_super_admin', true)::boolean = true
    );
  ```
- Model:
  - `$connection = 'pgsql'; $table = 'acct.company_bank_accounts'; $keyType = 'string'; public $incrementing = false;`
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

### acct.bank_transactions
- Purpose: individual transactions (deposits, withdrawals, transfers, fees).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `bank_account_id` uuid not null FK → `acct.company_bank_accounts.id` (CASCADE/CASCADE).
  - `reconciliation_id` uuid nullable FK → `acct.bank_reconciliations.id` (SET NULL/CASCADE).
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
  - `$connection = 'pgsql'; $table = 'acct.bank_transactions'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','bank_account_id','reconciliation_id','transaction_date','value_date','description','reference_number','transaction_type','amount','balance_after','payee_name','category','is_reconciled','reconciled_date','reconciled_by_user_id','matched_payment_id','matched_bill_payment_id','gl_transaction_id','source','external_id','raw_data','notes','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','bank_account_id'=>'string','reconciliation_id'=>'string','transaction_date'=>'date','value_date'=>'date','amount'=>'decimal:6','balance_after'=>'decimal:2','is_reconciled'=>'boolean','reconciled_date'=>'date','reconciled_by_user_id'=>'string','matched_payment_id'=>'string','matched_bill_payment_id'=>'string','gl_transaction_id'=>'string','raw_data'=>'array','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo BankAccount; belongsTo Reconciliation; belongsTo Payment (AR); belongsTo BillPayment (AP); belongsTo GlTransaction (Transaction).
- Validation:
  - `bank_account_id`: required|uuid|exists:acct.company_bank_accounts,id.
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

### acct.bank_reconciliations
- Purpose: reconciliation header for matching bank statements.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `bank_account_id` uuid not null FK → `acct.company_bank_accounts.id` (CASCADE/CASCADE).
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
  - `$connection = 'pgsql'; $table = 'acct.bank_reconciliations'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','bank_account_id','statement_date','statement_ending_balance','book_balance','reconciled_balance','difference','status','started_at','completed_at','completed_by_user_id','notes','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','bank_account_id'=>'string','statement_date'=>'date','statement_ending_balance'=>'decimal:2','book_balance'=>'decimal:2','reconciled_balance'=>'decimal:2','difference'=>'decimal:2','started_at'=>'datetime','completed_at'=>'datetime','completed_by_user_id'=>'string','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo BankAccount; hasMany BankTransaction (reconciled items).
- Validation:
  - `bank_account_id`: required|uuid|exists:acct.company_bank_accounts,id.
  - `statement_date`: required|date; unique per bank account.
  - `statement_ending_balance`: required|numeric.
  - `status`: in:in_progress,completed,cancelled.
- Business rules:
  - difference = statement_ending_balance - reconciled_balance.
  - Can only complete when difference = 0.
  - Completing updates bank account last_reconciled_date and last_reconciled_balance.
  - Linked transactions marked as reconciled.
  - Cannot complete reconciliation before previous period.

### acct.bank_rules
- Purpose: auto-categorization rules for imported transactions.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `bank_account_id` uuid nullable FK → `acct.company_bank_accounts.id` (SET NULL/CASCADE).
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
  - `$connection = 'pgsql'; $table = 'acct.bank_rules'; $keyType = 'string'; public $incrementing = false;`
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
create or replace function acct.update_account_balance()
returns trigger as $$
declare
  v_account_id uuid;
  v_new_balance numeric(15,2);
begin
  v_account_id := coalesce(new.bank_account_id, old.bank_account_id);

  select coalesce(sum(amount), 0) + opening_balance
  into v_new_balance
  from acct.company_bank_accounts a
  left join acct.bank_transactions t on t.bank_account_id = a.id and t.deleted_at is null
  where a.id = v_account_id
  group by a.opening_balance;

  update acct.company_bank_accounts
  set current_balance = v_new_balance
  where id = v_account_id;

  return null;
end;
$$ language plpgsql;

create trigger bank_transactions_aiud
after insert or update or delete on acct.bank_transactions
for each row execute function acct.update_account_balance();
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

---

## Integration Points

### GL Account Linkage

Every company bank account MUST link to a GL account for posting:

```
acct.company_bank_accounts.gl_account_id → acct.accounts.id
```

**Constraint**: The linked GL account must have `subtype IN ('bank', 'cash')`.

### Payment Integration (AR)

When a customer payment is recorded:

1. User selects `deposit_account_id` from `acct.accounts` (bank/cash subtype)
2. The system looks up `acct.company_bank_accounts` where `gl_account_id = deposit_account_id`
3. Creates `acct.bank_transactions` with:
   - `bank_account_id` = matched company bank account
   - `transaction_type` = 'deposit'
   - `amount` = positive (money in)
   - `matched_payment_id` = payment.id
4. GL posting: DR Bank, CR AR

```php
// PaymentService
public function recordPayment(Payment $payment): void
{
    // Find bank account by GL account
    $bankAccount = BankAccount::where('gl_account_id', $payment->deposit_account_id)->first();

    if ($bankAccount) {
        BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'transaction_type' => 'deposit',
            'amount' => $payment->amount,
            'matched_payment_id' => $payment->id,
            // ...
        ]);
    }
}
```

### Bill Payment Integration (AP)

When a vendor bill payment is recorded:

1. User selects `payment_account_id` from `acct.accounts` (bank/cash/credit_card subtype)
2. The system looks up `acct.company_bank_accounts` where `gl_account_id = payment_account_id`
3. Creates `acct.bank_transactions` with:
   - `bank_account_id` = matched company bank account
   - `transaction_type` = 'withdrawal'
   - `amount` = negative (money out)
   - `matched_bill_payment_id` = bill_payment.id
4. GL posting: DR AP, CR Bank

### Reconciliation → GL

When a bank transaction is matched and not yet posted to GL:

1. User matches bank transaction to a customer payment or vendor bill payment
2. System creates GL transaction if not already posted
3. Links `bank_transactions.gl_transaction_id` to the GL transaction

### Account Selector Integration

The payment forms should use accounts from COA, not directly from `acct.company_bank_accounts`:

```vue
<!-- Payment form -->
<AccountSelect
  v-model="form.deposit_account_id"
  :filter="{ subtype: ['bank', 'cash'] }"
  label="Deposit To"
/>
```

The backend validates that the selected account exists and optionally links to a bank account.

### Bank Account ↔ GL Account Sync

| Action | Result |
|--------|--------|
| Create bank account | Optionally auto-create GL account with same name |
| Update bank account currency | Must match GL account currency |
| Delete bank account | Check GL account has no postings first |
| Deactivate bank account | GL account can remain active |

### Required System Accounts

On company setup, seed these accounts in COA:

| Code | Name | Type | Subtype | Purpose |
|------|------|------|---------|---------|
| 1000 | Cash on Hand | asset | cash | Petty cash |
| 1010 | Primary Bank Account | asset | bank | Main operating account |
| 1020 | Savings Account | asset | bank | Reserve funds |

### Migration Dependency

Banking tables depend on:
1. `auth.companies` (company_id FK)
2. `acct.accounts` (gl_account_id FK)
3. `acct.payments` (matched_payment_id FK)
4. `acct.bill_payments` (matched_bill_payment_id FK)
5. `acct.transactions` (gl_transaction_id FK)

**Order**: GL Core tables must exist before Banking migration.

---

## UX Philosophy

Banking features follow the dual-mode experience defined in `docs/frontend-experience-contract.md`:

### Owner Mode (Bank section)
- **Review Transactions** — Resolution Engine for categorizing/matching (the Bank Feed)
- **Balance Overview** — Cash position with Balance Explainer widget
- Simple language: "Money In", "Money Out", "Transactions to review"

### Accountant Mode (Banking section)
- **Bank Accounts** — Full CRUD with GL linking
- **Transactions** — High-density grid with filters
- **Reconciliation** — Formal statement reconciliation workflow
- **Bank Rules** — Auto-categorization rule management
- Technical language: "Unreconciled items", "Statement balance", "Book balance"

### Key Distinction: Bank Feed vs Reconciliation

| Feature | Bank Feed (Resolution Engine) | Bank Reconciliation |
|---------|------------------------------|---------------------|
| Purpose | Categorize/match incoming transactions | Formal statement reconciliation |
| Location | Owner Mode: Bank → Review | Accountant Mode: Banking → Reconciliation |
| Workflow | Match/Create/Transfer/Park | Compare statement vs book, mark reconciled |
| Frequency | Daily/ongoing | Monthly/periodic |
| Output | GL postings created | Transactions marked reconciled |

---

## Extending
- Add new transaction_type values here first.
- Bank feed integration would add `acct.bank_feeds` and `acct.feed_connections` tables.
- Consider `acct.transfers` table for explicit internal transfers.
