# Haasib System Architecture

**Last Updated**: 2025-02-01  
**Purpose**: Comprehensive guide to Haasib's architecture, patterns, and request flow  
**Audience**: All developers working on the codebase

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Technology Stack](#2-technology-stack)
3. [Multi-Tenancy Architecture](#3-multi-tenancy-architecture)
4. [Project Structure](#4-project-structure)
5. [Request Lifecycle](#5-request-lifecycle)
6. [Key Architectural Patterns](#6-key-architectural-patterns)
7. [Database Architecture](#7-database-architecture)
8. [File Locations Quick Reference](#8-file-locations-quick-reference)

---

## 1. System Overview

Haasib is a **multi-tenant accounting and business management SaaS platform** built for non-accountants while providing full accounting capabilities for advanced users.

### Core Modules

| Module | Description | Schema |
|--------|-------------|--------|
| **Auth** | Users, companies, roles, permissions | `auth` |
| **Accounting** | General ledger, invoices, bills, customers, vendors | `acct` |
| **Banking** | Bank accounts, reconciliation, feeds | `bank` |
| **Inventory** | Items, warehouses, stock tracking | `inv` |
| **Payroll** | Employees, salary, leaves, deductions | `pay` |
| **Tax** | Tax compliance, registrations, rates | `tax` |
| **CRM** | Customer relationships, marketing | `crm` |

### Product Philosophy

> The product must be easy for non-accountants: beginners/amateurs should not be forced through complex accounting setup, while advanced users still have access to full accounting controls.

---

## 2. Technology Stack

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend (Browser)                        │
│   Vue 3 + TypeScript + Tailwind CSS + Shadcn-Vue           │
│   Vite + Inertia.js (Server-Side Rendering)                │
└─────────────────────────┬───────────────────────────────────┘
                          │ HTTP/WebSocket
┌─────────────────────────▼───────────────────────────────────┐
│                Backend (Laravel 12)                          │
│   Octane Server (FrankenPHP) + Inertia.js Adapter          │
│   ├─ HTTP Routes (company-scoped endpoints)                │
│   ├─ API Routes (REST + company context)                   │
│   ├─ Middleware (Auth, Company identification)             │
│   ├─ Models (Eloquent with UUIDs)                          │
│   ├─ Actions (Business logic via Command Bus)              │
│   └─ RBAC (Spatie Laravel Permission + Teams)              │
└─────────────────────────┬───────────────────────────────────┘
                          │ SQL Queries
┌─────────────────────────▼───────────────────────────────────┐
│           PostgreSQL Database (Multi-Schema)                 │
│   ├─ auth: Users, Companies, Roles, Permissions            │
│   ├─ acct: General Ledger, AR, AP, Accounts                │
│   ├─ bank: Bank Accounts, Reconciliations                  │
│   ├─ inv: Items, Warehouses, Stock                         │
│   └─ pay: Payroll, Employees, Leaves                       │
└─────────────────────────────────────────────────────────────┘
```

### Stack Details

- **Framework**: Laravel 12 (PHP 8.4)
- **Frontend**: Vue 3, TypeScript, Inertia v2
- **UI Components**: Shadcn-Vue, Tailwind CSS
- **Database**: PostgreSQL 16 with Row-Level Security
- **Server**: FrankenPHP via Laravel Octane
- **Auth**: Laravel Fortify + Spatie Permission (with Teams)
- **Testing**: PHPUnit, Pest

---

## 3. Multi-Tenancy Architecture

### Company-Scoped URLs

Every request includes company context via URL parameter:

```
GET /{company}/invoices          → List invoices for company
GET /{company}/customers         → List customers for company
POST /{company}/invoices         → Create invoice in company context
```

### Company Context Flow

```
User Request: GET /acme-corp/invoices
    ↓
Route Parameter: company = "acme-corp"
    ↓
Middleware: identify.company
    - Validates user can access "acme-corp"
    - Sets CurrentCompany singleton
    - Sets Spatie team context
    - Sets PostgreSQL session variable for RLS
    ↓
Controller: app(CurrentCompany::class)->get()
    ↓
Database: SELECT * FROM acct.invoices 
    → RLS policy filters: WHERE company_id = 'acme-uuid'
```

### Context Layers

| Layer | Mechanism | Purpose |
|-------|-----------|---------|
| **URL** | Route parameter `{company}` | Company identification |
| **Middleware** | `identify.company` | Validation & context setup |
| **Application** | `CurrentCompany` singleton | Access company in code |
| **Database** | PostgreSQL session variable | RLS policy enforcement |

### Critical Files

- `app/Http/Middleware/IdentifyCompany.php` - Extracts and validates company
- `app/Services/CurrentCompany.php` - Singleton holding current company
- `app/Providers/AppServiceProvider.php` - Registers singleton

---

## 4. Project Structure

### Backend (`/app`)

```
app/
├── Http/
│   ├── Controllers/          # Thin controllers (coordination only)
│   ├── Middleware/
│   │   ├── IdentifyCompany.php      # Company context extraction
│   │   └── HandleInertiaRequests.php # Inertia props injection
│   ├── Requests/             # FormRequest (validation + auth)
│   └── Resources/            # API response formatting
├── Models/                   # Eloquent models (11 core)
│   ├── User.php
│   ├── Company.php
│   ├── Invoice.php
│   └── ...
├── Actions/                  # Domain-driven business logic
│   ├── Invoice/CreateInvoice.php
│   ├── Payment/AllocatePayment.php
│   └── ...
├── Services/                 # Shared business logic
│   ├── CurrentCompany.php    # Company context singleton
│   └── CompanyContextService.php
├── Constants/                # Centralized definitions
│   ├── Permissions.php       # 226+ permission constants
│   └── Tables.php
└── Console/Commands/         # Artisan commands
    └── Rbac/                 # Permission sync commands
```

### Frontend (`/resources/js`)

```
resources/js/
├── app.ts                    # Vue app entry point
├── layouts/
│   └── AppLayout.vue         # Main layout wrapper
├── pages/                    # Page components
│   ├── auth/                 # Login, Register
│   ├── companies/            # Company selection
│   └── ...
├── routes/                   # Feature modules (49+)
│   ├── invoices/
│   ├── customers/
│   └── ...
├── components/
│   ├── ui/                   # Shadcn-Vue components
│   ├── forms/                # Form components
│   └── palette/              # Command palette
├── navigation/
│   └── registry.ts           # Dynamic nav config
├── lib/                      # Utilities
└── types/                    # TypeScript types
```

### Routes (`/routes`)

```
routes/
├── web.php                   # Web routes (Inertia responses)
└── api.php                   # API routes (JSON responses)
```

### Database (`/database`)

```
database/
├── migrations/               # Schema migrations
├── factories/                # Test data factories
└── seeders/                  # Database seeders
```

---

## 5. Request Lifecycle

### Web Request Flow

```
1. HTTP Request arrives
    ↓
2. Middleware Pipeline:
    - auth (Laravel Fortify)
    - identify.company (sets company context)
    - HandleInertiaRequests (injects shared props)
    ↓
3. Route Matching:
    - Pattern: /{company}/resource
    - Resolves to Controller@method
    ↓
4. Controller executes:
    - FormRequest validation (authorize + rules)
    - Business logic via Bus::dispatch()
    - Returns Inertia response
    ↓
5. Response rendered:
    - Inertia renders Vue component
    - Props passed from controller
```

### Company Context in Code

```php
// Get current company in controllers, services, actions
$company = app(CurrentCompany::class)->get();

// Query company-scoped data
$invoices = $company->invoices()->get();

// Create with company context
$invoice = $company->invoices()->create($data);
```

---

## 6. Key Architectural Patterns

### 6.1 UUID Primary Keys (MANDATORY)

All tables use UUID primary keys - never auto-incrementing integers.

```php
// ✅ CORRECT
Schema::create('acct.invoices', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('company_id');
    // ...
});

// Model configuration
class Invoice extends Model
{
    use HasUuids;
    protected $keyType = 'string';
    public $incrementing = false;
}
```

### 6.2 Multi-Schema Database

Tables organized by functional domain:

```php
// ✅ CORRECT
Schema::create('auth.users', ...);        // Authentication
Schema::create('acct.invoices', ...);     // Accounting
Schema::create('bank.accounts', ...);     // Banking
Schema::create('inv.items', ...);         // Inventory
Schema::create('pay.employees', ...);     // Payroll
```

### 6.3 Row-Level Security (RLS)

Every tenant table has RLS enabled for automatic data isolation:

```php
Schema::create('acct.invoices', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('company_id');
    // ...
});

// Enable RLS
DB::statement('ALTER TABLE acct.invoices ENABLE ROW LEVEL SECURITY');

// Create policy
DB::statement("
    CREATE POLICY invoices_company_isolation ON acct.invoices
    FOR ALL USING (company_id = current_setting('app.current_company_id')::uuid)
");
```

### 6.4 Command Bus for Business Logic

All write operations go through the command bus:

```php
// ✅ CORRECT - In Controller
use Illuminate\Support\Facades\Bus;

$result = Bus::dispatch('invoice.create', $request->validated());

// Action class
class CreateInvoice
{
    use Dispatchable;
    
    public function handle(): Invoice
    {
        $company = app(CurrentCompany::class)->get();
        return DB::transaction(function () {
            // Business logic here
        });
    }
}
```

### 6.5 FormRequest Pattern

Validation and authorization in dedicated classes:

```php
class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVOICES_CREATE);
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|uuid|exists:acct.customers,id',
            'due_date' => 'required|date|after:today',
            'line_items' => 'required|array|min:1',
        ];
    }
}
```

### 6.6 Frontend Pattern (Vue 3 + Inertia)

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const props = defineProps<{
  invoices: Invoice[]
}>()

const form = useForm({
  customer_id: '',
  due_date: '',
})

const submit = () => {
  form.post(route('invoices.store', { company: props.company.slug }))
}
</script>
```

---

## 7. Database Architecture

### Schema Organization

| Schema | Purpose | Example Tables |
|--------|---------|----------------|
| `public` | Reference data | currencies |
| `auth` | Authentication & tenancy | users, companies, roles |
| `acct` | General ledger & accounting | accounts, invoices, customers |
| `bank` | Banking & reconciliation | bank_accounts, transactions |
| `inv` | Inventory & products | items, warehouses, stock |
| `pay` | Payroll & HR | employees, payslips |
| `audit` | Audit logs | activity_logs |

### Schema Decision Tree

```
user/company/permission → auth
financial/customer/invoice → acct
hospitality/booking → hsp
crm/marketing → crm
logs/history → audit
```

### RLS Implementation

```sql
-- Set company context before queries
SET app.current_company_id = 'company-uuid';

-- All queries automatically filtered
SELECT * FROM acct.invoices; 
-- → Only returns invoices WHERE company_id = 'company-uuid'
```

---

## 8. File Locations Quick Reference

### Critical Backend Files

| File | Purpose |
|------|---------|
| `bootstrap/app.php` | Laravel 12 application bootstrap |
| `routes/web.php` | Web routes (Inertia) |
| `routes/api.php` | API routes (REST) |
| `app/Http/Middleware/IdentifyCompany.php` | Company context extraction |
| `app/Services/CurrentCompany.php` | Company context singleton |
| `app/Constants/Permissions.php` | All permission definitions |
| `app/Models/*.php` | Entity models |
| `app/Http/Requests/*.php` | FormRequest classes |

### Critical Frontend Files

| File | Purpose |
|------|---------|
| `resources/js/app.ts` | Vue app entry point |
| `resources/js/layouts/AppLayout.vue` | Main layout |
| `resources/js/navigation/registry.ts` | Navigation config |
| `resources/js/composables/useInlineEdit.ts` | Inline editing |
| `resources/js/components/InlineEditable.vue` | Inline edit component |

### Configuration Files

| File | Purpose |
|------|---------|
| `config/role-permissions.php` | Role-permission matrix |
| `config/permission.php` | Spatie permission config |
| `.env` | Environment variables |

---

## Next Steps

1. **New developers**: Read [02-DEVELOPMENT-STANDARDS.md](02-DEVELOPMENT-STANDARDS.md)
2. **Working on auth/permissions**: Read [03-RBAC-GUIDE.md](03-RBAC-GUIDE.md)
3. **Building UI**: Read [04-FRONTEND-GUIDE.md](04-FRONTEND-GUIDE.md)
4. **Database work**: Read [05-DATABASE-GUIDE.md](05-DATABASE-GUIDE.md)
5. **Schema contracts**: See `docs/contracts/00-master-index.md`

---

**Related Documentation**:
- [02-DEVELOPMENT-STANDARDS.md](02-DEVELOPMENT-STANDARDS.md) - Coding standards
- [03-RBAC-GUIDE.md](03-RBAC-GUIDE.md) - Permissions system
- [04-FRONTEND-GUIDE.md](04-FRONTEND-GUIDE.md) - UI development
- [05-DATABASE-GUIDE.md](05-DATABASE-GUIDE.md) - Database patterns
- [docs/contracts/00-master-index.md](docs/contracts/00-master-index.md) - Schema contracts
