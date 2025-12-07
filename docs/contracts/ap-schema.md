# Schema Contract — Accounts Payable (acct)

Single source of truth for vendors, bills, bill payments, vendor credits, and allocations. Read this before touching migrations, models, requests, resources, or Vue forms. Do not invent new columns/props; if something is missing, pause and update this contract first.

## Guardrails
- Schema: `acct` on `pgsql` (shared with AR entities).
- Currency naming: use `base_currency` (ISO 4217 uppercase, length 3). Do not introduce `currency`/`baseCurrency` variants.
- Currency codes: `char(3)` referencing `public.currencies(code)`; defaults derive from the company's base currency, never a hard-coded `'USD'`.
- UUID primary keys, `public.gen_random_uuid()` default where applicable.
- Soft deletes via `deleted_at`; uniqueness constraints must be filtered on `deleted_at IS NULL`.
- RLS required; include super-admin override and safe `current_setting(..., true)` calls.
- Models must declare `$connection = 'pgsql'` and schema-qualified `$table`.
- Money precision (locked): `currency_amount numeric(18,6)`, `exchange_rate numeric(18,8)`, `base_amount numeric(15,2)`, `debit/credit numeric(15,2)`. Journals must balance at 15,2.
- Addresses: single address JSON per vendor in v1.

## Tables

### acct.vendors
- Purpose: vendor/supplier master for bills.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (ON DELETE CASCADE, ON UPDATE CASCADE).
  - `vendor_number` varchar(50) not null; unique per company (filtered on `deleted_at is null`).
  - `name` varchar(255) not null.
  - `email` varchar(255) null.
  - `phone` varchar(50) null.
  - `address` jsonb null (keys: street, city, state, zip, country).
  - `tax_id` varchar(100) null.
  - `base_currency` char(3) not null default (company base); FK → `public.currencies.code`.
  - `payment_terms` integer not null default 30 (days).
  - `account_number` varchar(100) null (vendor's account # for us).
  - `notes` text null.
  - `website` varchar(500) null.
  - `is_active` boolean not null default true.
  - `created_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps; `deleted_at` timestamp nullable (soft delete).
- Defaults quick ref: `base_currency: company base`, `payment_terms: 30`, `is_active: true`.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `vendor_number`) where `deleted_at is null`.
  - Unique (`company_id`, `email`) where `email is not null` and `deleted_at is null`.
  - Indexes: `company_id`; (`company_id`, `is_active`) where `deleted_at is null`.
- RLS:
  ```sql
  alter table acct.vendors enable row level security;
  create policy vendors_policy on acct.vendors
    for all
    using (
      company_id = current_setting('app.current_company_id', true)::uuid
      or current_setting('app.is_super_admin', true)::boolean = true
    );
  ```
- Model (canonical):
  - `$connection = 'pgsql';`
  - `$table = 'acct.vendors';`
  - `$keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','vendor_number','name','email','phone','address','tax_id','base_currency','payment_terms','account_number','notes','website','is_active','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['id'=>'string','company_id'=>'string','address'=>'array','payment_terms'=>'integer','is_active'=>'boolean','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships:
  - belongsTo Company.
  - hasMany Bill.
  - hasMany BillPayment.
  - hasMany VendorCredit.
- Validation:
  - `vendor_number`: required|string|max:50, unique per company (soft-delete aware).
  - `name`: required|string|max:255.
  - `email`: nullable|email|max:255, unique per company (soft-delete aware).
  - `phone`: nullable|string|max:50.
  - `address.*`: nullable|string with max lengths (street 255, city/state 100, zip 20, country 2).
  - `tax_id`: nullable|string|max:100.
  - `base_currency`: required|string|size:3|uppercase (must equal company base).
  - `payment_terms`: required|integer|min:0|max:365.
  - `account_number`: nullable|string|max:100.
  - `notes`: nullable|string.
  - `website`: nullable|string|max:500.
  - `is_active`: boolean.
- Business rules:
  - Vendor number unique per company.
  - Email unique per company when present.
  - Base currency should match company base currency for now; disallow changes once bills exist.
  - Cannot delete vendor with unpaid bills.

### acct.bills
- Purpose: purchase invoice/bill headers (what you owe vendors).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `vendor_id` uuid not null FK → `acct.vendors.id` (RESTRICT/CASCADE).
  - `bill_number` varchar(50) not null; unique per company (filtered).
  - `vendor_invoice_number` varchar(100) null (vendor's invoice reference).
  - `bill_date` date not null default `current_date`.
  - `due_date` date not null.
  - `status` varchar(20) not null default `'draft'` (enum: draft, received, partial, paid, overdue, void, cancelled).
  - `currency` char(3) not null default (company base) (transaction currency, must be enabled for company); FK → `public.currencies.code`.
  - `base_currency` char(3) not null (denormalized from company); FK → `public.currencies.code`.
  - `exchange_rate` numeric(18,8) nullable (required if currency != base_currency; store as 1 foreign = X base).
  - `subtotal` numeric(18,6) not null default 0.00 (transaction currency).
  - `tax_amount` numeric(18,6) not null default 0.00 (transaction currency).
  - `discount_amount` numeric(18,6) not null default 0.00 (transaction currency).
  - `total_amount` numeric(18,6) not null default 0.00 (transaction currency).
  - `paid_amount` numeric(18,6) not null default 0.00 (transaction currency).
  - `balance` numeric(18,6) not null default 0.00 (transaction currency).
  - `base_amount` numeric(15,2) not null default 0.00 (total in base currency).
  - `payment_terms` integer not null default 30.
  - `notes` text null.
  - `internal_notes` text null.
  - `received_at` timestamp nullable.
  - `approved_at` timestamp nullable.
  - `paid_at` timestamp nullable.
  - `voided_at` timestamp nullable.
  - `recurring_schedule_id` uuid nullable FK → `acct.recurring_bill_schedules.id` (SET NULL/CASCADE).
  - `created_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`; `deleted_at` for soft delete.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `bill_number`) where `deleted_at is null`.
  - Indexes: `company_id`; `vendor_id`; (`company_id`, `status`) where `deleted_at is null`; (`company_id`, `bill_date`, `due_date`); (`company_id`, `due_date`, `status`) where status not in (paid, void, cancelled).
- RLS: same pattern as vendors with company_id check + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.bills'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','vendor_id','bill_number','vendor_invoice_number','bill_date','due_date','status','currency','base_currency','exchange_rate','subtotal','tax_amount','discount_amount','total_amount','paid_amount','balance','base_amount','payment_terms','notes','internal_notes','received_at','approved_at','paid_at','voided_at','recurring_schedule_id','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','vendor_id'=>'string','recurring_schedule_id'=>'string','bill_date'=>'date','due_date'=>'date','subtotal'=>'decimal:6','tax_amount'=>'decimal:6','discount_amount'=>'decimal:6','total_amount'=>'decimal:6','paid_amount'=>'decimal:6','balance'=>'decimal:6','base_amount'=>'decimal:2','exchange_rate'=>'decimal:8','payment_terms'=>'integer','received_at'=>'datetime','approved_at'=>'datetime','paid_at'=>'datetime','voided_at'=>'datetime','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships:
  - belongsTo Company; belongsTo Vendor; hasMany BillLineItem; hasMany BillPaymentAllocation; belongsTo RecurringBillSchedule.
- Validation:
  - `vendor_id`: required|uuid|exists:acct.vendors,id.
  - `bill_number`: required|string|max:50 (unique per company, soft-delete aware).
  - `vendor_invoice_number`: nullable|string|max:100.
  - `bill_date`: required|date.
  - `due_date`: required|date|after_or_equal:bill_date.
  - `status`: in:draft,received,partial,paid,overdue,void,cancelled.
  - `currency`: required|string|size:3|uppercase; must be enabled for company.
  - `base_currency`: required|string|size:3|uppercase (company base).
  - `exchange_rate`: nullable|numeric|min:0.00000001|max_digits:18|decimal:8 (required if currency != base_currency; NULL if currency = base_currency).
  - `payment_terms`: integer|min:0|max:365.
  - `notes`/`internal_notes`: nullable|string.
  - `line_items`: required|array|min:1 with validated fields below.
- Business rules:
  - Bill number unique per company.
  - Due date >= bill date.
  - Totals in transaction currency; balance = total_amount - paid_amount.
  - base_amount = ROUND(total_amount * COALESCE(exchange_rate,1), 2).
  - exchange_rate required when currency != base_currency; must be NULL when currency = base_currency.
  - Default due_date is computed as `bill_date + payment_terms` when not provided.
  - Status transitions: draft → received → partial → paid; overdue when due_date < today and balance > 0; void/cancelled stop edits/payments.
  - Cannot modify line items after approved/paid; use vendor credits for adjustments.
  - Currency must be enabled for company.

### acct.bill_line_items
- Purpose: bill lines (expense/inventory detail).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `bill_id` uuid not null FK → `acct.bills.id` (CASCADE/CASCADE).
  - `line_number` integer not null.
  - `description` varchar(500) not null.
  - `quantity` numeric(10,2) not null default 1.00.
  - `unit_price` numeric(18,6) not null default 0.00.
  - `tax_rate` numeric(5,2) not null default 0.00.
  - `discount_rate` numeric(5,2) not null default 0.00.
  - `line_total` numeric(18,6) not null default 0.00.
  - `tax_amount` numeric(18,6) not null default 0.00.
  - `total` numeric(18,6) not null default 0.00.
  - `account_id` uuid nullable FK → `acct.accounts.id` (expense account for GL posting).
  - `created_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` (soft delete).
- Indexes/constraints:
  - PK `id`.
  - Indexes: `company_id`; (`bill_id`, `line_number`).
- RLS: same pattern with company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.bill_line_items'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','bill_id','line_number','description','quantity','unit_price','tax_rate','discount_rate','line_total','tax_amount','total','account_id','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','bill_id'=>'string','account_id'=>'string','line_number'=>'integer','quantity'=>'decimal:2','unit_price'=>'decimal:6','tax_rate'=>'decimal:2','discount_rate'=>'decimal:2','line_total'=>'decimal:6','tax_amount'=>'decimal:6','total'=>'decimal:6','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Bill; belongsTo Account (nullable).
- Validation:
  - `bill_id`: required|uuid|exists:acct.bills,id.
  - `line_number`: required|integer|min:1.
  - `description`: required|string|max:500.
  - `quantity`: required|numeric|min:0.01.
  - `unit_price`: required|numeric|min:0.
  - `tax_rate`/`discount_rate`: nullable|numeric|min:0|max:100.
  - `account_id`: nullable|uuid|exists:acct.accounts,id.
- Business rules:
  - Line numbers sequential per bill.
  - Calculated fields (`line_total`, `tax_amount`, `total`) computed server-side.
  - No edits after bill approved/paid.

### acct.bill_payments
- Purpose: payments made to vendors (header-level).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `vendor_id` uuid not null FK → `acct.vendors.id` (RESTRICT/CASCADE).
  - `payment_number` varchar(50) not null; unique per company (filtered).
  - `payment_date` date not null default `current_date`.
  - `amount` numeric(18,6) not null default 0.00 (payment currency).
  - `currency` char(3) not null default (company base) (payment currency; must be enabled for company); FK → `public.currencies.code`.
  - `exchange_rate` numeric(18,8) nullable (required if currency != base_currency; NULL if currency = base).
  - `base_currency` char(3) not null (company base, denormalized); FK → `public.currencies.code`.
  - `base_amount` numeric(15,2) not null default 0.00 (amount in base currency).
  - `payment_method` varchar(50) not null; constrained values: cash, check, card, bank_transfer, ach, wire, other.
  - `reference_number` varchar(100) null.
  - `notes` text null.
  - `created_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at`.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `payment_number`) where `deleted_at is null`.
  - Indexes: `company_id`; `vendor_id`; `payment_date`.
- RLS: same pattern with company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.bill_payments'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','vendor_id','payment_number','payment_date','amount','currency','exchange_rate','base_currency','base_amount','payment_method','reference_number','notes','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','vendor_id'=>'string','payment_date'=>'date','amount'=>'decimal:6','exchange_rate'=>'decimal:8','base_amount'=>'decimal:2','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Vendor; hasMany BillPaymentAllocation.
- Validation:
  - `vendor_id`: required|uuid|exists:acct.vendors,id.
  - `payment_number`: required|string|max:50 (unique per company, soft-delete aware).
  - `payment_date`: required|date|before_or_equal:today.
  - `amount`: required|numeric|min:0.01|decimal:6.
  - `currency`: required|string|size:3|uppercase (enabled for company); must equal bill currency or company base when allocating.
  - `exchange_rate`: nullable|numeric|min:0.00000001|decimal:8 (required if currency != base_currency; NULL if currency = base).
  - `base_currency`: required|string|size:3|uppercase (company base).
  - `payment_method`: required|in:cash,check,card,bank_transfer,ach,wire,other.
  - `reference_number`: nullable|string|max:100.
  - `notes`: nullable|string.
- Business rules:
  - base_amount = ROUND(amount * COALESCE(exchange_rate,1), 2).
  - Payment currency must match bill currency or company base currency when allocating (Phase 1 rule).
  - Payment amount cannot exceed sum of allocations.
  - Cannot delete payment with allocations; void instead if required.

### acct.bill_payment_allocations
- Purpose: allocate a bill payment across bills (supports partial and multi-bill).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `bill_payment_id` uuid not null FK → `acct.bill_payments.id` (CASCADE/CASCADE).
  - `bill_id` uuid not null FK → `acct.bills.id` (RESTRICT/CASCADE).
  - `amount_allocated` numeric(18,6) not null (bill currency).
  - `base_amount_allocated` numeric(15,2) not null default 0.00 (base currency).
  - `applied_at` timestamp not null default now().
  - `created_at`, `updated_at`.
- Indexes/constraints:
  - Indexes: `company_id`; `bill_payment_id`; `bill_id`; unique (`bill_payment_id`, `bill_id`) optional if desired.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.bill_payment_allocations'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','bill_payment_id','bill_id','amount_allocated','base_amount_allocated','applied_at'];`
  - `$casts = ['company_id'=>'string','bill_payment_id'=>'string','bill_id'=>'string','amount_allocated'=>'decimal:6','base_amount_allocated'=>'decimal:2','applied_at'=>'datetime','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo BillPayment; belongsTo Bill.
- Business rules:
  - `sum(amount_allocated) per payment` ≤ bill_payment.amount; enforce in service layer and/or DB constraint.
  - Payment currency must equal bill currency or company base currency (Phase 1 rule).
  - base_amount_allocated = ROUND(amount_allocated * payment.exchange_rate, 2) when currency differs from base.
  - On create/update/delete, recompute bill `paid_amount`/`balance` (transaction currency) and status/paid_at accordingly.

### acct.vendor_credits
- Purpose: credits received from vendors (returns, adjustments, rebates).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `vendor_id` uuid not null FK → `acct.vendors.id` (RESTRICT/CASCADE).
  - `bill_id` uuid null FK → `acct.bills.id` (RESTRICT/CASCADE) (original bill if applicable).
  - `credit_number` varchar(50) not null; unique per company (filtered).
  - `vendor_credit_number` varchar(100) null (vendor's credit memo reference).
  - `credit_date` date not null default `current_date`.
  - `amount` numeric(18,6) not null default 0.00 (must be > 0 via check).
  - `currency` char(3) not null default (company base); FK → `public.currencies.code`.
  - `base_currency` char(3) not null default (company base); FK → `public.currencies.code`.
  - `exchange_rate` numeric(18,8) nullable (required if currency != base_currency; NULL if currency = base).
  - `base_amount` numeric(15,2) not null default 0.00 (base currency).
  - `reason` varchar(255) not null.
  - `status` varchar(20) not null default `'draft'` (enum: draft, received, applied, void).
  - `notes` text null.
  - `received_at` timestamp nullable.
  - `voided_at` timestamp nullable.
  - `cancellation_reason` varchar(255) nullable.
  - `journal_entry_id` uuid nullable (GL linkage placeholder).
  - `created_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at`.
- Indexes/constraints: PK `id`; unique (`company_id`, `credit_number`) where `deleted_at is null`; indexes on `company_id`, `vendor_id`, `bill_id`, (`company_id`, `status`).
- RLS: same pattern with company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.vendor_credits'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','vendor_id','bill_id','credit_number','vendor_credit_number','credit_date','amount','currency','base_currency','exchange_rate','base_amount','reason','status','notes','received_at','voided_at','cancellation_reason','journal_entry_id','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','vendor_id'=>'string','bill_id'=>'string','credit_date'=>'date','amount'=>'decimal:6','exchange_rate'=>'decimal:8','base_amount'=>'decimal:2','received_at'=>'datetime','voided_at'=>'datetime','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Vendor; belongsTo Bill (nullable); hasMany VendorCreditItem; hasMany VendorCreditApplication.
- Validation:
  - `vendor_id`: required|uuid|exists:acct.vendors,id.
  - `bill_id`: nullable|uuid|exists:acct.bills,id.
  - `credit_number`: required|string|max:50 (unique per company, soft-delete aware).
  - `vendor_credit_number`: nullable|string|max:100.
  - `credit_date`: required|date.
  - `amount`: required|numeric|min:0.01.
  - `currency`: required|string|size:3|uppercase (enabled for company); when `bill_id` present, must equal bill currency; otherwise must equal company base.
  - `base_currency`: required|string|size:3|uppercase (company base).
  - `exchange_rate`: nullable|numeric|min:0.00000001|max_digits:18|decimal:8 (required if currency != base_currency; NULL if currency = base_currency).
  - `reason`: required|string|max:255.
  - `status`: in:draft,received,applied,void.
  - `notes`: nullable|string.
- Business rules:
  - Credit amount cannot exceed bill balance if tied to a bill.
  - Currency must equal bill currency when tied to a bill; otherwise use company base currency (Phase 1 rule).
  - exchange_rate required when currency != base_currency; NULL when currency = base_currency.
  - base_amount = ROUND(amount * COALESCE(exchange_rate,1), 2).
  - On apply, reduce bill balance per chosen accounting treatment and update status; keep base amounts balanced at 15,2.
  - Status transitions: draft → received → applied; void stops usage.

### acct.vendor_credit_items
- Purpose: item-level detail for vendor credits.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `vendor_credit_id` uuid not null FK → `acct.vendor_credits.id` (CASCADE/CASCADE).
  - `line_number` integer not null.
  - `description` varchar(500) not null.
  - `quantity` numeric(10,2) not null default 1.00.
  - `unit_price` numeric(18,6) not null default 0.00.
  - `tax_rate` numeric(5,2) not null default 0.00.
  - `discount_rate` numeric(5,2) not null default 0.00.
  - `line_total` numeric(18,6) not null default 0.00.
  - `tax_amount` numeric(18,6) not null default 0.00.
  - `total` numeric(18,6) not null default 0.00.
  - `account_id` uuid nullable FK → `acct.accounts.id` (expense account).
  - `created_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at`.
- Indexes/constraints: PK `id`; indexes on `company_id`; (`vendor_credit_id`, `line_number`); unique (`vendor_credit_id`, `line_number`) enforced.
- RLS: company isolation + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.vendor_credit_items'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','vendor_credit_id','line_number','description','quantity','unit_price','tax_rate','discount_rate','line_total','tax_amount','total','account_id','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','vendor_credit_id'=>'string','account_id'=>'string','line_number'=>'integer','quantity'=>'decimal:2','unit_price'=>'decimal:6','tax_rate'=>'decimal:2','discount_rate'=>'decimal:2','line_total'=>'decimal:6','tax_amount'=>'decimal:6','total'=>'decimal:6','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo VendorCredit; belongsTo Account (nullable).
- Business rules: line numbers sequential per credit; calculated fields computed server-side; edits blocked after received.

### acct.vendor_credit_applications
- Purpose: allocate a vendor credit to bills.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `vendor_credit_id` uuid not null FK → `acct.vendor_credits.id` (CASCADE/CASCADE).
  - `bill_id` uuid not null FK → `acct.bills.id` (CASCADE/CASCADE).
  - `amount_applied` numeric(18,6) not null (check > 0, credit currency).
  - `applied_at` timestamp not null default now().
  - `user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `notes` text nullable.
  - `bill_balance_before` numeric(15,2) not null.
  - `bill_balance_after` numeric(15,2) not null.
  - `created_at`, `updated_at`.
- Indexes/constraints: indexes on company_id, vendor_credit_id, bill_id, applied_at; unique (`vendor_credit_id`, `bill_id`) enforced; check bill_balance_before >= bill_balance_after.
- RLS: company isolation + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.vendor_credit_applications'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','vendor_credit_id','bill_id','amount_applied','applied_at','user_id','notes','bill_balance_before','bill_balance_after'];`
  - `$casts = ['company_id'=>'string','vendor_credit_id'=>'string','bill_id'=>'string','amount_applied'=>'decimal:6','bill_balance_before'=>'decimal:2','bill_balance_after'=>'decimal:2','applied_at'=>'datetime','user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo VendorCredit; belongsTo Bill.
- Business rules: sum of `amount_applied` per credit ≤ vendor_credit.amount (credit currency); must not exceed bill balance; recompute bill paid/balance/status in both transaction and base currency using credit exchange_rate; update credit status to applied when fully used.

### acct.recurring_bill_schedules
- Purpose: recurrence templates for bill generation.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `vendor_id` uuid not null FK → `acct.vendors.id` (RESTRICT/CASCADE).
  - `name` varchar(255) not null.
  - `frequency` varchar(20) not null (daily, weekly, monthly, quarterly, yearly).
  - `interval` integer not null default 1.
  - `start_date` date not null.
  - `end_date` date null.
  - `next_bill_date` date not null.
  - `last_generated_at` timestamp null.
  - `template_data` jsonb not null. Allowed keys: `line_items`, `payment_terms`, `base_currency`, `notes`, `internal_notes`.
  - `is_active` boolean not null default true.
  - `created_at`, `updated_at`, `deleted_at`.
- Indexes: PK `id`; indexes on `company_id`; `vendor_id`; (`company_id`, `is_active`, `next_bill_date`) where `is_active = true`.
- RLS: same pattern.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.recurring_bill_schedules'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','vendor_id','name','frequency','interval','start_date','end_date','next_bill_date','last_generated_at','template_data','is_active'];`
  - `$casts = ['company_id'=>'string','vendor_id'=>'string','interval'=>'integer','start_date'=>'date','end_date'=>'date','next_bill_date'=>'date','last_generated_at'=>'datetime','template_data'=>'array','is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Vendor; hasMany Bill.
- Business rules: same as recurring invoice schedules.

## Enums Reference

### Bill Status
| Status    | Description               | Can Edit? | Can Pay? |
|-----------|---------------------------|-----------|----------|
| draft     | Being entered             | Yes       | No       |
| received  | Received from vendor      | Limited   | Yes      |
| partial   | Partially paid            | No        | Yes      |
| paid      | Fully paid                | No        | No       |
| overdue   | Past due, unpaid          | No        | Yes      |
| void      | Cancelled, no effect      | No        | No       |
| cancelled | Cancelled before received | No        | No       |

### Vendor Credit Status
| Status   | Description              |
|----------|--------------------------|
| draft    | Not received             |
| received | Active, can be applied   |
| applied  | Used against bills       |
| void     | Cancelled                |

## Entity Mapping (AR ↔ AP)

| AR (Revenue)           | AP (Expenses)              |
|------------------------|----------------------------|
| Customer               | Vendor                     |
| Invoice                | Bill                       |
| InvoiceLineItem        | BillLineItem               |
| Payment                | BillPayment                |
| PaymentAllocation      | BillPaymentAllocation      |
| CreditNote             | VendorCredit               |
| CreditNoteItem         | VendorCreditItem           |
| CreditNoteApplication  | VendorCreditApplication    |
| RecurringSchedule      | RecurringBillSchedule      |

## Out of Scope (v1)
- Purchase Orders (pre-bill approval workflow).
- Expense claims / employee reimbursements.
- 1099 tracking for US tax reporting.
- Automatic bill import from email/PDF.
- Vendor portal.

## Extending
- If a new column/enum value is required, add it here first, then add migration + validation + resource + form updates in one cohesive change.
