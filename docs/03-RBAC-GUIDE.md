# RBAC System Guide

**Last Updated**: 2025-02-01  
**Purpose**: Role-Based Access Control implementation and usage guide  
**Audience**: Developers implementing authorization

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Core Concepts](#2-core-concepts)
3. [File Structure](#3-file-structure)
4. [Permission System](#4-permission-system)
5. [Role System](#5-role-system)
6. [Authorization Patterns](#6-authorization-patterns)
7. [God-Mode Users](#7-god-mode-users)
8. [Middleware Flow](#8-middleware-flow)
9. [Deployment & Setup](#9-deployment--setup)
10. [Testing & Debugging](#10-testing--debugging)

---

## 1. System Overview

Haasib uses a **two-tier RBAC system** powered by Spatie Laravel Permission with Teams enabled:

1. **Global Permissions**: Defined once, shared across all companies
2. **Company-Scoped Roles**: Each company has its own role instances (owner, admin, accountant, viewer)

### Key Principles

- **Route-based context**: URLs like `/{company}/customers` (not session-based)
- **Dot notation permissions**: `acct.customers.create` (scannable, hierarchical)
- **Teams feature**: Roles scoped to companies via `company_id` foreign key
- **UUID everywhere**: All IDs are UUIDs for consistency
- **RLS integration**: Works seamlessly with Row-Level Security policies

---

## 2. Core Concepts

### Permission

An action you can take, identified by a dot-notation string:

```
acct.customers.view        → View customers in accounting
acct.invoices.create       → Create invoices
acct.invoices.approve      → Approve invoices (workflow)
system.manage_users        → Manage company users
```

### Role

A named collection of permissions assigned to users within a company:

- `owner` - Full company access
- `admin` - Company management
- `accountant` - Accounting operations  
- `viewer` - Read-only access

### Company Context

Every permission check happens within a company context:

```php
// User has 'acct.invoices.create' in company ABC
$user->can('acct.invoices.create')  // true within ABC context
                                    // false within XYZ context (unless assigned there too)
```

---

## 3. File Structure

```
app/
├── Constants/
│   └── Permissions.php              # All permission constants (source of truth)
├── Http/
│   ├── Middleware/
│   │   ├── IdentifyCompany.php      # Sets company context + team
│   │   └── EnsureHasCompany.php     # Redirect if no companies
│   └── Requests/
│       └── BaseFormRequest.php      # hasCompanyPermission() helper
├── Models/
│   ├── User.php                     # HasRoles trait + helpers
│   ├── Company.php                  # Company model
│   ├── Permission.php               # UUID-based Permission
│   └── Role.php                     # UUID-based Role
├── Providers/
│   └── AuthServiceProvider.php      # Gate::before() super admin bypass
├── Services/
│   ├── CurrentCompany.php           # Tracks active company + team context
│   ├── CompanyService.php           # Member/role management
│   └── InvitationService.php        # Invite users to companies
└── Console/Commands/
    ├── RbacSyncPermissions.php      # rbac:sync-permissions
    └── RbacSyncRolePermissions.php  # rbac:sync-role-permissions

config/
├── permission.php                   # Spatie config (teams=true)
└── role-permissions.php             # Role-permission matrix
```

---

## 4. Permission System

### Adding New Permissions

**Step 1**: Add constant to `app/Constants/Permissions.php`

```php
class Permissions
{
    // Existing permissions...
    
    // New permission
    public const CREDIT_NOTE_CREATE = 'credit_note.create';
    public const CREDIT_NOTE_VIEW = 'credit_note.view';
}
```

**Step 2**: Add to role matrix in `config/role-permissions.php`

```php
return [
    'owner' => [
        // ... existing permissions
        Permissions::CREDIT_NOTE_CREATE,
        Permissions::CREDIT_NOTE_VIEW,
    ],
    'admin' => [
        // ... existing permissions
        Permissions::CREDIT_NOTE_CREATE,
        Permissions::CREDIT_NOTE_VIEW,
    ],
    'accountant' => [
        // ... existing permissions
        Permissions::CREDIT_NOTE_CREATE,
        Permissions::CREDIT_NOTE_VIEW,
    ],
    'viewer' => [
        // ... existing permissions
        Permissions::CREDIT_NOTE_VIEW,
    ],
];
```

**Step 3**: Sync to database

```bash
php artisan rbac:sync-permissions        # Creates permission records
php artisan rbac:sync-role-permissions   # Assigns to roles
```

### Permission Naming Convention

**Pattern**: `{module}.{resource}.{action}`

**Modules**:
- `system` - System-wide operations
- `companies` - Company management
- `users` - User management
- `acct` - Accounting module
- `bank` - Banking module
- `inv` - Inventory module
- `pay` - Payroll module
- `crm` - CRM module

**Examples**:
```
acct.customers.view
acct.customers.create
acct.customers.update
acct.customers.delete

acct.invoices.view
acct.invoices.create
acct.invoices.approve
acct.invoices.void

system.manage_users
system.manage_roles
system.view_audit_logs
```

---

## 5. Role System

### Built-in Roles

**System Roles** (global, no company_id):
- `super_admin` - Bypasses all checks (UUID prefix `00000000-0000-0000-0000-000000000000`)
- `system_admin` - System administration
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
// User automatically assigned 'owner' role
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

$invitation = app(InvitationService::class)->invite(
    $company,
    'user@example.com',
    'accountant',
    $inviterUser
);
```

---

## 6. Authorization Patterns

### 6.1 FormRequest Authorization

Use `hasCompanyPermission()` in FormRequest classes:

```php
<?php

namespace App\Http\Requests;

use App\Constants\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::ACCT_INVOICES_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|uuid|exists:acct.customers,id',
            'due_date' => 'required|date|after:today',
        ];
    }
}
```

### 6.2 Controller Authorization

Use `$this->authorize()` with policies for business rules:

```php
public function update(UpdateInvoiceRequest $request, Invoice $invoice)
{
    // FormRequest already checked permission
    // Policy checks ownership + business rules
    $this->authorize('update', $invoice);
    
    $result = Bus::dispatch('invoice.update', $request->validated());
    
    return response()->json(['success' => true, 'data' => $result]);
}
```

### 6.3 Policy Creation

```php
<?php

namespace App\Policies;

use App\Constants\Permissions;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CurrentCompany;

class InvoicePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        return null;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (!$this->belongsToCurrentCompany($invoice)) {
            return false;
        }
        return $user->can(Permissions::ACCT_INVOICES_VIEW);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (!$this->belongsToCurrentCompany($invoice)) {
            return false;
        }
        
        if (!$user->can(Permissions::ACCT_INVOICES_UPDATE)) {
            return false;
        }
        
        // Business rule: can't edit paid invoices
        if ($invoice->status === 'paid') {
            return false;
        }
        
        return true;
    }

    private function belongsToCurrentCompany(Invoice $invoice): bool
    {
        $currentCompany = app(CurrentCompany::class)->get();
        return $currentCompany && $invoice->company_id === $currentCompany->id;
    }
}
```

### 6.4 Frontend Authorization (Vue)

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

// Check permission
const can = (permission: string): boolean => {
  return page.props.permissions?.includes(permission) ?? false
}

// Check role
const isOwner = computed(() => page.props.role === 'owner')
</script>

<template>
  <Button v-if="can('acct.customers.create')" @click="createCustomer">
    Create Customer
  </Button>
  
  <div v-if="isOwner">
    Owner-only settings
  </div>
</template>
```

### 6.5 Blade Template Authorization

```blade
@can(Permissions::ACCT_INVOICES_CREATE)
    <a href="{{ route('invoices.create', $company) }}">Create Invoice</a>
@endcan

@can(Permissions::ACCT_INVOICES_APPROVE)
    <button wire:click="approve">Approve</button>
@endcan
```

---

## 7. God-Mode Users

Super admin users bypass all permission checks.

### Identification

Users with UUID prefix `00000000-0000-0000-0000-` are god-mode users:

- `00000000-0000-0000-0000-000000000000` - Super admin
- `00000000-0000-0000-0000-000000000001` - System admin
- `00000000-0000-0000-0000-000000000002` - System admin 2

### Implementation

```php
// app/Providers/AuthServiceProvider.php

public function boot(): void
{
    Gate::before(function (User $user, string $ability) {
        // Super admin bypass
        if ($user->isSuperAdmin()) {
            return true;
        }
        return null;
    });
}
```

### User Model Helper

```php
// app/Models/User.php

public function isSuperAdmin(): bool
{
    return str_starts_with($this->id, '00000000-0000-0000-0000-');
}
```

### Characteristics

- No company memberships required
- Bypass all permission checks via `Gate::before()`
- Can access any company via direct URL
- Created manually or via seeders

---

## 8. Middleware Flow

### Route Protection

```php
// routes/web.php

// Company-scoped routes
Route::middleware(['auth', 'identify.company'])->group(function () {
    Route::get('/{company}/invoices', [InvoiceController::class, 'index']);
    Route::post('/{company}/invoices', [InvoiceController::class, 'store']);
});

// Routes requiring company membership
Route::middleware(['auth', 'has.company'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

### Middleware Sequence

```
Request
    ↓
auth (Laravel Fortify)
    ↓
has.company (redirects if no companies)
    ↓
identify.company
    - Extract {company} from route
    - Validate user can access
    - Set CurrentCompany singleton
    - Set Spatie team context
    - Set RLS session variable
    ↓
Share permissions with Inertia
    ↓
Controller → FormRequest → Policy
```

### Middleware: IdentifyCompany

```php
<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdentifyCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $companySlug = $request->route('company');
        
        if (!$companySlug) {
            return $next($request);
        }

        $company = Company::where('slug', $companySlug)->firstOrFail();
        
        // Set company context
        app(CurrentCompany::class)->set($company);
        
        // Set Spatie team context
        auth()->user()?->setTeam($company);
        
        // Set RLS session variable
        DB::statement(
            "SET app.current_company_id = ?",
            [$company->id]
        );

        return $next($request);
    }
}
```

---

## 9. Deployment & Setup

### Fresh Installation

```bash
# 1. Run migrations
php artisan migrate

# 2. Sync global permissions
php artisan rbac:sync-permissions

# 3. Create a company (auto-creates roles)
php artisan tinker
>>> $user = User::first()
>>> $company = app(CompanyService::class)->createForUser($user, ['name' => 'Test Co'])

# 4. Create super admin (optional)
>>> $admin = User::where('email', 'admin@example.com')->first()
>>> $admin->assignRole('super_admin')
```

### Existing Installation Upgrade

```bash
# 1. Backup database
pg_dump haasib_production > backup_pre_rbac.sql

# 2. Run migrations
php artisan migrate

# 3. Sync permissions
php artisan rbac:sync-permissions

# 4. Sync roles for all existing companies
php artisan rbac:sync-role-permissions

# 5. Test authorization
php artisan test tests/Feature/RbacTest.php
```

### CLI Commands

```bash
# Sync all permissions from config
php artisan rbac:sync-permissions

# Sync role permissions for all companies
php artisan rbac:sync-role-permissions

# Sync for specific company
php artisan rbac:sync-role-permissions --company=<uuid>

# Reset permission cache
php artisan permission:cache-reset
```

---

## 10. Testing & Debugging

### Testing Permissions

```php
<?php

namespace Tests\Feature;

use App\Constants\Permissions;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\CurrentCompany;
use Tests\TestCase;

class RbacTest extends TestCase
{
    public function test_owner_can_create_invoices()
    {
        $owner = User::factory()->create();
        $company = app(CompanyService::class)->createForUser($owner, [
            'name' => 'Test Company'
        ]);
        
        app(CurrentCompany::class)->set($company);
        
        $this->assertTrue($owner->can(Permissions::ACCT_INVOICES_CREATE));
    }
    
    public function test_viewer_cannot_create_invoices()
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        
        $company = app(CompanyService::class)->createForUser($owner, [
            'name' => 'Test Company'
        ]);
        
        app(CompanyService::class)->addMember($company, $viewer, 'viewer');
        app(CurrentCompany::class)->set($company);
        
        $this->assertFalse($viewer->can(Permissions::ACCT_INVOICES_CREATE));
        $this->assertTrue($viewer->can(Permissions::ACCT_INVOICES_VIEW));
    }
}
```

### Debug Commands

```bash
# List all permissions
php artisan tinker
>>> Permission::all()->pluck('name')

# List roles for a company
>>> app(PermissionRegistrar::class)->setPermissionsTeamId($companyId)
>>> Role::all()->pluck('name')

# Check user roles in current context
>>> auth()->user()->getRoleNames()

# Check user permissions
>>> auth()->user()->getAllPermissions()->pluck('name')

# Verify company context
>>> app(CurrentCompany::class)->get()

# Check if user is super admin
>>> auth()->user()->isSuperAdmin()
```

### Common Issues

**"No company context set" error**:
- Ensure `identify.company` middleware is applied
- Check route has `{company}` parameter

**Permission check always returns false**:
- Clear permission cache: `php artisan permission:cache-reset`
- Verify team context is set
- Check user has role in company

**Super admin bypass not working**:
- Ensure `AuthServiceProvider` is registered
- Check user has `super_admin` role (no company_id)

---

## Best Practices

### DO

1. **Always use permission constants**
   ```php
   // ✅ Good
   $this->hasCompanyPermission(Permissions::ACCT_INVOICES_CREATE)
   
   // ❌ Bad
   $this->hasCompanyPermission('acct.invoices.create')
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
           return $this->hasCompanyPermission(Permissions::ACCT_INVOICES_CREATE);
       }
   }
   ```

4. **Combine permission + business logic in policies**
   ```php
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

### DON'T

1. Don't create roles without team context
2. Don't hardcode permission strings
3. Don't bypass BaseFormRequest authorization
4. Don't mix session-based and route-based context
5. Don't forget to sync permissions after adding new ones

---

## Related Documentation

- [01-ARCHITECTURE.md](01-ARCHITECTURE.md) - System architecture
- [02-DEVELOPMENT-STANDARDS.md](02-DEVELOPMENT-STANDARDS.md) - Coding standards
- `app/Constants/Permissions.php` - Permission definitions
- `config/role-permissions.php` - Role matrix
