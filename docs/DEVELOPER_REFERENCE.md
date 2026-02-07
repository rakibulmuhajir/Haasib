# Haasib Developer Quick Reference

**Patterns, quick-start guides, and common tasks**

---

## Table of Contents

1. [Setup & Running](#setup--running)
2. [Project Structure](#project-structure)
3. [Common Code Patterns](#common-code-patterns)
4. [Adding Features](#adding-features)
5. [Database Changes](#database-changes)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)
8. [Key Files Reference](#key-files-reference)

---

## Setup & Running

### Start Development Server

```bash
cd build/
php artisan octane:start --server=frankenphp --port=9001 --watch
```

In another terminal:
```bash
npm run dev
```

Access application at: `http://localhost:5180`

### Environment Setup

```bash
cd build/
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### Database Commands

```bash
# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Fresh database (careful!)
php artisan migrate:fresh --seed

# Create migration
php artisan make:migration create_table_name

# Sync permissions
php artisan rbac:sync-permissions
php artisan rbac:sync-role-permissions
```

---

## Project Structure

### Backend

```
build/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # Route handlers
│   │   ├── Middleware/           # Request processing
│   │   ├── Requests/             # FormRequest (validation + auth)
│   │   └── Resources/            # API response formatting
│   ├── Models/                   # Eloquent models (11 core)
│   ├── Services/                 # Business logic services
│   ├── Actions/                  # Domain-specific actions
│   ├── Constants/                # Permissions.php, Tables.php
│   ├── Policies/                 # Authorization policies
│   └── Console/
│       └── Commands/             # Artisan commands
├── bootstrap/
│   └── app.php                   # Laravel 12 modular config
├── routes/
│   ├── web.php                   # Web routes (449 lines)
│   └── api.php                   # API routes
├── database/
│   ├── migrations/               # Schema changes (40+)
│   ├── factories/                # Test data factories
│   └── seeders/                  # Database seeding
├── config/
│   ├── app.php                   # App config
│   ├── database.php              # DB config
│   ├── auth.php                  # Fortify config
│   ├── permission.php            # Spatie permission
│   └── role-permissions.php      # RBAC mappings
└── resources/
    └── views/                    # Blade templates (mostly unused with Inertia)
```

### Frontend

```
build/resources/js/
├── app.ts                        # Vue app entry
├── pages/                        # Page components (11)
│   ├── auth/
│   ├── companies/
│   └── ...
├── routes/                       # Feature modules (49+)
│   ├── invoices/
│   ├── customers/
│   ├── fuel/                     # Industry vertical
│   └── ...
├── components/
│   ├── ui/                       # Shadcn Vue components
│   ├── forms/                    # Form components
│   ├── palette/                  # Command palette
│   └── Generic.vue
├── layouts/
│   └── AppLayout.vue             # Main layout
├── navigation/
│   └── registry.ts               # Navigation configs
├── lib/                          # Utilities
└── types/                        # TypeScript types
```

---

## Common Code Patterns

### Pattern 1: Request with Company Context

**Route:**
```php
// build/routes/web.php
Route::post('/{company}/invoices', [InvoiceController::class, 'store'])
    ->middleware(['auth', 'identify.company']);
```

**Controller:**
```php
// build/app/Http/Controllers/InvoiceController.php
public function store(StoreInvoiceRequest $request)
{
    $company = app(CurrentCompany::class)->get();

    $invoice = $company->invoices()->create(
        $request->validated()
    );

    return inertia('Invoices/Show', [
        'invoice' => $invoice,
    ]);
}
```

**FormRequest:**
```php
// build/app/Http/Requests/StoreInvoiceRequest.php
public function authorize(): bool
{
    return auth()->user()
        ->hasPermissionTo(Permissions::INVOICE_CREATE);
}

public function rules(): array
{
    return [
        'customer_id' => 'required|exists:acct.customers',
        'due_date' => 'required|date|after:today',
        'items' => 'required|array|min:1',
    ];
}
```

### Pattern 2: Model with Company Scope

**Model:**
```php
// build/app/Models/Invoice.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasUlids;  // UUID primary keys

    protected $table = 'acct.invoices';

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_number',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'invoice_date' => 'date',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineItems()
    {
        return $this->hasMany(InvoiceLineItem::class);
    }
}
```

**Usage:**
```php
// Automatically scoped by company
$company = app(CurrentCompany::class)->get();
$invoices = $company->invoices()
    ->where('status', 'pending')
    ->paginate();
```

### Pattern 3: Permission Constant

**Define:**
```php
// build/app/Constants/Permissions.php
public const INVOICE_CREATE = 'invoice.create';
public const INVOICE_VOID = 'invoice.void';
```

**Use:**
```php
// In controller
$this->authorize(Permissions::INVOICE_VOID);

// In FormRequest
public function authorize(): bool
{
    return auth()->user()
        ->hasPermissionTo(Permissions::INVOICE_VOID);
}

// In Vue/Blade
@can(Permissions::INVOICE_VOID)
    <button>Void Invoice</button>
@endcan
```

### Pattern 4: Vue Component with Form

**Component:**
```vue
<!-- build/resources/js/routes/invoices/Create.vue -->
<template>
  <AppLayout>
    <div class="container">
      <h1>Create Invoice</h1>
      <form @submit.prevent="submit">
        <input v-model="form.customer_id" type="text" />
        <input v-model="form.due_date" type="date" />
        <button type="submit" :disabled="form.processing">
          Create
        </button>
      </form>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

const form = useForm({
  customer_id: '',
  due_date: '',
  items: [],
})

const submit = () => {
  form.post(route('invoices.store', { company: props.company.slug }))
}

defineProps({
  company: Object,
})
</script>
```

### Pattern 5: Middleware - Identify Company

**Middleware:**
```php
// build/app/Http/Middleware/IdentifyCompany.php
namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\CurrentCompany;

class IdentifyCompany
{
    public function handle($request, $next)
    {
        $company = Company::where('slug', $request->route('company'))
            ->firstOrFail();

        // Set company context
        app(CurrentCompany::class)->set($company);
        auth()->user()->setTeam($company);

        // Set database session variable for RLS
        DB::statement(
            "SET app.current_company_id = ?",
            [$company->id]
        );

        return $next($request);
    }
}
```

---

## Adding Features

### Add a New CRUD Feature (5 Steps)

#### Step 1: Create Migration

```bash
php artisan make:migration create_invoices_table
```

```php
// build/database/migrations/YYYY_MM_DD_create_invoices_table.php
Schema::create('acct.invoices', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->ulid('company_id');
    $table->string('invoice_number')->unique();
    $table->date('invoice_date');
    $table->decimal('amount', 15, 2);
    $table->timestamps();

    $table->foreign('company_id')
        ->references('id')
        ->on('auth.companies');
});

php artisan migrate
```

#### Step 2: Create Model

```bash
php artisan make:model Invoice
```

```php
// build/app/Models/Invoice.php
class Invoice extends Model
{
    use HasUlids;

    protected $table = 'acct.invoices';

    protected $fillable = ['company_id', 'invoice_number', 'amount'];

    public function company() {
        return $this->belongsTo(Company::class);
    }
}
```

#### Step 3: Add Permission

```php
// build/app/Constants/Permissions.php
public const INVOICE_CREATE = 'invoice.create';
public const INVOICE_VIEW = 'invoice.view';
public const INVOICE_UPDATE = 'invoice.update';
public const INVOICE_DELETE = 'invoice.delete';
```

```bash
php artisan rbac:sync-permissions
```

#### Step 4: Update config/role-permissions.php

```php
'Admin' => [
    Permissions::INVOICE_CREATE,
    Permissions::INVOICE_VIEW,
    Permissions::INVOICE_UPDATE,
    Permissions::INVOICE_DELETE,
],
```

```bash
php artisan rbac:sync-role-permissions
```

#### Step 5: Create FormRequest & Controller

```bash
php artisan make:request StoreInvoiceRequest
php artisan make:controller InvoiceController --resource
```

```php
// build/app/Http/Requests/StoreInvoiceRequest.php
class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()
            ->hasPermissionTo(Permissions::INVOICE_CREATE);
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:acct.customers',
            'invoice_number' => 'required|unique:acct.invoices',
            'amount' => 'required|numeric|min:0.01',
        ];
    }
}
```

```php
// build/app/Http/Controllers/InvoiceController.php
class InvoiceController extends Controller
{
    public function store(StoreInvoiceRequest $request)
    {
        $company = app(CurrentCompany::class)->get();

        $invoice = $company->invoices()
            ->create($request->validated());

        return inertia('Invoices/Show', ['invoice' => $invoice]);
    }
}
```

---

## Database Changes

### Add a Column

1. **Create migration:**
   ```bash
   php artisan make:migration add_reference_to_invoices_table
   ```

2. **Update migration:**
   ```php
   Schema::table('acct.invoices', function (Blueprint $table) {
       $table->string('reference')->nullable();
   });
   ```

3. **Update model:**
   ```php
   protected $fillable = ['reference']; // add to list
   ```

4. **Run migration:**
   ```bash
   php artisan migrate
   ```

### Add a Foreign Key

```php
Schema::table('acct.invoices', function (Blueprint $table) {
    $table->ulid('project_id')->nullable();

    $table->foreign('project_id')
        ->references('id')
        ->on('acct.projects')
        ->onDelete('cascade');
});
```

---

## Testing

### Create Test

```bash
php artisan make:test InvoiceTest
```

```php
// tests/Feature/InvoiceTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Invoice;
use App\Constants\Permissions;

class InvoiceTest extends TestCase
{
    public function test_can_create_invoice()
    {
        $user = $this->createUser();
        $company = $this->createCompany($user);

        $this->actingAs($user)
            ->withPermission(Permissions::INVOICE_CREATE)
            ->post(route('invoices.store', ['company' => $company->slug]), [
                'customer_id' => $customer->id,
                'amount' => 1000,
            ])
            ->assertOk();
    }

    public function test_cannot_create_invoice_without_permission()
    {
        $user = $this->createUser();
        $company = $this->createCompany($user);

        $this->actingAs($user)
            ->post(route('invoices.store', ['company' => $company->slug]), [
                'customer_id' => $customer->id,
                'amount' => 1000,
            ])
            ->assertForbidden();
    }
}
```

### Run Tests

```bash
php artisan test                    # All tests
php artisan test --filter Invoice   # Specific test
php artisan test --parallel         # Parallel execution
```

---

## Troubleshooting

### "No company context" Error

**Problem:** `RuntimeException: No company context`

**Solution:** Ensure route has `identify.company` middleware:
```php
Route::middleware(['auth', 'identify.company'])
    ->prefix('/{company}')
    ->group(...)
```

### Permission Denied (403)

**Problem:** User gets 403 Unauthorized

**Solution:**
1. Check user has role in company
2. Check role has permission:
   ```bash
   php artisan rbac:sync-role-permissions
   ```
3. Verify permission constant exists and is used in FormRequest

### Database Connection Error

**Problem:** Connection refused to PostgreSQL

**Solution:**
```bash
# Check .env
DB_HOST=localhost
DB_DATABASE=haasib_development
DB_USERNAME=postgres
DB_PASSWORD=...

# Test connection
php artisan tinker
>>> DB::connection()->getPdo()
```

### Migration Fails

**Problem:** Migration error

**Solution:**
```bash
# Check migration status
php artisan migrate:status

# Rollback and retry
php artisan migrate:rollback
php artisan migrate

# Fresh start (careful!)
php artisan migrate:fresh --seed
```

---

## Key Files Reference

### Configuration
- `build/bootstrap/app.php` - Main app config
- `build/config/app.php` - App settings
- `build/config/database.php` - Database config
- `build/config/auth.php` - Fortify settings
- `build/config/role-permissions.php` - RBAC mappings
- `.env` - Environment variables

### Models (11 core)
- `build/app/Models/User.php`
- `build/app/Models/Company.php`
- `build/app/Models/Invoice.php`
- `build/app/Models/Customer.php`
- ... others

### Services & Middleware
- `build/app/Services/CurrentCompany.php` - Company context
- `build/app/Http/Middleware/IdentifyCompany.php` - Company extraction
- `build/app/Http/Middleware/HandleInertiaRequests.php` - Inertia props

### Permissions & Roles
- `build/app/Constants/Permissions.php` - All 151 permissions
- `build/config/role-permissions.php` - Role-permission mappings
- `build/app/Console/Commands/Rbac/` - Sync commands

### Routes
- `build/routes/web.php` - Web routes (449 lines)
- `build/routes/api.php` - API routes

### Database
- `build/database/migrations/` - All migrations (40+)
- `build/database/factories/` - Test data factories
- `build/database/seeders/` - Database seeders

---

## CLAUDE.md Quick Reference

From `CLAUDE.md`:

### ✅ DO Use

```php
$table->uuid('id')->primary()
Schema::create('acct.customers')
app(CurrentCompany::class)->get()
Route::get('/{company}/resource', ...)
Bus::dispatch(new Command())
FormRequest for validation
```

### ❌ DON'T Use

```php
$table->id()                              // Use uuid
Schema::create('customers')               // Use 'acct.customers'
session('active_company_id')              // Use CurrentCompany service
Route::get('/resource', ...)              // Must have /{company}
new Service()                             // Use Bus::dispatch()
$request->validate()                      // Use FormRequest
```

---

## Learning Resources

- **Architecture:** [ARCHITECTURE.md](ARCHITECTURE.md)
- **Entry Points:** [ARCHITECTURE-ENTRY-POINTS.md](ARCHITECTURE-ENTRY-POINTS.md)
- **Request Flow:** [ARCHITECTURE-REQUEST-FLOW.md](ARCHITECTURE-REQUEST-FLOW.md)
- **Permissions:** [PERMISSIONS.md](PERMISSIONS.md)
- **Schemas:** [SCHEMAS.md](SCHEMAS.md)
- **Code:** `docs/contracts/` (schema documentation)
- **Laravel Docs:** https://laravel.com/docs/12
- **Spatie Permission:** https://spatie.be/docs/laravel-permission/v6
- **Vue 3:** https://vuejs.org/guide/
