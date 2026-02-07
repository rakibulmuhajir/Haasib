# Haasib System Architecture Guide

**For Developer Onboarding - Start here to understand how everything works.**

---

## Quick Navigation

1. **New to the codebase?** → Start with [System Overview](#system-overview)
2. **Want to add a feature?** → Go to [Architecture Layers](#architecture-layers) then [Code Patterns](#code-patterns)
3. **Need to find something?** → Check [Key Files & Locations](#key-files--locations)
4. **Confused about permissions?** → See [RBAC System](#rbac-system)
5. **Want to understand the database?** → Jump to [Database Architecture](#database-architecture)

---

## System Overview

### What is Haasib?

Haasib is a **multi-tenant accounting and business management SaaS platform** with modular features:
- **Accounting** - General Ledger, Invoices, Bills, Customers, Vendors
- **Inventory** - Items, Warehouses, Stock Tracking
- **Payroll** - Employees, Salary, Leaves, Deductions
- **Banking** - Reconciliation, Bank Feeds
- **Tax** - Tax Compliance, Registrations, Rates
- **Industry Verticals** - Fuel Station management (21+ features)

Each company operates in isolation with role-based access control.

### Technology Stack

```
┌─────────────────────────────────────────────────────────┐
│                  Frontend (Browser)                      │
│  Vue 3 + TypeScript + Tailwind CSS + Shadcn Components │
│  Vite 7 + Inertia.js (Server-Side Rendering)           │
└─────────────────────┬───────────────────────────────────┘
                      │ HTTP/WebSocket
┌─────────────────────▼───────────────────────────────────┐
│              Backend (Laravel 12)                        │
│  Octane Server (FrankenPHP) + Inertia.js Adapter        │
│  ├─ HTTP Routes (45+ company-scoped endpoints)          │
│  ├─ API Routes (REST + company context)                 │
│  ├─ Middleware (Auth, Company identification)           │
│  ├─ Models (11 core entity types)                       │
│  ├─ Services & Actions (Business logic)                 │
│  └─ RBAC (Spatie Laravel Permission + Teams)            │
└─────────────────────┬───────────────────────────────────┘
                      │ SQL Queries
┌─────────────────────▼───────────────────────────────────┐
│         PostgreSQL Database (Multi-Schema)              │
│  ├─ auth: Users, Companies, Roles, Permissions          │
│  ├─ acct: General Ledger, AR, AP, Accounts              │
│  ├─ bank: Bank Accounts, Reconciliations                │
│  ├─ inv: Items, Warehouses, Stock                       │
│  └─ pay: Payroll, Employees, Leaves                     │
└──────────────────────────────────────────────────────────┘
```

### Multi-Tenancy Model

**Company-Scoped Architecture:**
- Every user belongs to one or more companies
- Every request includes company context via URL parameter: `/{company}/resource`
- Company context is enforced at every layer:
  1. **URL Level:** Route parameter `{company}`
  2. **Middleware Level:** `identify.company` middleware extracts and validates
  3. **Application Level:** `CurrentCompany` singleton holds context
  4. **Database Level:** PostgreSQL session variables enable Row-Level Security (RLS)

**Example Request Flow:**
```
User clicks "View Invoices"
    ↓
Frontend: GET /company-abc123/invoices
    ↓
Route Parameter: company = "abc123"
    ↓
Middleware: identify.company → validates user can access "abc123"
    ↓
Service: app(CurrentCompany::class)->get() → returns Company model
    ↓
Controller: $company->invoices()->get()
    ↓
Database: SELECT * FROM acct.invoices WHERE company_id = $companyId (via RLS)
```

---

## Architecture Layers

### Layer 1: Frontend (Vue 3 + TypeScript)

**Entry Point:** `build/resources/js/app.ts`

**Structure:**
```
build/resources/js/
├── app.ts                          # Vue app initialization
├── layouts/
│   └── AppLayout.vue               # Main layout wrapper
├── pages/                          # Page components
│   ├── auth/                       # Login, Register, Password Reset
│   ├── companies/                  # Company selection UI
│   ├── company/                    # Company settings
│   ├── accounting/                 # Accounting page container
│   └── ...
├── routes/                         # 49+ feature modules
│   ├── invoices/
│   ├── customers/
│   ├── items/
│   ├── employees/
│   └── ...
├── components/
│   ├── ui/                         # Shadcn Vue components
│   │   ├── Button, Input, Table, etc.
│   ├── forms/                      # Custom form components
│   │   ├── EntitySearch.vue
│   │   ├── DueDatePicker.vue
│   ├── palette/                    # Command palette
│   │   └── CommandPalette.vue
│   └── Generic/                    # DataTable, Breadcrumbs, etc.
├── navigation/
│   └── registry.ts                 # Dynamic nav config
├── wayfinder/                      # Type-safe route helpers
├── lib/                            # Utilities
│   ├── composables/
│   ├── helpers/
│   └── validators/
└── types/                          # TypeScript interfaces
```

**Key Patterns:**
- **Inertia Props:** Backend sends props to Vue components
- **Form Submission:** Uses `useForm()` composable (wraps Inertia form API)
- **Navigation:** Dynamic registry-based nav that auto-generates from routes
- **Inline Editing:** `useInlineEdit()` composable for edit-in-place

### Layer 2: Backend (Laravel 12)

**Entry Point:** `build/bootstrap/app.php` (Laravel 12 modular config)

**Request Lifecycle:**
```
1. HTTP Request arrives
    ↓
2. Middleware Pipeline executes:
    - auth (Laravel Fortify)
    - identify.company (company context)
    - HandleInertiaRequests (data injection)
    - CheckFirstTimeUser
    ↓
3. Route Matching:
    - Pattern: /{company}/resource or /resource
    - Resolves to Controller@method
    ↓
4. Controller executes:
    - FormRequest validation (authorize + rules)
    - Business logic (via Service/Action)
    - Returns Inertia response or JSON
    ↓
5. Response rendered:
    - Inertia: Props sent to Vue component
    - JSON: Data returned to API client
```

**Structure:**
```
build/app/
├── Http/
│   ├── Controllers/
│   │   ├── CompanyController.php        # Company CRUD
│   │   ├── DashboardController.php      # Dashboard
│   │   ├── InvoiceController.php        # Invoice CRUD
│   │   ├── PartnerController.php        # Partner management
│   │   └── Api/                         # API-specific controllers
│   ├── Requests/                        # FormRequest classes
│   │   ├── StoreInvoiceRequest.php
│   │   ├── UpdateInvoiceRequest.php
│   │   └── ...
│   └── Middleware/
│       ├── IdentifyCompany.php          # Company context extraction
│       ├── HandleInertiaRequests.php    # Inertia props injection
│       ├── RequireModuleEnabled.php     # Feature gating
│       └── ...
├── Models/                              # Eloquent models (11 files)
│   ├── User.php
│   ├── Company.php
│   ├── Invoice.php
│   ├── Customer.php
│   ├── Vendor.php
│   ├── Item.php
│   ├── Employee.php
│   ├── Role.php
│   ├── Permission.php
│   └── ...
├── Services/
│   ├── CurrentCompany.php               # Singleton - holds company context
│   ├── CompanyContextService.php        # Company setup/config
│   └── RolePermissionSynchronizer.php   # RBAC sync
├── Actions/                             # Domain-driven business logic
│   ├── Company/
│   │   ├── CreateCompany.php
│   │   ├── OnboardCompany.php
│   ├── Invoice/
│   │   ├── CreateInvoice.php
│   │   ├── ApproveInvoice.php
│   │   ├── VoidInvoice.php
│   ├── Payment/
│   │   ├── AllocatePayment.php
│   ├── User/
│   │   ├── CreateUser.php
│   │   ├── InviteUser.php
│   └── ...
├── Constants/
│   ├── Permissions.php                  # 226+ permission definitions
│   └── Tables.php
└── Console/
    └── Commands/                        # Artisan commands
        ├── RbacSyncPermissions.php
        ├── RbacSyncRolePermissions.php
        └── ...
```

**Routing:**
```
build/routes/
├── web.php (449 lines)
│   ├── Public routes: /, /invite/{token}
│   ├── Auth routes: /login, /register (Fortify)
│   ├── Authenticated: /companies, /dashboard
│   └── Company-scoped:
│       GET /{company}                    # Dashboard
│       GET /{company}/invoices           # Invoice list
│       POST /{company}/invoices          # Create invoice
│       GET /{company}/invoices/{id}      # View invoice
│       PUT /{company}/invoices/{id}      # Update invoice
│       DELETE /{company}/invoices/{id}   # Delete invoice
│       ... (30+ more company-scoped routes)
│
└── api.php
    ├── Public: /api/login, /api/register
    └── Authenticated (Sanctum):
        GET /api/{company}/invoices
        POST /api/{company}/invoices
        ... (REST endpoints)
```

### Layer 3: Database (PostgreSQL Multi-Schema)

**5 Main Schemas:**

1. **`auth` Schema** - Authentication & Tenancy
   - `users` - User accounts
   - `companies` - Tenant organizations
   - `company_user` - Company membership
   - `permissions` - Spatie permission records
   - `roles` - Spatie role records

2. **`acct` Schema** - General Ledger & Accounting
   - `accounts` - Chart of Accounts
   - `fiscal_years` - Financial years
   - `accounting_periods` - Periods
   - `transactions` - GL transactions
   - `journal_entries` - Debit/credit entries
   - `customers` - AR counterparties
   - `invoices` / `invoice_line_items` - Sales invoices
   - `vendors` - AP counterparties
   - `bills` / `bill_line_items` - Purchase invoices
   - `payments` / `payment_allocations` - AR collections
   - `bill_payments` / `bill_payment_allocations` - AP payments

3. **`bank` Schema** - Banking & Reconciliation
   - `company_bank_accounts` - Bank accounts per company
   - `bank_transactions` - Transaction feed
   - `bank_reconciliations` - Statement matching

4. **`inv` Schema** - Inventory & Products
   - `items` - SKU master records
   - `item_categories` - Product categories
   - `warehouses` - Storage locations
   - `stock_levels` - Quantity on hand
   - `stock_movements` - In/out transactions

5. **`pay` Schema** - Payroll & HR
   - `employees` - Employee records
   - `payroll_periods` - Pay cycles
   - `payroll_runs` - Batch processing
   - `payslips` - Paychecks
   - `earning_types` - Salary components
   - `deduction_types` - Tax/insurance
   - `leave_types` - PTO categories

---

## RBAC System

### Concepts

**Role:** A named collection of permissions (e.g., "Owner", "Accountant", "Viewer")
**Permission:** An action you can take (e.g., "invoices.create", "invoices.approve")
**Team:** In Spatie, "team" = company (each company has its own role assignments)

### Permission Structure

Permissions follow the pattern: `{resource}.{action}`

**Examples:**
```
invoices.view              # Can view invoices
invoices.create            # Can create invoices
invoices.approve           # Can approve invoices (workflow state)
invoices.void              # Can void invoices (workflow state)
customers.manage           # Can CRUD customers
employees.create           # Can create employees
payroll.process            # Can process payroll
accounting.reports.view    # Can view accounting reports
```

### Default Roles

| Role | Purpose |
|------|---------|
| **Owner** | Full access to all resources |
| **Admin** | Administrative access (users, roles, settings) |
| **Manager** | Can create/edit most resources, approve workflows |
| **Staff** | Can view and create, but not approve/delete |
| **Viewer** | Read-only access |

### Permission Checking

**In Controllers (Authorization):**
```php
// In FormRequest::authorize()
public function authorize(): bool
{
    return $this->user()->hasCompanyPermission(
        Permissions::INVOICES_CREATE
    );
}
```

**In Views (Conditionally render):**
```vue
<template v-if="can('invoices.approve')">
  <button @click="approveInvoice">Approve</button>
</template>
```

**In Database (Row-Level Security):**
```sql
CREATE POLICY invoice_company_isolation ON acct.invoices
  USING (company_id = current_setting('app.current_company_id')::uuid);
```

---

## Code Patterns

### Pattern 1: Multi-Tenancy

**Always include company context in queries:**
```php
// ✅ CORRECT
$invoices = app(CurrentCompany::class)->get()
    ->invoices()
    ->where('status', 'draft')
    ->get();

// ❌ WRONG - No company filter
$invoices = Invoice::where('status', 'draft')->get();
```

**URL always includes {company}:**
```php
// ✅ CORRECT
Route::get('/{company}/invoices', [InvoiceController::class, 'index']);

// ❌ WRONG - Missing company parameter
Route::get('/invoices', [InvoiceController::class, 'index']);
```

### Pattern 2: FormRequest with Authorization

**Validate AND authorize in one place:**
```php
namespace App\Http\Requests;

class StoreInvoiceRequest extends FormRequest
{
    // STEP 1: Check permission
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission(
            Permissions::INVOICES_CREATE
        );
    }

    // STEP 2: Validate input
    public function rules(): array
    {
        return [
            'customer_id' => 'required|uuid|exists:acct.customers,id',
            'invoice_date' => 'required|date|before_or_equal:today',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'line_items' => 'required|array|min:1',
            'line_items.*.item_id' => 'required|uuid|exists:inv.items,id',
            'line_items.*.quantity' => 'required|numeric|min:0.01',
            'line_items.*.unit_price' => 'required|numeric|min:0',
        ];
    }
}
```

**Use in controller:**
```php
public function store(StoreInvoiceRequest $request)
{
    // Request is already validated and authorized

    $invoice = Bus::dispatch(
        new CreateInvoice($request->validated())
    );

    return to_route('invoices.show', [
        'company' => auth()->user()->current_company_id,
        'invoice' => $invoice->id,
    ]);
}
```

### Pattern 3: Command Bus for Complex Operations

**Action class (business logic):**
```php
namespace App\Actions\Invoice;

use App\Models\Invoice;
use Illuminate\Bus\Dispatchable;

class CreateInvoice
{
    use Dispatchable;

    public function __construct(public array $data) {}

    public function handle(): Invoice
    {
        $company = app(CurrentCompany::class)->get();

        $invoice = $company->invoices()->create([
            'customer_id' => $this->data['customer_id'],
            'invoice_date' => $this->data['invoice_date'],
            'due_date' => $this->data['due_date'],
            'status' => 'draft',
            'total' => 0,
        ]);

        // Add line items
        foreach ($this->data['line_items'] as $item) {
            $invoice->lineItems()->create([
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'amount' => $item['quantity'] * $item['unit_price'],
            ]);
        }

        // Update total
        $invoice->update([
            'total' => $invoice->lineItems->sum('amount'),
        ]);

        return $invoice;
    }
}
```

**Use in controller:**
```php
use Illuminate\Support\Facades\Bus;

$invoice = Bus::dispatch(
    new CreateInvoice($request->validated())
);
```

### Pattern 4: Company Context Access

**Via CurrentCompany singleton:**
```php
use App\Services\CurrentCompany;

class InvoiceController extends Controller
{
    public function index()
    {
        $company = app(CurrentCompany::class)->get();

        $invoices = $company->invoices()
            ->where('status', '!=', 'void')
            ->paginate();

        return inertia('Invoices/Index', [
            'invoices' => $invoices,
        ]);
    }
}
```

**Via Middleware Injection:**
```php
// In middleware:
$company = Company::findOrFail($companyId);
app(CurrentCompany::class)->set($company);

// Later in controller, middleware has already set it:
$company = app(CurrentCompany::class)->get();
```

### Pattern 5: UUID Primary Keys

**All models use UUID for IDs:**
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    // ✅ CORRECT - UUID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    protected $primaryKey = 'id';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
    }
}

// ❌ WRONG - Don't use auto-incrementing IDs
// $table->id();  // ← This is wrong!
```

### Pattern 6: FormRequest Structure

**Always use FormRequest for requests:**
```php
// ❌ WRONG - Validating in controller
$request->validate([...]);

// ✅ CORRECT - Use FormRequest
class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool { ... }
    public function rules(): array { ... }
}

// In controller:
public function store(StoreInvoiceRequest $request) { ... }
```

### Pattern 7: Inertia Props Pattern

**Return data from controller to Vue:**
```php
// In controller
return inertia('Invoices/Index', [
    'invoices' => $company->invoices()->paginate(),
    'customers' => $company->customers()->select('id', 'name')->get(),
    'filters' => request()->query('filters'),
]);
```

**In Vue component:**
```vue
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps<{
  invoices: Invoice[]
  customers: Customer[]
  filters: Record<string, string>
}>()

const form = useForm({
  status: filters.status || '',
  customer_id: filters.customer_id || '',
})
</script>

<template>
  <InvoiceTable :invoices="invoices" />
</template>
```

---

## Key Files & Locations

### Backend Critical Files

| File | Purpose |
|------|---------|
| `build/bootstrap/app.php` | Laravel 12 application bootstrap |
| `build/routes/web.php` | Web routes (Inertia responses) |
| `build/routes/api.php` | API routes (REST endpoints) |
| `build/app/Http/Middleware/IdentifyCompany.php` | Company context extraction |
| `build/app/Services/CurrentCompany.php` | Company context singleton |
| `build/app/Constants/Permissions.php` | All 226+ permission definitions |
| `build/app/Models/*` | Entity models (User, Company, Invoice, etc.) |
| `build/app/Http/Requests/*` | FormRequest validation classes |
| `build/database/migrations/*` | Database schema migrations |

### Frontend Critical Files

| File | Purpose |
|------|---------|
| `build/resources/js/app.ts` | Vue app entry point |
| `build/resources/js/layouts/AppLayout.vue` | Main layout wrapper |
| `build/resources/js/navigation/registry.ts` | Dynamic navigation config |
| `build/resources/js/routes/*` | 49+ feature modules |
| `build/resources/js/components/ui/*` | Shadcn Vue components |

### Documentation

| File | Purpose |
|------|---------|
| `CLAUDE.md` | Quick dev reference (do's and don'ts) |
| `docs/contracts/` | Schema contracts (21 files) |
| `docs/frontend-experience-contract.md` | UX specifications |

---

## Common Workflows

### Adding a New Feature

1. **Check the contract** (e.g., `docs/contracts/accounting-invoicing-contract.md`)
2. **Add database migration** → `build/database/migrations/`
3. **Create model** → `build/app/Models/YourModel.php`
4. **Create FormRequest** → `build/app/Http/Requests/StoreYourModelRequest.php`
5. **Create Action** → `build/app/Actions/YourDomain/CreateYourModel.php`
6. **Create controller** → `build/app/Http/Controllers/YourModelController.php`
7. **Add routes** → `build/routes/web.php`
8. **Create Vue pages** → `build/resources/js/pages/yourmodule/`
9. **Add permissions** → `build/app/Constants/Permissions.php` + artisan commands

### Adding a Permission

1. **Add to `Permissions.php`:**
```php
const YOUR_FEATURE_ACTION = 'your_feature.action';
```

2. **Sync permissions:**
```bash
php artisan rbac:sync-permissions
```

3. **Assign to roles in `config/role-permissions.php`:**
```php
'your_feature' => [
    'admin' => ['your_feature.create', 'your_feature.delete'],
    'manager' => ['your_feature.create', 'your_feature.view'],
    'staff' => ['your_feature.view'],
],
```

4. **Sync role permissions:**
```bash
php artisan rbac:sync-role-permissions
```

### Running the Application

```bash
# Terminal 1: Start Laravel Octane server
php artisan octane:start --server=frankenphp --port=9001 --watch

# Terminal 2: Start Vite dev server
npm run dev

# Access at http://localhost:5180
```

---

## Database Architecture

### Schema Organization

```sql
-- Public schema (reference data)
CREATE SCHEMA public;
CREATE TABLE public.currencies (...);  -- ISO 4217

-- Authentication schema
CREATE SCHEMA auth;
CREATE TABLE auth.users (...);
CREATE TABLE auth.companies (...);
CREATE TABLE auth.company_user (...);
CREATE TABLE auth.permissions (...);
CREATE TABLE auth.roles (...);

-- Accounting schema (General Ledger)
CREATE SCHEMA acct;
CREATE TABLE acct.accounts (...);
CREATE TABLE acct.fiscal_years (...);
CREATE TABLE acct.accounting_periods (...);
CREATE TABLE acct.transactions (...);
CREATE TABLE acct.journal_entries (...);

-- Accounts Receivable (Invoices)
CREATE TABLE acct.customers (...);
CREATE TABLE acct.invoices (...);
CREATE TABLE acct.invoice_line_items (...);
CREATE TABLE acct.payments (...);
CREATE TABLE acct.payment_allocations (...);

-- Accounts Payable (Bills)
CREATE TABLE acct.vendors (...);
CREATE TABLE acct.bills (...);
CREATE TABLE acct.bill_line_items (...);
CREATE TABLE acct.bill_payments (...);
CREATE TABLE acct.bill_payment_allocations (...);

-- Banking schema
CREATE SCHEMA bank;
CREATE TABLE bank.company_bank_accounts (...);
CREATE TABLE bank.bank_transactions (...);
CREATE TABLE bank.bank_reconciliations (...);

-- Inventory schema
CREATE SCHEMA inv;
CREATE TABLE inv.items (...);
CREATE TABLE inv.item_categories (...);
CREATE TABLE inv.warehouses (...);
CREATE TABLE inv.stock_levels (...);
CREATE TABLE inv.stock_movements (...);

-- Payroll schema
CREATE SCHEMA pay;
CREATE TABLE pay.employees (...);
CREATE TABLE pay.payroll_periods (...);
CREATE TABLE pay.payroll_runs (...);
CREATE TABLE pay.payslips (...);
CREATE TABLE pay.earning_types (...);
CREATE TABLE pay.deduction_types (...);
CREATE TABLE pay.leave_types (...);
```

### Row-Level Security (RLS)

Every schema-specific table has RLS enabled:

```sql
-- Example: Invoice table isolation
CREATE POLICY invoice_company_isolation ON acct.invoices
  USING (company_id = current_setting('app.current_company_id')::uuid);

-- During request, before querying:
SET app.current_company_id = 'company-uuid-here';

-- Now SELECT * FROM acct.invoices only returns that company's invoices
```

---

## Related Documentation

For detailed information, see:
- **Schema Contracts:** `docs/contracts/00-master-index.md`
- **Frontend UX Contract:** `docs/frontend-experience-contract.md`
- **Quick Dev Reference:** `CLAUDE.md`
- **Request Journey Detailed:** See `ARCHITECTURE-REQUEST-FLOW.md` (coming next)
- **Feature Tree:** See `ARCHITECTURE-FEATURES.md` (coming next)

---

## Next Steps for New Developers

1. ✅ **Read this document** (15 minutes)
2. 📖 Read `ARCHITECTURE-REQUEST-FLOW.md` to understand request lifecycle (10 minutes)
3. 📖 Read `ARCHITECTURE-FEATURES.md` to see the feature tree (10 minutes)
4. 🔐 Read `ARCHITECTURE-PERMISSIONS.md` for permission details (10 minutes)
5. 💾 Read specific schema contract in `docs/contracts/` for your feature area (10-20 minutes)
6. 🛠️ Start building: Pick a small feature to add (invoicing, items, customers)

**Total time to understand system: ~1 hour**

---

*Last updated: 2025-01-21*
