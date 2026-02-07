# Haasib Request Flow: Frontend → Backend → Database

**Understanding how a request travels through the entire system.**

---

## Quick Navigation

1. **Complete request lifecycle** → [Full Request Lifecycle](#full-request-lifecycle)
2. **Middleware chain** → [Middleware Pipeline](#middleware-pipeline)
3. **Company context setup** → [Company Context](#company-context)
4. **Permission checking** → [Authorization Flow](#authorization-flow)
5. **Database queries** → [Database Layer](#database-layer)
6. **Response rendering** → [Response Rendering](#response-rendering)
7. **Example walkthrough** → [Example: Create Invoice](#example-create-invoice)

---

## Full Request Lifecycle

### Step-by-Step Overview

```
1. FRONTEND
   User Action: Click "Create Invoice" button
        ↓
   useForm() hook: Gathers form data
        ↓
   POST /company-abc/invoices
        ↓

2. NETWORK
   Browser: Send HTTP POST request
   Headers: Authorization, Cookie, Content-Type
   Body: JSON form data
        ↓

3. BACKEND - ENTRY
   Laravel: Route matching
   Pattern: /{company}/invoices → InvoiceController@store
        ↓

4. MIDDLEWARE PIPELINE (executes in order)
   a. authenticate (Fortify) → user logged in?
   b. identify.company → extract company, validate access
   c. HandleInertiaRequests → prepare Inertia props
   d. CheckFirstTimeUser → first-time flow?
        ↓
   Result: Request enriched with user, company, middleware-set properties
        ↓

5. REQUEST VALIDATION
   FormRequest (StoreInvoiceRequest)
   a. authorize() → user has invoices.create permission?
   b. rules() → validate input data
   c. Errors? → Return 422 with validation errors
        ↓

6. BUSINESS LOGIC
   Controller: infer from request, dispatch Action
        ↓
   Action (CreateInvoice)
   a. Get company context: app(CurrentCompany::class)->get()
   b. Validate business rules
   c. Create invoice model
   d. Dispatch related operations (events, notifications, etc.)
   e. Return created invoice
        ↓

7. DATABASE OPERATIONS
   Eloquent ORM → SQL queries
   a. INSERT invoice into acct.invoices
   b. INSERT line items into acct.invoice_line_items
   c. UPDATE totals
   d. All within company context (RLS policy enforcement)
        ↓

8. BACKEND - RESPONSE
   a. Success: return created invoice
   b. Validation error: return 422 + errors
   c. Authorization error: return 403
   d. Server error: return 500
        ↓

9. FRONTEND - PROCESSING
   Inertia response received
   a. Validate response status
   b. Handle errors: show toast notification
   c. Success: redirect to invoices list
        ↓

10. USER FEEDBACK
    Page updates, confirms success to user
```

---

## Request Entry Points

### URL Request

```
GET/POST/PUT/DELETE {protocol}://{host}{:port}/{path}?{query}

Example 1: Create Invoice Form
  GET /company-abc123/invoices/create
  Headers: Authorization: Bearer {token}, Cookie: ...
  Response: HTML/Inertia → Invoices/Create page with component

Example 2: Submit Invoice
  POST /company-abc123/invoices
  Headers: Authorization: Bearer {token}, Content-Type: application/json
  Body: { customer_id, invoice_date, due_date, line_items: [...] }
  Response: { id, customer_id, status: 'draft', total: ... }

Example 3: Update Invoice
  PUT /company-abc123/invoices/{invoiceId}
  Headers: Authorization: Bearer {token}, Content-Type: application/json
  Body: { ... modified fields ... }
  Response: { ... updated invoice ... }
```

### Route Resolution

**Location:** `build/routes/web.php`

```php
// Simplified example
Route::middleware(['auth', 'identify.company'])->prefix('{company}')->group(function () {
    Route::get('/invoices', [InvoiceController::class, 'index']);        // List
    Route::get('/invoices/create', [InvoiceController::class, 'create']); // Form
    Route::post('/invoices', [InvoiceController::class, 'store']);        // Submit
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit']);
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);
});
```

---

## Middleware Pipeline

### Middleware Execution Order

Middleware defined in `build/bootstrap/app.php` and applied via route groups.

```
Request arrives
    ↓
┌─ GLOBAL MIDDLEWARE (always runs)
│  ├─ ... (session, CSRF, etc.)
│  └─ (configured in bootstrap/app.php)
├─
├─ ROUTE MIDDLEWARE (depends on route group)
│  ├─ 'auth' → Ensure user is authenticated
│  ├─ 'identify.company' → Extract company from URL, validate access
│  ├─ 'HandleInertiaRequests' → Prepare Inertia props
│  └─ 'CheckFirstTimeUser' → Check if first-time user
├─
└─ Controller method executes
```

### 1. Authentication Middleware

**Middleware:** `authenticat(Fortify)`

**Purpose:** Verify user is logged in

**Logic:**
```php
// Pseudo-code
if (!Auth::check()) {
    return redirect('/login');
}
```

**Sets:** `Auth::user()` available in controller

**Throws:** `AuthenticationException` if not authenticated

### 2. Identify Company Middleware

**Middleware:** `build/app/Http/Middleware/IdentifyCompany.php`

**Purpose:** Extract company from route parameter, validate user can access

**Flow:**
```php
// 1. Get company ID from route parameter
$companyId = request()->route('company');

// 2. Find company
$company = Company::findOrFail($companyId);

// 3. Verify user has access
$hasAccess = auth()->user()
    ->companies()
    ->where('company_id', $company->id)
    ->exists();

if (!$hasAccess) {
    abort(403); // Forbidden
}

// 4. Set company in singleton
app(CurrentCompany::class)->set($company);

// 5. Set PostgreSQL session variable for RLS
DB::statement('SET app.current_company_id = ?', [$company->id]);
```

**Side Effects:**
- Sets `CurrentCompany` singleton
- Sets PostgreSQL `app.current_company_id` session variable
- All subsequent queries automatically filtered by company (RLS)

**Throws:** `ModelNotFoundException` if company not found, `ForbiddenHttpException` if user lacks access

### 3. Handle Inertia Requests Middleware

**Middleware:** `build/app/Http/Middleware/HandleInertiaRequests.php`

**Purpose:** Inject shared props into every Inertia response

**Props Injected:**
```php
[
    'user' => [
        'id' => auth()->user()->id,
        'name' => auth()->user()->name,
        'email' => auth()->user()->email,
        'roles' => auth()->user()->roles->pluck('name'),
        'current_company_id' => app(CurrentCompany::class)->get()->id,
    ],
    'company' => [
        'id' => app(CurrentCompany::class)->get()->id,
        'name' => app(CurrentCompany::class)->get()->name,
        // ... other company data
    ],
    'auth' => [...],
    'errors' => [...],  // Validation errors from previous request
]
```

**Available in All Vue Components:**
```vue
<script setup>
const props = defineProps<{
  user: User
  company: Company
}>();

// Props automatically available
console.log(props.user.name); // From Inertia middleware
</script>
```

### 4. Check First Time User Middleware

**Purpose:** Detect first-time users, redirect to onboarding

**Logic:**
```php
if (auth()->user()->first_time_user && !in_array(route(), ['company.onboarding.index'])) {
    return redirect(route('company.onboarding.index'));
}
```

---

## Company Context

### What is Company Context?

Company context = "which company is this request for?"

It's established at **3 levels**:

### Level 1: URL Parameter

```
GET /company-abc123/invoices
                ↑
          Company ID in URL
```

### Level 2: Middleware Sets Singleton

```php
// In IdentifyCompany middleware
app(CurrentCompany::class)->set($company);
```

**Later in controller, retrieve it:**
```php
$company = app(CurrentCompany::class)->get(); // Returns Company model
```

### Level 3: Database Session Variable

```php
// In IdentifyCompany middleware, after setting singleton
DB::statement('SET app.current_company_id = ?', [$company->id]);
```

**PostgreSQL Row-Level Security Policy:**
```sql
CREATE POLICY invoice_company_isolation ON acct.invoices
  USING (company_id = current_setting('app.current_company_id')::uuid);
```

**Result:** All queries to `acct.invoices` automatically filtered to current company

### How to Access Company Context

#### In Controller
```php
namespace App\Http\Controllers;

use App\Services\CurrentCompany;

class InvoiceController extends Controller
{
    public function index()
    {
        $company = app(CurrentCompany::class)->get();

        // This query automatically scoped to company by RLS
        $invoices = $company->invoices()->where('status', '!=', 'void')->paginate();

        return inertia('Invoices/Index', ['invoices' => $invoices]);
    }
}
```

#### In Model Scope
```php
namespace App\Models;

class Invoice extends Model
{
    protected $schema = 'acct';

    public function scopeForCurrentCompany($query)
    {
        return $query->where(
            'company_id',
            app(CurrentCompany::class)->get()->id
        );
    }
}

// Usage
Invoice::forCurrentCompany()->where('status', 'draft')->get();
```

#### In Vue Component
```vue
<script setup>
const props = defineProps<{
  company: { id: string; name: string }
}>();

// Use company from props
console.log(props.company.id); // Current company UUID
</script>
```

---

## Authorization Flow

### 1. User Has Permission?

**Check:** Does the user have the required permission for this company?

**Where Checked:** In `FormRequest::authorize()`

```php
namespace App\Http\Requests;

use App\Constants\Permissions;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if user has permission for THIS company
        return $this->user()->hasCompanyPermission(
            Permissions::INVOICES_CREATE,
            app(CurrentCompany::class)->get()
        );
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|uuid|exists:acct.customers,id',
            'invoice_date' => 'required|date',
            // ...
        ];
    }
}
```

**Result:**
- ✅ Permission granted → Request continues to controller
- ❌ Permission denied → Return `403 Forbidden`

### 2. User Has Company Access?

**Check:** Does the user belong to this company?

**Where Checked:** In `IdentifyCompany` middleware

```php
$hasAccess = auth()->user()
    ->companies()
    ->where('company_id', $companyId)
    ->exists();

if (!$hasAccess) {
    abort(403); // Forbidden
}
```

### 3. Permission Database Structure

**Location:** `build/app/Constants/Permissions.php`

```php
class Permissions
{
    const INVOICES_VIEW = 'invoices.view';
    const INVOICES_CREATE = 'invoices.create';
    const INVOICES_APPROVE = 'invoices.approve';
    const INVOICES_VOID = 'invoices.void';
    // ... 200+ more
}
```

**Database tables:**
- `auth.permissions` - Permission records
- `auth.roles` - Role records
- `auth.role_has_permissions` - Mapping (role → permissions)
- `auth.model_has_roles` - Mapping (user → roles, scoped by company via team_id)

### 4. Permission Checking in Frontend

**Available Helper:** `can()` function in Vue

```vue
<template>
  <!-- Only show if user has permission -->
  <button v-if="can('invoices.approve')" @click="approveInvoice">
    Approve
  </button>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'

const page = usePage();

function can(permission: string): boolean {
  return page.props.user.permissions.includes(permission);
}
</script>
```

---

## Validation Flow

### Form Request Validation

**Location:** `build/app/Http/Requests/StoreInvoiceRequest.php`

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    // Step 1: Authorization
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission(
            Permissions::INVOICES_CREATE
        );
    }

    // Step 2: Validation Rules
    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'uuid',
                'exists:acct.customers,id',
                function ($attribute, $value, $fail) {
                    // Custom validation: Customer belongs to current company
                    $company = app(CurrentCompany::class)->get();
                    if (!$company->customers()->where('id', $value)->exists()) {
                        $fail('Invalid customer for this company.');
                    }
                },
            ],
            'invoice_date' => 'required|date|before_or_equal:today',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'line_items' => 'required|array|min:1',
            'line_items.*.item_id' => 'required|uuid|exists:inv.items,id',
            'line_items.*.quantity' => 'required|numeric|min:0.01',
            'line_items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    // Optional: Custom messages
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Please select a customer',
            'line_items.min' => 'Invoice must have at least one line item',
        ];
    }
}
```

**Validation Execution:**
```
Controller calls FormRequest
    ↓
Laravel calls authorize()
    ├─ ✅ Authorized → continue
    └─ ❌ Not authorized → abort(403)
    ↓
Laravel calls rules()
    ├─ ✅ All rules pass → inject validated data into controller
    └─ ❌ Rule fails → return 422 + errors back to frontend
    ↓
Controller receives validated data
```

---

## Database Layer

### Query Execution Flow

```
1. Controller builds query
   $invoices = $company->invoices()->where('status', 'draft');

2. Eloquent generates SQL
   SELECT * FROM acct.invoices WHERE company_id = ? AND status = ?

3. PostgreSQL RLS policy applied BEFORE query
   Policy: invoice_company_isolation
   SELECT * FROM acct.invoices WHERE company_id = ? AND status = ?
     AND company_id = current_setting('app.current_company_id')::uuid

4. Query executes
   Returns only rows matching BOTH where clauses

5. Results returned to application
```

### Example: Create Invoice with Line Items

```php
// Controller
public function store(StoreInvoiceRequest $request)
{
    $company = app(CurrentCompany::class)->get();

    // Line 1: Create invoice
    $invoice = Bus::dispatch(new CreateInvoice($request->validated()));

    // Returns: Invoice model with id, company_id, status, total

    return inertia('Invoices/Show', ['invoice' => $invoice]);
}
```

```php
// Action (CreateInvoice)
public function handle(): Invoice
{
    $company = app(CurrentCompany::class)->get();

    // INSERT into acct.invoices
    $invoice = $company->invoices()->create([
        'customer_id' => $this->data['customer_id'],
        'invoice_date' => $this->data['invoice_date'],
        'status' => 'draft',
        'total' => 0,
    ]);

    // INSERT into acct.invoice_line_items (multiple)
    foreach ($this->data['line_items'] as $item) {
        $lineItem = $invoice->lineItems()->create([
            'item_id' => $item['item_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'amount' => $item['quantity'] * $item['unit_price'],
        ]);
    }

    // UPDATE acct.invoices (set total)
    $invoice->update([
        'total' => $invoice->lineItems()->sum('amount'),
    ]);

    return $invoice->refresh();
}
```

**SQL Generated:**
```sql
-- RLS session variable already set
SET app.current_company_id = 'abc123-uuid';

-- 1. Insert invoice
INSERT INTO acct.invoices (id, company_id, customer_id, invoice_date, status, total, created_at, updated_at)
VALUES ('inv-uuid', 'abc123-uuid', 'cust-uuid', '2025-01-21', 'draft', 0, now(), now());

-- 2. Insert line items (multiple)
INSERT INTO acct.invoice_line_items (id, invoice_id, item_id, quantity, unit_price, amount, created_at, updated_at)
VALUES ('line-uuid', 'inv-uuid', 'item-uuid', 10, 99.99, 999.90, now(), now());

-- 3. Update invoice total
UPDATE acct.invoices SET total = 999.90, updated_at = now()
WHERE id = 'inv-uuid' AND company_id = 'abc123-uuid';
```

---

## Response Rendering

### Inertia Response

**From Controller:**
```php
public function index()
{
    $company = app(CurrentCompany::class)->get();
    $invoices = $company->invoices()->paginate();

    return inertia('Invoices/Index', [
        'invoices' => $invoices,
        'filters' => request()->query('filters'),
    ]);
}
```

**What Inertia Does:**
```
1. Determines response format:
   - Request wants HTML? → Return HTML with inline props
   - Request wants JSON? (SPA) → Return JSON props

2. Merges props:
   - Explicit props (from controller): invoices, filters
   - Shared props (from middleware): user, company, auth
   - Middleware props: flash messages, errors

3. Renders Vue component:
   - Component: build/resources/js/pages/Invoices/Index.vue
   - Passes merged props
```

### Vue Component Receives Props

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { InvoiceTable } from '@/components'

defineProps<{
  invoices: Invoice[]
  filters: Record<string, string>
  user: { id: string; name: string }
  company: { id: string; name: string }
}>()

function can(permission: string): boolean {
  // Check if user has permission
  return canUserDo(permission);
}
</script>

<template>
  <Head title="Invoices" />

  <div class="p-4">
    <h1>{{ company.name }} - Invoices</h1>

    <button
      v-if="can('invoices.create')"
      @click="createInvoice"
      class="btn btn-primary"
    >
      Create Invoice
    </button>

    <InvoiceTable :invoices="invoices" />
  </div>
</template>
```

---

## Example: Create Invoice

### Complete Flow Walkthrough

**User Action:** Click "Create Invoice" button on Invoices list page

### Step 1: Frontend - Form Display

```vue
<!-- pages/Invoices/Create.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  customer_id: '',
  invoice_date: new Date().toISOString().split('T')[0],
  due_date: '',
  line_items: [{ item_id: '', quantity: 1, unit_price: 0 }],
})

function submit() {
  form.post(`/company-abc123/invoices`, {
    onSuccess: () => router.visit(`/company-abc123/invoices`),
    onError: (errors) => console.log(errors),
  })
}
</script>

<template>
  <form @submit.prevent="submit">
    <input v-model="form.customer_id" type="hidden" name="customer_id" />
    <input v-model="form.invoice_date" type="date" name="invoice_date" />
    <input v-model="form.due_date" type="date" name="due_date" />
    <button type="submit">Create Invoice</button>
  </form>
</template>
```

### Step 2: Frontend - Submit Request

```javascript
// Inertia form.post() generates:
POST /company-abc123/invoices HTTP/1.1
Content-Type: application/json
Authorization: Bearer {token}

{
  "customer_id": "cust-123",
  "invoice_date": "2025-01-21",
  "due_date": "2025-02-21",
  "line_items": [
    { "item_id": "item-456", "quantity": 2, "unit_price": 99.99 }
  ]
}
```

### Step 3: Backend - Route Matching

```
Route: POST /{company}/invoices
Matches: company=abc123
Calls: InvoiceController@store
```

### Step 4: Backend - Middleware Pipeline

```
1. authenticate
   - Check Authorization header
   - Load user from token
   - Auth::user() now available

2. identify.company
   - Extract company=abc123 from URL
   - Verify auth()->user() can access company
   - app(CurrentCompany::class)->set($company)
   - DB::statement('SET app.current_company_id = abc123')

3. HandleInertiaRequests
   - Prepare Inertia props
   - Include user, company, etc.

4. CheckFirstTimeUser
   - Not applicable here
```

### Step 5: Backend - FormRequest Validation

```php
class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check: does user have invoices.create permission?
        return auth()->user()->hasCompanyPermission(
            Permissions::INVOICES_CREATE,
            app(CurrentCompany::class)->get()
        );
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|uuid|exists:acct.customers,id',
            // ... more rules
        ];
    }
}

// Laravel validates:
// 1. authorize() returns true?
// 2. All rules pass?
// If both yes → continues to controller
// If no → returns 422 + validation errors
```

### Step 6: Backend - Business Logic

```php
class InvoiceController
{
    public function store(StoreInvoiceRequest $request)
    {
        // Form already validated & authorized

        $invoice = Bus::dispatch(
            new CreateInvoice($request->validated())
        );

        return inertia('Invoices/Show', [
            'invoice' => $invoice,
            'message' => 'Invoice created successfully'
        ]);
    }
}

// CreateInvoice action
class CreateInvoice
{
    public function handle(): Invoice
    {
        $company = app(CurrentCompany::class)->get();

        $invoice = $company->invoices()->create([
            'customer_id' => $this->data['customer_id'],
            'invoice_date' => $this->data['invoice_date'],
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

### Step 7: Backend - Database Operations

```sql
-- PostgreSQL receives queries
SET app.current_company_id = 'abc123-uuid';

-- 1. Insert invoice
INSERT INTO acct.invoices (id, company_id, customer_id, invoice_date, due_date, status, total)
VALUES ('inv-new-uuid', 'abc123-uuid', 'cust-123-uuid', '2025-01-21', '2025-02-21', 'draft', 0);

-- 2. Insert line item
INSERT INTO acct.invoice_line_items (id, invoice_id, item_id, quantity, unit_price, amount)
VALUES ('line-uuid', 'inv-new-uuid', 'item-456-uuid', 2, 99.99, 199.98);

-- 3. Update invoice total
UPDATE acct.invoices SET total = 199.98
WHERE id = 'inv-new-uuid' AND company_id = 'abc123-uuid';

-- Result: Success ✅
```

### Step 8: Backend - Response

```
HTTP/1.1 200 OK
Content-Type: application/json

{
  "component": "Invoices/Show",
  "props": {
    "invoice": {
      "id": "inv-new-uuid",
      "company_id": "abc123-uuid",
      "customer_id": "cust-123-uuid",
      "invoice_date": "2025-01-21",
      "status": "draft",
      "total": 199.98,
      "line_items": [...]
    },
    "message": "Invoice created successfully",
    "user": { "id": "...", "name": "...", ... },
    "company": { "id": "...", "name": "...", ... }
  },
  "url": "/company-abc123/invoices/inv-new-uuid"
}
```

### Step 9: Frontend - Receive & Process

```javascript
// Inertia receives response
// 1. Validates HTTP status (200 = success)
// 2. Extracts component name: "Invoices/Show"
// 3. Extracts props
// 4. Calls onSuccess callback
// 5. Redirects to new URL
```

### Step 10: Frontend - Display

```vue
<!-- Route redirects to: /company-abc123/invoices/inv-new-uuid -->

<!-- Inertia loads Invoices/Show.vue component -->
<!-- Passes props: invoice, message, user, company -->

<!-- Component renders with new invoice data -->
<div>
  <h1>Invoice #inv-new-uuid</h1>
  <p>{{ message }}</p>
  <table>
    <tr><td>Customer</td><td>{{ invoice.customer.name }}</td></tr>
    <tr><td>Date</td><td>{{ invoice.invoice_date }}</td></tr>
    <tr><td>Total</td><td>{{ invoice.total }}</td></tr>
  </table>
</div>

✅ User sees success message and invoice details
```

---

## Error Handling

### Validation Errors

**Request:** Form validation fails

**Response:** HTTP 422 with errors
```json
{
  "errors": {
    "customer_id": ["The customer_id field is required."],
    "invoice_date": ["The invoice_date must be a valid date."]
  }
}
```

**Frontend:** Displays inline error messages on form

### Authorization Errors

**Request:** User lacks required permission

**Response:** HTTP 403 Forbidden
```
You are not authorized to perform this action.
```

**Frontend:** Shows error toast notification

### Company Access Errors

**Request:** User doesn't belong to company

**Response:** HTTP 403 Forbidden (from identify.company middleware)

**Frontend:** Redirects to /companies

### Database Errors

**Request:** Database constraint violation (e.g., invalid customer_id)

**Response:** HTTP 500 or 422 depending on error type

**Frontend:** Shows error toast

---

## Related Documentation

- **System Architecture:** `ARCHITECTURE.md`
- **Entry Points:** `ARCHITECTURE-ENTRY-POINTS.md`
- **Permissions:** `ARCHITECTURE-PERMISSIONS.md`
- **Database Schemas:** `docs/contracts/00-master-index.md`

---

*Last updated: 2025-01-21*
