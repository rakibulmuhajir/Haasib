# Schema Contract — Accounting / Invoicing (acct)

Single source of truth for customers, invoices, payments, credit notes, recurring schedules, and allocations. Read this before touching migrations, models, requests, resources, or Vue forms. Do not invent new columns/props; if something is missing, pause and update this contract first.

## Guardrails
- Schema: `acct` on `pgsql`.
- Currency naming: use `base_currency` (ISO 4217 uppercase, length 3). Do not introduce `currency`/`baseCurrency` variants. All money amounts are assumed to be in `base_currency`.
- UUID primary keys, `public.gen_random_uuid()` default where applicable.
- Soft deletes via `deleted_at`; uniqueness constraints must be filtered on `deleted_at IS NULL`.
- RLS required; include super-admin override and safe `current_setting(..., true)` calls.
- Models must declare `$connection = 'pgsql'` and schema-qualified `$table`.
- Money precision (locked): `currency_amount numeric(18,6)`, `exchange_rate numeric(18,8)`, `base_amount numeric(15,2)`, `debit/credit numeric(15,2)`. Journals must balance at 15,2.
- Settings JSON keys must be declared; no freeform additions without updating this contract.
- Addresses: single billing/shipping JSON per customer in v1; multi-address support deferred.

## Tables

### acct.customers
- Purpose: customer master for invoicing.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (ON DELETE CASCADE, ON UPDATE CASCADE).
  - `customer_number` varchar(50) not null; unique per company (filtered on `deleted_at is null`).
  - `name` varchar(255) not null.
  - `email` varchar(255) null.
  - `phone` varchar(50) null.
  - `billing_address` jsonb null (keys: street, city, state, zip, country).
  - `shipping_address` jsonb null (same shape).
  - `tax_id` varchar(100) null.
  - `base_currency` char(3) not null default `'USD'`.
  - `payment_terms` integer not null default 30 (days).
  - `credit_limit` numeric(15,2) null.
  - `notes` text null.
  - `logo_url` varchar(500) null.
  - `is_active` boolean not null default true.
  - `created_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps; `deleted_at` timestamp nullable (soft delete).
- Defaults quick ref: `base_currency: 'USD'`, `payment_terms: 30`, `is_active: true`.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `customer_number`) where `deleted_at is null`.
  - Unique (`company_id`, `email`) where `email is not null` and `deleted_at is null`.
  - Indexes: `company_id`; (`company_id`, `is_active`) where `deleted_at is null`.
- RLS:
  ```sql
  alter table acct.customers enable row level security;
  create policy customers_policy on acct.customers
    for all
    using (
      company_id = current_setting('app.current_company_id', true)::uuid
      or current_setting('app.is_super_admin', true)::boolean = true
    );
  ```
  - Model (canonical):
    - `$connection = 'pgsql';`
    - `$table = 'acct.customers';`
    - `$keyType = 'string'; public $incrementing = false;`
    - `$fillable = ['company_id','customer_number','name','email','phone','billing_address','shipping_address','tax_id','base_currency','payment_terms','credit_limit','notes','logo_url','is_active','created_by_user_id','updated_by_user_id'];`
    - `$casts = ['id'=>'string','company_id'=>'string','billing_address'=>'array','shipping_address'=>'array','credit_limit'=>'decimal:2','payment_terms'=>'integer','is_active'=>'boolean','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
  - Relationships:
    - belongsTo Company.
    - hasMany Invoice.
  - hasMany Payment.
  - hasMany CreditNote.
- Validation:
  - `customer_number`: required|string|max:50, unique per company (soft-delete aware).
  - `name`: required|string|max:255.
  - `email`: nullable|email|max:255, unique per company (soft-delete aware).
  - `phone`: nullable|string|max:50.
  - `billing_address.*`/`shipping_address.*`: nullable|string with max lengths (street 255, city/state 100, zip 20, country 2).
  - `tax_id`: nullable|string|max:100.
  - `base_currency`: required|string|size:3|uppercase.
  - `payment_terms`: required|integer|min:0|max:365.
  - `credit_limit`: nullable|numeric|min:0.
  - `notes`: nullable|string.
  - `logo_url`: nullable|string|max:500.
  - `is_active`: boolean.
- Business rules:
  - Customer number unique per company.
  - Email unique per company when present.
  - Base currency should match company base currency for now; disallow changes once invoices exist.
  - Cannot delete customer with unpaid invoices.
  - Billing address must be present before issuing an invoice (enforce at invoice creation).

### acct.invoices
- Purpose: invoice headers.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `customer_id` uuid not null FK → `acct.customers.id` (RESTRICT/ CASCADE).
  - `invoice_number` varchar(50) not null; unique per company (filtered).
  - `invoice_date` date not null default `current_date`.
  - `due_date` date not null.
  - `status` varchar(20) not null default `'draft'` (enum: draft, sent, viewed, partial, paid, overdue, void, cancelled).
  - `currency` char(3) not null default `'USD'` (transaction currency, must be enabled for company).
  - `base_currency` char(3) not null (denormalized from company).
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
  - `sent_at`, `viewed_at`, `paid_at`, `voided_at` timestamps nullable.
  - `created_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `recurring_schedule_id` uuid nullable FK → `acct.recurring_schedules.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`; `deleted_at` for soft delete.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `invoice_number`) where `deleted_at is null`.
  - Indexes: `company_id`; `customer_id`; (`company_id`, `status`) where `deleted_at is null`; (`company_id`, `invoice_date`, `due_date`); (`company_id`, `due_date`, `status`) where status not in (paid, void, cancelled).
- RLS: same pattern as customers with company_id check + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.invoices'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','customer_id','invoice_number','invoice_date','due_date','status','currency','base_currency','exchange_rate','subtotal','tax_amount','discount_amount','total_amount','paid_amount','balance','base_amount','payment_terms','notes','internal_notes','sent_at','viewed_at','paid_at','voided_at','recurring_schedule_id','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','customer_id'=>'string','recurring_schedule_id'=>'string','invoice_date'=>'date','due_date'=>'date','subtotal'=>'decimal:6','tax_amount'=>'decimal:6','discount_amount'=>'decimal:6','total_amount'=>'decimal:6','paid_amount'=>'decimal:6','balance'=>'decimal:6','base_amount'=>'decimal:2','exchange_rate'=>'decimal:8','payment_terms'=>'integer','sent_at'=>'datetime','viewed_at'=>'datetime','paid_at'=>'datetime','voided_at'=>'datetime','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships:
  - belongsTo Company; belongsTo Customer; hasMany InvoiceLineItem; hasMany PaymentAllocation; hasMany Payment (if directly linked); belongsTo RecurringSchedule.
- Validation:
  - `customer_id`: required|uuid|exists:acct.customers,id.
  - `invoice_number`: required|string|max:50 (unique per company, soft-delete aware).
  - `invoice_date`: required|date.
  - `due_date`: required|date|after_or_equal:invoice_date.
  - `status`: in:draft,sent,viewed,partial,paid,overdue,void,cancelled.
  - `currency`: required|string|size:3|uppercase; must be enabled for company.
  - `base_currency`: required|string|size:3|uppercase (company base).
  - `exchange_rate`: nullable|numeric|min:0.00000001|max_digits:18|decimal:8 (required if currency != base_currency; NULL if currency = base_currency).
  - `payment_terms`: integer|min:0|max:365.
  - `notes`/`internal_notes`: nullable|string.
  - `line_items`: required|array|min:1 with validated fields below.
- Business rules:
  - Invoice number unique per company.
  - Due date >= invoice date.
  - Totals in transaction currency; balance = total_amount - paid_amount.
  - base_amount = ROUND(total_amount * COALESCE(exchange_rate,1), 2).
  - exchange_rate required when currency != base_currency; must be NULL when currency = base_currency.
  - Default due_date is computed in service layer as `invoice_date + payment_terms` when not provided.
  - Status transitions: draft → sent → viewed → partial → paid; overdue when due_date < today and balance > 0; void/cancelled stop edits/payments.
  - Cannot modify line items after sent/paid; use credit notes for adjustments.
  - Currency must be enabled for company.

### acct.invoice_line_items
- Purpose: invoice lines.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `invoice_id` uuid not null FK → `acct.invoices.id` (CASCADE/CASCADE).
  - `line_number` integer not null.
  - `description` varchar(500) not null.
  - `quantity` numeric(10,2) not null default 1.00.
  - `unit_price` numeric(15,2) not null default 0.00.
  - `tax_rate` numeric(5,2) not null default 0.00.
  - `discount_rate` numeric(5,2) not null default 0.00.
  - `line_total` numeric(15,2) not null default 0.00.
  - `tax_amount` numeric(15,2) not null default 0.00.
  - `total` numeric(15,2) not null default 0.00.
  - `created_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` (soft delete).
- Indexes/constraints:
  - PK `id`.
  - Indexes: `company_id`; (`invoice_id`, `line_number`).
- RLS: same pattern with company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.invoice_line_items'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','invoice_id','line_number','description','quantity','unit_price','tax_rate','discount_rate','line_total','tax_amount','total','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','invoice_id'=>'string','line_number'=>'integer','quantity'=>'decimal:2','unit_price'=>'decimal:2','tax_rate'=>'decimal:2','discount_rate'=>'decimal:2','line_total'=>'decimal:2','tax_amount'=>'decimal:2','total'=>'decimal:2','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Invoice.
- Validation:
  - `invoice_id`: required|uuid|exists:acct.invoices,id.
  - `line_number`: required|integer|min:1.
  - `description`: required|string|max:500.
  - `quantity`: required|numeric|min:0.01.
  - `unit_price`: required|numeric|min:0.
  - `tax_rate`/`discount_rate`: nullable|numeric|min:0|max:100.
- Business rules:
  - Line numbers sequential per invoice.
  - Calculated fields (`line_total`, `tax_amount`, `total`) computed server-side.
  - No edits after invoice sent/paid.

### acct.payments
- Purpose: payments received (header-level).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `customer_id` uuid not null FK → `acct.customers.id` (RESTRICT/CASCADE).
  - `payment_number` varchar(50) not null; unique per company (filtered).
  - `payment_date` date not null default `current_date`.
  - `amount` numeric(18,6) not null default 0.00 (payment currency).
  - `currency` char(3) not null default `'USD'` (payment currency; must be enabled for company).
  - `exchange_rate` numeric(18,8) nullable (required if currency != base_currency; NULL if currency = base).
  - `base_currency` char(3) not null (company base, denormalized).
  - `base_amount` numeric(15,2) not null default 0.00 (amount in base currency).
  - `payment_method` varchar(50) not null; constrained values: cash, check, card, bank_transfer, other.
  - `reference_number` varchar(100) null.
  - `notes` text null.
  - `created_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at`.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `payment_number`) where `deleted_at is null`.
  - Indexes: `company_id`; `customer_id`; `payment_date`.
- RLS: same pattern with company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.payments'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','customer_id','payment_number','payment_date','amount','currency','exchange_rate','base_currency','base_amount','payment_method','reference_number','notes','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','customer_id'=>'string','payment_date'=>'date','amount'=>'decimal:6','exchange_rate'=>'decimal:8','base_amount'=>'decimal:2','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Customer; hasMany PaymentAllocation.
- Validation:
  - `customer_id`: required|uuid|exists:acct.customers,id.
  - `payment_number`: required|string|max:50 (unique per company, soft-delete aware).
  - `payment_date`: required|date|before_or_equal:today.
  - `amount`: required|numeric|min:0.01|decimal:6.
  - `currency`: required|string|size:3|uppercase (enabled for company); must equal invoice currency or company base when allocating.
  - `exchange_rate`: nullable|numeric|min:0.00000001|decimal:8 (required if currency != base_currency; NULL if currency = base).
  - `base_currency`: required|string|size:3|uppercase (company base).
  - `payment_method`: required|in:cash,check,card,bank_transfer,other.
  - `reference_number`: nullable|string|max:100.
  - `notes`: nullable|string.
- Business rules:
  - base_amount = ROUND(amount * COALESCE(exchange_rate,1), 2).
  - Payment currency must match invoice currency or company base currency when allocating (Phase 1 rule).
  - Payment amount cannot exceed sum of allocations.
  - Cannot delete payment with allocations; void instead if required.

### acct.payment_allocations
- Purpose: allocate a payment across invoices (supports partial and multi-invoice).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `payment_id` uuid not null FK → `acct.payments.id` (CASCADE/CASCADE).
  - `invoice_id` uuid not null FK → `acct.invoices.id` (RESTRICT/CASCADE).
  - `amount_allocated` numeric(18,6) not null (invoice currency).
  - `base_amount_allocated` numeric(15,2) not null default 0.00 (base currency).
  - `applied_at` timestamp not null default now().
  - `created_at`, `updated_at`.
- Indexes/constraints:
  - Indexes: `company_id`; `payment_id`; `invoice_id`; unique (`payment_id`, `invoice_id`) optional if desired.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.payment_allocations'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','payment_id','invoice_id','amount_allocated','base_amount_allocated','applied_at'];`
  - `$casts = ['company_id'=>'string','payment_id'=>'string','invoice_id'=>'string','amount_allocated'=>'decimal:6','base_amount_allocated'=>'decimal:2','applied_at'=>'datetime','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Payment; belongsTo Invoice.
- Business rules:
  - `sum(amount_allocated) per payment` ≤ payment.amount; enforce in service layer and/or DB constraint.
  - Payment currency must equal invoice currency or company base currency (Phase 1 rule).
  - base_amount_allocated = ROUND(amount_allocated * payment.exchange_rate, 2) when currency differs from base.
  - On create/update/delete, recompute invoice `paid_amount`/`balance` (transaction currency) and status/paid_at accordingly.

### acct.credit_notes
- Purpose: credits/refunds. (Extended to support line items and applications in this implementation.)
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `customer_id` uuid not null FK → `acct.customers.id` (RESTRICT/CASCADE).
  - `invoice_id` uuid null FK → `acct.invoices.id` (RESTRICT/CASCADE).
  - `credit_note_number` varchar(50) not null; unique per company (filtered).
  - `credit_date` date not null default `current_date`.
  - `amount` numeric(15,2) not null default 0.00 (must be > 0 via check).
  - `base_currency` char(3) not null default `'USD'`.
  - `reason` varchar(255) not null.
  - `status` varchar(20) not null default `'draft'` (enum: draft, issued, applied, void).
  - `notes` text null.
  - `terms` text null. *(Extension)*
  - `sent_at`, `posted_at`, `voided_at` timestamps nullable. *(Extension)*
  - `cancellation_reason` varchar(255) nullable. *(Extension)*
  - `journal_entry_id` uuid nullable. *(Extension placeholder for GL linkage)*
  - `created_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at`.
- Indexes/constraints: PK `id`; unique (`company_id`, `credit_note_number`) where `deleted_at is null`; indexes on `company_id`, `customer_id`, `invoice_id`, (`company_id`, `status`).
- RLS: same pattern with company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.credit_notes'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','customer_id','invoice_id','credit_note_number','credit_date','amount','base_currency','reason','status','notes','terms','sent_at','posted_at','voided_at','cancellation_reason','journal_entry_id','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','customer_id'=>'string','invoice_id'=>'string','credit_date'=>'date','amount'=>'decimal:2','sent_at'=>'datetime','posted_at'=>'datetime','voided_at'=>'datetime','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Customer; belongsTo Invoice.
- Validation:
  - `customer_id`: required|uuid|exists:acct.customers,id.
  - `invoice_id`: nullable|uuid|exists:acct.invoices,id.
  - `credit_note_number`: required|string|max:50 (unique per company, soft-delete aware).
  - `credit_date`: required|date.
  - `amount`: required|numeric|min:0.01.
  - `base_currency`: required|string|size:3|uppercase.
  - `reason`: required|string|max:255.
  - `status`: in:draft,issued,applied,void.
  - `notes`: nullable|string.
  - `terms`: nullable|string. *(Extension)*
  - `sent_at`/`posted_at`/`voided_at`: nullable|date. *(Extension)*
- Business rules:
  - Credit amount cannot exceed invoice balance if tied to an invoice.
  - Currency must match invoice/customer base currency.
  - On apply, reduce invoice balance/paid_amount per chosen accounting treatment (define in service).
  - Status transitions: draft → issued → applied; void stops usage.
  - In this implementation, line items/applications are enabled; amount should align with sum of items, and applications must not exceed available credit. Document any future GL posting in `journal_entry_id`.

### acct.credit_note_items *(Extension to support itemized credits)*
- Purpose: item-level detail for credit notes.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `credit_note_id` uuid not null FK → `acct.credit_notes.id` (CASCADE/CASCADE).
  - `line_number` integer not null.
  - `description` varchar(500) not null.
  - `quantity` numeric(10,2) not null default 1.00.
  - `unit_price` numeric(15,2) not null default 0.00.
  - `tax_rate` numeric(5,2) not null default 0.00.
  - `discount_rate` numeric(5,2) not null default 0.00.
  - `line_total` numeric(15,2) not null default 0.00.
  - `tax_amount` numeric(15,2) not null default 0.00.
  - `total` numeric(15,2) not null default 0.00.
  - `created_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid null FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at`.
- Indexes/constraints: PK `id`; indexes on `company_id`; (`credit_note_id`, `line_number`); unique (`credit_note_id`, `line_number`) enforced; check constraints ensure positive quantities/amounts and discount_rate ≤ 100.
- RLS: company isolation + super-admin override (same pattern).
- Model: `$fillable = ['company_id','credit_note_id','line_number','description','quantity','unit_price','tax_rate','discount_rate','line_total','tax_amount','total','created_by_user_id','updated_by_user_id'];` with casts matching numeric/date fields above.
- Business rules: line numbers sequential per credit note; calculated fields computed server-side; edits blocked after issuance if business rules require.

### acct.credit_note_applications *(Extension to allow applying credits to invoices)*
- Purpose: allocate a credit note to an invoice.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `credit_note_id` uuid not null FK → `acct.credit_notes.id` (CASCADE/CASCADE).
  - `invoice_id` uuid not null FK → `acct.invoices.id` (CASCADE/CASCADE).
  - `amount_applied` numeric(15,2) not null (check > 0).
  - `applied_at` timestamp not null default now().
  - `user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `notes` text nullable.
  - `invoice_balance_before` numeric(15,2) not null.
  - `invoice_balance_after` numeric(15,2) not null.
  - `created_at`, `updated_at`.
- Indexes/constraints: indexes on company_id, credit_note_id, invoice_id, applied_at; unique (`credit_note_id`, `invoice_id`) enforced; check invoice_balance_before ≥ invoice_balance_after.
- RLS: company isolation + super-admin override.
- Model: `$fillable = ['company_id','credit_note_id','invoice_id','amount_applied','applied_at','user_id','notes','invoice_balance_before','invoice_balance_after'];` with casts for numeric/datetime fields.
- Business rules: sum of `amount_applied` per credit note ≤ credit_note.amount; must not exceed invoice balance; applying should update invoice paid/balance/status accordingly and update credit note status to applied when fully used.

### acct.recurring_schedules
- Purpose: recurrence templates for invoice generation.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `customer_id` uuid not null FK → `acct.customers.id` (RESTRICT/CASCADE).
  - `name` varchar(255) not null.
  - `frequency` varchar(20) not null (daily, weekly, monthly, quarterly, yearly).
  - `interval` integer not null default 1 (every X frequency units).
  - `start_date` date not null.
  - `end_date` date null.
  - `next_invoice_date` date not null.
  - `last_generated_at` timestamp null.
  - `template_data` jsonb not null. Allowed keys: `line_items` (array of {description, quantity, unit_price, tax_rate, discount_rate}), `payment_terms` (int), `base_currency` (char(3)), `notes`, `internal_notes`.
  - `is_active` boolean not null default true.
  - `created_at`, `updated_at`, `deleted_at`.
- Indexes: PK `id`; indexes on `company_id`; `customer_id`; (`company_id`, `is_active`, `next_invoice_date`) where `is_active = true`.
- RLS: same pattern with company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'acct.recurring_schedules'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','customer_id','name','frequency','interval','start_date','end_date','next_invoice_date','last_generated_at','template_data','is_active'];`
  - `$casts = ['company_id'=>'string','customer_id'=>'string','interval'=>'integer','start_date'=>'date','end_date'=>'date','next_invoice_date'=>'date','last_generated_at'=>'datetime','template_data'=>'array','is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Customer; hasMany Invoice.
- Validation:
  - `customer_id`: required|uuid|exists:acct.customers,id.
  - `name`: required|string|max:255.
  - `frequency`: required|in:daily,weekly,monthly,quarterly,yearly.
  - `interval`: required|integer|min:1|max:365.
  - `start_date`: required|date.
  - `end_date`: nullable|date|after:start_date.
  - `next_invoice_date`: required|date|after_or_equal:start_date.
  - `template_data`: required|array with allowed keys above; `line_items` must match invoice line validation (description, quantity>=0.01, unit_price>=0, tax_rate/discount_rate 0-100).
  - `is_active`: boolean.
- Business rules:
  - Generated invoices inherit customer base currency, payment terms, and line items from template unless overridden explicitly in template_data.
  - Update `next_invoice_date` after generation; set `last_generated_at` timestamp.
  - Stop generation when `is_active` false or `end_date` passed.

## Shared validation snippets
- Base currency: `required|string|size:3|uppercase`.
- UUID FKs: `required|uuid|exists:<schema.table>,id` (schema-qualified).
- Soft-delete aware uniqueness: apply `whereNull('deleted_at')` in rules/queries.

## Enums Reference

### Invoice Status
| Status   | Description               | Can Edit? | Can Pay? |
|----------|---------------------------|-----------|----------|
| draft    | Not sent                  | ✅        | ❌       |
| sent     | Delivered to customer     | ❌        | ✅       |
| viewed   | Customer opened           | ❌        | ✅       |
| partial  | Partially paid            | ❌        | ✅       |
| paid     | Fully paid                | ❌        | ❌       |
| overdue  | Past due, unpaid          | ❌        | ✅       |
| void     | Cancelled, no effect      | ❌        | ❌       |
| cancelled| Cancelled before send     | ❌        | ❌       |

### Credit Note Status
| Status | Description            |
|--------|------------------------|
| draft  | Not issued             |
| issued | Active, can be applied |
| applied| Used against invoice   |
| void   | Cancelled              |

## Out of Scope (v1)
- Multi-currency invoices (all amounts in base_currency).
- Multiple addresses per customer (single billing/shipping JSON only).
- Product/service catalog (line items are freeform).
- Tax jurisdictions / tax codes.
- Approval workflows.
- Email delivery tracking.

## Extending
- If a new column/enum value is required, add it here first, then add migration + validation + resource + form updates in one cohesive change.
- Keep enums and validation snippets in sync across requests, DTOs, Vue components, and tests.
