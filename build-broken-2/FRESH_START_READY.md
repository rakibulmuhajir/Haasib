# Fresh Start Ready ✅

**Date**: 2025-11-26
**Status**: RBAC Implementation Complete - Ready for Fresh Start

---

## What Was Implemented

### RBAC System (Complete)
- ✅ Spatie Laravel Permission with teams (company-scoped roles)
- ✅ 71 permissions defined in `app/Constants/Permissions.php`
- ✅ 4 company roles: owner, admin, accountant, viewer
- ✅ Role-permission matrix in `config/role-permissions.php`
- ✅ Super admin role with Gate::before() bypass
- ✅ UUID support in all Spatie pivot tables
- ✅ Global roles support (company_id nullable)

### Route-Based Company Context (Complete)
- ✅ `CurrentCompany` singleton service
- ✅ `IdentifyCompany` middleware (extracts {company} from URL)
- ✅ `CompanyService` for member/role management
- ✅ `BaseFormRequest` with `hasCompanyPermission()` helper
- ✅ Pattern: `/{company}/resource` for all multi-tenant routes

### Database (Complete)
- ✅ Multi-schema: auth, acct, hsp, crm, audit
- ✅ UUID primary keys on all tables
- ✅ RLS policies with current_setting('app.current_company_id')
- ✅ Users table includes username field
- ✅ Spatie permission tables use UUID for model_id
- ✅ company_id nullable for global roles

### Commands (Complete)
- ✅ `php artisan rbac:sync-permissions` - Sync global permissions
- ✅ `php artisan rbac:sync-role-permissions` - Sync company roles

---

## Files to KEEP (Critical)

### RBAC Core
```
app/Constants/Permissions.php
app/Providers/AuthServiceProvider.php
app/Services/CurrentCompany.php
app/Services/CompanyService.php
app/Http/Middleware/IdentifyCompany.php
app/Http/Requests/BaseFormRequest.php
app/Console/Commands/SyncPermissions.php
app/Console/Commands/SyncRolePermissions.php
config/permission.php
config/permissions.php
config/role-permissions.php
```

### Critical Migrations
```
database/migrations/2025_11_01_000000_create_schemas.php
database/migrations/2025_11_01_100000_create_auth_users_table.php
database/migrations/2025_11_01_200000_create_audit_schema_wrapper.php
database/migrations/2025_11_02_000000_create_companies_table.php
database/migrations/2025_11_21_074439_create_permission_tables.php (UUID modified)
database/migrations/2025_11_26_000001_add_teams_support_to_permission_tables.php
database/migrations/2025_11_26_101411_add_username_to_auth_users_table.php
database/migrations/2025_11_26_105810_make_company_id_nullable_in_spatie_tables.php
```

### Seeder
```
database/seeders/DatabaseSeeder.php (Creates super admin)
```

---

## Fresh Start Procedure

### 1. Drop Database
```bash
PGPASSWORD="AcctP@ss" psql -h localhost -U superadmin haasib_build -c "DROP SCHEMA IF EXISTS acct CASCADE; DROP SCHEMA IF EXISTS auth CASCADE; DROP SCHEMA IF EXISTS audit CASCADE; DROP SCHEMA IF EXISTS hsp CASCADE; DROP SCHEMA IF EXISTS crm CASCADE;"
```

### 2. Fresh Migration
```bash
php artisan migrate:fresh --force
```

**Expected Output**:
- ✅ All schemas created
- ✅ auth.users table with UUID + username
- ✅ auth.companies table
- ✅ Spatie permission tables with UUID support
- ✅ Customer/Invoice/Payment tables with RLS
- ✅ ~24 migrations completed successfully

### 3. Seed Super Admin
```bash
php artisan db:seed
```

**Expected Output**:
```
Super admin created: admin@haasib.com / password
```

### 4. Sync Permissions
```bash
php artisan rbac:sync-permissions
```

**Expected Output**:
```
✓ Created: system.admin
✓ Created: system.audit
... (71 total permissions)
Permissions synced: 71 created, 0 already existed.
```

### 5. Login & Create Company
1. Start server: `php artisan octane:start --server=frankenphp --port=9001 --watch`
2. Start frontend: `npm run dev`
3. Visit: `http://localhost:5180`
4. Login: `admin@haasib.com` / `password`
5. Create first company via UI
6. System auto-creates company roles and assigns you as owner

### 6. Sync Company Roles (After Company Created)
```bash
php artisan rbac:sync-role-permissions
```

**Expected Output**:
```
Company: YourCompany (id: xxx)
  ✓ Created role: owner (71 permissions)
  ✓ Created role: admin (XX permissions)
  ✓ Created role: accountant (XX permissions)
  ✓ Created role: viewer (XX permissions)
Role permissions synced for 1 company(ies).
```

---

## Verification Checklist

After fresh start, verify:

```bash
# Check database state
php artisan tinker --execute="
echo 'Users: ' . App\Models\User::count() . PHP_EOL;
echo 'Companies: ' . App\Models\Company::count() . PHP_EOL;
echo 'Permissions: ' . Spatie\Permission\Models\Permission::count() . PHP_EOL;
echo 'Roles: ' . Spatie\Permission\Models\Role::count() . PHP_EOL;
echo 'Super admin exists: ' . (App\Models\User::where('email', 'admin@haasib.com')->exists() ? 'YES' : 'NO') . PHP_EOL;
"
```

**Expected After Step 4**:
- Users: 1 (super admin)
- Companies: 0
- Permissions: 71
- Roles: 1 (super_admin)
- Super admin exists: YES

**Expected After Step 5**:
- Users: 1
- Companies: 1 (your company)
- Permissions: 71
- Roles: 5 (super_admin + 4 company roles)
- Super admin exists: YES

---

## Route Pattern (MANDATORY)

**ALL multi-tenant routes MUST use this pattern:**

```php
// ✅ CORRECT
Route::middleware(['auth', 'identify.company'])->group(function () {
    Route::get('/{company}/customers', [CustomerController::class, 'index']);
    Route::post('/{company}/customers', [CustomerController::class, 'store']);
});

// ❌ WRONG (DO NOT USE)
Route::get('/customers', [CustomerController::class, 'index']);
Route::get('/accounting/customers', [CustomerController::class, 'index']);
```

**In Controllers:**
```php
use App\Services\CurrentCompany;

public function index(Request $request): Response
{
    // ✅ CORRECT
    $company = app(CurrentCompany::class)->get();
    $companyId = $company->id;
    
    // ❌ WRONG
    $companyId = session('active_company_id');  // NEVER USE THIS
}
```

---

## Documentation Updated

- ✅ `CLAUDE.md` - Updated with route-based context, RBAC patterns
- ✅ `AI_PROMPTS/RBAC_SYSTEM.md` - Complete 400+ line implementation guide
- ✅ `migration-journal.md` - Phase 3.5 RBAC entry
- ✅ `MIGRATION_PLAN.md` - Phase 2.5 RBAC section

---

## What Changed From Session to Route-Based

| Old Pattern (Session) | New Pattern (Route) |
|----------------------|---------------------|
| `/customers` | `/{company}/customers` |
| `session('active_company_id')` | `app(CurrentCompany::class)->get()` |
| No middleware | `identify.company` middleware |
| Manual context setting | Automatic from URL |
| Session management complexity | Clean, stateless |

---

## Ready to Start Fresh! 🚀

All RBAC infrastructure is in place. When you run `migrate:fresh`, you'll have a clean, fully-functional multi-tenant system with:
- Route-based company context
- Spatie permissions with UUID support
- Global + company-scoped roles
- Super admin with bypass
- 71 pre-defined permissions
- RLS policies for data isolation

**Next Step**: Run the "Fresh Start Procedure" above ☝️
