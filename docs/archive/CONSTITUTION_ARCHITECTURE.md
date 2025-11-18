# Haasib Architecture Constitution & Core Principles

> **Single Source of Truth** - Consolidated from Constitution v1.3.0, Technical Brief v3.0, and Team Memory
>
> **Last Updated**: 2025-11-13 | **Owner**: Project Architecture Committee

---

## Core Constitutional Principles

### I. Multi-Schema Domain Separation

**Non-negotiable**: All tenant data lives in deliberate PostgreSQL schemas with RLS policies.

| Schema | Purpose | Key Tables | RLS Pattern |
|--------|---------|------------|-------------|
| `auth` | Identity, companies, RBAC | `users`, `companies`, `company_user` | `app.current_user_id`/`app.current_company_id` |
| `acct` | Customer-facing finance | `customers`, `invoices`, `payments`, `allocations` | `current_setting('app.current_company_id')` |
| `ledger` | Double-entry bookkeeping | `journal_entries`, `journal_lines`, `accounts` | Balanced debits/credits enforced |
| `audit` | Immutable compliance logs | `entries`, `financial_transactions` | Append-only, tamper-proof |
| `ops` | Operational support | `bank_transactions`, `tax_rates`, `scheduled_jobs` | Company-scoped access |

**Implementation Rules**:
- Every table MUST have `company_id uuid not null`
- RLS policies use `current_setting('app.current_company_id', true)::uuid`
- Schema creation: `DB::statement('CREATE SCHEMA IF NOT EXISTS auth')`
- RLS enforcement: `ALTER TABLE ... FORCE ROW LEVEL SECURITY`

### II. Security-First Bookkeeping

**Financial mutations require ALL of**:
- Row Level Security (RLS) policies
- `company_id` scoping in all queries
- Comprehensive audit coverage via `audit_log()` helper
- Database constraints (CHECK amounts ≥ 0, FK relationships)
- Transaction boundaries for multi-table operations

**Critical Rule**: Never bypass RLS. All access goes through PostgreSQL policies.

### III. Command Bus Architecture

**All write operations flow through command bus**:

```php
// ✅ Correct - Command Bus
Bus::dispatch('invoices.create', $params, $context);

// ❌ Forbidden - Direct service calls
new InvoiceService()->create($data);
```

**Command Bus Registration**:
```php
// config/command-bus.php
return [
    'invoices.create' => Actions\InvoiceCreate::class,
    'payments.allocate' => Actions\PaymentAllocate::class,
    'journal.post' => Actions\JournalEntryPost::class,
];
```

### IV. ServiceContext Pattern

**Explicit context injection for all operations**:

```php
// Services accept context explicitly
class InvoiceService {
    public function create(CreateInvoiceData $data, ServiceContext $context): Invoice {
        // Context provides user, company, idempotency
    }
}

// Controllers derive context from request
$context = ServiceContextHelper::fromRequest($request);
```

**CLI Context Handling**:
```bash
# CLI commands accept --company flag
php artisan invoice:create --company=uuid --data=...

# Or use current company from session
php artisan company:switch target-uuid
```

### V. Idempotency Requirements

**All mutating API requests MUST include**:
```
Idempotency-Key: <uuid-v4>
```

**Scope**: Unique per `(user_id, company_id, action)`
- First request: Process normally, store response
- Replay with identical request: Return stored response
- Replay with different request: Return 409 conflict

### VI. Observability & Audit

**Financial and security events MUST**:
- Use `audit_log()` helper for structured logging
- Include `user_id`, `company_id`, `action`, `entity_id`
- Store in `audit.entries` schema
- Be queryable for compliance reporting

---

## Technical Stack & Versions

### Backend Stack
- **PHP**: 8.2+ (Laravel 12)
- **Database**: PostgreSQL 16 with RLS
- **Queue**: Redis + Horizon
- **Server**: Octane + Swoole
- **Authentication**: Sanctum + Spatie Laravel Permission

### Frontend Stack
- **Framework**: Vue 3 Composition API
- **Routing**: Inertia.js v2
- **UI Library**: PrimeVue v4 (EXCLUSIVE)
- **Styling**: Tailwind CSS + PrimeVue tokens
- **State**: Pinia for complex state
- **TypeScript**: Partial adoption for critical components

### Development Tools
- **Testing**: Pest (PHP) + Playwright (E2E)
- **Formatting**: Laravel Pint
- **Static Analysis**: Larastan (max feasible level)
- **Documentation**: Scribe for API docs

---

## Schema Ownership & Examples

### `auth` Schema - Identity & Access
```sql
-- Core tables
auth.users (id, email, password, created_at, updated_at)
auth.companies (id, name, base_currency, language, locale)
auth.company_user (company_id, user_id, role, created_at)

-- All with RLS policies
CREATE POLICY users_company_policy ON auth.users
    FOR ALL USING (company_id = current_setting('app.current_company_id', true)::uuid);
```

### `acct` Schema - Business Operations
```sql
-- Customer management
acct.customers (id, company_id, customer_number, name, email, tax_id)
acct.invoices (id, company_id, customer_id, invoice_number, total_amount, status)
acct.payments (id, company_id, customer_id, amount, payment_date, method)

-- All require audit triggers
CREATE TRIGGER invoices_audit_trigger
    AFTER INSERT OR UPDATE ON acct.invoices
    FOR EACH ROW EXECUTE FUNCTION audit_log();
```

### `ledger` Schema - Double-Entry System
```sql
-- Chart of accounts
ledger.accounts (id, company_id, account_number, name, type, parent_id)

-- Journal entries (must balance)
ledger.journal_entries (id, company_id, entry_number, date, description, status)
ledger.journal_lines (id, company_id, entry_id, account_id, debit_amount, credit_amount)

-- Balance constraint enforced
ALTER TABLE ledger.journal_lines ADD CHECK (
    (debit_amount IS NOT NULL AND debit_amount >= 0 AND credit_amount IS NULL) OR
    (credit_amount IS NOT NULL AND credit_amount >= 0 AND debit_amount IS NULL)
);
```

---

## Data Patterns & Conventions

### Primary Keys
- **UUIDs only**: All tables use UUID primary keys
- **String type**: `$keyType = 'string'; $incrementing = false;`
- **No composite PKs**: Use UUID with unique constraints instead

### Column Naming
- **snake_case**: All database columns use snake_case
- **company_id**: Foreign key to auth.companies
- **created_at/updated_at**: Laravel timestamps
- **deleted_at**: Soft deletes where applicable

### Model Patterns
```php
class Invoice extends Model {
    use HasUuids, BelongsToCompany, SoftDeletes;

    protected $table = 'acct.invoices';

    protected $fillable = [
        'company_id', 'customer_id', 'invoice_number',
        'total_amount', 'status', 'issue_date', 'due_date'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
    ];

    // Relationships always return typed
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    // Business logic methods
    public function isPaid(): bool {
        return $this->balance_due <= 0;
    }
}
```

---

## API & Route Conventions

### Route Structure
```php
// API v1 routes
Route::prefix('api/v1')->group(function () {
    Route::apiResource('companies', CompanyController::class);
    Route::apiResource('invoices', InvoiceController::class);
    Route::apiResource('payments', PaymentController::class);
});

// Web routes (Inertia)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/invoices', [InvoiceController::class, 'index']);
});
```

### API Response Format
```php
// Success response
return response()->json([
    'success' => true,
    'data' => $resource,
    'meta' => ['total' => $total, 'page' => $page],
    'message' => 'Operation completed successfully'
]);

// Error response
return response()->json([
    'success' => false,
    'message' => 'Validation failed',
    'errors' => $validator->errors()->toArray()
], 422);
```

### API Versioning
- **URL versioning**: `/api/v1/`, `/api/v2/`
- **Backward compatibility**: 90-day deprecation notice
- **Structured error codes**: `INVOICE_NOT_FOUND`, `PAYMENT_ALREADY_ALLOCATED`

---

## Multi-Tenant Implementation

### Context Setting
```php
// Middleware: SetTenantContext
public function handle(Request $request, Closure $next) {
    $companyId = $this->getCompanyId($request);

    if ($companyId) {
        DB::statement("SET LOCAL app.current_company_id = ?", [$companyId]);
    }

    return $next($request);
}
```

### Cross-Tenant Protection
```php
// All queries automatically scoped by RLS
$invoices = Invoice::all(); // Only current company's invoices

// Explicit company switching
$context = new ServiceContext($user, $targetCompany);
$invoices = $this->invoiceService->getAll($context);
```

### Company Switching
```php
// API endpoint
POST /api/v1/me/companies/switch
{
    "company_id": "uuid-here"
}

// Response
{
    "success": true,
    "data": {
        "current_company": {...},
        "available_companies": [...]
    }
}
```

---

## Security Architecture

### Authentication Flow
1. **Web**: Laravel session + Inertia
2. **API**: Sanctum tokens + `X-Company-Id` header
3. **CLI**: Command-line authentication with context flags

### Authorization (RBAC)
```php
// Permission slugs: {resource}.{action}.{scope?}
'companies.view',           // View companies
'companies.manage',         // Create/edit/delete companies
'invoices.create',         // Create invoices
'invoices.post',           // Post invoices to ledger
'ledger.view',             // View ledger entries
'ledger.post',             // Post journal entries

// Role definitions (system roles use team_id = null)
'superadmin' => ['*'],                    // All permissions
'systemadmin' => ['system.*'],            // System functions only
'owner' => ['companies.manage', 'invoices.*', 'ledger.*'],
'accountant' => ['invoices.*', 'ledger.view'],
'viewer' => ['companies.view', 'invoices.view', 'ledger.view']
```

### Data Protection
- **Encryption**: Sensitive data encrypted at rest
- **Audit Trail**: All changes logged with user, timestamp, old→new values
- **RLS**: Database-level tenant isolation
- **Input Validation**: All inputs sanitized and validated
- **Rate Limiting**: API endpoints protected from abuse

---

## CLI & Command Architecture

### Command Palette
- **Entity-first flow**: entity → verb → parameters
- **Freeform parser**: Natural language command parsing
- **CLI parity**: All GUI features available in CLI
- **Keyboard-first**: Full keyboard navigation and shortcuts

### Command Bus Usage
```php
// GUI (Command Palette)
POST /commands
{
    "action": "invoices.create",
    "params": {...},
    "idempotency_key": "uuid"
}

// CLI (Artisan)
php artisan command:execute invoices.create --params="{...}" --company=uuid
```

### Command Registration
```php
// Each command has bus action + CLI registration
class InvoiceCreate extends CommandAction {
    public function handle(array $params, ServiceContext $context): Invoice {
        return $this->invoiceService->create($params, $context);
    }
}

// CLI command maps to same action
Artisan::command('invoice:create {--company=} {--data=}', function ($company, $data) {
    $context = ServiceContext::fromCompanyId($company);
    Bus::dispatch('invoices.create', json_decode($data, true), $context);
});
```

---

## Governance & Amendments

### Constitutional Supremacy
- This constitution is the **source of truth** for all architectural decisions
- Any divergence must be documented and ratified
- Amendments require PR with justification and impact analysis

### Review Process
1. **Proposal**: Create PR highlighting changes
2. **Impact Analysis**: Update affected documentation
3. **Review**: Technical committee reviews schema, RLS, security impact
4. **Ratification**: Merge changes and update version

### Version Control
- **Major changes**: Increment version (1.3.0 → 2.0.0)
- **Minor additions**: Increment patch (1.3.0 → 1.3.1)
- **Documentation**: Keep change log with dates and rationale

---

**Current Version**: 2.0.0 (Consolidated)
**Ratified**: 2025-11-13
**Next Review**: As needed for architectural changes

---

*This constitution consolidates and supersedes all previous architectural documents. Any conflicts between older documents and this constitution should be resolved in favor of this document.*