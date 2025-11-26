# Haasib Development Standards

**Last Updated**: 2025-11-26 (Post-RBAC Implementation)
**Purpose**: Navigation hub for AI-assisted development

---

## 🎯 QUICK START BY TASK

### Creating a New Feature
1. **Read**: `AI_PROMPTS/MASTER_REMEDIATION_PROMPT.md` - Complete workflow
2. **RBAC**: See `AI_PROMPTS/RBAC_SYSTEM.md` - Authorization patterns
3. **Database**: See `AI_PROMPTS/DATABASE_SCHEMA_REMEDIATION.md`
4. **Backend**: See `AI_PROMPTS/CONTROLLER_REMEDIATION.md`
5. **Frontend**: See `AI_PROMPTS/FRONTEND_REMEDIATION.md`
6. **Test**: Run validation commands below

### Fixing Existing Code
1. **Identify**: Use `AI_PROMPTS/QUALITY_VALIDATION_PROMPT.md`
2. **Fix**: Use pattern-specific remediation file
3. **Validate**: Run quality gates

### Adding Permissions
1. **Define**: Add to `app/Constants/Permissions.php`
2. **Sync**: Run `php artisan rbac:sync-permissions`
3. **Assign**: Update `config/role-permissions.php`
4. **Deploy**: Run `php artisan rbac:sync-role-permissions`
5. **Use**: `$this->hasCompanyPermission(Permissions::NEW_PERM)`

### Creating a New Controller/Route
1. **Route**: Use `/{company}/resource` pattern (route-based context)
2. **Middleware**: Apply `['auth', 'identify.company']`
3. **Controller**: Get company via `app(CurrentCompany::class)->get()`
4. **FormRequest**: Use `hasCompanyPermission()` for authorization
5. **Service**: Receive `ServiceContext` via Command Bus

---

## 🏛️ CONSTITUTIONAL RULES (NON-NEGOTIABLE)

### Architecture
- **Module Structure**: ALL business logic in `/build/modules/{Name}` (models, controllers, migrations, pages, config)
- **Root `/build/app`**: Only shared infrastructure (User, Company, Auth, RBAC, BaseFormRequest)
- **Multi-Schema**: `auth`, `acct`, `hsp`, `crm`, `audit` with full RLS isolation
- **UUID Only**: NEVER integer primary keys (exception: Spatie permission pivot tables use bigint for role_id/permission_id)
- **Company Context**: Route-based via `/{company}/resource` pattern
- **RBAC**: Spatie Laravel Permission with teams feature (company_id as team)

### Backend Patterns
```php
// ✅ ALWAYS
Route::get('/{company}/customers', ...)->middleware(['auth', 'identify.company']);
$company = app(CurrentCompany::class)->get();
Bus::dispatch('action.name', $data, ServiceContext::fromRequest($request))
class Request extends BaseFormRequest { authorize() }
$table->uuid('id')->primary()

// ❌ NEVER
Route::get('/customers', ...);  // Missing {company} parameter
session('active_company_id');   // Use route-based context instead
new Service(); Request $request; $table->id()
```

### Frontend Patterns
```vue
<!-- ✅ ALWAYS -->
<script setup lang="ts">
import { Button } from '@/components/ui/button'
</script>

<!-- ❌ NEVER -->
<script>export default { data() }</script>
<button>Click</button>
```

### Critical Rules
| Must Have | Must NOT Have |
|-----------|---------------|
| `/{company}` in routes | Session-based company context |
| `identify.company` middleware | Routes without company parameter |
| Command Bus for writes | Direct service calls |
| FormRequest validation | Inline `$request->validate()` |
| `hasCompanyPermission()` in authorize() | Direct permission checks |
| Shadcn/Vue components | HTML elements (`<input>`, `<button>`) |
| Module-specific code in `/modules/` | Business logic in `/app` |
| RLS on ALL tenant tables | Missing `company_id` |
| UUID primary keys | `$table->id()` or bigIncrements |

---

## 📋 IMPLEMENTATION GUIDE

### When Creating Database Tables
**Read**: `AI_PROMPTS/DATABASE_SCHEMA_REMEDIATION.md`

**Quick Reference**:
```php
// Module migration: modules/{Name}/Database/Migrations/
Schema::create('{module_schema}.{table}', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('company_id');
    // ... fields
});

DB::statement('ALTER TABLE {schema}.{table} ENABLE ROW LEVEL SECURITY');
DB::statement("CREATE POLICY ... USING (company_id = current_setting('app.current_company_id')::uuid)");
```

### When Creating Controllers
**Read**: `AI_PROMPTS/CONTROLLER_REMEDIATION.md`

**Quick Reference**:
```php
// Module controller: modules/{Name}/Http/Controllers/
use App\Services\CurrentCompany;

public function index(Request $request): Response
{
    $company = app(CurrentCompany::class)->get();
    
    $entities = Entity::where('company_id', $company->id)->get();
    
    return Inertia::render('Module/Entity/Index', [
        'entities' => $entities,
    ]);
}

public function store(StoreRequest $request): JsonResponse
{
    $result = Bus::dispatch('entity.create', $request->validated());
    return response()->json(['success' => true, 'data' => $result], 201);
}
```

**Routes**:
```php
Route::middleware(['auth', 'identify.company'])->group(function () {
    Route::get('/{company}/entities', [EntityController::class, 'index']);
    Route::post('/{company}/entities', [EntityController::class, 'store']);
});
```

### When Creating Models
**Read**: `AI_PROMPTS/MODEL_REMEDIATION.md`

**Quick Reference**:
```php
// Module model: modules/{Name}/Models/
class Entity extends Model
{
    use HasUuids, BelongsToCompany, SoftDeletes;
    protected $table = '{module_schema}.{table}';
    protected $keyType = 'string';
    public $incrementing = false;
}
```

### When Creating Vue Pages
**Read**: `AI_PROMPTS/FRONTEND_REMEDIATION.md`

**Quick Reference**:
```vue
<!-- Module page: modules/{Name}/Resources/js/Pages/ -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Button } from '@/components/ui/button'
</script>

<template>
  <Head title="Page" />
  <UniversalLayout title="Page">
    <!-- Shadcn/Vue components only -->
  </UniversalLayout>
</template>
```

---

## 🔐 SECURITY & RBAC

**Read**: `AI_PROMPTS/RBAC_SYSTEM.md` - Complete RBAC implementation guide

### Company Context (CRITICAL)

**ALWAYS use route-based company context:**

```php
// ✅ CORRECT - Route-based context
Route::middleware(['auth', 'identify.company'])->group(function () {
    Route::get('/{company}/customers', [CustomerController::class, 'index']);
});

// In Controller
$company = app(CurrentCompany::class)->get();
$companyId = $company->id;

// ❌ WRONG - Session-based context (DO NOT USE)
$companyId = session('active_company_id');  // NEVER DO THIS
```

**How it works:**
1. User visits `https://app.com/my-company/customers`
2. `IdentifyCompany` middleware extracts `{company}` slug from URL
3. Finds company, verifies user has access
4. Sets `CurrentCompany` singleton + Spatie team context
5. Controller gets company via `app(CurrentCompany::class)->get()`

### Authorization Pattern
```php
// In FormRequest (modules/{Name}/Http/Requests/)
use App\Constants\Permissions;

public function authorize(): bool
{
    return $this->hasCompanyPermission(Permissions::ACCT_CUSTOMERS_CREATE) 
        && $this->validateRlsContext();
}
```

### Permission Management
```bash
# 1. Add permission to app/Constants/Permissions.php
public const ACCT_CUSTOMERS_CREATE = 'acct.customers.create';

# 2. Sync to database
php artisan rbac:sync-permissions

# 3. Update config/role-permissions.php to assign to roles
'owner' => [Permissions::ACCT_CUSTOMERS_CREATE, ...],

# 4. Sync roles for all companies
php artisan rbac:sync-role-permissions
```

### Key Components
- **CurrentCompany**: Singleton tracks active company + sets Spatie team context
- **IdentifyCompany**: Middleware extracts `{company}` from route, verifies membership, sets context
- **CompanyService**: Manage members (`addMember`, `changeRole`, `removeMember`)
- **BaseFormRequest**: `hasCompanyPermission()` + `validateRlsContext()` helpers
- **Roles**: `owner`, `admin`, `accountant`, `viewer` (company-scoped via Spatie teams)
- **Permissions**: Global, defined once in `app/Constants/Permissions.php`
- **Super Admin**: Global role with `Gate::before()` bypass (company_id = null)

### Database Structure
- **Spatie Tables**: Use UUID for `model_id`, `company_id` nullable for global roles
- **RLS Policies**: Use `current_setting('app.current_company_id')::uuid` for isolation
- **Schemas**: `auth` (users, companies, roles), `acct` (customers, invoices), `audit` (logs)

---

## 🖊️ INLINE EDITING

**Read**: `docs/inline-editing-system.md`

**Decision Matrix**:
| Field Type | Inline? | Reason |
|------------|---------|--------|
| `name`, `email`, `status` | ✅ | Simple, atomic |
| `total_amount`, `balance` | ❌ | Calculated |
| `address`, `line_items` | ❌ | Complex data |

---

## 🧪 TESTING & VALIDATION

### Pre-Commit
```bash
cd stack && composer quality-check
php artisan test tests/Feature/CriticalPathTest.php
php artisan layout:validate --json
bash validate-migration.sh
```

### RBAC
```bash
php artisan db:seed --class=PermissionSeeder
php artisan test tests/Feature/RbacTest.php
```

### Full Validation
**Read**: `AI_PROMPTS/QUALITY_VALIDATION_PROMPT.md`

---

## 🚀 DEVELOPMENT SERVER

```bash
# Primary (3-10x faster)
php artisan octane:start --server=frankenphp --port=9001 --watch

# Frontend
npm run dev

# Access
http://localhost:5180 (Vite proxy)
```

---

## 📚 REFERENCE INDEX

### By Task
| Task | Primary File | Supporting Files |
|------|-------------|------------------|
| **New Feature** | `MASTER_REMEDIATION_PROMPT.md` | All others |
| **RBAC/Auth** | `RBAC_SYSTEM.md` | `Permissions.php`, `BaseFormRequest.php` |
| **Database** | `DATABASE_SCHEMA_REMEDIATION.md` | `constitution.md` |
| **Controllers** | `CONTROLLER_REMEDIATION.md` | `BaseFormRequest.php` |
| **Models** | `MODEL_REMEDIATION.md` | `BelongsToCompany` trait |
| **Frontend** | `FRONTEND_REMEDIATION.md` | `UniversalLayout.vue` |
| **Fix Violations** | `SYSTEMATIC_REPLACEMENT_GUIDE.md` | Pattern-specific files |
| **Validate** | `QUALITY_VALIDATION_PROMPT.md` | Test files |

### By Category
**Architecture**:
- `.specify/memory/constitution.md` - Full rationale
- `docs/modules-architecture.md` - Module structure
- `docs/TEAM_MEMORY.md` - Historical decisions
- `MIGRATION_PLAN.md` - Phase-by-phase migration guide
- `migration-journal.md` - Implementation timeline

**Security & RBAC**:
- `AI_PROMPTS/RBAC_SYSTEM.md` - **Complete RBAC implementation guide**
- `app/Constants/Permissions.php` - Permission definitions (source of truth)
- `config/role-permissions.php` - Role-permission matrix
- `app/Http/Requests/BaseFormRequest.php` - Authorization helpers
- `app/Services/CurrentCompany.php` - Company context singleton
- `app/Services/CompanyService.php` - Member/role management

**UI Standards**:
- `resources/js/layouts/UniversalLayout.vue` - Page structure
- `resources/js/components/ui/` - Shadcn/Vue components
- `resources/js/styles/themes/blue-whale.css` - Theme

**Testing**:
- `docs/HAASIB_TESTING_PLAN.md` - Test strategy
- `app/Console/Commands/ValidateLayoutCompliance.php` - Validators
- `validate-migration.sh` - Migration checks

---

## ❌ COMMON MISTAKES (NEVER DO)

### Database
```php
❌ $table->id()                    // Use uuid('id')->primary()
❌ Schema::create('customers')     // Use Schema::create('acct.customers')
❌ No RLS policies                 // Always enable RLS on tenant tables
❌ Missing username field          // Users table MUST have username column
```

### Backend
```php
❌ new Service()                         // Use Bus::dispatch()
❌ Request $request                      // Use FormRequest
❌ Customer::find($id)                   // Do in service layer, not controller
❌ session('active_company_id')          // Use app(CurrentCompany::class)->get()
❌ Route::get('/customers', ...)         // Use Route::get('/{company}/customers', ...)
❌ No 'identify.company' middleware      // Always add to multi-tenant routes
```

### RBAC
```php
❌ $user->can('permission')              // Use hasCompanyPermission() in FormRequest
❌ Creating roles manually                // Use rbac:sync-role-permissions command
❌ Spatie tables with bigint model_id    // Must use UUID for model_id (already fixed in migrations)
```

### Frontend
```vue
❌ export default { data() }       // Use <script setup>
❌ <input v-model="name">          // Use <Input v-model="name" />
❌ fetch('/api/...')               // Use Inertia form.post()
```

---

## 🎯 DECISION TREES

### Database: Which Schema?
```
Is it user/company/permission? → auth
Is it financial/customer? → acct
Is it hospitality/booking? → hsp
Is it CRM/marketing? → crm
```

### Backend: Service vs Controller?
```
Business logic? → Service/Action
Validation? → FormRequest
Coordination? → Controller (thin)
Write operation? → Command Bus
```

### Frontend: Inline vs Form?
```
Single atomic field? → Inline editing
Multiple related fields? → Form
Complex validation? → Form
Creation (new record)? → Minimal form
```

### Code: Root vs Module?
```
Used by multiple modules? → Root /build/app
Module-specific domain logic? → /build/modules/{Name}
Shared UI component? → Root /build/resources/js/components
Module-specific page? → /build/modules/{Name}/Resources/js
```

---

## 📐 TEMPLATE SKELETONS

### Migration
```php
Schema::create('{schema}.{table}', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('company_id');
    $table->timestamps();
});
// + RLS policies
```

### Controller
```php
public function store(StoreRequest $request): JsonResponse
{
    return response()->json([
        'success' => true,
        'data' => Bus::dispatch('entity.create', $request->validated())
    ], 201);
}
```

### Model
```php
class Entity extends Model
{
    use HasUuids, BelongsToCompany, SoftDeletes;
    protected $table = '{schema}.{table}';
    protected $keyType = 'string';
    public $incrementing = false;
}
```

### Vue Page
```vue
<script setup lang="ts">
import UniversalLayout from '@/layouts/UniversalLayout.vue'
</script>
<template>
  <UniversalLayout title="Page">
    <!-- Content -->
  </UniversalLayout>
</template>
```

**Full examples**: See respective remediation files

---

## 🛠️ TECHNOLOGY STACK

- **Backend**: PHP 8.4, Laravel 12, PostgreSQL 16
- **Server**: Laravel Octane + FrankenPHP
- **Frontend**: Vue 3, Inertia.js v2, Shadcn/Vue, Tailwind
- **Auth**: Sanctum + Spatie Permissions
- **Testing**: Pest, Playwright

---

## ✅ FINAL CHECKLIST

**Before Implementation**:
- [ ] Correct remediation file identified
- [ ] Module structure planned
- [ ] Reference templates reviewed
- [ ] Routes use `/{company}` parameter
- [ ] `identify.company` middleware applied

**During Implementation**:
- [ ] Command Bus used for writes
- [ ] FormRequest validation with `hasCompanyPermission()`
- [ ] Company context via `app(CurrentCompany::class)->get()`
- [ ] Shadcn/Vue components only
- [ ] Code in correct location (root vs module)
- [ ] UUID primary keys on all tables
- [ ] RLS policies on tenant tables

**Before Commit**:
- [ ] Quality gates pass
- [ ] Tests cover changes
- [ ] Migration validated
- [ ] Layout compliance checked
- [ ] No session-based company context used
- [ ] All routes protected with proper middleware

---

## 🔄 FRESH START CHECKLIST

When starting fresh with RBAC in place:

**Database Setup**:
```bash
# 1. Fresh migration
php artisan migrate:fresh --force

# 2. Seed super admin
php artisan db:seed  # Creates admin@haasib.com / password

# 3. Sync permissions
php artisan rbac:sync-permissions  # 71 permissions

# 4. Create first company via UI (no seeder needed)
# Login → Create Company → Auto-assigned as owner

# 5. Sync company roles
php artisan rbac:sync-role-permissions
```

**Critical Files** (DO NOT DELETE):
- `app/Constants/Permissions.php` - Permission definitions
- `config/role-permissions.php` - Role-permission matrix
- `app/Services/CurrentCompany.php` - Company context singleton
- `app/Http/Middleware/IdentifyCompany.php` - Route-based context
- `app/Services/CompanyService.php` - Member/role management
- `database/migrations/2025_11_21_074439_create_permission_tables.php` - UUID Spatie tables
- `database/migrations/2025_11_26_105810_make_company_id_nullable_in_spatie_tables.php` - Global roles support

**Expected State**:
- ✅ 71 permissions in database
- ✅ 1 super_admin role (global, company_id = null)
- ✅ Super admin user exists
- ✅ All migrations use UUID
- ✅ All Spatie pivot tables use UUID for model_id
- ✅ Users table has username column
- ✅ Route-based company context ready

---

**Note**: This file is a navigation hub. For detailed patterns and examples, always refer to the specific remediation files listed above.
