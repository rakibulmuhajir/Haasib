# RBAC Implementation Brief

## 1. Overview & Objectives

### 1.1 Purpose
Implement a comprehensive Role-Based Access Control (RBAC) system using Spatie's laravel-permission package with team-aware multi-tenancy support for company-based permission isolation.

### 1.2 Business Requirements
- Secure multi-company tenancy with permission isolation
- Role-based access control at company and system levels
- Audit logging for permission changes and access denials
- Frontend permission hydration for conditional UI rendering
- Performance-optimized permission checking

### 1.3 Success Criteria
- [ ] All system actions protected by appropriate permissions
- [ ] Company permission isolation prevents cross-tenant data access
- [ ] Frontend components conditionally render based on permissions
- [ ] Permission changes audited and logged
- [ ] Performance impact < 50ms on permission checks

## 2. Architecture & Data Model

### 2.1 Package Selection
**Decision**: Spatie laravel-permission v6.x with team support
- Industry standard with active maintenance
- Built-in team/tenant awareness
- Comprehensive caching layer
- Supports wildcard permissions

### 2.2 Schema Design

```sql
-- Core Permission Tables (Spatie - Default with Team Support)
permissions
- id (bigint, primary) - Using Spatie's default BIGINT
- name (varchar, unique) - e.g., "companies.currencies.manage"
- guard_name (varchar) - "web"
- created_at, updated_at

roles
- id (bigint, primary) - Using Spatie's default BIGINT
- name (varchar) - "owner", "admin", "manager", "employee", "viewer"
- guard_name (varchar) - "web"
- team_id (uuid, nullable, foreign key to companies) - Added by team migration
- created_at, updated_at

model_has_permissions
- permission_id (bigint, FK)
- model_type (varchar) - "App\Models\User"
- model_id (uuid) - user_id
- team_id (uuid, nullable, FK to companies) - Added by team migration

model_has_roles
- role_id (bigint, FK)
- model_type (varchar) - "App\Models\User"
- model_id (uuid) - user_id
- team_id (uuid, nullable, FK to companies) - Added by team migration

role_has_permissions
- permission_id (bigint, FK)
- role_id (bigint, FK)
- NOTE: No team_id needed here - roles already have team_id for scoping

-- Company Context (Existing)
companies
- id (uuid, primary)
- settings (jsonb) - stores company-specific permission overrides if needed
```

### 2.3 Permission Naming Convention
`{resource}.{action}.{scope}`

Examples:
- `companies.currencies.view` - View company currencies
- `companies.currencies.manage` - Add/edit/remove company currencies
- `system.currencies.manage` - Manage system-wide currencies (super-admin only)
- `ledger.entries.create` - Create journal entries
- `ledger.entries.approve` - Approve journal entries (requires higher role)
- `users.invite` - Invite users to company
- `users.roles.assign` - Assign roles within company

### 2.4 Role Hierarchy & Permissions

#### System Roles (team_id = null)
- **super_admin** - Full system access
  - `system.*` - All system permissions
  - `companies.*` - Access to all companies

#### Company Roles (team_id = company_id)
- **owner** - Full company access
  - `companies.*` - All company management
  - `users.*` - User management
  - `billing.*` - Billing and subscriptions
  - `ledger.*` - Full ledger access

- **admin** - Operational management
  - `companies.currencies.*` - Currency management
  - `companies.settings.*` - Company settings
  - `users.invite` - Invite users
  - `users.roles.assign` - Assign roles (except owner)
  - `ledger.entries.*` - Ledger management
  - `invoices.*` - Invoice management
  - `payments.*` - Payment processing

- **manager** - Day-to-day operations
  - `ledger.entries.view` - View ledger
  - `ledger.entries.create` - Create entries
  - `invoices.view` - View invoices
  - `invoices.create` - Create invoices
  - `payments.process` - Process payments
  - `reports.view` - View reports

- **employee** - Basic operations
  - `ledger.entries.view` - View ledger (read-only)
  - `invoices.view` - View assigned invoices
  - `customers.view` - View customer information

- **viewer** - Read-only access
  - `*.view` - View permissions only
  - No write or delete permissions

## 3. Implementation Plan

### 3.1 Phase 1: Foundation Setup
**Timeline**: 3-4 days

#### Tasks
1. **Package Installation & Configuration**
   - Install spatie/laravel-permission v6.x
   - Publish migrations
   - Add team_id columns to relevant tables
   - Configure permission guards

2. **Database Migration**
   ```bash
   # Use Spatie's published team migration
   php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="migrations"
   
   # Then run the team-specific migration
   php artisan migrate
   ```
   
   This will add team_id columns to the correct tables (roles, model_has_permissions, model_has_roles) as defined by Spatie's team support package.

3. **Permission & Role Seeder**
   - Create `database/seeders/RbacSeeder.php`
   - Define all system permissions
   - Create role hierarchy
   - Assign permissions to roles

### 3.2 Phase 2: Backend Integration
**Timeline**: 4-5 days

#### Tasks
1. **Middleware Implementation**
   ```php
   // app/Http/Middleware/RequirePermission.php
   class RequirePermission
   {
       public function handle($request, Closure $next, string $permission)
       {
           $company = $request->route('company');
           
           if (!$company) {
               abort(403, 'No company context provided');
           }
           
           // Set the team context for permission checking
           auth()->user()->setPermissionsTeamId($company->id);
           
           if (!$request->user()->hasPermissionTo($permission)) {
               abort(403, 'Unauthorized');
           }
           
           return $next($request);
       }
   }
   ```

2. **Request Context Enhancement**
   ```php
   // app/Http/Middleware/HandleInertiaRequests.php
   protected function sharePermissions($request)
   {
       $user = $request->user();
       $currentCompany = $this->getCurrentCompany($request);
       
       // Snapshot current team context using Spatie's helper
       $previousTeamId = getPermissionsTeamId();
       
       // Explicitly clear team context to get true system-wide permissions
       $user->setPermissionsTeamId(null);
       $systemPermissions = $user->getAllPermissions()->pluck('name');
       $systemRoles = $user->getRoleNames();
       
       // Get company-specific permissions by setting team context
       $companyPermissions = collect();
       $companyRoles = collect();
       
       if ($currentCompany) {
           // Set team context to get company-specific permissions
           $user->setPermissionsTeamId($currentCompany->id);
           $companyPermissions = $user->getAllPermissions()->pluck('name');
           $companyRoles = $user->getRoleNames();
           
           // Check management permissions while in company context
           $canManageCompany = $user->hasRole('owner') || $user->hasRole('admin');
       } else {
           $canManageCompany = false;
       }
       
       // Always restore original team context at the end
       $user->setPermissionsTeamId($previousTeamId);
       
       return [
           'permissions' => $systemPermissions,
           'companyPermissions' => $companyPermissions,
           'roles' => [
               'system' => $systemRoles->toArray(), // Use system roles collected earlier
               'company' => $companyRoles->toArray(),
           ],
           'canManageCompany' => $canManageCompany,
           'currentCompanyId' => $currentCompany?->id,
       ];
   }
   ```

3. **Policy Updates**
   - Update existing policies to use permission checks
   - Add company context to all policy methods
   - Implement team-aware authorization

### 3.3 Phase 3: Frontend Integration
**Timeline**: 3-4 days

#### Tasks
1. **Permission Composable**
   ```javascript
   // composables/usePermissions.js
   export function usePermissions() {
       const page = usePage();
       
       const has = (permission) => {
           return page.props.auth.companyPermissions?.includes(permission) ?? false;
       };
       
       const hasRole = (role) => {
           return page.props.auth.roles.company?.includes(role) ?? false;
       };
       
       const canManageCompany = computed(() => 
           page.props.auth.canManageCompany ?? false
       );
       
       return { has, hasRole, canManageCompany };
   }
   ```

2. **Component Updates**
   - Update CurrencySettings.vue with permission checks
   - Implement conditional rendering throughout app
   - Add permission-aware navigation

### 3.4 Phase 4: Audit & Security
**Timeline**: 2-3 days

#### Tasks
1. **Audit Logging**
   ```php
   // app/Listeners/LogPermissionChanges.php
   class LogPermissionChanges
   {
       public function handle($event)
       {
           activity()
               ->causedBy(auth()->user())
               ->performedOn($event->model)
               ->withProperties([
                   'action' => $event->action,
                   'permissions' => $event->permissions,
                   'company' => $this->getCurrentCompany(),
               ])
               ->log('permission_changed');
       }
   }
   ```

2. **Security Enhancements**
   - Implement permission caching with invalidation
   - Add rate limiting to permission checks
   - Create permission audit reports

## 4. Migration Strategy

### 4.1 Data Migration Plan
1. **Backup existing data**
2. **Create new permission structure**
3. **Migrate existing company_user roles**
   ```php
   // Migration script
   foreach (DB::table('auth.company_user')->get() as $relation) {
       $user = User::find($relation->user_id);
       $company = Company::find($relation->company_id);
       
       // Set team context before assigning role/permissions
       $user->setPermissionsTeamId($company->id);
       
       // Assign role (will automatically be scoped to current team)
       $user->assignRole($relation->role);
       
       // Grant base permissions based on role
       $permissions = $this->getBasePermissionsForRole($relation->role);
       foreach ($permissions as $permission) {
           $user->givePermissionTo($permission);
       }
       
       // Clear team context for next iteration
       $user->setPermissionsTeamId(null);
   }
   ```

### 4.2 Rollback Plan
- Keep original company_user table until migration verified
- Create rollback migration to restore previous state
- Implement feature flags for gradual rollout

## 5. Testing Strategy

### 5.1 Unit Tests
```php
// tests/Unit/Rbac/RolePermissionTest.php
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;
    
    /** @test */
    public function owner_can_manage_company_currencies()
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create();
        
        // Set team context and assign role
        $owner->setPermissionsTeamId($company->id);
        $owner->assignRole('owner');
        
        // Check permission within team context
        $this->assertTrue(
            $owner->hasPermissionTo('companies.currencies.manage')
        );
        
        // Clear context for clean state
        $owner->setPermissionsTeamId(null);
    }
    
    /** @test */
    public function employee_cannot_assign_roles()
    {
        $employee = User::factory()->create();
        $company = Company::factory()->create();
        
        // Set team context and assign role
        $employee->setPermissionsTeamId($company->id);
        $employee->assignRole('employee');
        
        $this->assertFalse(
            $employee->hasPermissionTo('users.roles.assign')
        );
        
        // Clear context for clean state
        $employee->setPermissionsTeamId(null);
    }
    
    /** @test */
    public function permissions_isolated_between_companies()
    {
        $user = User::factory()->create();
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        
        // Assign admin role to company1
        $user->setPermissionsTeamId($company1->id);
        $user->assignRole('admin');
        
        // Switch context to company2 and assign viewer role
        $user->setPermissionsTeamId($company2->id);
        $user->assignRole('viewer');
        
        // Test permissions in company1 context
        $user->setPermissionsTeamId($company1->id);
        $this->assertTrue(
            $user->hasPermissionTo('companies.currencies.manage')
        );
        
        // Test permissions in company2 context
        $user->setPermissionsTeamId($company2->id);
        $this->assertFalse(
            $user->hasPermissionTo('companies.currencies.manage')
        );
        
        // Clear context for clean state
        $user->setPermissionsTeamId(null);
    }
}
```

### 5.2 Feature Tests
```php
// tests/Feature/Rbac/CurrencyManagementTest.php
class CurrencyManagementTest extends TestCase
{
    /** @test */
    public function admin_can_add_currencies_to_company()
    {
        $admin = User::factory()->create();
        $company = Company::factory()->create();
        
        // Set team context and assign role
        $admin->setPermissionsTeamId($company->id);
        $admin->assignRole('admin');
        $admin->setPermissionsTeamId(null); // Clear for test
        
        $response = $this->actingAs($admin)
            ->post("/api/companies/{$company->id}/currencies", [
                'currency_code' => 'EUR',
            ]);
            
        $response->assertStatus(201);
    }
    
    /** @test */
    public function viewer_cannot_add_currencies()
    {
        $viewer = User::factory()->create();
        $company = Company::factory()->create();
        
        // Set team context and assign role
        $viewer->setPermissionsTeamId($company->id);
        $viewer->assignRole('viewer');
        $viewer->setPermissionsTeamId(null); // Clear for test
        
        $response = $this->actingAs($viewer)
            ->post("/api/companies/{$company->id}/currencies", [
                'currency_code' => 'EUR',
            ]);
            
        $response->assertStatus(403);
    }
}
```

### 5.3 Browser Tests
```php
// tests/Browser/Rbac/PermissionIsolationTest.php
class PermissionIsolationTest extends DuskTestCase
{
    /** @test */
    public function currency_management_hidden_for_viewers()
    {
        $this->browse(function ($browser) {
            $viewer = User::factory()->create();
            $company = Company::factory()->create();
            
            // Set team context and assign role
            $viewer->setPermissionsTeamId($company->id);
            $viewer->assignRole('viewer');
            $viewer->setPermissionsTeamId(null); // Clear context for clean state
            
            $browser->loginAs($viewer)
                ->visit('/settings?group=currency')
                ->assertMissing('[data-test="add-currency-button"]')
                ->assertMissing('[data-test="manage-system-currencies"]');
        });
    }
}
```

## 6. Performance Considerations

### 6.1 Caching Strategy
- Enable Spatie's permission caching
- Cache user permissions per company context
- Implement cache invalidation on role/permission changes
- Use Redis for distributed cache if available

### 6.2 Database Optimization
- Add composite indexes on (team_id, model_id) columns
- Eager load permissions when possible
- Use database-level constraints for data integrity

## 7. Monitoring & Metrics

### 7.1 Key Metrics
- Permission check latency (target: < 50ms)
- Cache hit rate (target: > 95%)
- Permission audit log volume
- Access denial rate by role/resource

### 7.2 Monitoring Implementation
```php
// app/Http/Middleware/LogPermissionChecks.php
class LogPermissionChecks
{
    public function handle($request, Closure $next, $permission)
    {
        $start = microtime(true);
        $result = $next($request);
        $duration = microtime(true) - $start;
        
        if ($duration > 0.05) { // Log slow checks
            Log::warning('Slow permission check', [
                'permission' => $permission,
                'duration' => $duration,
                'user' => auth()->id(),
                'company' => $request->route('company'),
            ]);
        }
        
        return $result;
    }
}
```

## 8. Acceptance Criteria

### 8.1 Functional Requirements
- [ ] All system endpoints protected by appropriate permissions
- [ ] Company permission isolation enforced
- [ ] Frontend UI elements conditionally render based on permissions
- [ ] Role assignment and revocation works correctly
- [ ] Permission inheritance through roles functions

### 8.2 Non-Functional Requirements
- [ ] Permission checks complete within 50ms
- [ ] Permission changes take effect immediately
- [ ] Comprehensive audit trail maintained
- [ ] Zero security vulnerabilities related to authorization
- [ ] 100% test coverage for permission logic

### 8.3 Rollout Criteria
- [ ] All existing functionality preserved
- [ ] Migration completes without data loss
- [ ] Performance impact within acceptable limits
- [ ] Security audit passed
- [ ] Documentation complete

## 9. Risks & Mitigations

### 9.1 Technical Risks
| Risk | Impact | Likelihood | Mitigation |
|------|---------|------------|------------|
| Performance degradation | High | Medium | Implement caching, add indexes, monitor latency |
| Migration data loss | Critical | Low | Full backup, run in maintenance window, verify counts |
| Permission bypass | Critical | Low | Comprehensive testing, security review, audit logging |

### 9.2 Business Risks
| Risk | Impact | Likelihood | Mitigation |
|------|---------|------------|------------|
| User access issues | High | Medium | Gradual rollout, support team training, rollback plan |
| Complex permission management | Medium | High | Simple UI, role templates, documentation |

## 10. Implementation Guidelines & Best Practices

### 10.1 How to Extend RBAC for New Features

When adding new modules or features, follow this pattern to extend the RBAC system:

#### 1. Define New Permissions
Create permissions in your seeder:

```php
// database/seeders/PermissionsSeeder.php
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create the new permission
        $perm = Permission::firstOrCreate(['name' => 'crm.contacts.create']);

        // Assign it to the appropriate roles
        $adminRole = Role::firstWhere('name', 'admin');
        $adminRole?->givePermissionTo($perm);

        $ownerRole = Role::firstWhere('name', 'owner');
        $ownerRole?->givePermissionTo($perm);
    }
}
```

#### 2. Protect Backend Routes
Use permission middleware:

```php
// routes/web.php or routes/api.php
Route::post('/contacts', [ContactController::class, 'store'])
    ->middleware(['auth', 'permission:crm.contacts.create'])
    ->name('contacts.store');
```

#### 3. Authorize in Controllers
Add authorization checks:

```php
// app/Http/Controllers/Crm/ContactController.php
public function store(StoreContactRequest $request)
{
    $this->authorize('create', Contact::class);
    // ... logic to create the contact
}
```

#### 4. Conditionally Render UI Elements
Check permissions in Vue components:

```javascript
// resources/js/components/ContactManager.vue
<script setup>
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const canCreateContacts = computed(() => {
  return page.props.auth.companyPermissions?.includes('crm.contacts.create') ?? false;
});
</script>

<template>
  <button v-if="canCreateContacts" @click="showCreateModal">
    Create Contact
  </button>
</template>
```

### 10.2 Permission Naming Conventions

Follow the established pattern: `{resource}.{action}.{scope}`

**Examples:**
- `companies.currencies.view` - View company currencies
- `companies.currencies.manage` - Add/edit/remove company currencies  
- `system.currencies.manage` - Manage system-wide currencies (super-admin only)
- `ledger.entries.create` - Create journal entries
- `ledger.entries.approve` - Approve journal entries (requires higher role)
- `users.invite` - Invite users to company
- `users.roles.assign` - Assign roles within company

### 10.3 Testing New Permissions

Always test new permissions with multiple roles:

```php
/** @test */
public function admin_can_create_contacts()
{
    $admin = User::factory()->create();
    $company = Company::factory()->create();
    
    $admin->setPermissionsTeamId($company->id);
    $admin->assignRole('admin');
    $admin->setPermissionsTeamId(null);
    
    $this->actingAs($admin)
        ->post('/contacts', $contactData)
        ->assertSuccessful();
}

/** @test */
public function viewer_cannot_create_contacts()
{
    $viewer = User::factory()->create();
    $company = Company::factory()->create();
    
    $viewer->setPermissionsTeamId($company->id);
    $viewer->assignRole('viewer');
    $viewer->setPermissionsTeamId(null);
    
    $this->actingAs($viewer)
        ->post('/contacts', $contactData)
        ->assertForbidden();
}
```

## 11. Timeline & Resources

### 11.1 Project Timeline
- **Week 1**: Phase 1 - Foundation Setup
- **Week 2**: Phase 2 - Backend Integration  
- **Week 3**: Phase 3 - Frontend Integration
- **Week 4**: Phase 4 - Audit & Security + Testing

### 11.2 Resource Requirements
- **Backend Developer**: 1.0 FTE for 2 weeks
- **Frontend Developer**: 0.5 FTE for 1 week
- **QA Engineer**: 0.5 FTE for 1 week
- **DevOps**: 0.25 FTE for deployment support

### 11.3 Deliverables
1. Updated migration files with team support
2. RbacSeeder with roles and permissions
3. Middleware for permission checking
4. Frontend permission composables
5. Updated components with permission checks
6. Comprehensive test suite
7. Documentation and runbooks

---

**Document Version**: 1.0  
**Last Updated**: 2025-09-30  
**Next Review**: 2025-10-07