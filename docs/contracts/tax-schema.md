# Schema Contract — Tax Management (tax)

Single source of truth for tax jurisdictions, rates, groups, and company tax settings. Integrates with AR/AP line items for tax calculations.

## Guardrails
- Schema: `tax` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Soft deletes via `deleted_at` on company-scoped tables.
- RLS required with company isolation + super-admin override.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`.
- Tax rates stored as percentage (e.g., 15.00 for 15%).
- Tax amounts calculated at line level, summed to document level.
- Compound taxes calculated in sequence by priority.

## Tables

### tax.jurisdictions
- Purpose: define tax regions (countries, states, cities).
- Columns:
  - `id` uuid PK.
  - `parent_id` uuid nullable FK → `tax.jurisdictions.id` (SET NULL/CASCADE).
  - `country_code` char(2) not null FK → `public.countries.code`.
  - `code` varchar(50) not null (e.g., 'PK', 'US-CA', 'US-CA-SF').
  - `name` varchar(255) not null.
  - `level` varchar(20) not null default 'country'. Enum: country, state, county, city.
  - `is_active` boolean not null default true.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`country_code`, `code`).
  - Index: `country_code`; `parent_id`; `level`.
- RLS: None (reference data, shared across companies).
- Model:
  - `$connection = 'pgsql'; $table = 'tax.jurisdictions'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['parent_id','country_code','code','name','level','is_active'];`
  - `$casts = ['parent_id'=>'string','is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Parent (self); hasMany Children (self); hasMany TaxRate.
- Validation:
  - `country_code`: required|string|size:2|exists:public.countries,code.
  - `code`: required|string|max:50; unique per country.
  - `name`: required|string|max:255.
  - `level`: required|in:country,state,county,city.
- Business rules:
  - Seed with common jurisdictions (Pakistan, US states, UAE, etc.).
  - Hierarchy: country → state → county → city.
  - Used by tax rates to define applicability.

### tax.company_tax_settings
- Purpose: per-company tax feature toggle and defaults.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null unique FK → `auth.companies.id` (CASCADE/CASCADE).
  - `tax_enabled` boolean not null default false.
  - `default_jurisdiction_id` uuid nullable FK → `tax.jurisdictions.id` (SET NULL/CASCADE).
  - `default_sales_tax_rate_id` uuid nullable FK → `tax.tax_rates.id` (SET NULL/CASCADE).
  - `default_purchase_tax_rate_id` uuid nullable FK → `tax.tax_rates.id` (SET NULL/CASCADE).
  - `price_includes_tax` boolean not null default false (tax-inclusive pricing).
  - `rounding_mode` varchar(20) not null default 'half_up'. Enum: half_up, half_down, floor, ceiling, bankers.
  - `rounding_precision` smallint not null default 2; check (0-6).
  - `tax_number_label` varchar(50) not null default 'Tax ID' (e.g., 'VAT Number', 'GST Number', 'NTN').
  - `show_tax_column` boolean not null default true (show tax on invoices).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique `company_id`.
- RLS:
  ```sql
  alter table tax.company_tax_settings enable row level security;
  create policy company_tax_settings_policy on tax.company_tax_settings
    for all using (
      company_id = current_setting('app.current_company_id', true)::uuid
      or current_setting('app.is_super_admin', true)::boolean = true
    );
  ```
- Model:
  - `$connection = 'pgsql'; $table = 'tax.company_tax_settings'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','tax_enabled','default_jurisdiction_id','default_sales_tax_rate_id','default_purchase_tax_rate_id','price_includes_tax','rounding_mode','rounding_precision','tax_number_label','show_tax_column'];`
  - `$casts = ['company_id'=>'string','tax_enabled'=>'boolean','default_jurisdiction_id'=>'string','default_sales_tax_rate_id'=>'string','default_purchase_tax_rate_id'=>'string','price_includes_tax'=>'boolean','rounding_precision'=>'integer','show_tax_column'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo DefaultJurisdiction; belongsTo DefaultSalesTaxRate; belongsTo DefaultPurchaseTaxRate.
- Validation:
  - `tax_enabled`: boolean.
  - `price_includes_tax`: boolean.
  - `rounding_mode`: in:half_up,half_down,floor,ceiling,bankers.
  - `rounding_precision`: integer|min:0|max:6.
  - `tax_number_label`: string|max:50.
- Business rules:
  - Created automatically when company enables tax module.
  - If tax_enabled = false, skip tax calculations entirely.
  - Defaults applied when creating invoices/bills if no override.

### tax.tax_rates
- Purpose: individual tax rates per company and jurisdiction.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `jurisdiction_id` uuid not null FK → `tax.jurisdictions.id` (RESTRICT/CASCADE).
  - `code` varchar(50) not null (e.g., 'VAT-STD', 'GST-5', 'SALES-TAX').
  - `name` varchar(255) not null (e.g., 'Standard VAT 17%', 'Reduced Rate 5%').
  - `rate` numeric(8,4) not null; check (rate >= 0 AND rate <= 100).
  - `tax_type` varchar(30) not null default 'sales'. Enum: sales, purchase, withholding, both.
  - `is_compound` boolean not null default false.
  - `compound_priority` integer not null default 0 (order for compound calculation).
  - `gl_account_id` uuid nullable FK → `acct.accounts.id` (liability account for collected tax).
  - `recoverable_account_id` uuid nullable FK → `acct.accounts.id` (asset account for input tax).
  - `effective_from` date not null default current_date.
  - `effective_to` date nullable.
  - `is_default` boolean not null default false.
  - `is_active` boolean not null default true.
  - `description` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `code`, `effective_from`) where deleted_at is null.
  - Index: `company_id`; `jurisdiction_id`; (`company_id`, `tax_type`, `is_active`); (`company_id`, `is_default`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'tax.tax_rates'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','jurisdiction_id','code','name','rate','tax_type','is_compound','compound_priority','gl_account_id','recoverable_account_id','effective_from','effective_to','is_default','is_active','description','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','jurisdiction_id'=>'string','rate'=>'decimal:4','is_compound'=>'boolean','compound_priority'=>'integer','gl_account_id'=>'string','recoverable_account_id'=>'string','effective_from'=>'date','effective_to'=>'date','is_default'=>'boolean','is_active'=>'boolean','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Jurisdiction; belongsTo GlAccount; belongsTo RecoverableAccount; hasManyThrough TaxGroupComponent.
- Validation:
  - `jurisdiction_id`: required|uuid|exists:tax.jurisdictions,id.
  - `code`: required|string|max:50; unique per company+effective_from (soft-delete aware).
  - `name`: required|string|max:255.
  - `rate`: required|numeric|min:0|max:100.
  - `tax_type`: required|in:sales,purchase,withholding,both.
  - `is_compound`: boolean.
  - `compound_priority`: integer|min:0.
  - `effective_from`: required|date.
  - `effective_to`: nullable|date|after:effective_from.
  - `gl_account_id`: nullable|uuid|exists:acct.accounts,id (must be liability type).
  - `recoverable_account_id`: nullable|uuid|exists:acct.accounts,id (must be asset type).
- Business rules:
  - Rate lookup by effective_from/to dates.
  - Version tax rates by creating new record with different effective_from.
  - Compound taxes: calculate in compound_priority order, each on subtotal + previous taxes.
  - gl_account_id for sales tax collected (liability); recoverable_account_id for purchase tax (asset).
  - Cannot delete rate with existing usage; deactivate instead.

### tax.tax_groups
- Purpose: combine multiple tax rates (e.g., GST + PST = Combined 12%).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `jurisdiction_id` uuid not null FK → `tax.jurisdictions.id` (RESTRICT/CASCADE).
  - `code` varchar(50) not null.
  - `name` varchar(255) not null.
  - `is_default` boolean not null default false.
  - `is_active` boolean not null default true.
  - `description` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `code`) where deleted_at is null.
  - Index: `company_id`; (`company_id`, `is_active`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'tax.tax_groups'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','jurisdiction_id','code','name','is_default','is_active','description','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','jurisdiction_id'=>'string','is_default'=>'boolean','is_active'=>'boolean','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Jurisdiction; hasMany TaxGroupComponent.
- Validation:
  - `jurisdiction_id`: required|uuid|exists:tax.jurisdictions,id.
  - `code`: required|string|max:50; unique per company (soft-delete aware).
  - `name`: required|string|max:255.
- Business rules:
  - Combined rate = sum of component rates (non-compound) or calculated sequentially (compound).
  - Used on invoices/bills as shorthand for multiple taxes.
  - Display as single line or itemized based on company preference.

### tax.tax_group_components
- Purpose: link tax rates to tax groups.
- Columns:
  - `id` uuid PK.
  - `tax_group_id` uuid not null FK → `tax.tax_groups.id` (CASCADE/CASCADE).
  - `tax_rate_id` uuid not null FK → `tax.tax_rates.id` (RESTRICT/CASCADE).
  - `priority` smallint not null default 1 (calculation order).
  - `created_at` timestamp default now().
- Indexes/constraints:
  - PK `id`.
  - Unique (`tax_group_id`, `tax_rate_id`).
  - Index: `tax_group_id`; `tax_rate_id`.
- RLS: inherited from parent (tax_groups).
- Model:
  - `$connection = 'pgsql'; $table = 'tax.tax_group_components'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['tax_group_id','tax_rate_id','priority'];`
  - `$casts = ['tax_group_id'=>'string','tax_rate_id'=>'string','priority'=>'integer','created_at'=>'datetime'];`
- Relationships: belongsTo TaxGroup; belongsTo TaxRate.
- Business rules:
  - Priority determines calculation order for compound taxes.
  - All components must be same tax_type (sales, purchase, or both).

### tax.company_tax_registrations
- Purpose: company's tax registration numbers per jurisdiction.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `jurisdiction_id` uuid not null FK → `tax.jurisdictions.id` (RESTRICT/CASCADE).
  - `registration_number` varchar(100) not null.
  - `registration_type` varchar(50) not null default 'vat'. Enum: vat, gst, sales_tax, withholding, other.
  - `registered_name` varchar(255) nullable (if different from company name).
  - `effective_from` date not null.
  - `effective_to` date nullable.
  - `is_active` boolean not null default true.
  - `notes` text nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `jurisdiction_id`, `registration_number`).
  - Index: `company_id`; (`company_id`, `is_active`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'tax.company_tax_registrations'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','jurisdiction_id','registration_number','registration_type','registered_name','effective_from','effective_to','is_active','notes','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','jurisdiction_id'=>'string','effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo Jurisdiction.
- Validation:
  - `jurisdiction_id`: required|uuid|exists:tax.jurisdictions,id.
  - `registration_number`: required|string|max:100; unique per company+jurisdiction.
  - `registration_type`: required|in:vat,gst,sales_tax,withholding,other.
  - `effective_from`: required|date.
  - `effective_to`: nullable|date|after:effective_from.
- Business rules:
  - Printed on invoices based on customer's jurisdiction.
  - Can have multiple registrations (e.g., VAT in multiple countries).
  - Lookup registration by jurisdiction when generating documents.

### tax.tax_exemptions
- Purpose: exemption reasons for customers/vendors.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `code` varchar(50) not null.
  - `name` varchar(255) not null.
  - `description` text nullable.
  - `exemption_type` varchar(30) not null default 'full'. Enum: full, partial, rate_override.
  - `override_rate` numeric(8,4) nullable (for rate_override type).
  - `requires_certificate` boolean not null default false.
  - `is_active` boolean not null default true.
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `code`) where deleted_at is null.
  - Index: `company_id`; (`company_id`, `is_active`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'tax.tax_exemptions'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','code','name','description','exemption_type','override_rate','requires_certificate','is_active'];`
  - `$casts = ['company_id'=>'string','exemption_type'=>'string','override_rate'=>'decimal:4','requires_certificate'=>'boolean','is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Validation:
  - `code`: required|string|max:50; unique per company (soft-delete aware).
  - `name`: required|string|max:255.
  - `exemption_type`: required|in:full,partial,rate_override.
  - `override_rate`: nullable|numeric|min:0|max:100 (required if exemption_type = rate_override).
- Business rules:
  - Assign to customer/vendor for automatic exemption.
  - full = 0% tax, partial = reduced rate, rate_override = specific rate.
  - If requires_certificate, prompt for certificate number on invoice.

## Tax Calculation Logic

### Single Tax Rate
```
line_total = quantity * unit_price * (1 - discount_rate/100)
tax_amount = ROUND(line_total * tax_rate/100, precision)
total = line_total + tax_amount
```

### Compound Taxes (Priority Order)
```
base = line_total
for each tax in order of compound_priority:
  if is_compound:
    tax_amount = ROUND(running_total * rate/100, precision)
  else:
    tax_amount = ROUND(base * rate/100, precision)
  running_total += tax_amount
  taxes.push({rate_id, amount})
```

### Tax-Inclusive Pricing
```
# When price_includes_tax = true
base_amount = total / (1 + tax_rate/100)
tax_amount = total - base_amount
```

## Enums Reference

### Tax Type
| Type | Description | Used For |
|------|-------------|----------|
| sales | Sales/output tax | AR invoices |
| purchase | Purchase/input tax | AP bills |
| withholding | Withholding tax | Both |
| both | Applies to both | Either |

### Exemption Type
| Type | Description |
|------|-------------|
| full | Completely exempt (0%) |
| partial | Reduced rate applies |
| rate_override | Specific rate instead of standard |

### Rounding Mode
| Mode | Description |
|------|-------------|
| half_up | Round 0.5 up (default) |
| half_down | Round 0.5 down |
| floor | Always round down |
| ceiling | Always round up |
| bankers | Round to nearest even |

## Form Behaviors

### Tax Settings Form
- Fields: tax_enabled, default_jurisdiction_id, default_sales_tax_rate_id, default_purchase_tax_rate_id, price_includes_tax, rounding_mode, rounding_precision, tax_number_label, show_tax_column
- Jurisdiction dropdown from tax.jurisdictions
- Tax rate dropdowns filtered by company and active
- Preview of tax calculation with sample amounts

### Tax Rate Form
- Fields: jurisdiction_id, code, name, rate, tax_type, is_compound, compound_priority, gl_account_id, recoverable_account_id, effective_from, effective_to, is_default, description
- Rate input as percentage with preview
- GL account dropdowns filtered to appropriate types
- Effective date range for versioning

### Tax Group Form
- Fields: jurisdiction_id, code, name, components[]
- Each component: tax_rate_id, priority
- Shows combined rate preview
- Validates all components are same tax_type

## Out of Scope (v1)
- Automatic tax rate updates from government sources.
- Tax return filing/generation.
- Reverse charge mechanism (for EU VAT).
- Place of supply rules (digital services).
- Tax point (point of supply) rules.
- Tax audit reports.

## Extending
- Add new tax_type values here first.
- Consider `tax.tax_returns` for return filing in future.
- Withholding tax may need separate line item tracking.
