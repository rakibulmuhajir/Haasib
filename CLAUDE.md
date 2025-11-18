# Haasib Development Instructions & Reference Guide

**Last Updated**: 2025-11-13
**Auto-generated**: Comprehensive instruction dump for AI developers

---

## 🏛️ CONSTITUTIONAL REQUIREMENTS (MANDATORY)

### Core Architecture Principles
You MUST follow these constitutional requirements from `.specify/memory/constitution.md`:

1. **Multi-Schema Domain Separation**
   - Core tables: `public` (system), `auth` (identity)
   - Module-specific schemas: `acct` (accounting), `hsp` (hospitality), `crm` (customer management)
   - RLS (Row Level Security) policies on ALL tenant tables
   - Company-based tenant isolation with `company_id` scoping

2. **Security-First Bookkeeping**
   - All financial mutations require RLS, `company_id` scoping, and audit coverage
   - Positive amount checks and FK constraints in migrations
   - Use `current_setting('app.current_company_id')` for RLS policies

3. **Test & Review Discipline**
   - Database migrations and command-bus handlers ship with regression coverage
   - PRs must describe backward-compatibility impacts
   - Document RLS or audit updates for QA

4. **Observability & Traceability**
   - Use `audit_log()` helper for financial and security events
   - Surface key metrics via monitoring playbooks in `docs/monitoring/`

### Implementation Patterns
- **UUID Primary Keys Only**: Never use integer IDs
- **Command Bus Pattern**: All write operations via `Bus::dispatch()`
- **ServiceContext**: Always inject user/company context explicitly
- **FormRequest Validation**: Never inject `Request` directly in controllers
- **PrimeVue First**: Use PrimeVue v4 components exclusively in frontend
- **Composition API**: All Vue components use `<script setup>` with TypeScript

### Forbidden Patterns (DO NOT USE)
- Direct service calls in controllers (`new Service()`)
- Direct model access in controllers
- Integer primary keys
- Tables in `public` schema (except system tables)
- Missing RLS policies on tenant module schemas (`acct`, `hsp`, `crm`, etc.)
- Non-PrimeVue UI components
- Vue Options API (use Composition API)
- Manual validation in controllers

---

## 📋 TASK-SPECIFIC INSTRUCTION DUMPS

### 🗄️ DATABASE MIGRATIONS

**When Creating New Tables:**
- **Reference File**: `AI_PROMPTS/DATABASE_SCHEMA_REMEDIATION.md`
- **Sample Code**: Migration template with UUID, RLS, module schema compliance
- **Key Requirements**: UUID primary keys, company_id, RLS policies, cross-schema FKs
- **Forbidden**: Integer IDs, public schema (except system tables), missing RLS on module schemas

**When Modifying Existing Tables:**
- **Reference File**: `docs/dosdonts/migrations-best-practices.md`
- **Check**: RLS policy updates, audit triggers, constraint changes
- **Validation**: Run `php artisan migrate:fresh --dry-run`

### 🏛️ MODELS

**When Creating New Models:**
- **Reference File**: `AI_PROMPTS/MODEL_REMEDIATION.md`
- **Sample Code**: Complete model with traits, relationships, business logic
- **Required**: `HasUuids`, `BelongsToCompany`, `SoftDeletes`, proper casts
- **Structure**: Properties table, relationships with return types, business methods

**When Updating Models:**
- **Reference File**: `docs/dosdonts/models-best-practices.md`
- **Check**: Trait consistency, fillable arrays, relationship definitions
- **Validation**: Model factory tests still work

### 🎛️ CONTROLLERS

**When Creating New Controllers:**
- **Reference File**: `AI_PROMPTS/CONTROLLER_PATTERNS.md`
- **Sample Code**: Controller with FormRequest, Command Bus, JSON responses
- **Required**: ServiceContext, Command Bus dispatch, error handling
- **Structure**: Thin controller, FormRequest validation, resource responses

**When Updating Controllers:**
- **Reference File**: `AI_PROMPTS/CONTROLLER_PATTERNS.md`
- **Check**: Command Bus usage, response format, validation patterns
- **Validation**: API endpoints still work with existing tests

### 🛠️ SERVICES & COMMANDS

**When Creating New Services:**
- **Reference File**: `AI_PROMPTS/SERVICE_LAYER_PATTERNS.md`
- **Sample Code**: Service with ServiceContext injection, transaction handling
- **Required**: Constructor injection, explicit context, proper error handling
- **Forbidden**: `auth()`, `request()`, direct model access

**When Creating Command Bus Actions:**
- **Reference File**: `AI_PROMPTS/SERVICE_LAYER_PATTERNS.md`
- **Sample Code**: Command action with proper registration and handling
- **Required**: Container resolve, transaction boundaries, audit logging

### 🎨 FRONTEND COMPONENTS

**When Creating New Vue Components:**
- **Reference File**: `AI_PROMPTS/FRONTEND_COMPONENT_STANDARDS.md`
- **Sample Code**: Component with Composition API, PrimeVue, Inertia.js
- **Required**: `<script setup>`, PrimeVue components, error handling
- **Forbidden**: Options API, HTML elements, fetch() calls

**Mandatory Page Structure:**
Every page MUST use this exact hierarchy:

```vue
<template>
  <div class="page-container">
    <!-- REQUIRED: Sidebar with blu-whale theme -->
    <Sidebar theme="blu-whale" />
    
    <div class="main-content">
      <!-- REQUIRED: PageHeader with actions -->
      <PageHeader :title="pageTitle" :subtitle="pageSubtitle">
        <template #actionsRight>
          <!-- Auto-managed by PageActions component -->
        </template>
      </PageHeader>
      
      <!-- CONDITIONAL: QuickLinks when applicable -->
      <QuickLinks 
        v-if="quickLinks.length"
        :links="quickLinks"
        :title="quickLinksTitle" 
      />
      
      <div class="page-content">
        <!-- Your content here using PrimeVue components ONLY -->
      </div>
    </div>
  </div>
</template>
```

**Component References:**
- **Sidebar**: `@stack/resources/js/Components/Sidebar/`
- **PageHeader**: `@stack/resources/js/Components/PageHeader.vue`  
- **PageActions**: `@stack/resources/js/Components/PageActions.vue`
- **QuickLinks**: `@stack/resources/js/Components/QuickLinks.vue`
- **PrimeVue Docs**: `@docs/vendor/primevue-docs/apps/showcase/doc`

**UI Element Decision Framework:**
Before adding ANY button/element, ask:
1. **Purpose**: What specific user task does this solve?
2. **Duplication**: Does PageActions/QuickLinks/Sidebar already handle this?
3. **Placement**: Why here instead of existing components?
4. **Value**: What happens if we remove it?

**Placement Rules:**
- **Primary actions** → PageHeader actionsRight slot
- **Quick access** → QuickLinks component  
- **Navigation** → Sidebar component
- **Context actions** → Inline with content

**Required PrimeVue Components:**
```vue
<!-- ✅ USE THESE -->
<DataTable /> <Column /> <Button /> 
<InputText /> <Dropdown /> <Calendar />
<Toast /> <Dialog /> <Panel />

<!-- ❌ NEVER USE HTML ELEMENTS -->
<table> <input> <button> <select>
```

**When Creating Forms:**
- **Reference File**: `AI_PROMPTS/FORM_VALIDATION_PATTERNS.md`
- **Sample Code**: Form with Inertia.js form helper, validation, toast notifications
- **Required**: PrimeVue form components, loading states, error feedback

### 🖊️ INLINE EDITING SYSTEM

**When Implementing Inline Editing:**
- **Reference File**: `stack/docs/inline-editing-system.md`
- **Universal Service**: Use `UniversalFieldSaver` for ALL inline edits
- **Controller**: Route through `InlineEditController` only
- **Component**: Use `InlineEditable.vue` component

**Field Editability Rules (Apply to ALL models):**

#### ✅ ALWAYS INLINE EDITABLE
```php
// Simple, independent fields
'name', 'email', 'phone', 'description', 'notes'

// Status toggles  
'is_active', 'is_featured', 'is_published'

// Simple selections
'currency', 'language', 'timezone', 'priority'

// Dates (single field)
'due_date', 'start_date', 'issue_date'
```

#### ❌ NEVER INLINE EDITABLE  
```php
// Calculated/computed fields
'total_amount', 'balance_due', 'outstanding_amount'

// Complex relationships
'address' (use address form), 'line_items' (use item manager)

// Security-sensitive
'password', 'permissions', 'api_keys'

// Multi-step workflows
'status' (when requires workflow), 'approval_process'
```

#### 🤔 CONDITIONAL INLINE EDITABLE
```php
// Permission-dependent
'credit_limit' => user.hasPermissionTo('customers.manage_credit')

// Status-dependent  
'due_date' => invoice.status === 'draft'

// Relationship-dependent
'discount_rate' => customer.type === 'premium'
```

**Implementation Template:**
```php
// In your model configuration
'customer' => [
    'model' => Customer::class,
    'fields' => [
        'name' => [
            'inline' => true,
            'permission' => 'customers.update',
            'validation' => 'required|string|max:255'
        ],
        'credit_limit' => [
            'inline' => true,
            'permission' => 'customers.manage_credit',
            'validation' => 'numeric|min:0',
            'dependencies' => ['status' => ['!=', 'blocked']]
        ],
        'total_outstanding' => [
            'inline' => false,
            'reason' => 'calculated_field'
        ]
    ]
]
```

**Frontend Usage:**
```vue
<!-- ✅ CORRECT: Use InlineEditable component -->
<InlineEditable
  v-model="customer.name"
  field="name" 
  :model-id="customer.id"
  model-type="customer"
  :permissions="can"
/>

<!-- ❌ FORBIDDEN: Custom inline edit implementation -->
<span @click="editField">{{ customer.name }}</span>
```

**Decision Framework (Ask these questions):**
1. **Is it a single, atomic value?** → Likely inline
2. **Does it affect other fields?** → Probably form
3. **Does user have permission?** → Check permission
4. **Is it calculated/computed?** → Never inline
5. **Does it need complex UI?** → Use form instead

### 🚦 CREATION vs EDITING PATTERNS

**Minimal Creation Strategy:**
All new records start with minimal forms (3-4 fields max):

```php
// Customer creation - MINIMAL ONLY
required_fields: ['name', 'type', 'currency']
optional_fields: [] // Everything else via inline editing later

// Invoice creation - MINIMAL ONLY  
required_fields: ['customer_id', 'issue_date', 'due_date']
optional_fields: [] // Line items, notes, etc. added after creation
```

**Post-Creation Enhancement:**
After creation, use progressive disclosure:
1. **Inline editing** for simple fields
2. **Specialized forms** for complex data (address, line items)
3. **Tabbed interfaces** for related data

**Form vs Inline Decision Matrix:**
```
Creation (new record):     → Minimal form (required fields only)
Single field edit:         → Inline editing
Multiple related fields:   → Targeted form
Complex relationships:     → Full form with validation
Bulk operations:           → Full form with batch processing
```

**Implementation Pattern:**
```vue
<!-- Creation: Minimal form -->
<CustomerCreateForm v-if="isCreating" :minimal="true" />

<!-- Editing: Inline + targeted forms -->
<InlineEditable v-model="customer.name" />
<Button @click="openAddressForm" label="Edit Address" />
```

**GitHub-Style Patterns:**
- Simple fields: name, description, notes → Inline
- Complex data: address, settings → Forms  
- Creation workflows: Always minimal form first
- Bulk operations: Always full forms

### 📋 API ENDPOINTS

**When Creating New API Endpoints:**
- **Reference File**: `AI_PROMPTS/API_ENDPOINT_PATTERNS.md`
- **Sample Code**: API endpoint with proper routing, middleware, validation
- **Required**: Idempotency-Key header, standard JSON response format
- **Validation**: Test with curl and Postman/Newman

### 🔧 CLI COMMANDS

**When Creating New Artisan Commands:**
- **Reference File**: `AI_PROMPTS/CLI_COMMAND_PATTERNS.md`
- **Sample Code**: CLI command with context handling, proper output formatting
- **Required**: ServiceContext handling, CLI-GUI parity, error handling
- **Validation**: Test in both CLI and web contexts

### 🧪 TESTING

**When Creating Tests:**
- **Reference File**: `docs/HAASIB_TESTING_PLAN.md`
- **Sample Code**: Feature tests, unit tests, browser tests
- **Required**: Constitutional compliance, inline editing, RLS testing, critical path testing
- **Coverage**: Minimum 80% for features, 90% for critical paths

**Constitutional Compliance Testing:**
```php
// Test Command Bus usage
test('controller uses command bus for write operations', function () {
    // Verify no direct service calls in controllers
    expect($controller)->toUseCommandBusFor('create', 'update', 'delete');
});

// Test ServiceContext injection
test('service accepts service context', function () {
    // Verify all services accept ServiceContext parameter
    expect($service)->toRequireServiceContext();
});

// Test FormRequest validation
test('controller uses form request validation', function () {
    // Verify no inline validation in controllers
    expect($controller)->toUseFormRequestValidation();
});
```

**Inline Editing Testing:**
```php
// Test field editability rules
test('simple fields are inline editable', function () {
    expect($customer->name)->toBeInlineEditable();
    expect($customer->email)->toBeInlineEditable();
    expect($customer->total_outstanding)->not->toBeInlineEditable(); // calculated field
});

// Test UniversalFieldSaver integration
test('inline edits use universal field saver', function () {
    $response = $this->putJson("/api/inline-edit/customer/{$customer->id}", [
        'field' => 'name',
        'value' => 'New Name'
    ]);
    
    $response->assertOk();
    expect($customer->refresh()->name)->toBe('New Name');
});
```

**UI Consistency Testing:**
```php
// Test mandatory page structure
test('page uses mandatory component structure', function () {
    $this->get('/customers')
         ->assertSee('Sidebar')
         ->assertSee('PageHeader')
         ->assertSee('PageActions');
});

// Test PrimeVue component usage
test('page uses primevue components only', function () {
    $response = $this->get('/customers');
    
    // Should not contain HTML form elements
    expect($response->content())->not->toContain('<table>');
    expect($response->content())->not->toContain('<button>');
    expect($response->content())->not->toContain('<input>');
});
```

### 🔐 SECURITY & RBAC

**When Implementing Security Features:**
- **Reference Files**: 
  - `stack/app/Constants/Permissions.php` - Standardized permission constants
  - `stack/app/Http/Requests/BaseFormRequest.php` - Authorization helpers
  - `stack/database/seeders/PermissionSeeder.php` - Role-based permission setup
  - `docs/briefs/rbac_implementation_brief.md` - Complete RBAC guide

**RBAC Implementation Pattern:**
```php
// In FormRequest classes - COPY THIS PATTERN
class CreateCustomerRequest extends BaseFormRequest 
{
    public function authorize(): bool
    {
        return $this->authorizeCustomerOperation('create');
    }
}

// In Controllers - NEVER inline authorization
class CustomerController extends Controller
{
    public function store(CreateCustomerRequest $request)
    {
        // Authorization already handled in FormRequest
        return Bus::dispatch(new CreateCustomerCommand($request->validated()));
    }
}
```

**Required RBAC Elements:**
- Use `Permissions::ACCT_CUSTOMERS_CREATE` constants (never strings)
- Use `BaseFormRequest::authorize*Operation()` methods
- Validate RLS context with `validateRlsContext()`
- Set company context in `prepareForValidation()`

**Validation Commands:**
```bash
php artisan db:seed --class=PermissionSeeder
php artisan test tests/Feature/RbacTest.php
```

### 📊 REPORTING & ANALYTICS

**When Creating Reports:**
- **Reference File**: `docs/test-plan-comprehensive.md` (reporting section)
- **Sample Code**: Financial reports, dashboards, analytics
- **Required**: Proper data aggregation, tenant isolation, performance
- **Validation**: Report accuracy, load testing, data integrity

### 🔄 DATA IMPORT/EXPORT

**When Creating Import Features:**
- **Reference File**: `docs/test-plan-comprehensive.md` (integration section)
- **Sample Code**: CSV/OFX imports, data validation, batch processing
- **Required**: Idempotency protection, error handling, progress feedback
- **Validation**: Large dataset handling, data integrity tests

### 💾 BACKUPS & MAINTENANCE

**When Creating Backup Systems:**
- **Reference File**: `docs/test-plan-comprehensive.md` (disaster recovery)
- **Sample Code**: Automated backups, point-in-time recovery
- **Required**: Data integrity verification, backup testing
- **Validation**: Restore procedures, backup retention policies

---

## 🎨 STRICT LAYOUT STANDARDS - ZERO DEVIATION

### **MANDATORY Layout Hierarchy (NO EXCEPTIONS)**

Every page MUST follow this exact structure:

```vue
<template>
  <LayoutShell>
    <!-- REQUIRED: Single-row compact header -->
    <UniversalPageHeader
      :title="pageTitle"
      :description="pageDescription"
      :show-search="needsSearch"
      :search-placeholder="searchPlaceholder"
      :default-actions="pageActions"
    />
    
    <!-- REQUIRED: Content grid (5/6 + 1/6) -->
    <div class="content-grid-5-6">
      <!-- REQUIRED: Main content area -->
      <div class="main-content">
        <!-- ONLY PrimeVue DataTable allowed -->
        <DataTable>
          <Column />
        </DataTable>
      </div>
      
      <!-- REQUIRED: Sidebar for quick actions -->
      <div class="sidebar-content">
        <QuickLinks :links="quickLinks" />
      </div>
    </div>
  </LayoutShell>
</template>
```

❌ **FORBIDDEN PATTERNS:**
- Multiple header rows
- HTML table/form elements
- Custom grid layouts
- Missing LayoutShell/UniversalPageHeader
- Non-PrimeVue UI components

### **Component Placement Rules (STRICT)**

#### 1. **Logo & Branding**
- **Location**: Fixed Topbar (part of LayoutShell)
- **Never**: In page content or headers

#### 2. **Navigation Menu**
- **Location**: Collapsible Sidebar
- **Theme**: `blu-whale` ONLY
- **Never**: In page headers or inline

#### 3. **Page Title + Search + Actions**
- **Location**: Single row in UniversalPageHeader
- **Layout**: `Title (left) | Search (center) | Actions (right)`
- **Never**: Multiple rows or separate sections

#### 4. **Quick Links**
- **Location**: Right sidebar only
- **Component**: QuickLinks component
- **Never**: In main content area

#### 5. **Main Content**
- **Location**: Left content area (5/6 width)
- **Component**: PrimeVue DataTable ONLY
- **Never**: HTML tables or custom layouts

### **Space-Saving Requirements**

#### **Header Compact Design**
```vue
<!-- EXACT template - copy this -->
<UniversalPageHeader
  title="Customers"
  description="Manage customer accounts and relationships"
  :show-search="true"
  search-placeholder="Search customers..."
  :default-actions="customerActions"
/>
```

#### **Grid Layout Standard**
```vue
<!-- EXACT template - copy this -->
<div class="content-grid-5-6">
  <div class="main-content">
    <DataTable
      v-model:selection="selectedItems"
      :value="data"
      :paginator="true"
      dataKey="id"
      selectionMode="multiple"
    >
      <Column selectionMode="multiple" />
      <Column field="name" header="Name" sortable />
    </DataTable>
  </div>
  
  <div class="sidebar-content">
    <QuickLinks
      :links="quickLinks"
      title="Quick Actions"
    />
  </div>
</div>
```

### **Permission Integration (MANDATORY)**

Every page MUST integrate permissions:

```vue
<script setup>
const props = defineProps({
  data: Object,
  can: Object, // REQUIRED: Permission flags from controller
})

// REQUIRED: Define actions with permission checks
const pageActions = [
  {
    key: 'create',
    label: 'Add Customer',
    icon: 'pi pi-plus',
    show: props.can.customers_create, // REQUIRED: Permission check
    action: () => createCustomer()
  }
]

const quickLinks = [
  {
    label: 'Add Customer',
    url: '/customers/create',
    icon: 'fas fa-plus-circle',
    show: props.can.customers_create, // REQUIRED: Permission check
  }
]
</script>
```

### **Controller Data Pattern (MANDATORY)**

Controllers MUST pass permission data:

```php
return Inertia::render('Customers/Index', [
    'customers' => $customers,
    'can' => [
        'customers_create' => $user->hasPermissionTo(Permissions::ACCT_CUSTOMERS_CREATE),
        'customers_update' => $user->hasPermissionTo(Permissions::ACCT_CUSTOMERS_UPDATE),
        'customers_delete' => $user->hasPermissionTo(Permissions::ACCT_CUSTOMERS_DELETE),
    ],
]);
```

### **Visual Standards (EXACT SPECS)**

#### **Typography**
- Page Title: `text-xl font-semibold` (compact)
- Search Input: `text-sm h-9` (space-saving)
- Buttons: `text-sm` (consistent sizing)

#### **Spacing**
- Header: `py-3` (minimal vertical padding)
- Content: `mb-4` (reduced margins)
- Gaps: `gap-2` or `gap-3` (tight spacing)

#### **Colors**
- Sidebar Theme: `blu-whale` (mandatory)
- Borders: `border-gray-200 dark:border-gray-700`
- Text: Standard Tailwind gray scale

### **Responsive Behavior (REQUIRED)**

```vue
<!-- Mobile adaptations -->
<style scoped>
@media (max-width: 768px) {
  .content-grid-5-6 {
    @apply flex-col; /* Stack on mobile */
  }
  
  .sidebar-content {
    @apply order-first; /* QuickLinks on top */
  }
  
  .page-header .flex {
    @apply flex-wrap gap-2; /* Wrap header elements */
  }
}
</style>
```

### **Component Import Standards (EXACT ORDER)**

```vue
<script setup>
// 1. Vue core
import { ref, computed } from 'vue'

// 2. Inertia
import { Link, router } from '@inertiajs/vue3'

// 3. PrimeVue (alphabetical)
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import { useToast } from 'primevue/usetoast'

// 4. App components (alphabetical)
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'

// 5. Composables and utilities
import { usePageActions } from '@/composables/usePageActions'
import { route } from 'ziggy-js'
</script>
```

---

## 🔒 CONTROLLER TEMPLATES - COPY EXACTLY

### Store Method Template
```php
public function execute(ExecuteCommandRequest $request): JsonResponse
{
    /** @var User $user */
    $user = Auth::user();
    $company = $this->getCompanyFromRequest($request);

    $commandName = $request->get('command_name');
    $parameters = $request->get('parameters', []);
    $idempotencyKey = $request->header('Idempotency-Key');

    try {
        $result = $this->executionService->executeCommand(
            $company,
            $user,
            $commandName,
            $parameters,
            $idempotencyKey
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 422);
    }
}
```

❌ FORBIDDEN: try/catch in controllers, custom status codes, success message wrapping
✅ REQUIRED: ServiceContext injection, Command Bus dispatch, JsonResponse returns

### Index Method Template
```php
public function index(Request $request): JsonResponse
{
    /** @var User $user */
    $user = Auth::user();
    $company = $this->getCompanyFromRequest($request);

    $category = $request->query('category');
    $search = $request->query('search');

    $commands = $this->commandRegistry->getAvailableCommands($company)
        ->filter(fn ($command) => $command->userHasPermission($user));

    return response()->json([
        'data' => $commands->values()->map(function ($command) {
            return [
                'id' => $command->id,
                'name' => $command->name,
                'description' => $command->description,
            ];
        }),
        'meta' => [
            'total' => $commands->count(),
        ],
    ]);
}
```

---

## 🔒 VUE COMPONENT TEMPLATES - COPY EXACTLY

### Page Component Template
```vue
<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { usePageActions } from '@/composables/usePageActions'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import { route } from 'ziggy-js'

const props = defineProps({
    customers: Object,
    filters: Object,
    statistics: Object,
    can: Object
})

const toast = useToast()
const { actions } = usePageActions()

// Define page actions
const customerActions = [
    {
        key: 'add-customer',
        label: 'Add Customer',
        icon: 'pi pi-plus',
        severity: 'primary',
        routeName: 'customers.create'
    }
]

// Define quick links
const quickLinks = [
    {
        label: 'Add Customer',
        url: '/customers/create',
        icon: 'fas fa-plus-circle',
        color: 'text-green-600'
    }
]

// Set page actions
actions.value = customerActions

const dt = ref()
const selectedCustomers = ref([])

// Methods
const confirmDelete = (customer) => {
    customerToDelete.value = customer
    deleteCustomerDialog.value = true
}
</script>

<template>
  <LayoutShell>
    <Toast />

    <!-- Universal Page Header -->
    <UniversalPageHeader
      title="Customers"
      description="Manage your customer relationships"
      subDescription="Create, edit, and manage customer accounts"
      :default-actions="customerActions"
      :selected-items="selectedCustomers"
    />

    <!-- Main Content Grid -->
    <div class="content-grid-5-6">
      <!-- Left Column - Main Content -->
      <div class="main-content">
        <DataTable
          ref="dt"
          v-model:selection="selectedCustomers"
          :value="customers.data"
          :paginator="true"
          dataKey="id"
        >
          <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
          <Column field="name" header="Name" sortable>
            <template #body="{ data }">
              <div class="cursor-pointer hover:bg-gray-50 p-2 rounded">
                <div class="font-medium">{{ data.name }}</div>
              </div>
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- Right Column - Quick Links -->
      <div class="sidebar-content">
        <QuickLinks
          :links="quickLinks"
          title="Customer Actions"
        />
      </div>
    </div>
  </LayoutShell>
</template>
```

❌ FORBIDDEN: HTML elements, Options API, fetch() calls, missing mandatory structure
✅ REQUIRED: `<script setup>`, LayoutShell, UniversalPageHeader, PrimeVue components only

---

## 🔒 FORM REQUEST TEMPLATES - COPY EXACTLY

### FormRequest Template
```php
<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class Create{Model}Request extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('{model_permission}') &&
               $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            // Basic information
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[\pL\s\-\',\.&]+$/u',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('{schema_table}', 'email')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId())
                        ->whereNull('deleted_at');
                }),
            ],
            'status' => [
                'required',
                'string',
                Rule::in(['active', 'inactive', 'blocked']),
            ],
            // Add other fields...
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '{Model} name is required',
            'name.min' => '{Model} name must be at least 2 characters',
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email address is already registered',
            'status.required' => 'Status is required',
            'status.in' => 'Status must be one of: active, inactive, or blocked',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
```

❌ FORBIDDEN: direct validation in controllers, missing permission checks, RLS bypass
✅ REQUIRED: extends BaseFormRequest, company permission checks, RLS validation

---

## 🔒 COMMAND ACTION TEMPLATES - COPY EXACTLY

### Command Action Template
```php
<?php

namespace Modules\{Module}\Domain\{Feature}\Actions;

use App\Models\Company;
use App\Models\{Model};
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class Create{Model}Action
{
    public function __construct(
        private {Model}QueryService $queryService
    ) {}

    /**
     * Create a new {model}.
     */
    public function execute(Company $company, array $data, User $createdBy): {Model}
    {
        $this->validateData($company, $data);

        try {
            DB::beginTransaction();

            // Create {model}
            $model = {Model}::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'status' => $data['status'] ?? 'active',
                // Add other fields...
            ]);

            // Emit audit event
            Event::dispatch('{model}.created', [
                '{model}_id' => $model->id,
                'company_id' => $company->id,
                'user_id' => $createdBy->id,
                'name' => $model->name,
                'status' => $model->status,
            ]);

            DB::commit();

            return $model;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new {Model}CreationException('Failed to create {model}: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate {model} creation data.
     */
    private function validateData(Company $company, array $data): void
    {
        // Add validation logic here...
    }
}
```

❌ FORBIDDEN: direct model calls in controllers, missing transactions, no audit events
✅ REQUIRED: DB transactions, audit events, proper exception handling, dependency injection

---

## 🔒 MODEL TEMPLATES - COPY EXACTLY

### Model Template
```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class {Model} extends Model
{
    use BelongsToCompany, HasFactory, HasUuids, SoftDeletes;

    protected $table = '{schema}.{table}';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'status',
        // Add other fields...
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'company_id' => 'string',
        // Add other casts...
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the company that owns the {model}.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created the {model}.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope to only include active {models}.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to search {models} by name or email.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ILIKE', "%{$term}%")
                ->orWhere('email', 'ILIKE', "%{$term}%");
        });
    }
}
```

❌ FORBIDDEN: integer IDs, missing UUID traits, no company scoping, no soft deletes
✅ REQUIRED: HasUuids, BelongsToCompany, SoftDeletes, proper casts, UUID primary keys

---

## 📦 IMPORT ORDER - EXACT SEQUENCE

### PHP Files
```php
<?php

// 1. Framework imports (Illuminate)
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// 2. Application imports (App\)
use App\Http\Controllers\Controller;
use App\Models\{Model};
use App\Services\{Service};

// 3. Module imports (Modules\)
use Modules\{Module}\Domain\{Feature}\Actions\{Action};

// 4. External packages (sorted alphabetically)
use LaravelDaily\Invoices\Invoice;

// 5. Aliases (only if needed)
use function response;
```

### Vue Files
```vue
<script setup>
// 1. Vue core imports
import { ref, computed, onMounted } from 'vue'

// 2. Inertia imports
import { Link, router } from '@inertiajs/vue3'

// 3. PrimeVue imports
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'

// 4. Application imports (@/)
import { usePageActions } from '@/composables/usePageActions'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'

// 5. External libraries
import { route } from 'ziggy-js'
</script>
```

---

## 📤 RESPONSE FORMATS - USE EXACTLY

### Success Response
```php
return response()->json([
    'data' => $resource,
    'meta' => [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
    ],
]);
```

### Created Response
```php
return response()->json([
    'data' => $createdResource,
    'message' => '{Model} created successfully',
], 201);
```

### Error Response
```php
return response()->json([
    'success' => false,
    'message' => 'Failed to {action}',
    'errors' => $validator->errors()->toArray(),
], 422);
```

❌ FORBIDDEN: custom success messages, wrapping data in "success" objects, non-standard status codes
✅ REQUIRED: data/meta structure, 201 for created, 422 for validation errors, 500 for server errors

---

## ❌ ERROR HANDLING - COPY EXACTLY

### Controller Error Handling
```php
try {
    $result = $this->service->execute($data);
    return response()->json(['data' => $result]);
} catch (ValidationException $e) {
    return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors(),
    ], 422);
} catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => 'Operation failed',
        'error' => $e->getMessage(),
    ], 500);
}
```

### Command Action Error Handling
```php
try {
    DB::beginTransaction();
    
    $result = $this->performAction($data);
    
    DB::commit();
    return $result;
} catch (\Exception $e) {
    DB::rollBack();
    throw new {Action}Exception('Failed to {action}: ' . $e->getMessage(), 0, $e);
}
```

---

## 🛤️ ROUTE TEMPLATES - COPY EXACTLY

### Web Routes
```php
Route::prefix('{module}')->name('{module}.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [{Model}Controller::class, 'index'])->name('index');
    Route::get('/create', [{Model}Controller::class, 'create'])->name('create');
    Route::post('/', [{Model}Controller::class, 'store'])->name('store');
    Route::get('/{model}', [{Model}Controller::class, 'show'])->name('show');
    Route::get('/{model}/edit', [{Model}Controller::class, 'edit'])->name('edit');
    Route::put('/{model}', [{Model}Controller::class, 'update'])->name('update');
    Route::delete('/{model}', [{Model}Controller::class, 'destroy'])->name('destroy');
});
```

### API Routes
```php
Route::prefix('api/{module}')->name('api.{module}.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [{Model}ApiController::class, 'index'])->name('index');
    Route::post('/', [{Model}ApiController::class, 'store'])->name('store');
    Route::get('/{model}', [{Model}ApiController::class, 'show'])->name('show');
    Route::put('/{model}', [{Model}ApiController::class, 'update'])->name('update');
    Route::delete('/{model}', [{Model}ApiController::class, 'destroy'])->name('destroy');
    
    // Bulk operations
    Route::post('/bulk', [{Model}ApiController::class, 'bulk'])->name('bulk');
});
```

---

## 📁 CRITICAL REFERENCE FILES

### Constitutional Documents
- **Constitution**: `.specify/memory/constitution.md`
- **Unified Standards**: This `CLAUDE.md` file (single source of truth)

### Archived Documents (Reference Only)
- **Legacy Standards**: `docs/archive/DEVELOPMENT_STANDARDS.md`
- **Legacy Architecture**: `docs/archive/CONSTITUTION_ARCHITECTURE.md`
- **Legacy Controllers**: `docs/archive/controllers-best-practices.md`
- **Legacy Models**: `docs/archive/models-best-practices.md`
- **Legacy Services**: `docs/archive/services-best-practices.md`
- **Legacy Migrations**: `docs/archive/migrations-best-practices.md`
- **Legacy Integration**: `docs/archive/integration-best-practices.md`
- **Legacy CLI**: `docs/archive/cli-best-practices.md`
- **Legacy Command Bus**: `docs/archive/command-bus-best-practices.md`
- **Legacy Views**: `docs/archive/views-best-practices.md`

### Architecture & Patterns
- **Team Memory**: `docs/TEAM_MEMORY.md`
- **Frontend Architecture**: `docs/frontend-architecture.md`
- **Idempotency**: `docs/idempotency.md`
- **Modules Architecture**: `docs/modules-architecture.md`

### Testing & Quality
- **Unified Testing Plan**: `docs/HAASIB_TESTING_PLAN.md`
- **Comprehensive Test Plan v2**: `docs/test-plan-comprehensive-v2.md`
- **Quality Gates**: `QUALITY_GATES_AUTOMATION.md`
- **Implementation Plan**: `IMPLEMENTATION_PLAN.md`
- **Layout Compliance Validator**: `stack/app/Console/Commands/ValidateLayoutCompliance.php`
- **Migration Validation Script**: `stack/validate-migration.sh`

### Theme & UI Standards
- **Blue-Whale Theme CSS**: `stack/resources/js/styles/themes/blue-whale.css`
- **UniversalPageHeader Component**: `stack/resources/js/Components/UniversalPageHeader.vue`
- **RBAC Implementation Guide**: `docs/briefs/rbac_implementation_brief.md`

### RBAC & Security
- **Permission Constants**: `stack/app/Constants/Permissions.php`
- **Base Form Request**: `stack/app/Http/Requests/BaseFormRequest.php`
- **Permission Seeder**: `stack/database/seeders/PermissionSeeder.php`

### AI Development
- **Master Remediation**: `AI_PROMPTS/MASTER_REMEDIATION_PROMPT.md`
- **Database Schema**: `AI_PROMPTS/DATABASE_SCHEMA_REMEDIATION.md`
- **Controller Patterns**: `AI_PROMPTS/CONTROLLER_PATTERNS.md`
- **Service Layer**: `AI_PROMPTS/SERVICE_LAYER_PATTERNS.md`
- **Model Patterns**: `AI_PROMPTS/MODEL_REMEDIATION.md`
- **Frontend Components**: `AI_PROMPTS/FRONTEND_COMPONENT_STANDARDS.md`
- **Form Validation**: `AI_PROMPTS/FORM_VALIDATION_PATTERNS.md`
- **API Endpoints**: `AI_PROMPTS/API_ENDPOINT_PATTERNS.md`
- **CLI Commands**: `AI_PROMPTS/CLI_COMMAND_PATTERNS.md`
- **Quality Validation**: `AI_PROMPTS/QUALITY_VALIDATION_PROMPT.md`

---

## 🚀 PROJECT STRUCTURE

### Working Directories
- **Active Laravel**: `/stack` (main working directory)
- **Feature Specs**: `/specs/` (feature specifications)
- **Documentation**: `/docs/` (project documentation)
- **AI Prompts**: `/AI_PROMPTS/` (AI development prompts)
- **Templates**: `.specify/templates/` (consistency templates)
- **Old Directory**: `/app` (deprecated, use `/stack` instead)

### Technology Stack
- **Backend**: PHP 8.2+, Laravel 12, PostgreSQL 16
- **Frontend**: Vue 3, Inertia.js v2, PrimeVue v4, Tailwind CSS
- **Authentication**: Laravel Sanctum, Spatie Laravel Permission
- **Testing**: PestPHP, Playwright
- **Queue**: Redis + Laravel Horizon

---

## ✅ QUALITY GATES

### Pre-Commit Checks
```bash
cd stack && composer quality-check
./.husky/pre-commit
php artisan test tests/Feature/CriticalPathTest.php
```

### RBAC Validation
```bash
# Test permission seeder
cd stack && php artisan db:seed --class=PermissionSeeder

# Validate permission constants
cd stack && php artisan tinker --execute="App\Constants\Permissions::getAll()"

# Test RBAC integration
cd stack && php artisan test tests/Feature/RbacTest.php
```

### Database Validation
```bash
php artisan migrations:validate
php artisan schema:check-integrity
```

### Frontend Validation
```bash
npm run build
npm run test:e2e
```

### Layout & Theme Validation
```bash
# Layout compliance validation
cd stack && php artisan layout:validate

# Migration readiness validation
cd stack && bash validate-migration.sh

# JSON output for automation
cd stack && php artisan layout:validate --json

# Strict mode validation
cd stack && php artisan layout:validate --strict
```

---

## 🎯 TASK EXECUTION CHECKLIST

### For Any Development Task:
1. **Check Constitutional Requirements** - Verify compliance
2. **Reference Relevant Files** - Use sample code patterns
3. **Follow Implementation Patterns** - Apply established approaches
4. **Run Quality Gates** - Validate before commit
5. **Test Integration** - Ensure no regressions
6. **Update Documentation** - Keep references current

### For Problem Solving:
1. **Read Dos & Don'ts** - Check for specific anti-patterns
2. **Consult Team Memory** - Reference established patterns
3. **Review AI Prompts** - Use remediation guides
4. **Run Validation Scripts** - Automated compliance checks

---

## 🚫 ABSOLUTE PROHIBITIONS

**NEVER DO THESE (Will Break System Consistency):**

### Controllers
- ❌ Direct service injection in constructor (`new Service()`)
- ❌ Inline validation (`$request->validate([])`) 
- ❌ Direct model access (`Model::create()`)
- ❌ Missing ServiceContext injection
- ❌ Bypassing Command Bus for write operations

### Frontend
- ❌ HTML elements instead of PrimeVue components
- ❌ Custom inline edit implementations
- ❌ Missing Sidebar, PageHeader, or component structure
- ❌ Vue Options API (use Composition API only)
- ❌ Non-blu-whale theme usage

### Database
- ❌ Integer primary keys (UUID only)
- ❌ Tables in public schema (use module schemas)
- ❌ Missing RLS policies on tenant tables
- ❌ Missing company_id columns

### Patterns
- ❌ Bypassing inline editing rules
- ❌ Full forms for single field edits
- ❌ Complex creation forms (minimal only)
- ❌ Direct `auth()`, `request()` calls in services

---

## ✅ PRE-IMPLEMENTATION CHECKLIST

**Before writing ANY code, verify:**

### 📋 Planning Phase
- [ ] Task matches constitutional requirements
- [ ] Referenced appropriate AI_PROMPTS file
- [ ] Identified inline vs form editing approach
- [ ] Verified component reuse opportunities

### 🔧 Implementation Phase  
- [ ] Using Command Bus for write operations
- [ ] ServiceContext injected properly
- [ ] PrimeVue components used exclusively
- [ ] Mandatory page structure followed
- [ ] Inline editing rules applied correctly

### ✅ Validation Phase
- [ ] Code follows reference file patterns
- [ ] No prohibited patterns used
- [ ] Quality gates will pass
- [ ] Tests cover critical paths
- [ ] Documentation updated if needed

---

## 🎨 STRICT BLUE-WHALE THEME STANDARDS - MANDATORY

### Theme Application Rules
**EVERY page/template MUST use blue-whale theme with NO DEVIATIONS:**

#### ✅ MANDATORY Theme Application
```vue
<!-- Root App.vue or Layout components MUST have theme attribute -->
<div data-theme="blue-whale" class="theme-blue-whale">
  <!-- All page content -->
</div>

<!-- Dark mode support (automatic detection) -->
<div :data-theme="isDark ? 'blue-whale-dark' : 'blue-whale'" 
     :class="isDark ? 'theme-blue-whale-dark' : 'theme-blue-whale'">
  <!-- All page content with dark mode support -->
</div>
```

#### PrimeVue Component Theme Integration
```vue
<!-- Sidebar MUST use blu-whale theme -->
<Sidebar theme="blu-whale" />

<!-- All PrimeVue components inherit theme automatically -->
<DataTable />  <!-- ✅ Inherits blue-whale theme -->
<Button />     <!-- ✅ Inherits blue-whale theme -->
<Dialog />     <!-- ✅ Inherits blue-whale theme -->
```

### Dark/Light Mode Implementation
**Comprehensive theme switching MUST be supported:**

#### Theme Detection & Storage
```typescript
// composables/useTheme.ts - MANDATORY PATTERN
import { ref, computed, watch } from 'vue'

const isDark = ref(false)

// Auto-detect system preference
const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
isDark.value = mediaQuery.matches

// Watch for system changes
mediaQuery.addEventListener('change', (e) => {
  isDark.value = e.matches
})

// Allow manual override (stored in localStorage)
const themeOverride = ref(localStorage.getItem('theme-override'))

export const useTheme = () => ({
  isDark: computed(() => 
    themeOverride.value === 'dark' ? true :
    themeOverride.value === 'light' ? false :
    isDark.value
  ),
  setTheme: (theme: 'dark' | 'light' | 'auto') => {
    if (theme === 'auto') {
      localStorage.removeItem('theme-override')
      themeOverride.value = null
    } else {
      localStorage.setItem('theme-override', theme)
      themeOverride.value = theme
    }
  }
})
```

#### CSS Variable Integration
```css
/* MANDATORY: All custom styles MUST use blue-whale CSS variables */

/* ✅ CORRECT: Use blue-whale theme variables */
.custom-element {
  background-color: var(--p-surface-0);
  color: var(--p-text-color);
  border: 1px solid var(--p-border-color);
}

/* ❌ FORBIDDEN: Hard-coded colors that break theme */
.custom-element {
  background-color: #ffffff;
  color: #000000;
  border: 1px solid #cccccc;
}
```

### Theme Validation Requirements

#### Automated Theme Compliance
```vue
<!-- Theme compliance checker component -->
<template>
  <div v-if="!isThemeCompliant" class="theme-error">
    ❌ Theme compliance error: Missing blue-whale theme
  </div>
</template>

<script setup>
const isThemeCompliant = computed(() => {
  const rootElement = document.documentElement
  return rootElement.getAttribute('data-theme')?.includes('blue-whale') ||
         rootElement.classList.contains('theme-blue-whale')
})
</script>
```

### Color Scheme Standards
**All UI elements MUST follow blue-whale color specifications:**

#### Primary Colors (from blue-whale.css)
```css
/* Light mode */
--p-primary-50: #eff6ff;
--p-primary-500: #3b82f6;
--p-primary-950: #1e3a8a;

/* Dark mode */  
--p-primary-50: #1e3a8a;
--p-primary-500: #60a5fa;
--p-primary-950: #eff6ff;
```

#### Surface Colors
```css
/* Light surfaces */
--p-surface-0: #ffffff;
--p-surface-50: #f8fafc;
--p-surface-900: #0f172a;

/* Dark surfaces */
--p-surface-0: #0f172a;
--p-surface-50: #1e293b;
--p-surface-900: #f8fafc;
```

### Implementation Patterns

#### App.vue Integration
```vue
<template>
  <div 
    :data-theme="currentTheme"
    :class="themeClass"
    class="app-container"
  >
    <Toast />
    <ConfirmDialog />
    <router-view />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTheme } from '@/composables/useTheme'

const { isDark } = useTheme()

const currentTheme = computed(() => 
  isDark.value ? 'blue-whale-dark' : 'blue-whale'
)

const themeClass = computed(() => 
  isDark.value ? 'theme-blue-whale-dark' : 'theme-blue-whale'
)
</script>
```

#### Layout Component Integration
```vue
<!-- LayoutShell.vue MUST include theme -->
<template>
  <div class="layout-container theme-aware">
    <Sidebar theme="blu-whale" :class="sidebarThemeClass" />
    <div class="main-content">
      <UniversalPageHeader />
      <main class="page-content">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { useTheme } from '@/composables/useTheme'

const { isDark } = useTheme()

const sidebarThemeClass = computed(() => ({
  'sidebar-dark': isDark.value,
  'sidebar-light': !isDark.value
}))
</script>
```

### Quality Gates & Validation

#### Pre-commit Theme Checks
```bash
# MANDATORY: Run before any commit
npm run theme:validate
php artisan layout:validate --check-theme
bash stack/validate-migration.sh
```

#### Theme Validation Files (Reference These)
- **Layout Compliance Command**: `stack/app/Console/Commands/ValidateLayoutCompliance.php`
  - Includes `checkThemeCompliance()` method
  - Validates blue-whale theme usage, hard-coded colors, forbidden themes
  - Usage: `php artisan layout:validate --json`

- **Migration Validation Script**: `stack/validate-migration.sh` 
  - Bash script with comprehensive theme checks
  - Validates theme compliance before migration
  - Usage: `bash validate-migration.sh`

- **Blue-Whale Theme CSS**: `stack/resources/js/styles/themes/blue-whale.css`
  - Complete theme definition with CSS custom properties
  - Light/dark mode support with data-theme attributes
  - Reference for all color variables

#### Theme Validation Script
```javascript
// scripts/validate-theme.js
const fs = require('fs')
const path = require('path')

const validateTheme = () => {
  const files = glob.sync('resources/js/**/*.vue')
  const errors = []
  
  files.forEach(file => {
    const content = fs.readFileSync(file, 'utf8')
    
    // Check for hard-coded colors
    if (content.match(/#[0-9a-f]{6}/gi)) {
      errors.push(`${file}: Hard-coded colors found`)
    }
    
    // Check for missing theme attributes
    if (content.includes('<div') && !content.includes('data-theme')) {
      errors.push(`${file}: Missing theme attributes`)
    }
  })
  
  return errors
}
```

---

## ❌ ABSOLUTE THEME PROHIBITIONS

### Forbidden Color Patterns
```css
/* ❌ NEVER USE: Hard-coded colors */
.element { color: #000000; }
.element { background: white; }
.element { border-color: #cccccc; }

/* ❌ NEVER USE: Non-blue-whale themes */
<Sidebar theme="default" />
<Sidebar theme="custom" />
data-theme="material-dark"
```

### Forbidden Implementation Patterns
```vue
<!-- ❌ FORBIDDEN: Missing theme integration -->
<template>
  <div class="page">
    <h1 style="color: black;">Title</h1>
  </div>
</template>

<!-- ❌ FORBIDDEN: Theme override without blue-whale base -->
<template>
  <div data-theme="custom-theme">
</template>

<!-- ❌ FORBIDDEN: Component-level theme conflicts -->
<Sidebar theme="default" />
<Button severity="custom-color" />
```

---

**Remember**: Every AI session should reference these instructions and sample code files to ensure consistent, constitutional-compliant development across your team.
- frontend should always see toasts and alerts as errors, not the laravel detailed errors