# Current Session

**Date**: 2025-11-27  
**Branch**: `new-build`  
**Focus**: Fresh Laravel build with RBAC implementation

---

## Session Context

Starting fresh after migration issues in `build-broken-2`. Created new `/build` directory and selectively copied working RBAC components.

---

## Work Completed Today

### 1. Fresh Installation
- Laravel 12 + all packages (Spatie Permission, Octane, FrankenPHP, Vue, Shadcn-Vue, Inertia)
- PostgreSQL database setup (haasib_dev)
- All migrations using UUID primary keys
- Auth schema with users, companies, sessions tables

### 2. God-Mode System Implementation ⭐
- **Sentinel UUID System**: Super admin & system admins use sequential UUIDs
- **Super Admin**: `00000000-0000-0000-0000-000000000000` (admin@haasib.com / password)
- **System Admins**: Sequential UUIDs (`...000001`, `...000002`, etc.)
- **Authorization**: `Gate::before()` bypasses all checks for god-mode users
- **Audit Trail**: Each admin traceable via unique UUID
- **No Companies**: God-mode users have no company associations
- **No Roles**: Access via sentinel UUID, not Spatie roles

### 3. God-Mode Files Created/Modified
- `app/Models/User.php` - Helper methods (isGodMode, isSuperAdmin, isSystemAdmin, createSystemAdmin)
- `app/Providers/AppServiceProvider.php` - Gate::before() for god-mode bypass
- `app/Http/Middleware/IdentifyCompany.php` - God-mode users can access any company
- `app/Console/Commands/CreateSystemAdmin.php` - Command to create system admins
- `database/seeders/DatabaseSeeder.php` - Seeds super admin with sentinel UUID
- `docs/god-mode-system.md` - **Complete god-mode documentation**
- `CLAUDE.md` - Updated with god-mode references

### 4. Database Configuration
- **Spatie Permission Tables**: Modified to use UUID for model_id (not bigint)
- **Teams Feature**: Enabled with `team_foreign_key = 'company_id'`
- **Nullable company_id**: Pivot tables allow null for god-mode compatibility
- **Migration**: `2025_11_26_175213_create_permission_tables.php` - Custom UUID migration

### 5. User Model Enhancements
- `HasRoles` trait (Spatie)
- `HasUuids` trait (Laravel)
- UUID configuration (`$keyType = 'string'`, `$incrementing = false`)
- Schema prefix (`$table = 'auth.users'`)
- Username field in fillable
- Companies relationship
- God-mode helper methods
- Manual UUID assignment support

---

## Current State

### ✅ Working
- ✅ Fresh Laravel 12 installation with all dependencies
- ✅ PostgreSQL database configured (haasib_dev, app_user)
- ✅ All migrations complete (auth schema, Spatie tables)
- ✅ **Super admin created**: `00000000-0000-0000-0000-000000000000` (admin@haasib.com)
- ✅ **God-mode system**: Sentinel UUIDs, Gate::before(), helper methods
- ✅ **System admin creation**: `php artisan admin:create-system` command
- ✅ User model with UUID support, HasRoles, god-mode methods
- ✅ IdentifyCompany middleware handles god-mode users
- ✅ Route-based company context via CurrentCompany singleton
- ✅ Spatie Permission tables with UUID model_id
- ✅ Teams feature enabled (company_id as team_foreign_key)

### ⚠️ Ready for Implementation
- **CompanyController** - Need to create controller
- **Company FormRequests** - Need StoreCompanyRequest, UpdateCompanyRequest
- **Company Model** - Need to create with UUID support
- **Backend CRUD logic** - Controllers + services for companies
- Company Show/Edit pages (frontend)
- Company deletion logic
- Role assignment when company is created (owner role for creator)
- Permission sync commands (rbac:sync-permissions, rbac:sync-role-permissions)

---

## Key Technical Decisions

1. **Route-based company context**: `/{company}/resource` pattern (NOT session-based)
2. **UUID primary keys**: All tables use UUID instead of auto-increment
3. **Spatie Permission with teams**: `company_id` as team foreign key
4. **UniversalLayout standard**: All authenticated pages use this layout
5. **Shadcn/Vue only**: No raw HTML elements (use Button, Input, etc.)

---

## File Locations Reference

### RBAC Core
- `/build/app/Constants/Permissions.php`
- `/build/app/Services/CurrentCompany.php`
- `/build/app/Services/CompanyService.php`
- `/build/app/Http/Middleware/IdentifyCompany.php`
- `/build/app/Http/Requests/BaseFormRequest.php`
- `/build/app/Console/Commands/SyncPermissions.php`
- `/build/app/Console/Commands/SyncRolePermissions.php`

### Frontend
- `/build/resources/js/layouts/UniversalLayout.vue`
- `/build/resources/js/components/AppSidebar.vue`
- `/build/resources/js/pages/Companies.vue`
- `/build/resources/js/pages/Companies/Create.vue`

### Routes
- `/build/routes/web.php` (companies routes at lines 17-24)

---

## Next Steps (Priority Order)

1. **Copy Company Model** from `build-broken-2/app/Models/Company.php`
2. **Create CompanyController** with index, create, store methods
3. **Create StoreCompanyRequest** FormRequest with validation
4. **Update routes** to use controller instead of closures
5. **Test company creation flow** end-to-end
6. **Create Show.vue and Edit.vue** pages
7. **Implement company deletion** with confirmation
8. **Add role creation** when company is created (owner role for creator)

---

## Known Issues

- Companies POST route will 404 until controller is created
- No validation on company creation form
- Company model doesn't exist yet in new build
- Missing Show/Edit pages (routes exist in old build, need to copy)

---

## Quick Commands

```bash
# Development server
cd /home/banna/projects/Haasib/build
php artisan octane:start --server=frankenphp --port=9001 --watch
npm run dev

# Access
http://localhost:5180

# RBAC
php artisan rbac:sync-permissions
php artisan rbac:sync-role-permissions --company=1

# Database
php artisan migrate:fresh --seed
```

---

**For next session**: Read this file first, then continue with "Create CompanyController and FormRequest"
