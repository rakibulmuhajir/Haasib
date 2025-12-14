# Haasib Development Standards

**Last Updated**: 2025-11-28
**Purpose**: Single source of truth for AI-assisted development

---

## 🚨 BEFORE YOU START

### Creating/Modifying Database
1. **Read the schema contract**: `docs/contracts/{schema}-schema.md` (e.g., `auth-schema.md`, `acct-schema.md`)
2. Schema contracts define: columns, types, defaults, FKs, `$fillable`, `$casts`, relationships
3. **Do not invent columns** — if it's not in the contract, update the contract first
4. After migration, update the contract to match

### Creating Any Feature
1. Read relevant contract in `docs/contracts/`
2. Read `AI_PROMPTS/MASTER_REMEDIATION_PROMPT.md`
3. Check Constitutional Rules below
4. Use Template Skeletons at bottom

---

## 🏛️ CONSTITUTIONAL RULES

These are non-negotiable. Violating any = restart.

### Architecture
| Rule | Detail |
|------|--------|
| Module structure | Business logic in `/build/modules/{Name}/` |
| Root `/build/app` | Only shared infrastructure (User, Company, Auth, RBAC) |
| Multi-schema | `auth`, `acct`, `hsp`, `crm`, `audit` — each with RLS |
| UUID only | `$table->uuid('id')->primary()` — never `$table->id()` |
| Company context | Route-based `/{company}/resource` — never session-based |
| Multi-currency (Phase 1) | Follow `docs/contracts/multicurrency-rules.md`: code-as-PK in `public.currencies`, char(3) everywhere, manual rates, journals balance in base at 15,2, payment currency must equal invoice currency or base, account currency immutable after postings |

### The Golden Patterns

```php
// ✅ THESE ARE MANDATORY
Route::get('/{company}/resource', ...)->middleware(['auth', 'identify.company']);
$company = app(CurrentCompany::class)->get();
Bus::dispatch('action.name', $request->validated());
$this->hasCompanyPermission(Permissions::RESOURCE_ACTION);
Schema::create('{schema}.{table}', ...);  // e.g., 'acct.customers'

// ❌ INSTANT REJECTION
Route::get('/resource', ...);              // Missing {company}
session('active_company_id');              // Session-based context
new Service();                             // Direct instantiation
$request->validate([...]);                 // Inline validation
$table->id();                              // Integer PK
```

### Frontend Rules
- `<script setup lang="ts">` only — no Options API
- Shadcn/Vue components only — no raw `<input>`, `<button>`
- Inertia forms — no `fetch()` or `axios`
- Mode-aware terminology — use `useLexicon()` for Owner/Accountant text (see `docs/frontend-experience-contract.md` Section 14)

---

## 📋 TASK-SPECIFIC GUIDES

### Database Work
**Required reading**: `docs/contracts/{schema}-schema.md`, then `AI_PROMPTS/DATABASE_SCHEMA_REMEDIATION.md`

### Controllers/Routes
**Required reading**: `AI_PROMPTS/CONTROLLER_REMEDIATION.md`

### Models
**Required reading**: Schema contract first (for `$fillable`, `$casts`), then `AI_PROMPTS/MODEL_REMEDIATION.md`

### Frontend/Vue
**Required reading**: `AI_PROMPTS/FRONTEND_REMEDIATION.md`

### RBAC/Permissions
**Required reading**: `AI_PROMPTS/RBAC_SYSTEM.md`

### CLI & Command Palette
**Required reading**: `cli-specs.md`, `cli-palette.md`

**Quick workflow**:
```bash
# 1. Add to app/Constants/Permissions.php
# 2. php artisan rbac:sync-permissions
# 3. Update config/role-permissions.php
# 4. php artisan rbac:sync-role-permissions
```

---

## 🔐 RBAC ESSENTIALS

### Company Context Flow
```
URL: /{company}/resource
  → IdentifyCompany middleware extracts slug
  → Sets CurrentCompany singleton + Spatie team atomically
  → Controller: app(CurrentCompany::class)->get()
```

### Authorization
```php
// In FormRequest authorize():
return $this->hasCompanyPermission(Permissions::RESOURCE_ACTION)
    && $this->validateRlsContext();
```

### God-Mode Users
- UUID prefix `00000000-0000-0000-0000-` = bypass all checks
- Super admin: `...000000000000`, System admins: `...000000000001`, `...000000000002`, etc.
- No company memberships — access everything via `Gate::before()`
- See `docs/god-mode-system.md` for details

### Key Files
| Purpose | File |
|---------|------|
| Permissions | `app/Constants/Permissions.php` |
| Role matrix | `config/role-permissions.php` |
| Auth helpers | `app/Http/Requests/BaseFormRequest.php` |
| Company context | `app/Services/CurrentCompany.php` |

---

## ❌ COMMON MISTAKES (HIGH-COST ERRORS)

These mistakes have caused restarts. Check every time.

### Database
```php
❌ $table->id()                        // → uuid('id')->primary()
❌ Schema::create('customers')         // → Schema::create('acct.customers')
❌ Missing RLS policies                // → Always enable on tenant tables
❌ Inventing columns not in contract   // → Update contract first
```

### Backend
```php
❌ session('active_company_id')        // → app(CurrentCompany::class)->get()
❌ $user->currentCompany()             // → DEPRECATED, use CurrentCompany singleton
❌ ServiceContext->currentCompany()    // → DEPRECATED, use CurrentCompany singleton
❌ Route::get('/customers', ...)       // → Route::get('/{company}/customers', ...)
❌ Missing identify.company middleware // → Always add to tenant routes
❌ Customer::find($id) in controller   // → Move to service layer
❌ new Service()                       // → Bus::dispatch()
```

### Models
```php
❌ protected $table = 'customers'      // → 'acct.customers' (schema prefix)
❌ Missing $keyType = 'string'         // → Required for UUID
❌ Missing $incrementing = false       // → Required for UUID
❌ Guessing $fillable                  // → Copy from schema contract
❌ Copying old code with currentCompany() // → Update to CurrentCompany singleton
```

### Frontend
```vue
❌ <input v-model="x">                 // → <Input v-model="x" />
❌ <button @click="...">               // → <Button @click="...">
❌ export default { data() }           // → <script setup lang="ts">
❌ fetch('/api/...')                   // → Inertia form.post()
❌ isAccountantMode ? 'Revenue' : 'Money In'  // → t('moneyIn') via useLexicon()
❌ Hardcoded "Transactions to review"  // → t('transactionsToReview')
```

---

## 🖊️ INLINE EDITING

**Full guide**: `docs/inline-editing-system.md`

### When to Use Inline Editing

| Field Type | Inline? | Reason |
|------------|---------|--------|
| `name`, `email`, `status` | ✅ | Simple, atomic, no side effects |
| `total_amount`, `balance` | ❌ | Calculated fields |
| `address`, `line_items` | ❌ | Complex/nested data |
| `currency` | ❌ | Affects other calculations |

**Rule**: If changing the field triggers recalculations or affects other fields, use a form.

### Universal Inline Edit System

Use the reusable composable and component for all inline editing:

**Files:**
- Composable: `resources/js/composables/useInlineEdit.ts`
- Component: `resources/js/components/InlineEditable.vue`

**Usage Example:**
```vue
<script setup lang="ts">
import InlineEditable from '@/components/InlineEditable.vue'
import { useInlineEdit } from '@/composables/useInlineEdit'

// Setup inline editing with endpoint
const inlineEdit = useInlineEdit({
  endpoint: `/${company.slug}/settings`,
  successMessage: 'Updated successfully',
  errorMessage: 'Failed to update',
})

// Register fields with initial values
const nameField = inlineEdit.registerField('name', props.company.name)
const statusField = inlineEdit.registerField('status', props.company.status)
</script>

<template>
  <!-- Text input -->
  <InlineEditable
    v-model="nameField.value.value"
    label="Company Name"
    :editing="nameField.isEditing.value"
    :saving="nameField.isSaving.value"
    :can-edit="canManage"
    type="text"
    @start-edit="nameField.startEditing()"
    @save="nameField.save()"
    @cancel="nameField.cancelEditing()"
  />

  <!-- Select input -->
  <InlineEditable
    v-model="statusField.value.value"
    label="Status"
    :editing="statusField.isEditing.value"
    :saving="statusField.isSaving.value"
    :can-edit="canManage"
    type="select"
    :options="[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]"
    :icon="StatusIcon"
    helper-text="Current status"
    @start-edit="statusField.startEditing()"
    @save="statusField.save()"
    @cancel="statusField.cancelEditing()"
  />
</template>
```

**InlineEditable Props:**
| Prop | Type | Description |
|------|------|-------------|
| `label` | string | Field label |
| `editing` | boolean | Is field being edited |
| `saving` | boolean | Is field saving |
| `canEdit` | boolean | Can user edit (default: true) |
| `type` | `'text' \| 'email' \| 'number' \| 'select' \| 'textarea'` | Input type |
| `options` | `{ value, label }[]` | Options for select type |
| `icon` | Component | Optional icon before value |
| `helperText` | string | Help text below value |
| `readonly` | boolean | Show without edit button |

**Keyboard Shortcuts:** Enter = Save, Escape = Cancel

---

## 🎯 DECISION TREES

### Which Schema?
```
user/company/permission → auth
financial/customer/invoice → acct
hospitality/booking → hsp
CRM/marketing → crm
logs/history → audit
```

### Service vs Controller?
```
Business logic → Service/Action via Bus::dispatch()
Validation → FormRequest
HTTP coordination → Controller (thin, no logic)
```

### Root vs Module?
```
Multi-module shared → /build/app or /build/resources
Module-specific → /build/modules/{Name}/
```

---

## 📐 TEMPLATE SKELETONS

### Migration
```php
// modules/{Module}/Database/Migrations/
Schema::create('{schema}.{table}', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('company_id');
    $table->foreignUuid('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
    // ... columns from schema contract
    $table->timestamps();
    $table->softDeletes();
});

DB::statement('ALTER TABLE {schema}.{table} ENABLE ROW LEVEL SECURITY');
DB::statement("CREATE POLICY {table}_company_isolation ON {schema}.{table}
    FOR ALL USING (company_id = current_setting('app.current_company_id')::uuid)");
```

### Model
```php
// modules/{Module}/Models/
class Entity extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = '{schema}.{table}';
    protected $keyType = 'string';
    public $incrementing = false;

    // Copy $fillable and $casts from schema contract
    protected $fillable = [];
    protected $casts = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
```

### Controller
```php
// modules/{Module}/Http/Controllers/
public function index(): Response
{
    $company = app(CurrentCompany::class)->get();
    return Inertia::render('Module/Entity/Index', [
        'entities' => Entity::where('company_id', $company->id)->get(),
    ]);
}

public function store(StoreRequest $request): JsonResponse
{
    $result = Bus::dispatch('entity.create', $request->validated());
    return response()->json(['success' => true, 'data' => $result], 201);
}
```

### Routes
```php
Route::middleware(['auth', 'identify.company'])->group(function () {
    Route::get('/{company}/entities', [EntityController::class, 'index']);
    Route::post('/{company}/entities', [EntityController::class, 'store']);
});
```

### FormRequest
```php
// modules/{Module}/Http/Requests/
class StoreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::MODULE_ENTITY_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        // Rules from schema contract validation section
        return [];
    }
}
```

### Vue Page
```vue
<!-- modules/{Module}/Resources/js/Pages/ -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import UniversalLayout from '@/layouts/UniversalLayout.vue'
</script>

<template>
  <Head title="Page" />
  <UniversalLayout title="Page">
    <!-- Shadcn/Vue components only -->
  </UniversalLayout>
</template>
```

---

## 🧪 VALIDATION

### Pre-Commit
```bash
composer quality-check
php artisan layout:validate --json
bash validate-migration.sh
```

### Quick Checklist
- [ ] Schema contract exists and is current
- [ ] `$fillable`/`$casts` match contract
- [ ] Routes have `/{company}` + `identify.company` middleware
- [ ] FormRequest uses `hasCompanyPermission()`
- [ ] UUID primary keys
- [ ] RLS policies on tenant tables
- [ ] Shadcn/Vue components (no raw HTML inputs)
- [ ] **Palette Actions implement PaletteAction interface**
- [ ] **Action permissions use App\Constants\Permissions**
- [ ] **Commands route has identify.company middleware**

---

## 🔄 FRESH START

```bash
php artisan migrate:fresh --seed --force
# Super admin: admin@haasib.com / password
# UUID: 00000000-0000-0000-0000-000000000000
```

### Critical Files (DO NOT DELETE)
- `app/Models/User.php`
- `app/Services/CompanyContextService.php`
- `app/Facades/CompanyContext.php`
- `app/Http/Middleware/IdentifyCompany.php`
- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/2025_11_26_175213_create_permission_tables.php`

---

## 📚 REFERENCE INDEX

| Task | Read First | Then |
|------|-----------|------|
| New table | `docs/contracts/{schema}-schema.md` | `AI_PROMPTS/DATABASE_SCHEMA_REMEDIATION.md` |
| New model | Schema contract | `AI_PROMPTS/MODEL_REMEDIATION.md` |
| New feature | Schema contract | `AI_PROMPTS/MASTER_REMEDIATION_PROMPT.md` |
| New screen/UI | `docs/frontend-experience-contract.md` | `docs/ui-screen-specifications.md` |
| UX patterns | `docs/frontend-experience-contract.md` | User modes, Resolution Engine, interactions |
| Technical specs | `docs/ui-screen-specifications.md` | Fields, actions, posting logic |
| Error handling | `docs/ui-screen-specifications.md` Section 15.11 | `AI_PROMPTS/toast.md` |
| RBAC | `AI_PROMPTS/RBAC_SYSTEM.md` | `docs/god-mode-system.md` |
| Fix violations | `AI_PROMPTS/QUALITY_VALIDATION_PROMPT.md` | Pattern-specific file |

---
## Completing Features

Every user-facing action must handle the full request cycle. Before marking work done, verify:

1. **Success path** - Response handled, user sees feedback (toast), UI updates or redirects
2. **Error path** - Validation errors shown inline, server errors shown as toast
3. **Loading state** - Button disabled, spinner if >300ms expected

**For complete error handling patterns, see `docs/ui-screen-specifications.md` Section 15.11.**

For toast notifications, use Sonner. See `AI_PROMPTS/toast.md` for implementation.

For Inertia actions specifically:
- Redirects: Return `redirect()->with('success', '...')`, frontend flash handler shows toast
- Stay on page: Return `back()->with('success', '...')`
- Never return raw JSON from routes that Inertia components call

**CRITICAL:** Never expose plain Laravel error pages for business logic failures. All errors must be caught and shown via Sonner toast.
## 🚀 DEVELOPMENT SERVER

```bash
# Backend (3-10x faster than php artisan serve)
php artisan octane:start --server=frankenphp --port=9001 --watch

# Frontend
npm run dev

# Access via Vite proxy
http://localhost:5180
```

---

## 🛠️ STACK

Laravel 12 / PHP 8.4 / PostgreSQL 16 / Octane+FrankenPHP / Vue 3 / Inertia v2 / Shadcn-Vue / Tailwind / Spatie Permissions

---

**Note**: This file is a hub. Schema contracts (`docs/contracts/`) are the source of truth for data structures. Remediation files (`AI_PROMPTS/`) have detailed examples.
