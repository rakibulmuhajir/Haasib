# Haasib Project Journal

**Project**: Multi-tenant ERP system for hospitality and accounting  
**Started**: 2024 (estimated)  
**Current Date**: 2025-11-27

---

## Table of Contents

1. [Project Vision](#project-vision)
2. [Technology Stack](#technology-stack)
3. [Architecture Evolution](#architecture-evolution)
4. [Build History](#build-history)
5. [RBAC Implementation Journey](#rbac-implementation-journey)
6. [Key Technical Decisions](#key-technical-decisions)
7. [Major Milestones](#major-milestones)
8. [Current State](#current-state)

---

## Project Vision

Haasib is a multi-tenant ERP system designed for:
- **Hospitality industry**: Hotels, restaurants, property management
- **Accounting**: Multi-currency, multi-company financial management
- **Multi-tenancy**: Company-scoped data isolation with Row-Level Security (RLS)

### Core Requirements
- Multiple companies per user
- Company-scoped permissions (owner, admin, accountant, viewer)
- Multi-schema PostgreSQL (auth, acct, hsp, crm, audit)
- UUID primary keys throughout
- Complete data isolation via RLS policies

---

## Technology Stack

### Backend
- **PHP 8.4** + **Laravel 12**
- **Laravel Octane** + **FrankenPHP** (high-performance server)
- **PostgreSQL 16** (multi-schema with RLS)
- **Spatie Laravel Permission** (RBAC with teams feature)

### Frontend
- **Vue 3** (Composition API, `<script setup>`)
- **Inertia.js v2** (SPA without API)
- **Shadcn/Vue** (UI component library)
- **Tailwind CSS** (styling)
- **TypeScript** (type safety)

### Testing
- **Pest** (backend testing)
- **Playwright** (E2E testing)

### Development Philosophy
- Command Bus pattern for all write operations
- FormRequest validation with RBAC checks
- No raw HTML (Shadcn/Vue components only)
- Module-based architecture (`/build/modules/{Name}`)

---

## Architecture Evolution

### Phase 1: Session-based Company Context (Early 2024)
**Approach**: Store active company in session/cookies

**Problems**:
- Session management complexity
- State synchronization issues across tabs
- Middleware dependency on session state
- Difficult to share URLs with company context

**Lesson**: Session-based multi-tenancy is fragile for route-heavy apps

---

### Phase 2: Route-based Company Context (Mid 2024)
**Pivot**: Changed to `/{company}/resource` URL pattern

**Benefits**:
- Company context explicit in URL
- Shareable URLs with company context baked in
- Middleware extracts company from route parameter
- Stateless (no session dependency)

**Implementation**:
- `IdentifyCompany` middleware extracts `{company}` param
- Sets `CurrentCompany` singleton
- Configures Spatie team context via `setPermissionsTeamId()`

**Current Standard**: All company-scoped routes use this pattern

---

### Phase 3: Multi-Schema PostgreSQL (2024)
**Decision**: Separate PostgreSQL schemas for domain separation

**Schemas**:
- `auth`: Users, companies, roles, permissions
- `acct`: Customers, invoices, payments, ledger
- `hsp`: Hospitality-specific tables (bookings, properties)
- `crm`: CRM and marketing
- `audit`: Audit logs and change tracking

**RLS Policies**: Every tenant table has:
```sql
CREATE POLICY company_isolation ON schema.table
USING (company_id = current_setting('app.current_company_id')::uuid);
```

---

## Build History

### Build 1: Initial Development
**Location**: `/app` (legacy location)  
**Period**: 2024  
**Status**: Retired

**Features Built**:
- Initial Laravel setup
- Basic multi-tenancy
- Customer management
- Invoice system

**Issues**: Architectural inconsistencies, moved to new location

---

### Build 2: Refactored Build
**Location**: `/stack`  
**Period**: Mid-2024  
**Status**: Archived

**Improvements**:
- Command Bus implementation
- Module architecture
- Improved RBAC
- Better frontend structure

**Issues**: Still had session-based company context issues

---

### Build 3: "build-broken"
**Location**: `/build-broken`  
**Period**: Late 2024  
**Status**: Abandoned

**Goals**: Fix company context, implement route-based approach

**Outcome**: Migration issues, incomplete RBAC, decided to start fresh

---

### Build 4: "build-broken-2"
**Location**: `/build-broken-2`  
**Period**: November 2025  
**Status**: Working but needs fresh start

**Achievements**:
- Successfully implemented route-based company context
- Complete RBAC with 71 permissions
- Company management working
- Accounting module (customers, invoices)
- Currency management (Phase 3 completed)

**Why Fresh Start Needed**:
- Migration inconsistencies
- Some architectural debt
- Wanted clean slate to apply all lessons learned
- Opportunity to standardize layout system

---

### Build 5: Current "build" (Fresh Start)
**Location**: `/build`  
**Period**: 2025-11-27 → Present  
**Status**: Active development

**Approach**: Selective copy of working components from build-broken-2

**Completed** (as of 2025-11-27):
- Fresh Laravel 12 installation
- All dependencies installed
- RBAC infrastructure copied and working
- UniversalLayout standardized
- Companies UI created (routes + pages)

**In Progress**:
- Companies backend (controller, model, validation)
- Company CRUD operations
- Role assignment on company creation

---

## RBAC Implementation Journey

### Requirements
1. **Two-tier permission system**:
   - Global permissions (71 total, defined once)
   - Company-scoped roles (owner, admin, accountant, viewer per company)

2. **Permission structure**: `{module}.{resource}.{action}`
   - Examples: `acct.customers.create`, `acct.invoices.view`, `hsp.bookings.edit`

3. **Super Admin**: Global role that bypasses all permissions via `Gate::before()`

### Implementation Details

**Permissions Class** (`app/Constants/Permissions.php`):
- 71 permission constants
- `getAll()` method via reflection
- `getAllByModule()` for grouped access

**Commands**:
- `php artisan rbac:sync-permissions` - Syncs 71 permissions to database
- `php artisan rbac:sync-role-permissions` - Creates company-scoped roles

**Middleware** (`IdentifyCompany`):
```php
$company = Company::where('slug', $companyParam)->orWhere('id', $companyParam)->first();
$this->currentCompany->set($company);
app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
```

**Authorization** (`BaseFormRequest`):
- `hasCompanyPermission($permission)` - Check user permission for company
- `validateRlsContext()` - Ensure RLS context set
- `authorizeCustomerOperation()`, `authorizeInvoiceOperation()` - Standard patterns

### Challenges Overcome
1. **Spatie teams feature**: Required understanding of `team_foreign_key` = `company_id`
2. **Permission sync**: Global permissions (no team_id) vs company-scoped roles
3. **Middleware order**: IdentifyCompany must run before permission checks
4. **RLS coordination**: Ensuring company context set for both Spatie and PostgreSQL

---

## Key Technical Decisions

### 1. UUID Primary Keys Everywhere
**Decision**: No auto-increment integers, UUID v4 for all tables

**Rationale**:
- Better for distributed systems
- No sequential ID leakage
- Easier multi-tenancy

**Implementation**:
```php
$table->uuid('id')->primary();
protected $keyType = 'string';
public $incrementing = false;
```

---

### 2. Command Bus for All Writes
**Decision**: All database writes go through Command Bus

**Pattern**:
```php
Bus::dispatch('customer.create', $data, ServiceContext::fromRequest($request))
```

**Benefits**:
- Centralized business logic
- Testable without HTTP layer
- Consistent error handling
- ServiceContext ensures company context

---

### 3. No Raw HTML in Frontend
**Decision**: Only Shadcn/Vue components, never `<input>`, `<button>`, etc.

**Why**: Consistency, theming, accessibility, type safety

**Example**:
```vue
<!-- ✅ Correct -->
<Button variant="outline">Click</Button>
<Input v-model="name" />

<!-- ❌ Wrong -->
<button>Click</button>
<input v-model="name" />
```

---

### 4. Module Architecture
**Decision**: Business logic in `/build/modules/{Name}`, shared code in `/build/app`

**Structure**:
```
/build/modules/Accounting/
  ├── Http/Controllers/
  ├── Models/
  ├── Database/Migrations/
  ├── Resources/js/Pages/
  └── config/
```

**Root `/build/app`**: Only shared infrastructure (User, Company, Auth, RBAC)

---

### 5. UniversalLayout Standard (2025-11-27)
**Decision**: One layout for all authenticated app pages

**Features**:
- `title` prop (page heading)
- `breadcrumbs` prop (optional navigation)
- `header-actions` slot (for action buttons)

**Usage**:
```vue
<UniversalLayout title="Companies">
  <template #header-actions>
    <Button>Add Company</Button>
  </template>
  <!-- content -->
</UniversalLayout>
```

---

## Major Milestones

### 2024
- ✅ Project inception and requirements gathering
- ✅ Laravel setup with multi-tenancy
- ✅ PostgreSQL multi-schema implementation
- ✅ RLS policies for all tenant tables
- ✅ Pivot from session-based to route-based company context
- ✅ Module architecture established
- ✅ Accounting module (customers, invoices)
- ✅ Command Bus pattern implementation

### November 2025
- ✅ Currency management (Phase 3) completed in build-broken-2
- ✅ Decision to start fresh build
- ✅ Fresh Laravel 12 installation
- ✅ RBAC infrastructure copied and tested
- ✅ UniversalLayout standardization
- ✅ Companies UI created

### Upcoming
- ⏳ Companies backend completion
- ⏳ Company CRUD operations
- ⏳ Accounting module migration to new build
- ⏳ Hospitality module development

---

## Current State (2025-11-27)

### What's Working
1. **Infrastructure**:
   - Fresh Laravel 12 with Octane + FrankenPHP
   - PostgreSQL with multi-schema setup
   - All migrations executed successfully

2. **RBAC**:
   - 71 permissions synced to database
   - Commands working (`rbac:sync-permissions`, `rbac:sync-role-permissions`)
   - Super admin seeded (`admin@haasib.com` / `password`)
   - Middleware and services in place

3. **Frontend**:
   - UniversalLayout standardized
   - Companies pages created (index + create)
   - AppSidebar with Companies link
   - Shadcn/Vue components throughout

### What's Not Working Yet
1. Companies backend (no controller, model, or validation)
2. Company CRUD operations not functional
3. Role assignment on company creation
4. Show/Edit pages for companies

### Active Branch
**Branch**: `new-build`  
**Last commit**: feat(currency): Complete phase 3 currency management system (from old build)

### Database State
- Migrations: Up to date (fresh)
- Permissions: 71 permissions in `permissions` table
- Users: 1 super admin user
- Companies: 0 (none created yet)

---

## Lessons Learned

1. **Session-based multi-tenancy is problematic** for route-heavy apps
2. **Route-based company context** is cleaner and more explicit
3. **UUID primary keys** eliminate many edge cases
4. **Command Bus** centralizes business logic effectively
5. **Spatie teams feature** requires understanding team_foreign_key mapping
6. **Fresh starts** are sometimes faster than fixing architectural debt
7. **Layout standardization** should happen early, not late
8. **Selective copying** from old builds preserves working code

---

## Next Major Goals

1. **Complete Companies Module**:
   - Backend implementation (controller, model, validation)
   - Full CRUD operations
   - Role assignment on creation
   - Company switching UI

2. **Migrate Accounting Module**:
   - Copy customers functionality
   - Copy invoices functionality
   - Ensure RLS working correctly
   - Test multi-currency features

3. **Hospitality Module**:
   - Bookings management
   - Property management
   - Room inventory

4. **Testing Infrastructure**:
   - Unit tests for services
   - Feature tests for CRUD operations
   - E2E tests with Playwright

---

**Last Updated**: 2025-11-27  
**Next Session**: Continue with Companies backend implementation (controller + model)
