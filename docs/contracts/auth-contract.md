# Schema Contract — Auth (Users & Companies)

Single source of truth for the shared auth schema. Read this before touching migrations, models, requests, resources, or Vue forms. Do not invent new columns/props; if something is missing, pause and update this contract first.

## Guardrails
- Tables live in Postgres `auth` schema on the `pgsql` connection.
- Currency fields use uppercase ISO 4217 codes, length 3 (`base_currency` only; do not introduce `currency`/`baseCurrency` variants).
- Slugs are derived from `name` via `Str::slug` with `-N` suffix for uniqueness; never require a slug input from the UI.
- System role: prefer `super_admin`, `admin`, `user`, `guest` for platform-level permissions. Legacy values (`superadmin`, `system_owner`, `company_owner`, etc.) exist; new code must standardize on `super_admin` and avoid introducing new variants.
- Company membership role enum is fixed: `owner`, `admin`, `accountant`, `viewer`, `member`.
- Row Level Security is enabled; APIs must set `app.current_user_id` and `app.is_super_admin` session settings where required.
- Currency: `base_currency` is a `char(3)` code (ISO 4217). No FK; validate against `public.currencies` (see currencies contract). `base_currency` is immutable once transactions exist.
- Reserved columns: `exchange_rate_id` was previously reserved—do not use. All currency work follows the multi-currency contracts (codes only, no FK IDs).

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

### auth.companies
- Columns:  
  - `id` uuid PK.  
  - `name` string(255) not null.  
  - `industry` string nullable.  
 - `slug` string unique not null (auto-generated).  
  - `country` string nullable; `country_id` uuid nullable.  
  - `base_currency` char(3) not null default `USD` (must exist and be active in `public.currencies`; immutable after transactions).  
  - `language` string(10) default `en`; `locale` string(10) default `en_US`.  
  - `settings` json nullable. Allowed root keys: `contact_email` (string), `contact_phone` (string), `website` (string). Do not add new keys without updating this contract.  
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
  - `$fillable = ['name', 'industry', 'country', 'country_id', 'base_currency', 'language', 'locale', 'settings', 'created_by_user_id'];`  
  - `$casts = ['settings' => 'array', 'industry' => 'string', 'country_id' => 'string', 'created_by_user_id' => 'string', 'is_active' => 'boolean'];`
- Relationships:  
  - belongsToMany User via `auth.company_user` (pivot: role, is_active, joined_at, left_at).  
  - belongsTo User as creator via `created_by_user_id`.  
  - hasMany CompanyInvitation, Module (via `auth.company_modules`), AuditEntry.  
- Validation/DTO expectations:  
  - Required: `name` (<=255), `base_currency` (exactly 3 uppercase chars).  
  - Optional: `industry`, `country`, `language` (<=10), `locale` (<=10), `settings` (json).  
  - Slug: server-generated; do not accept from UI/clients.  
  - Uniqueness: `slug` unique; (`name`, `country`) pair unique.  
  - Keep payload key as `base_currency` (not `currency`).

### auth.company_user (membership pivot)
- Columns:  
  - `company_id` uuid FK → `auth.companies.id` (on delete cascade).  
  - `user_id` uuid FK → `auth.users.id` (on delete cascade).  
  - `role` enum constrained to `owner|admin|accountant|viewer|member`, default `member`.  
  - `invited_by_user_id` uuid nullable FK → `auth.users.id` (on delete set null).  
  - `joined_at` timestamp nullable; `left_at` timestamp nullable.  
  - `is_active` bool default true.  
  - `created_at`, `updated_at`.
- Defaults quick ref: `role: 'member'`, `is_active: true`.
- FK behavior:  
  - `company_id` → `auth.companies.id` (ON DELETE CASCADE, ON UPDATE CASCADE).  
  - `user_id` → `auth.users.id` (ON DELETE CASCADE, ON UPDATE CASCADE).  
  - `invited_by_user_id` → `auth.users.id` (ON DELETE SET NULL, ON UPDATE CASCADE).
- Keys/Indexes: PK (`company_id`, `user_id`); indexes on (`user_id`, `role`), (`company_id`, `role`), `invited_by_user_id`, `is_active`, (`company_id`, `user_id`, `is_active`); role check constraint exists.
- RLS: enabled. Policies allow select for self; insert/update/delete for company owners/admins or super admins; first-owner bootstrap allowed.
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

### auth.company_currencies (company currency enablement)
- Columns:
  - `id` uuid PK.
  - `company_id` uuid FK → `auth.companies.id` (CASCADE).
  - `currency_code` char(3) not null (must exist and be active in `public.currencies`; no FK).
  - `is_base` boolean not null default false (exactly one per company; matches `auth.companies.base_currency`).
  - `enabled_at` timestamp not null default now().
  - `created_at`, `updated_at`.
- Constraints/Indexes:
  - unique (`company_id`, `currency_code`).
  - partial unique (`company_id`) where `is_base = true`.
  - check currency exists in `public.currencies` and `is_active = true`.
- RLS: company isolation policy (company_id match or super_admin).
- Laravel model (canonical):
  - `$connection = 'pgsql'; $table = 'auth.company_currencies'; $fillable = ['company_id','currency_code','is_base','enabled_at']; $casts = ['is_base'=>'boolean','enabled_at'=>'datetime'];`
- Business rules:
  - One base currency per company; base cannot be disabled.
  - Cannot disable if accounts/transactions exist in that currency with balances open.
  - On company create, insert base currency row with `is_base = true`.

## Usage Patterns
- Models should use `protected $connection = 'pgsql'` and table names with schema (`auth.users`, `auth.companies`, `auth.company_user`).
- Frontend/Inertia forms must mirror payload keys exactly as above; avoid renaming (`base_currency` vs `currency`, `system_role` vs `role`).
- When creating companies in flows that also create users, rely on server-side slugging and set membership via `auth.company_user` with an allowed `role`.

## Extending
- If a new column/enum value is required, add it here first, then add migration + validation + resource + form updates in one cohesive change.
- Keep the enum lists and validation snippets in sync across requests, DTOs, and Vue components.
