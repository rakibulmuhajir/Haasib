# Schema Contract — Currencies & Company Enablement (LOCKED)

Version: 1.0  
Status: LOCKED  
Last Updated: 2024-01-28

## Critical Decisions
- Primary key: natural key `code` char(3), no UUIDs.
- Schema: `public.currencies` (reference data, no RLS).
- No foreign keys to currencies: all references are `char(3)` codes validated by CHECK/application logic.
- Precision: `currency_amount numeric(18,6)`, `exchange_rate numeric(18,8)`, `base_amount numeric(15,2)`, `debit/credit numeric(15,2)`.
- Immutability: exchange rates and currencies on posted transactions do not change.

## Reference Table

### public.currencies
Purpose: ISO 4217 currency reference data, shared across all companies.  
RLS: none. Soft delete: none (use `is_active`). PK: `code`.

```sql
CREATE TABLE public.currencies (
    code            char(3) PRIMARY KEY,          -- 'USD', 'EUR', 'PKR'
    name            varchar(100) NOT NULL,
    symbol          varchar(10) NOT NULL,
    decimal_places  smallint NOT NULL DEFAULT 2,  -- 0 for JPY, up to 8 if needed
    is_active       boolean NOT NULL DEFAULT true,
    created_at      timestamp NOT NULL DEFAULT now(),
    updated_at      timestamp NOT NULL DEFAULT now(),
    CONSTRAINT currencies_code_format CHECK (code ~ '^[A-Z]{3}$'),
    CONSTRAINT currencies_decimal_places_range CHECK (decimal_places BETWEEN 0 AND 8)
);

CREATE INDEX idx_currencies_active ON public.currencies (code) WHERE is_active = true;
```

Model: `App\Models\Currency`
- `$table = 'currencies'` (public schema default), `$primaryKey = 'code'`, `$keyType = 'string'`, `$incrementing = false`.
- Read-only in application; seeded with ~150 active currencies.

Validation rule: currency codes must exist with `is_active = true`.

## Tenant Table

### auth.company_currencies
Purpose: which currencies a company has enabled; enforces one base currency.  
RLS: company isolation (`company_id = current_setting('app.current_company_id')::uuid`).  
Soft delete: none. PK: `id` uuid.

```sql
CREATE TABLE auth.company_currencies (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id     uuid NOT NULL REFERENCES auth.companies(id) ON DELETE CASCADE,
    currency_code  char(3) NOT NULL,
    is_base        boolean NOT NULL DEFAULT false,
    enabled_at     timestamp NOT NULL DEFAULT now(),
    created_at     timestamp NOT NULL DEFAULT now(),
    updated_at     timestamp NOT NULL DEFAULT now(),
    CONSTRAINT company_currencies_unique UNIQUE (company_id, currency_code),
    CONSTRAINT company_currencies_currency_exists CHECK (
        EXISTS (SELECT 1 FROM public.currencies WHERE code = currency_code AND is_active = true)
    )
);

CREATE UNIQUE INDEX idx_company_base_currency
    ON auth.company_currencies (company_id)
    WHERE is_base = true;

ALTER TABLE auth.company_currencies ENABLE ROW LEVEL SECURITY;
CREATE POLICY company_currencies_isolation ON auth.company_currencies
USING (
    company_id = current_setting('app.current_company_id', true)::uuid
    OR current_setting('app.is_super_admin', true)::boolean = true
);
```

Model: `App\Models\CompanyCurrency`
- `$fillable = ['company_id', 'currency_code', 'is_base', 'enabled_at'];`
- `$casts = ['is_base' => 'boolean', 'enabled_at' => 'datetime'];`

Business rules:
1) Exactly one `is_base = true` per company (matches `auth.companies.base_currency`).  
2) Base currency cannot be disabled/deleted.  
3) Cannot disable if accounts in that currency have non-zero balance or unpaid transactions.  
4) On company creation, auto-create base currency record.

## Updates to auth.companies

```sql
ALTER TABLE auth.companies
    ADD COLUMN base_currency char(3) NOT NULL DEFAULT 'USD';

ALTER TABLE auth.companies
    ADD CONSTRAINT companies_base_currency_exists CHECK (
        EXISTS (SELECT 1 FROM public.currencies WHERE code = base_currency AND is_active = true)
    );
```

Business rules:
- `base_currency` is immutable once the company has journal entries.
- Must exist and be active in `public.currencies`.
- On company creation, also create `auth.company_currencies` row with `is_base = true`.

## Migration Order (data)
1) Create `public.currencies` and seed codes.  
2) Add `base_currency` to `auth.companies` (default 'USD' but require selection in UI).  
3) Create `auth.company_currencies`.  
4) Populate `company_currencies` for existing companies with their `base_currency`.  
5) Add currency columns/constraints to acct tables (see accounting contract).
