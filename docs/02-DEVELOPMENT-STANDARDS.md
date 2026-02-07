# Haasib Development Standards

**Last Updated**: 2025-02-01  
**Purpose**: Coding standards, constitutional rules, and development patterns  
**Audience**: All developers (AI and human)

---

## Table of Contents

1. [Constitutional Rules (Non-Negotiable)](#1-constitutional-rules-non-negotiable)
2. [Code Patterns](#2-code-patterns)
3. [Template Skeletons](#3-template-skeletons)
4. [Common Mistakes to Avoid](#4-common-mistakes-to-avoid)
5. [Development Workflow](#5-development-workflow)
6. [Validation Checklist](#6-validation-checklist)

---

## 1. Constitutional Rules (Non-Negotiable)

Violating any of these requires a restart.

### 1.1 Architecture Rules

| Rule | Correct | Wrong |
|------|---------|-------|
| **UUID Primary Keys** | `$table->uuid('id')->primary()` | `$table->id()` |
| **Multi-Schema** | `Schema::create('acct.customers')` | `Schema::create('customers')` |
| **Company Context** | `/{company}/resource` | `/resource` |
| **Context Source** | `app(CurrentCompany::class)->get()` | `session('active_company_id')` |
| **Business Logic** | `Bus::dispatch('action.name', ...)` | `new Service()` |
| **Validation** | FormRequest class | `$request->validate([...])` |

### 1.2 Frontend Rules

| Rule | Correct | Wrong |
|------|---------|-------|
| **Vue API** | `<script setup lang="ts">` | `export default { data() }` |
| **Components** | Shadcn-Vue components | Raw `<input>`, `<button>` |
| **Forms** | Inertia `useForm()` | `fetch()`, `axios` |
| **Terminology** | `useLexicon()` composable | Hardcoded mode checks |

### 1.3 Database Rules

- All tenant tables MUST have `company_id` UUID column
- All tenant tables MUST have RLS enabled with company isolation policy
- Use schema prefixes: `auth.users`, `acct.invoices`, `bank.accounts`
- Never use auto-incrementing integer IDs

### 1.4 The Golden Patterns

```php
// ✅ THESE ARE MANDATORY

// Routes
Route::get('/{company}/invoices', [InvoiceController::class, 'index'])
    ->middleware(['auth', 'identify.company']);

// Company context
$company = app(CurrentCompany::class)->get();

// Business logic
$result = Bus::dispatch('invoice.create', $data);

// Authorization
$this->hasCompanyPermission(Permissions::INVOICES_CREATE);

// Database schema
Schema::create('acct.invoices', ...);
DB::statement('ALTER TABLE acct.invoices ENABLE ROW LEVEL SECURITY');

// ❌ INSTANT REJECTION
Route::get('/invoices', ...);              // Missing {company}
session('active_company_id');              // Session-based context
new InvoiceService();                      // Direct instantiation
$request->validate([...]);                 // Inline validation
$table->id();                              // Integer PK
```

---

## 2. Code Patterns

### 2.1 Database: Migration Pattern

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('invoice_number', 50);
            $table->date('invoice_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->enum('status', ['draft', 'sent', 'paid'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');

            // Indexes
            $table->index(['company_id', 'status']);
            $table->unique(['company_id', 'invoice_number']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.invoices ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY invoices_company_isolation ON acct.invoices
            FOR ALL USING (company_id = current_setting('app.current_company_id')::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS invoices_company_isolation ON acct.invoices');
        Schema::dropIfExists('acct.invoices');
    }
};
```

### 2.2 Database: Model Pattern

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.invoices';
    protected $keyType = 'string';
    public $incrementing = false;

    // Copy from schema contract
    protected $fillable = [
        'company_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    // Business logic
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }
}
```

### 2.3 Backend: Controller Pattern

```php
<?php

namespace App\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Requests\StoreInvoiceRequest;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();
        
        return Inertia::render('Invoices/Index', [
            'invoices' => $company->invoices()
                ->with('customer')
                ->latest()
                ->paginate(25),
        ]);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $invoice = Bus::dispatch('invoice.create', $request->validated());

        return redirect()
            ->route('invoices.show', [
                'company' => app(CurrentCompany::class)->get()->slug,
                'invoice' => $invoice->id,
            ])
            ->with('success', 'Invoice created successfully');
    }
}
```

### 2.4 Backend: FormRequest Pattern

```php
<?php

namespace App\Http\Requests;

use App\Constants\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVOICES_CREATE)
            && $this->validateRlsContext();
    }

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

    public function messages(): array
    {
        return [
            'customer_id.exists' => 'The selected customer does not exist.',
            'line_items.min' => 'At least one line item is required.',
        ];
    }
}
```

### 2.5 Backend: Action Pattern

```php
<?php

namespace App\Actions\Invoice;

use App\Models\Invoice;
use App\Services\CurrentCompany;
use Illuminate\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class CreateInvoice
{
    use Dispatchable;

    public function __construct(public array $data) {}

    public function handle(): Invoice
    {
        $company = app(CurrentCompany::class)->get();

        return DB::transaction(function () use ($company) {
            // Create invoice
            $invoice = $company->invoices()->create([
                'customer_id' => $this->data['customer_id'],
                'invoice_date' => $this->data['invoice_date'],
                'due_date' => $this->data['due_date'],
                'status' => 'draft',
                'total_amount' => 0,
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
                'total_amount' => $invoice->lineItems->sum('amount'),
            ]);

            return $invoice;
        });
    }
}
```

### 2.6 Backend: Route Pattern

```php
<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [HomeController::class, 'index']);

// Auth routes (Fortify handles most)
Route::middleware(['auth'])->group(function () {
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// Company-scoped routes
Route::middleware(['auth', 'identify.company'])->group(function () {
    Route::get('/{company}', [DashboardController::class, 'companyDashboard']);
    
    // Invoices
    Route::get('/{company}/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/{company}/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/{company}/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/{company}/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::put('/{company}/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('/{company}/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
});
```

### 2.7 Frontend: Vue Page Pattern

```vue
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface Props {
  company: Company
  customers: Customer[]
  items: Item[]
}

const props = defineProps<Props>()

const form = useForm({
  customer_id: '',
  invoice_date: new Date().toISOString().split('T')[0],
  due_date: '',
  line_items: [] as LineItem[],
})

const totalAmount = computed(() => {
  return form.line_items.reduce((sum, item) => {
    return sum + (item.quantity * item.unit_price)
  }, 0)
})

const submit = () => {
  form.post(route('invoices.store', { company: props.company.slug }))
}

const addLineItem = () => {
  form.line_items.push({
    item_id: '',
    quantity: 1,
    unit_price: 0,
  })
}
</script>

<template>
  <Head title="Create Invoice" />
  
  <AppLayout>
    <div class="container mx-auto py-6">
      <h1 class="text-2xl font-bold mb-6">Create Invoice</h1>
      
      <form @submit.prevent="submit" class="space-y-6">
        <!-- Customer Selection -->
        <div>
          <Label for="customer">Customer</Label>
          <select id="customer" v-model="form.customer_id" class="w-full">
            <option value="">Select customer</option>
            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
              {{ customer.name }}
            </option>
          </select>
        </div>
        
        <!-- Dates -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <Label for="invoice_date">Invoice Date</Label>
            <Input id="invoice_date" v-model="form.invoice_date" type="date" />
          </div>
          <div>
            <Label for="due_date">Due Date</Label>
            <Input id="due_date" v-model="form.due_date" type="date" />
          </div>
        </div>
        
        <!-- Line Items -->
        <div>
          <h2 class="text-lg font-semibold mb-4">Line Items</h2>
          <Button type="button" @click="addLineItem">Add Item</Button>
        </div>
        
        <!-- Total -->
        <div class="text-right">
          <span class="text-xl font-bold">Total: {{ totalAmount }}</span>
        </div>
        
        <!-- Actions -->
        <div class="flex justify-end gap-4">
          <Button type="button" variant="secondary" @click="$inertia.visit(route('invoices.index', { company: props.company.slug }))">
            Cancel
          </Button>
          <Button type="submit" :disabled="form.processing">
            Create Invoice
          </Button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
```

---

## 3. Template Skeletons

Use these as starting points for new files.

### 3.1 Migration Skeleton

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{schema}.{table}', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            // Add your columns here
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE {schema}.{table} ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY {table}_company_isolation ON {schema}.{table}
            FOR ALL USING (company_id = current_setting('app.current_company_id')::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS {table}_company_isolation ON {schema}.{table}');
        Schema::dropIfExists('{schema}.{table}');
    }
};
```

### 3.2 Model Skeleton

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class {Entity} extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = '{schema}.{table}';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        // Add your fields
    ];

    protected $casts = [
        // Add your casts
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
```

### 3.3 Controller Skeleton

```php
<?php

namespace App\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Requests\{Store,Update}Request;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Inertia\Response;

class {Entity}Controller extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();
        
        return Inertia::render('{Module}/{Entity}/Index', [
            'entities' => $company->{entities}()->paginate(),
        ]);
    }

    public function store(StoreRequest $request)
    {
        $entity = Bus::dispatch('{entity}.create', $request->validated());
        
        return redirect()->route('{entities}.show', [
            'company' => app(CurrentCompany::class)->get()->slug,
            '{entity}' => $entity->id,
        ])->with('success', '{Entity} created successfully');
    }
}
```

### 3.4 FormRequest Skeleton

```php
<?php

namespace App\Http\Requests;

use App\Constants\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class {Action}{Entity}Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::{MODULE}_{ENTITY}_{ACTION})
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            // Validation rules from schema contract
        ];
    }
}
```

---

## 4. Common Mistakes to Avoid

### 4.1 Database Mistakes

```php
// ❌ WRONG
$table->id()                                    // Use uuid('id')->primary()
Schema::create('customers')                     // Use 'acct.customers'
Missing RLS policies                            // Always add RLS
Adding columns not in contract                  // Update contract first

// ✅ CORRECT
$table->uuid('id')->primary();
Schema::create('acct.customers', ...);
DB::statement('ALTER TABLE ... ENABLE ROW LEVEL SECURITY');
// Read schema contract first
```

### 4.2 Backend Mistakes

```php
// ❌ WRONG
session('active_company_id')                    // Use CurrentCompany singleton
$user->currentCompany()                         // Use app(CurrentCompany::class)->get()
Route::get('/customers', ...)                   // Use '/{company}/customers'
Missing identify.company middleware             // Always add to tenant routes
new Service()                                   // Use Bus::dispatch()
$request->validate([...])                       // Use FormRequest
Customer::find($id) in controller               // Move to service/action

// ✅ CORRECT
$company = app(CurrentCompany::class)->get();
Route::get('/{company}/customers', ...)
    ->middleware(['auth', 'identify.company']);
$result = Bus::dispatch('action.name', $data);
// Use dedicated FormRequest classes
```

### 4.3 Model Mistakes

```php
// ❌ WRONG
protected $table = 'customers';                 // Use 'acct.customers'
// Missing $keyType = 'string'                  // Required for UUID
// Missing $incrementing = false                // Required for UUID
Guessing $fillable                             // Copy from schema contract
Copying old code with currentCompany()          // Use CurrentCompany singleton

// ✅ CORRECT
protected $table = 'acct.customers';
protected $keyType = 'string';
public $incrementing = false;
// $fillable from schema contract
// Use app(CurrentCompany::class)->get()
```

### 4.4 Frontend Mistakes

```vue
<!-- ❌ WRONG -->
<input v-model="x">                             <!-- Use <Input /> -->
<button @click="...">                           <!-- Use <Button /> -->
export default { data() }                       <!-- Use <script setup> -->
fetch('/api/...')                               <!-- Use Inertia forms -->
isAccountantMode ? 'Revenue' : 'Money In'       <!-- Use useLexicon() -->

<!-- ✅ CORRECT -->
<Input v-model="x" />
<Button @click="...">
<script setup lang="ts">
const form = useForm({...})
const { t } = useLexicon()  // t('moneyIn')
```

---

## 5. Development Workflow

### 5.1 Before You Start

1. **Read the schema contract**: `docs/contracts/{schema}-schema.md`
2. **Check constitutional rules** (this document)
3. **Use template skeletons** from Section 3

### 5.2 Adding a Feature

```
1. Check schema contract
2. Create migration (if needed)
3. Update/create model
4. Create FormRequest
5. Create Action (if business logic needed)
6. Create Controller method
7. Add routes
8. Create Vue pages
9. Add permissions (if needed)
10. Run validation checklist
```

### 5.3 Adding Permissions

```bash
# 1. Add to app/Constants/Permissions.php
# 2. Sync permissions
php artisan rbac:sync-permissions
# 3. Update config/role-permissions.php
# 4. Sync role permissions
php artisan rbac:sync-role-permissions
```

---

## 6. Validation Checklist

Before marking work complete, verify:

### 6.1 Pre-Commit Checks

```bash
composer quality-check              # Run all quality checks
php artisan layout:validate --json  # Validate frontend
bash validate-migration.sh          # Validate migrations
```

### 6.2 Code Review Checklist

- [ ] Schema contract exists and is current
- [ ] `$fillable`/`$casts` match contract
- [ ] Routes have `/{company}` + `identify.company` middleware
- [ ] FormRequest uses `hasCompanyPermission()`
- [ ] UUID primary keys (`$table->uuid('id')->primary()`)
- [ ] RLS policies on tenant tables
- [ ] Business logic in Actions, not controllers
- [ ] Shadcn-Vue components (no raw HTML inputs)
- [ ] `<script setup lang="ts">` (no Options API)
- [ ] Error handling with toast notifications

### 6.3 Error Handling Requirements

Every user-facing action must handle:

1. **Success path**: Response handled, user sees feedback (toast), UI updates
2. **Error path**: Validation errors shown inline, server errors shown as toast
3. **Loading state**: Button disabled, spinner if >300ms expected

```php
// Backend - Always return proper responses
return redirect()->with('success', 'Created successfully');
// or
return back()->with('success', 'Updated successfully');
// Never return raw JSON from Inertia routes
```

---

## Related Documentation

- [01-ARCHITECTURE.md](01-ARCHITECTURE.md) - System overview
- [03-RBAC-GUIDE.md](03-RBAC-GUIDE.md) - Permissions system
- [04-FRONTEND-GUIDE.md](04-FRONTEND-GUIDE.md) - UI development
- [05-DATABASE-GUIDE.md](05-DATABASE-GUIDE.md) - Database patterns
- `docs/contracts/00-master-index.md` - Schema contracts
