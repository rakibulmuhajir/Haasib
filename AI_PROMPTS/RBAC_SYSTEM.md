# RBAC System Reference Guide

**Purpose**: Comprehensive guide for implementing Haasib's Role-Based Access Control system  
**Last Updated**: 2025-11-26  
**Status**: Production-Ready

---

## 🎯 SYSTEM OVERVIEW

### Architecture
Haasib uses a **two-tier RBAC system** powered by Spatie Laravel Permission:

1. **Global Permissions**: Defined once, shared across all companies
2. **Company-Scoped Roles**: Each company has its own role instances (owner, admin, accountant, viewer)

### Key Principles
- ✅ **Route-based context**: URLs like `/{company}/customers` (not session-based)
- ✅ **Dot notation permissions**: `acct.customers.create` (scannable, hierarchical)
- ✅ **Teams feature**: Roles scoped to companies via `company_id` foreign key
- ✅ **UUID everywhere**: All IDs are UUIDs for consistency
- ✅ **RLS integration**: Works seamlessly with Row Level Security policies

---

## 📁 FILE STRUCTURE

### Core Infrastructure
```
build/
├── app/
│   ├── Constants/
│   │   └── Permissions.php              # Permission definitions (source of truth)
│   ├── Http/
│   │   ├── Middleware/
│   │   │   ├── IdentifyCompany.php      # Extract {company} from route
│   │   │   └── EnsureHasCompany.php     # Redirect if no companies
│   │   └── Requests/
│   │       └── BaseFormRequest.php      # hasCompanyPermission() helper
│   ├── Models/
│   │   ├── User.php                     # HasRoles trait + helpers
│   │   ├── Company.php                  # Company model
│   │   ├── Permission.php               # UUID-based Permission
│   │   ├── Role.php                     # UUID-based Role
│   │   └── Invitation.php               # Invitation system
│   ├── Providers/
│   │   ├── AuthServiceProvider.php      # Gate::before() super admin bypass
│   │   └── AppServiceProvider.php       # CurrentCompany singleton
│   ├── Services/
│   │   ├── CurrentCompany.php           # Track active company + Spatie team context
│   │   ├── CompanyService.php           # Member/role management
│   │   └── InvitationService.php        # Invite users to companies
│   └── Console/Commands/
│       ├── SyncPermissions.php          # rbac:sync-permissions
│       └── SyncRolePermissions.php      # rbac:sync-role-permissions
├── config/
│   ├── permission.php                   # Spatie config (teams=true)
│   ├── permissions.php                  # Permission registry
│   └── role-permissions.php             # Role-permission matrix
├── database/migrations/
│   ├── 2025_11_21_074439_create_permission_tables.php
│   ├── 2025_11_26_000001_add_teams_support_to_permission_tables.php
│   └── 2025_11_26_000002_create_invitations_table.php
└── bootstrap/providers.php              # AuthServiceProvider registered
```

---

## 🔑 PERMISSION SYSTEM

### Adding New Permissions

**Step 1**: Add to `app/Constants/Permissions.php`
```php
// In Permissions class
public const ACCT_ORDERS_VIEW = 'acct.orders.view';
public const ACCT_ORDERS_CREATE = 'acct.orders.create';
public const ACCT_ORDERS_UPDATE = 'acct.orders.update';
public const ACCT_ORDERS_DELETE = 'acct.orders.delete';

// In getAllByModule() method
'acct.orders' => [
    self::ACCT_ORDERS_VIEW,
    self::ACCT_ORDERS_CREATE,
    self::ACCT_ORDERS_UPDATE,
    self::ACCT_ORDERS_DELETE,
],
```

**Step 2**: Sync to database
```bash
php artisan rbac:sync-permissions
```

**Step 3**: Update role matrix (optional)
```php
// In config/role-permissions.php
'owner' => [
    // ... existing permissions
    Permissions::ACCT_ORDERS_VIEW,
    Permissions::ACCT_ORDERS_CREATE,
    Permissions::ACCT_ORDERS_UPDATE,
    Permissions::ACCT_ORDERS_DELETE,
],

'accountant' => [
    // ... existing permissions
    Permissions::ACCT_ORDERS_VIEW,
    Permissions::ACCT_ORDERS_CREATE,
    Permissions::ACCT_ORDERS_UPDATE,
    // No delete
],
```

**Step 4**: Sync roles
```bash
php artisan rbac:sync-role-permissions
```

### Permission Naming Convention

**Pattern**: `{module}.{resource}.{action}`

**Examples**:
- `acct.customers.view` - View customers in accounting module
- `acct.invoices.approve` - Approve invoices
- `ledger.entries.post` - Post journal entries
- `companies.manage_users` - Manage company users

**Modules**:
- `system` - System-wide operations
- `companies` - Company management
- `users` - User management
- `acct` - Accounting module
- `ledger` - General ledger
- `reporting` - Reports and analytics
- `hsp` - Hospitality module (future)
- `crm` - CRM module (future)

---

## 👥 ROLE SYSTEM

### Built-in Roles

**System Roles** (global, no company_id):
- `super_admin` - Bypasses all checks
- `systemadmin` - System administration
- `system_manager` - Limited system ops
- `system_auditor` - Audit-only access

**Company Roles** (scoped to company):
- `owner` - Full company access
- `admin` - Company management
- `accountant` - Accounting operations
- `viewer` - Read-only access

### Role Management

**Create company with owner role**:
```php
use App\Services\CompanyService;

$service = app(CompanyService::class);
$company = $service->createForUser($user, [
    'name' => 'Acme Corp',
    'slug' => 'acme-corp',
]);
// User automatically assigned 'owner' role for this company
```

**Add member to company**:
```php
$service->addMember($company, $newUser, 'accountant');
```

**Change user role**:
```php
$service->changeRole($company, $user, 'admin');
```

**Remove member**:
```php
$service->removeMember($company, $user);
// Sets is_active=false in pivot, doesn't delete
```

**Invite user via email**:
```php
use App\Services\InvitationService;

$invitationService = app(InvitationService::class);
$invitation = $invitationService->invite(
    $company,
    'user@example.com',
    'accountant',
    $inviterUser
);
```

---

## 🛡️ AUTHORIZATION PATTERNS

### In FormRequests

**Pattern**: Use `hasCompanyPermission()` in `authorize()` method

```php
use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class CreateCustomerRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::ACCT_CUSTOMERS_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ];
    }
}
```

### In Controllers

**Pattern**: Use `$this->authorize()` with policies

```php
public function update(UpdateCustomerRequest $request, Company $company, Customer $customer)
{
    // FormRequest already checked permissions
    // Policy checks ownership + business rules
    $this->authorize('update', $customer);
    
    $result = Bus::dispatch(
        'customer.update',
        $request->validated(),
        ServiceContext::fromRequest($request)
    );
    
    return response()->json(['success' => true, 'data' => $result]);
}
```

### Creating Policies

```php
use App\Constants\Permissions;
use App\Services\CurrentCompany;

class CustomerPolicy
{
    public function before($user, $ability)
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        return null;
    }

    public function view($user, Customer $customer): bool
    {
        if (!$this->belongsToCurrentCompany($customer)) {
            return false;
        }
        return $user->can(Permissions::ACCT_CUSTOMERS_VIEW);
    }

    public function update($user, Customer $customer): bool
    {
        if (!$this->belongsToCurrentCompany($customer)) {
            return false;
        }
        
        if (!$user->can(Permissions::ACCT_CUSTOMERS_UPDATE)) {
            return false;
        }
        
        // Business rule: can't edit archived customers
        if ($customer->status === 'archived') {
            return false;
        }
        
        return true;
    }

    private function belongsToCurrentCompany(Customer $customer): bool
    {
        $currentCompany = app(CurrentCompany::class)->get();
        return $currentCompany && $customer->company_id === $currentCompany->id;
    }
}
```

### In Frontend (Vue/Inertia)

```vue
<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

// Permissions shared via IdentifyCompany middleware
const can = (permission) => {
  return page.props.permissions.includes(permission)
}

const isOwner = computed(() => page.props.role === 'owner')
</script>

<template>
  <Button v-if="can('acct.customers.create')" @click="createCustomer">
    Create Customer
  </Button>
  
  <div v-if="isOwner">
    Owner-only content
  </div>
</template>
```

---

## 🔄 MIDDLEWARE FLOW

### Route Protection

```php
// In routes/web.php
Route::middleware(['auth', 'identify.company'])->group(function () {
    Route::get('/{company}/customers', [CustomerController::class, 'index'])
        ->name('customers.index');
        
    Route::post('/{company}/customers', [CustomerController::class, 'store'])
        ->name('customers.store');
});

// Ensure users have at least one company
Route::middleware(['auth', 'has.company'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

### Middleware Sequence

```
Request → auth
        ↓
    has.company (redirects if no companies)
        ↓
    identify.company (sets context from {company} param)
        ↓
    Sets CurrentCompany singleton
        ↓
    Sets Spatie team context
        ↓
    Shares permissions with Inertia
        ↓
    Controller → FormRequest → Policy
```

---

## 🚀 DEPLOYMENT GUIDE

### Fresh Installation

```bash
# 1. Run migrations
php artisan migrate

# 2. Sync global permissions
php artisan rbac:sync-permissions

# 3. Create a company (this creates roles)
php artisan tinker
>>> $user = User::first()
>>> $company = app(CompanyService::class)->createForUser($user, ['name' => 'Test Co'])

# 4. Or sync roles for existing companies
php artisan rbac:sync-role-permissions

# 5. Create super admin (optional)
php artisan tinker
>>> $admin = User::where('email', 'admin@example.com')->first()
>>> $admin->assignRole('super_admin')
```

### Existing Installation Upgrade

```bash
# 1. Backup database
pg_dump haasib_build > backup_pre_rbac.sql

# 2. Run new migration
php artisan migrate

# 3. Sync permissions
php artisan rbac:sync-permissions

# 4. Sync roles for ALL existing companies
php artisan rbac:sync-role-permissions

# 5. Assign roles to existing company users
php artisan tinker
>>> $companies = Company::all()
>>> foreach ($companies as $company) {
...     $users = $company->users;
...     foreach ($users as $user) {
...         $role = $user->pivot->role ?? 'viewer';
...         app(CompanyService::class)->addMember($company, $user, $role);
...     }
... }

# 6. Update routes to include {company} parameter
# Manual step: Update routes/web.php

# 7. Test authorization
php artisan test tests/Feature/RbacTest.php
```

---

## 🧪 TESTING

### Test Permission Checks

```php
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Services\CompanyService;

class RbacTest extends TestCase
{
    public function test_owner_can_manage_users()
    {
        $owner = User::factory()->create();
        $company = app(CompanyService::class)->createForUser($owner, [
            'name' => 'Test Company'
        ]);
        
        // Set company context
        app(CurrentCompany::class)->set($company);
        
        $this->assertTrue($owner->can('companies.manage_users'));
        $this->assertTrue($owner->can('acct.customers.create'));
    }
    
    public function test_viewer_cannot_create_customers()
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        
        $company = app(CompanyService::class)->createForUser($owner, [
            'name' => 'Test Company'
        ]);
        
        app(CompanyService::class)->addMember($company, $viewer, 'viewer');
        app(CurrentCompany::class)->set($company);
        
        $this->assertTrue($viewer->can('acct.customers.view'));
        $this->assertFalse($viewer->can('acct.customers.create'));
    }
}
```

---

## 🔧 TROUBLESHOOTING

### Common Issues

**1. "No company context set" error**
```bash
# Ensure IdentifyCompany middleware is applied
# Check route has {company} parameter
Route::get('/{company}/customers', ...)
```

**2. Permission check always returns false**
```bash
# Clear permission cache
php artisan permission:cache-reset

# Verify team context is set
app(CurrentCompany::class)->get() // Should return Company instance
```

**3. User has no roles after company creation**
```bash
# Re-run role sync
php artisan rbac:sync-role-permissions --company=<company_id>

# Manually assign role
$user->assignRole('owner')
```

**4. Super admin bypass not working**
```bash
# Ensure AuthServiceProvider is registered
# Check bootstrap/providers.php includes AuthServiceProvider
# Verify user has super_admin role (no company_id)
```

### Debug Commands

```bash
# List all permissions
php artisan tinker
>>> Permission::all()->pluck('name')

# List roles for a company
>>> app(PermissionRegistrar::class)->setPermissionsTeamId($companyId)
>>> Role::all()->pluck('name')

# Check user roles in company
>>> $user->getRoleNames()

# Check user permissions in company
>>> $user->getAllPermissions()->pluck('name')

# Verify company context
>>> app(CurrentCompany::class)->get()
```

---

## 📝 BEST PRACTICES

### DO's ✅

1. **Always use permission constants**
   ```php
   // ✅ Good
   $this->hasCompanyPermission(Permissions::ACCT_CUSTOMERS_CREATE)
   
   // ❌ Bad
   $this->hasCompanyPermission('acct.customers.create')
   ```

2. **Set team context before role operations**
   ```php
   // ✅ Good
   app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
   $user->assignRole('owner');
   
   // ❌ Bad (creates global role)
   $user->assignRole('owner');
   ```

3. **Use BaseFormRequest for authorization**
   ```php
   // ✅ Good
   class CreateRequest extends BaseFormRequest {
       public function authorize(): bool {
           return $this->hasCompanyPermission(Permissions::ACCT_CUSTOMERS_CREATE);
       }
   }
   
   // ❌ Bad
   if (!auth()->user()->can('acct.customers.create')) { ... }
   ```

4. **Combine permission + business logic checks**
   ```php
   // ✅ Good - Policy checks permission + business rules
   public function update($user, Invoice $invoice): bool {
       if (!$user->can(Permissions::ACCT_INVOICES_UPDATE)) {
           return false;
       }
       
       // Business rule
       if ($invoice->status === 'paid') {
           return false;
       }
       
       return true;
   }
   ```

### DON'Ts ❌

1. **Don't create roles without team context**
2. **Don't hardcode permission strings**
3. **Don't bypass BaseFormRequest authorization**
4. **Don't mix session-based and route-based context**
5. **Don't forget to sync permissions after adding new ones**

---

## 🔮 FUTURE ENHANCEMENTS

### Module-Specific Permissions

Each module can register its own permissions:

```php
// In modules/Hospitality/config/permissions.php
return [
    'hsp.reservations' => [
        'hsp.reservations.view',
        'hsp.reservations.create',
        'hsp.reservations.update',
        'hsp.reservations.cancel',
    ],
];

// Auto-loaded by ModuleServiceProvider
```

### Dynamic Roles

Create custom roles per company:

```php
$customRole = Role::create([
    'name' => 'sales_manager',
    'guard_name' => 'web',
    'company_id' => $company->id, // Team context
]);

$customRole->givePermissionTo([
    Permissions::ACCT_CUSTOMERS_VIEW,
    Permissions::ACCT_CUSTOMERS_CREATE,
    Permissions::ACCT_INVOICES_VIEW,
]);
```

### Permission Groups

Group related permissions for easier UI management:

```php
// In Permissions class
public static function getGroupedPermissions(): array
{
    return [
        'Customer Management' => [
            'view' => self::ACCT_CUSTOMERS_VIEW,
            'create' => self::ACCT_CUSTOMERS_CREATE,
            'update' => self::ACCT_CUSTOMERS_UPDATE,
            'delete' => self::ACCT_CUSTOMERS_DELETE,
        ],
        'Invoice Management' => [
            'view' => self::ACCT_INVOICES_VIEW,
            'create' => self::ACCT_INVOICES_CREATE,
            // ...
        ],
    ];
}
```

---

## 📚 RELATED DOCUMENTATION

- **Spatie Laravel Permission**: https://spatie.be/docs/laravel-permission/
- **Haasib CLAUDE.md**: Core architectural patterns
- **Migration Journal**: Implementation timeline and decisions
- **BaseFormRequest**: Authorization helper methods
- **ServiceContext**: RLS integration

---

**For Questions**: Check migration-journal.md for implementation history  
**For Updates**: Sync permissions after changes, update this guide  
**For Issues**: See Troubleshooting section above
