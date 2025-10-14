# Company API Reference

The Company API exposes the HTTP surface for tenant management inside the active `stack/` Laravel workspace. Routes are registered in `stack/routes/api.php` under a `Route::prefix('companies')` group and are automatically namespaced with the `/api` prefix by the framework bootstrapping in `stack/bootstrap/app.php`.

## Prerequisites

- **Authentication**: Use Laravel Sanctum bearer tokens. Every request must include `Authorization: Bearer {token}` for a user who holds the relevant permission (`companies.view`, `companies.create`, `companies.switch`, etc.).
- **Tenancy context**: `App\Http\Middleware\SetCompanyContext` resolves the active tenant from the session or from an `X-Company-Id` header. Non-Inertia clients MUST send `X-Company-Id: <uuid>` on reads and writes.
- **Idempotency**: All mutating routes use the `idempotent` middleware alias (`App\Http\Middleware\Idempotency`). Provide `Idempotency-Key: <uuid>` on POST/PUT requests so retries are safe and constitution compliant.

### Base URL

```
https://{host}/api
```

Replace `{host}` with the environment domain (e.g. `http://localhost:8000` when running `php artisan serve` from the `stack/` directory).

## Endpoints

### List Companies

```http
GET /api/companies
```

**Query Parameters**
- `search` — partial company name match
- `country` — ISO-3166 alpha-2 filter
- `is_active` — boolean filter
- `sort` / `order` — sortable fields (`name`, `created_at`, `updated_at`)
- `page`, `per_page` — pagination (default 15, max 100)

**Success 200**
Returns a standard Laravel pagination envelope with company summaries and the current fiscal year context.

### Create Company

```http
POST /api/companies
```

**Headers**
- `Authorization: Bearer {token}`
- `Idempotency-Key: <uuid>`

**Body**
```json
{
  "name": "string (required, <=255)",
  "slug": "string (optional; auto-generated if omitted)",
  "country": "string (optional ISO-3166 alpha-2)",
  "country_id": "uuid (optional reference to public.countries)",
  "base_currency": "string (required ISO-4217)",
  "currency_id": "uuid (optional reference to public.currencies)",
  "timezone": "string (optional IANA timezone)",
  "language": "string (optional, default \"en\")",
  "locale": "string (optional, default \"en_US\")",
  "settings": {}
}
```

**Success 201**
Returns the created company, plus metadata flags for seeded fiscal year, chart of accounts, and default membership.

**Validation 422**
Returned when payload fails validation (missing required fields, invalid currency, duplicate slug, etc.).

### Retrieve Company

```http
GET /api/companies/{companyId}
```

Returns full company details including active users, fiscal periods, and chart-of-accounts status. Requires `companies.view`.

### Switch Active Company

```http
POST /api/companies/switch
```

**Headers**
- `Authorization: Bearer {token}`
- `Idempotency-Key: <uuid>`
- `X-Company-Id: <target_company_uuid>`

**Response 200**
```json
{
  "data": {
    "company_id": "uuid",
    "message": "Company context switched"
  }
}
```

The switch endpoint is guarded by RBAC; users must belong to the target company and carry one of `owner|admin|accountant|manager|viewer` depending on business requirements.

### Manage Invitations

```
GET    /api/companies/{companyId}/invitations
POST   /api/companies/{companyId}/invitations          # Idempotency-Key required
GET    /api/companies/{companyId}/invitations/{id}
DELETE /api/companies/{companyId}/invitations/{id}
```

- Creating invitations requires `companies.manage_invitations`.
- Payloads include invitee email, role, and optional expiration data.
- Responses return invitation status and audit metadata.

## Error Model

| Status | Scenario |
|--------|----------|
| 401 | Missing or invalid Sanctum token |
| 403 | Authenticated but lacks permission for the active company |
| 404 | Company or invitation not found in the current tenant scope |
| 409 | Idempotency key reused with a different request payload |
| 422 | Validation errors |

## Related Resources

- `.specify/memory/constitution.md` — source of truth for CLI parity, tenancy, RBAC, and idempotency rules (v2.2.0).
- `docs/idempotency.md` — retry semantics, key scoping, and conflict handling.
- `stack/routes/api.php` — authoritative routing definitions.
- `stack/app/Http/Middleware/SetCompanyContext.php` — tenancy middleware implementation.
