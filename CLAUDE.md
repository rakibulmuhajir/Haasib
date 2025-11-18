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

### 🔐 SECURITY

**When Implementing Security Features:**
- **Reference File**: `docs/dosdonts/migration-best-practices.md` (security section)
- **Sample Code**: RLS policies, audit logging, permission checks
- **Required**: Spatie permissions, audit triggers, security headers
- **Validation**: Security scans, penetration testing

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

**Remember**: Every AI session should reference these instructions and sample code files to ensure consistent, constitutional-compliant development across your team.
- frontend should always see toasts and alerts as errors, not the laravel detailed errors