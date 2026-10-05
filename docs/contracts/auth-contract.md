# Schema Contract — Auth (Users & Companies)

Company settings additionally allow `umrah_voucher`: a footer/contact defaults object following the voucher profile shape in `umrah-schema.md`. Updating this key preserves all unrelated settings and never rewrites voucher snapshots.

Single source of truth for the shared auth schema. Read this before touching migrations, models, requests, resources, or Vue forms. Do not invent new columns/props; if something is missing, pause and update this contract first.

## Guardrails
- Tables live in Postgres `auth` schema on the `pgsql` connection.
- Currency fields use uppercase ISO 4217 codes, length 3 (`base_currency` only; do not introduce `currency`/`baseCurrency` variants).
- Slugs are derived from `name` via `Str::slug` with `-N` suffix for uniqueness; never require a slug input from the UI.
- System role: prefer `super_admin`, `admin`, `user`, `guest` for platform-level permissions. Legacy values (`superadmin`, `system_owner`, `company_owner`, etc.) exist; new code must standardize on `super_admin` and avoid introducing new variants.
- Company membership role enum is fixed: `owner`, `manager`, `accountant`, `operations`, `agent`.
  `manager` is the company administrator role. `operations` is displayed as
  "Operations Clerk" and is intentionally denied accounting, price, cost, and
  profitability data. `agent` remains reserved for linked external Travel agents.
- Row Level Security is enabled; APIs must set `app.current_user_id` and `app.is_super_admin` session settings where required.
- Currency: `base_currency` is a `char(3)` code (ISO 4217). No FK; validate against `public.currencies` (see currencies contract). `base_currency` is immutable once transactions exist.
- Reserved columns: `exchange_rate_id` was previously reserved—do not use. All currency work follows the multi-currency contracts (codes only, no FK IDs).
- Company creation exposes only production-ready industries: `fuel_station` (Petrol Pump), `travel` (Travel), and `other` (Other). Other industry COA packs remain inactive legacy/reference data and cannot be selected for new companies or onboarding.

## Tables

### auth.users
- Columns:  
  - `id` uuid PK.  
  - `name` string(255) not null.  
  - `username` string unique not null.  
  - `email` string unique not null.  
  - `email_verified_at` timestamp nullable.  
  - `password` string not null.  
  - `system_role` string default `user`.  
  - `stamp_path` string(500) nullable, `signature_path` string(500) nullable. Public-disk paths (`company-stamps/{company_id}/{uuid}.png`, `company-signatures/{company_id}/{uuid}.png`); served at `/storage/{path}`. Uploads are re-encoded to PNG.  
  - `signer_name` string(120) nullable, `signer_title` string(120) nullable. Printed under the signature line.  
  - `stamp_documents` jsonb nullable. Map of document type => bool for where the stamp appears: `invoice`, `consolidated_invoice`, `statement` (customer statements), `payment_receipt`, `credit_note`, `bill_payment`. Missing keys use defaults (all true except `bill_payment`). Never on bills.  
  - `created_by_user_id` uuid nullable FK → `auth.users.id`.  
  - `is_active` bool default true.  
  - `settings` json nullable.  
  - `remember_token`, `created_at`, `updated_at`.
- Defaults quick ref: `system_role: 'user'`, `is_active: true`.
- FK behavior: `created_by_user_id` → `auth.users.id` (ON DELETE SET NULL, ON UPDATE CASCADE).
- Indexes:  
  - Essential: unique `username`, unique `email`.  
  - Performance: `system_role`, `is_active`, `created_by_user_id`, composite (`is_active`, `system_role`).
- RLS: enabled. Select/update allowed for self or when `current_setting('app.is_super_admin') = true`.
- Laravel model (canonical):  
  - `$connection = 'pgsql';`  
  - `$table = 'auth.users';`  
  - `$fillable = ['name', 'username', 'email', 'password', 'system_role', 'is_active', 'settings'];`  
  - `$hidden = ['password', 'remember_token'];`  
  - `$casts = ['email_verified_at' => 'datetime', 'settings' => 'array', 'is_active' => 'boolean', 'password' => 'hashed'];`

Authentication does not require email verification. `email_verified_at` is retained as nullable legacy metadata and must not gate login, dashboard access, company access, or profile updates.
- Relationships:  
  - belongsToMany Company via `auth.company_user` (pivot: role, is_active, joined_at, left_at).  
  - hasMany User (as createdUsers) via `created_by_user_id`.  
  - hasMany UserSetting.
- Validation/DTO expectations:  
  - Name: 2–255 chars, letters/spaces/hyphen/apostrophe/dot allowed.  
  - Username: 3–255 chars, `[A-Za-z0-9_]+`, unique.  
  - Email: valid email, unique.  
  - Password: min 8, confirmation required; strong password pattern used in admin flows.  
  - System role: `super_admin|admin|user|guest` (default `user`).  
  - is_active: boolean.
  - Umrah agent credentials are created or changed directly from the agent create/edit form using username and password. Passwords are accepted only for the request and stored through the model's `hashed` cast; plaintext credentials are never persisted for later display.

### auth.companies
- Columns:  
  - `id` uuid PK.  
  - `name` string(255) not null.  
  - `industry` string nullable.  
  - `industry_code` string nullable. New values are restricted to `fuel_station`, `travel`, or `other`.
  - `slug` string unique not null (auto-generated).  
  - `country` string nullable; `country_id` uuid nullable.  
  - `address` jsonb nullable. The company's postal address, printed as the letterhead on every document it issues. Same shape as `acct.customers.billing_address`: optional string keys `line1`, `line2`, `street`, `city`, `state`, `postal_code`, `country`. Absent keys are omitted from the rendered address rather than printed empty.  
  - `base_currency` char(3) not null default `USD` (must exist and be active in `public.currencies`; immutable after transactions).  
  - `language` string(10) default `en`; `locale` string(10) default `en_US`.  
  - `settings` json nullable. Allowed root keys: `contact_email` (string), `contact_phone` (string), `website` (string), `modules` (object of moduleKey => boolean), `fiscal_year_start_month` (int 1-12), `auto_create_fiscal_year` (boolean), `default_period_type` (string: monthly|quarterly|yearly). Do not add new keys without updating this contract.  
  - Default account IDs (all nullable uuid FK → `acct.accounts.id`):
    - `ar_account_id`, `ap_account_id`, `income_account_id`, `expense_account_id`, `bank_account_id`, `retained_earnings_account_id`,
    - `sales_tax_payable_account_id`, `purchase_tax_receivable_account_id`,
    - `transit_loss_account_id`, `transit_gain_account_id`.
  - `logo_url` string(500) nullable. Stores either a legacy HTTPS logo URL or the public `/storage/company-logos/...` path produced by company settings image upload.
  - `stamp_path` string(500) nullable, `signature_path` string(500) nullable. Public-disk paths (`company-stamps/{company_id}/{uuid}.png`, `company-signatures/{company_id}/{uuid}.png`); served at `/storage/{path}`. Uploads are re-encoded to PNG.  
  - `signer_name` string(120) nullable, `signer_title` string(120) nullable. Printed under the signature line.  
  - `stamp_documents` jsonb nullable. Map of document type => bool for where the stamp appears: `invoice`, `consolidated_invoice`, `statement` (customer statements), `payment_receipt`, `credit_note`, `bill_payment`. Missing keys use defaults (all true except `bill_payment`). Never on bills.  
  - `created_by_user_id` uuid nullable FK → `auth.users.id`.  
  - `is_active` bool default true.  
  - `created_at`, `updated_at`.  
- Defaults quick ref: `base_currency: 'USD'`, `language: 'en'`, `locale: 'en_US'`, `is_active: true`.
- FK behavior: `created_by_user_id` → `auth.users.id` (ON DELETE SET NULL, ON UPDATE CASCADE).
- Constraints/Indexes:  
  - Constraints: PK `id`; unique `slug`; unique (`name`, `country`).  
  - Essential indexes: `slug`; composite (`name`, `country`).  
  - Performance indexes: `country`, `industry`, `base_currency`, `currency_id` (reserved), `exchange_rate_id` (reserved), `is_active`, composite (`is_active`, `country`).
- RLS: enabled. Current policies restrict select/update to super admins; membership-aware policies are planned once context plumbing is finalized.
- Laravel model (canonical):  
  - `$connection = 'pgsql';`  
  - `$table = 'auth.companies';`  
  - `$fillable = ['name', 'industry', 'country', 'country_id', 'address', 'base_currency', 'language', 'locale', 'settings', 'logo_url', 'stamp_path', 'signature_path', 'signer_name', 'signer_title', 'stamp_documents', 'created_by_user_id', 'ar_account_id', 'ap_account_id', 'income_account_id', 'expense_account_id', 'bank_account_id', 'retained_earnings_account_id', 'sales_tax_payable_account_id', 'purchase_tax_receivable_account_id', 'transit_loss_account_id', 'transit_gain_account_id'];`  
  - `$casts = ['settings' => 'array', 'address' => 'array', 'stamp_documents' => 'array', 'industry' => 'string', 'country_id' => 'string', 'created_by_user_id' => 'string', 'is_active' => 'boolean', 'ar_account_id' => 'string', 'ap_account_id' => 'string', 'income_account_id' => 'string', 'expense_account_id' => 'string', 'bank_account_id' => 'string', 'retained_earnings_account_id' => 'string', 'sales_tax_payable_account_id' => 'string', 'purchase_tax_receivable_account_id' => 'string', 'transit_loss_account_id' => 'string', 'transit_gain_account_id' => 'string'];`
- Relationships:  
  - belongsToMany User via `auth.company_user` (pivot: role, is_active, joined_at, left_at).  
  - belongsTo User as creator via `created_by_user_id`.  
  - hasMany CompanyInvitation, Module (via `auth.company_modules`), AuditEntry.  
- Validation/DTO expectations:  
  - Required: `name` (<=255), `base_currency` (exactly 3 uppercase chars).  
  - Optional: `industry`, `country`, `language` (<=10), `locale` (<=10), `settings` (json), `address` (object; each key a string <=255).  
  - Company settings accepts `logo` as PNG/JPEG/WebP, maximum 2 MB, stores it on the public disk, and writes its public path to `logo_url`.  
  - Company settings accepts `stamp` and `signature` as PNG/JPEG/WebP, maximum 1 MB each, plus `remove_stamp`, `remove_signature` (boolean), `signer_name`, `signer_title` (<=120) and `stamp_documents` (object of the keys above => boolean). `CompanyLetterhead::forCompany` returns `stampUrl`, `signatureUrl`, `signerName`, `signerTitle`, `stampDocuments`; `CompanyLetterhead::stampFor()` returns the block for one document only when it is final (not draft/void/cancelled/reversed), its type is ticked, and an image exists. Pages receive it as the `stamp` prop and render it through `DocumentStamp` / `LedgerDocument`.
  - Slug: server-generated; do not accept from UI/clients.  
  - Uniqueness: `slug` unique; (`name`, `country`) pair unique.  
  - Keep payload key as `base_currency` (not `currency`).

### auth.company_user (membership pivot)
- Columns:  
  - `company_id` uuid FK → `auth.companies.id` (on delete cascade).  
  - `user_id` uuid FK → `auth.users.id` (on delete cascade).  
  - `role` enum constrained to `owner|manager|accountant|operations|agent`, default `operations`. The `agent` role is reserved for linked Travel agent logins and must not receive generic company-data permissions.
  - `invited_by_user_id` uuid nullable FK → `auth.users.id` (on delete set null).  
  - `joined_at` timestamp nullable; `left_at` timestamp nullable.  
  - `is_active` bool default true.  
  - `created_at`, `updated_at`.
- Defaults quick ref: `role: 'operations'`, `is_active: true`.
- FK behavior:  
  - `company_id` → `auth.companies.id` (ON DELETE CASCADE, ON UPDATE CASCADE).  
  - `user_id` → `auth.users.id` (ON DELETE CASCADE, ON UPDATE CASCADE).  
  - `invited_by_user_id` → `auth.users.id` (ON DELETE SET NULL, ON UPDATE CASCADE).
- Keys/Indexes: PK (`company_id`, `user_id`); indexes on (`user_id`, `role`), (`company_id`, `role`), `invited_by_user_id`, `is_active`, (`company_id`, `user_id`, `is_active`); role check constraint exists.
- RLS: enabled. Policies allow select for self; insert/update/delete for company owners/managers or super admins; first-owner bootstrap allowed.
- Laravel model (canonical):  
  - `$connection = 'pgsql';`  
  - `$table = 'auth.company_user';`  
  - `$fillable = ['company_id', 'user_id', 'role', 'invited_by_user_id', 'joined_at', 'left_at', 'is_active'];`  
  - `$casts = ['joined_at' => 'datetime', 'left_at' => 'datetime', 'is_active' => 'boolean'];`
- Relationships:  
  - belongsTo Company.  
  - belongsTo User.  
  - belongsTo User as inviter via `invited_by_user_id`.
- Validation/DTO expectations:  
  - `company_id` uuid required, must exist.  
  - `user_id` uuid required, must exist.  
  - `role` in enum above.  
  - `is_active` boolean; `joined_at` optional timestamp on join; set `left_at` when deactivating membership.

### auth.company_currencies (secondary currency enablement)
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK → `auth.companies.id` (CASCADE).
  - `currency_code` char(3) not null (must exist and be active in `public.currencies`; no FK).
  - `exchange_rate` numeric(18,8) not null. Convention: `1 secondary = X base`.
  - `enabled_at` timestamp not null default now().
  - `created_at`, `updated_at`.
- Constraints/Indexes:
  - unique (`company_id`, `currency_code`).
  - check currency exists in `public.currencies` and `is_active = true`.
  - check `exchange_rate > 0`.
- RLS: company isolation policy (company_id match or super_admin).
- Laravel model (canonical):
  - `$connection = 'pgsql'; $table = 'auth.company_currencies'; $fillable = ['company_id','currency_code','exchange_rate','enabled_at']; $casts = ['exchange_rate'=>'decimal:8','enabled_at'=>'datetime'];`
- Business rules:
  - The base currency exists only in `auth.companies.base_currency`; this table stores secondary currencies only.
  - A row cannot use the company's base currency code.
  - Cannot disable if accounts/transactions exist in that currency with balances open.
  - Company creation does not insert a row in this table.
  - Secondary rates are editable defaults only. Posted documents and journals retain immutable rate snapshots.

### auth.partners / auth.partner_transactions (partner ledger)
- `auth.partners` (company-scoped, soft deletes, RLS company isolation):
  - `id` uuid PK; `company_id`; `name`; `phone`; `email`; `cnic`; `address`; `profit_share_percentage` decimal(5,2) 0-100 (share of the monthly profit); `drawing_limit_period` enum `monthly|yearly|none`; `drawing_limit_amount` decimal(15,2) nullable.
  - `capital_account_id` uuid FK -> `acct.accounts` (nullOnDelete): the partner's own **Capital - name** account (equity, credit normal, code 3300-3499, not system). Created with the partner, or lazily by `PartnerLedgerService::accountsFor()`.
  - `drawing_account_id` uuid FK -> `acct.accounts`: the partner's own **Drawings - name** account (equity contra, debit normal, `is_contra = true`, code 3500-3699). A shared equity account chosen on the old form is replaced by an own one on first use.
  - `total_invested`, `total_withdrawn`: sums of the partner's `investment` / `withdrawal` rows (kept by the `auth.update_partner_totals` trigger and `Partner::refreshTotals()`; never incremented by hand). `current_period_withdrawn`, `period_reset_date`: legacy, no longer read.
- `auth.partner_transactions`:
  - `transaction_type` check: `investment | withdrawal | adjustment | profit_share` (`profit_share` amount is signed: negative for a loss; `reference` = `YYYY-MM`).
  - `gl_transaction_id` uuid FK -> `acct.transactions` (nullOnDelete): the journal that carries the movement (the page's own journal, the Daily Close's journal, or the month's profit-share journal). `journal_entry_id` points at the partner's own Capital / Drawings line in it. `bank_account_id` = the cash/bank account used. `payment_method` is display only (`cash`, `bank_transfer`).
- Balance = the books: **Capital - name** balance (credit - debit) minus **Drawings - name** balance (debit - credit). `Partner::net_capital`, the partner page and the Statements `partner` kind all show this figure. (Opening partner capital posted through Opening balances still sits on 2210 Investor Deposits and is not in it.)
- Posting (one path, `App\Services\PartnerLedgerService`, commands `partner.invest` / `partner.withdraw`, FormRequest `PartnerMovementRequest`):
  - invest: `Dr cash/bank, Cr Capital - name`. withdraw: `Dr Drawings - name, Cr cash/bank`. `transaction_type` `partner_investment` / `partner_withdrawal`, `reference_type = auth.partners`, `reference_id = partner id`. Dates in a locked day are refused (`DocumentDateLock`).
  - Daily Close: partner deposits / withdrawals are posted in the close's own journal to each partner's Capital / Drawings account (one line per partner per side, cash side as the close posts it); the `partner_transactions` rows carry the close's `gl_transaction_id`. Edit day / reopen deletes them (`PartnerLedgerService::undoClose`), totals follow. The shared Investor Deposits / Partner Drawings accounts are no longer used for partner money.
- Drawing limit (warn, never block): "withdrawn this period" = the partner's `withdrawal` rows with `transaction_date` in the calendar month (`monthly`, also the default) or year (`yearly`) of the date being posted. Over the limit the movement is recorded and a warning is returned (page: flash `warning`; close: `metadata.partner_limit_warnings` and the result `warnings`, a notice beside the withdrawal rows).
- Monthly profit share (`PartnerProfitShareService::share(companyId, 'YYYY-MM')`, command `partners:share-profit {month} {--company=} {--dry-run}`, command `partner.share_profit`, button on the partners list):
  - Net profit: the station profit statement (`ProfitStatementService`) for a fuel company, else the ledger P&L total. Each active partner gets `net x profit_share_percentage / 100`; the unshared remainder is reported, not posted.
  - Journal dated the month's last day, `transaction_type = partner_profit_share`, `metadata = {month, net_profit, allocations}`: profit `Dr Retained Earnings (company setting, else 3100, else a retained_earnings account, else created), Cr each Capital - name`; loss the other way round. One `profit_share` partner transaction per partner.
  - Idempotent per month: same figures post nothing; changed figures reverse the previous journal (`PostingService::reverseTransaction`, on its own date), delete its `profit_share` rows and post the new one. Once the month's daily closes are month-locked an existing share cannot be replaced.
- Statements: `kind = partner` (`StatementReportController`, `PartnerStatementService`): opening, each movement on the two accounts (reversed pairs hidden unless `reversed=1`), closing = the page's net capital.

## Usage Patterns
- Models should use `protected $connection = 'pgsql'` and table names with schema (`auth.users`, `auth.companies`, `auth.company_user`).
- Frontend/Inertia forms must mirror payload keys exactly as above; avoid renaming (`base_currency` vs `currency`, `system_role` vs `role`).
- When creating companies in flows that also create users, rely on server-side slugging and set membership via `auth.company_user` with an allowed `role`.

## Extending
- If a new column/enum value is required, add it here first, then add migration + validation + resource + form updates in one cohesive change.
- Keep the enum lists and validation snippets in sync across requests, DTOs, and Vue components.
