# Schema Contract — Posting Templates (acct)

Single source of truth for automatic GL posting configuration. Defines how AR/AP documents generate journal entries.

## Guardrails
- Schema: `acct` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Soft deletes via `deleted_at`.
- RLS required with company isolation + super-admin override.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`.
- Templates are company-specific; each company configures their own posting rules.
- All auto-generated transactions must balance (debit = credit).

## Tables

### acct.posting_templates
- Purpose: header for posting rules by document type.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `doc_type` varchar(30) not null. Enum: AR_INVOICE, AR_PAYMENT, AR_CREDIT_NOTE, AP_BILL, AP_PAYMENT, AP_VENDOR_CREDIT, BANK_TRANSFER, BANK_FEE, PAYROLL.
  - `name` varchar(255) not null.
  - `description` text nullable.
  - `is_active` boolean not null default true.
  - `is_default` boolean not null default false (default template for doc_type).
  - `version` integer not null default 1.
  - `effective_from` date not null default current_date.
  - `effective_to` date nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `doc_type`, `name`) where deleted_at is null.
  - Index: `company_id`; (`company_id`, `doc_type`, `is_active`); (`company_id`, `is_default`).
- RLS:
  ```sql
  alter table acct.posting_templates enable row level security;
  create policy posting_templates_policy on acct.posting_templates
    for all using (
      company_id = current_setting('app.current_company_id', true)::uuid
      or current_setting('app.is_super_admin', true)::boolean = true
    );
  ```
- Model:
  - `$connection = 'pgsql'; $table = 'acct.posting_templates'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','doc_type','name','description','is_active','is_default','version','effective_from','effective_to','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','is_active'=>'boolean','is_default'=>'boolean','version'=>'integer','effective_from'=>'date','effective_to'=>'date','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; hasMany PostingTemplateLine.
- Validation:
  - `doc_type`: required|in:AR_INVOICE,AR_PAYMENT,AR_CREDIT_NOTE,AP_BILL,AP_PAYMENT,AP_VENDOR_CREDIT,BANK_TRANSFER,BANK_FEE,PAYROLL.
  - `name`: required|string|max:255; unique per company+doc_type (soft-delete aware).
  - `is_active`: boolean.
  - `is_default`: boolean.
  - `effective_from`: required|date.
  - `effective_to`: nullable|date|after:effective_from.
- Business rules:
  - Only one is_default = true per company+doc_type.
  - Version templates by creating new record with different effective_from.
  - Default template used when posting if no specific template assigned.
  - Cannot delete template with posted transactions referencing it.

### acct.posting_template_lines
- Purpose: individual account mappings within a template.
- Columns:
  - `id` uuid PK.
  - `template_id` uuid not null FK → `acct.posting_templates.id` (CASCADE/CASCADE).
  - `role` varchar(50) not null. Enum: AR, AP, REVENUE, EXPENSE, TAX_PAYABLE, TAX_RECEIVABLE, DISCOUNT_GIVEN, DISCOUNT_RECEIVED, SHIPPING, BANK, CASH, CLEARING, RETAINED_EARNINGS, SUSPENSE.
  - `account_id` uuid not null FK → `acct.accounts.id` (RESTRICT/CASCADE).
  - `description` varchar(255) nullable (default line description).
  - `precedence` smallint not null default 1 (order of posting lines).
  - `is_required` boolean not null default true.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`template_id`, `role`).
  - Index: `template_id`; `account_id`.
- RLS: inherited from parent (posting_templates).
- Model:
  - `$connection = 'pgsql'; $table = 'acct.posting_template_lines'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['template_id','role','account_id','description','precedence','is_required'];`
  - `$casts = ['template_id'=>'string','account_id'=>'string','precedence'=>'integer','is_required'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo PostingTemplate; belongsTo Account.
- Validation:
  - `role`: required|in:AR,AP,REVENUE,EXPENSE,TAX_PAYABLE,TAX_RECEIVABLE,DISCOUNT_GIVEN,DISCOUNT_RECEIVED,SHIPPING,BANK,CASH,CLEARING,RETAINED_EARNINGS,SUSPENSE.
  - `account_id`: required|uuid|exists:acct.accounts,id.
  - `precedence`: integer|min:1.
- Business rules:
  - Role determines which document field maps to which account.
  - Account must be appropriate type for role (e.g., AR role → asset/receivable subtype).
  - Required roles must have accounts for template to be valid.

## Posting Role Requirements by Document Type

### AR_INVOICE (Required: AR, REVENUE)
| Role | Debit/Credit | Source Field | Account Type |
|------|--------------|--------------|--------------|
| AR | Debit | total_amount | Asset (accounts_receivable) |
| REVENUE | Credit | subtotal - discount | Revenue |
| TAX_PAYABLE | Credit | tax_amount | Liability |
| DISCOUNT_GIVEN | Debit | discount_amount | Expense (contra-revenue) |
| SHIPPING | Credit | shipping_amount | Revenue (other_income) |

### AR_PAYMENT (Required: AR, BANK or CASH)
| Role | Debit/Credit | Source Field | Account Type |
|------|--------------|--------------|--------------|
| BANK/CASH | Debit | amount | Asset (bank/cash) |
| AR | Credit | amount_allocated | Asset (accounts_receivable) |

### AR_CREDIT_NOTE (Required: AR, REVENUE)
| Role | Debit/Credit | Source Field | Account Type |
|------|--------------|--------------|--------------|
| REVENUE | Debit | amount | Revenue (contra) |
| AR | Credit | amount | Asset (accounts_receivable) |
| TAX_PAYABLE | Debit | tax_amount | Liability |

### AP_BILL (Required: AP, EXPENSE)
| Role | Debit/Credit | Source Field | Account Type |
|------|--------------|--------------|--------------|
| EXPENSE | Debit | subtotal - discount | Expense |
| TAX_RECEIVABLE | Debit | tax_amount | Asset |
| DISCOUNT_RECEIVED | Credit | discount_amount | Revenue (other_income) |
| AP | Credit | total_amount | Liability (accounts_payable) |

### AP_PAYMENT (Required: AP, BANK or CASH)
| Role | Debit/Credit | Source Field | Account Type |
|------|--------------|--------------|--------------|
| AP | Debit | amount_allocated | Liability (accounts_payable) |
| BANK/CASH | Credit | amount | Asset (bank/cash) |

### AP_VENDOR_CREDIT (Required: AP, EXPENSE)
| Role | Debit/Credit | Source Field | Account Type |
|------|--------------|--------------|--------------|
| AP | Debit | amount | Liability (accounts_payable) |
| EXPENSE | Credit | amount | Expense (contra) |
| TAX_RECEIVABLE | Credit | tax_amount | Asset |

## Auto-Posting Functions

### post_ar_invoice(invoice_id, template_id)
```sql
-- Creates GL transaction from AR invoice
-- 1. Look up template lines
-- 2. Create transaction header
-- 3. Create journal entries:
--    - Debit AR for total_amount
--    - Credit Revenue for (subtotal - discount)
--    - Credit Tax Payable for tax_amount (if tax enabled)
--    - Debit Discount Given for discount_amount (if > 0)
--    - Credit Shipping for shipping_amount (if > 0)
-- 4. Link transaction to invoice via reference_type/reference_id
-- 5. Update invoice.gl_transaction_id (if column exists)
```

### post_ap_bill(bill_id, template_id)
```sql
-- Creates GL transaction from AP bill
-- 1. Look up template lines
-- 2. Create transaction header
-- 3. Create journal entries:
--    - Debit Expense for (subtotal - discount)
--    - Debit Tax Receivable for tax_amount (if tax enabled)
--    - Credit Discount Received for discount_amount (if > 0)
--    - Credit AP for total_amount
-- 4. Link transaction to bill
```

## Trigger: Auto-Post on Status Change

```sql
-- When invoice status changes to 'posted', auto-generate GL entry
create or replace function acct.trg_autopost_ar_invoice()
returns trigger as $$
declare
  v_template_id uuid;
begin
  if new.status = 'posted' and (old.status is distinct from 'posted') then
    -- Find default template
    select id into v_template_id
    from acct.posting_templates
    where company_id = new.company_id
      and doc_type = 'AR_INVOICE'
      and is_active = true
      and is_default = true
    limit 1;

    if v_template_id is null then
      raise exception 'No active AR_INVOICE posting template for company %', new.company_id;
    end if;

    perform acct.post_ar_invoice(new.id, v_template_id);
  end if;
  return new;
end;
$$ language plpgsql;
```

## Enums Reference

### Document Types
| Type | Description | Trigger |
|------|-------------|---------|
| AR_INVOICE | Sales invoice posting | On status = 'posted' |
| AR_PAYMENT | Customer payment | On creation |
| AR_CREDIT_NOTE | Credit note/refund | On status = 'issued' |
| AP_BILL | Purchase bill posting | On status = 'posted' |
| AP_PAYMENT | Vendor payment | On creation |
| AP_VENDOR_CREDIT | Vendor credit | On status = 'received' |
| BANK_TRANSFER | Internal transfer | Manual |
| BANK_FEE | Bank fee posting | On match |
| PAYROLL | Payroll posting | On approval |

### Posting Roles
| Role | Description | Typical Account Type |
|------|-------------|---------------------|
| AR | Accounts Receivable control | Asset (accounts_receivable) |
| AP | Accounts Payable control | Liability (accounts_payable) |
| REVENUE | Sales/service revenue | Revenue |
| EXPENSE | Expenses | Expense |
| TAX_PAYABLE | Output tax liability | Liability |
| TAX_RECEIVABLE | Input tax asset | Asset |
| DISCOUNT_GIVEN | Sales discounts | Expense/Contra-revenue |
| DISCOUNT_RECEIVED | Purchase discounts | Revenue/Other income |
| SHIPPING | Shipping charges | Revenue |
| BANK | Bank account | Asset (bank) |
| CASH | Cash account | Asset (cash) |
| CLEARING | Clearing/suspense | Asset/Liability |
| RETAINED_EARNINGS | Year-end close | Equity |
| SUSPENSE | Suspense account | Liability |

## Form Behaviors

### Posting Template Form
- Fields: doc_type, name, description, is_active, is_default, effective_from, effective_to, lines[]
- Each line: role (dropdown), account_id (filtered by role), description
- Role dropdown shows only applicable roles for selected doc_type
- Account dropdown filtered by account type appropriate for role
- Validation: all required roles must have accounts

### Template Validation
```php
// Before saving, validate template completeness
$requiredRoles = [
  'AR_INVOICE' => ['AR', 'REVENUE'],
  'AR_PAYMENT' => ['AR'], // + BANK or CASH
  'AP_BILL' => ['AP', 'EXPENSE'],
  'AP_PAYMENT' => ['AP'], // + BANK or CASH
  // ...
];
```

### Test Posting Preview
- Select document (invoice, bill, etc.)
- Shows preview of journal entry that would be created
- Validates template before allowing posting
- Dry-run mode for testing

## Company Onboarding

When a company is created or enables accounting:
1. Create default posting templates for each doc_type
2. Prompt to map accounts based on chart of accounts
3. Allow customization before first document posting
4. Provide "Copy from Template" for quick setup

## Out of Scope (v1)
- Multi-line posting per role (e.g., split revenue by product category).
- Conditional posting rules (if amount > X, use account Y).
- Dimension-based posting (cost center, project).
- Currency gain/loss automatic posting.
- Intercompany posting templates.

## Extending
- Add new doc_type values here first.
- Add new role values here first.
- Consider adding `acct.posting_rules` for conditional logic in future.
