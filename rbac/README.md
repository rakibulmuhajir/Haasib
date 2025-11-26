# RBAC System for Haasib

Multi-tenant, company-based Role-Based Access Control system using Laravel + Spatie Permissions.

## Architecture Summary

| Component | Scope | Description |
|-----------|-------|-------------|
| **Permissions** | Global | Defined once, shared across all companies |
| **Roles** | Per-company | Each company has its own owner, accountant, viewer |
| **Role-Permission mapping** | Per-company | Uses Spatie teams feature |
| **Company context** | Route parameter | `/{company}/invoices` not session |

## Roles

| Role | Description |
|------|-------------|
| `owner` | Full access to everything including company management |
| `accountant` | Can do accounting work, limited company access |
| `viewer` | Read-only access |
| `super_admin` | Global role, bypasses all permission checks |

## Installation

### 1. Install Spatie Permissions

```bash
composer require spatie/laravel-permission
```

### 2. Copy Files

Copy the files from this package to your Laravel project:

- `config/permission.php` → Override Spatie default
- `config/permissions.php` → Your permission definitions
- `config/role-permissions.php` → Role-permission matrix
- `app/Models/*` → Models
- `app/Services/*` → Services
- `app/Http/Middleware/*` → Middleware
- `app/Policies/*` → Policies
- `app/Console/Commands/*` → Artisan commands
- `app/Providers/*` → Service providers
- `database/migrations/*` → Migrations
- `database/seeders/*` → Seeders

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Seed Permissions

```bash
php artisan db:seed --class=RbacSeeder
```

Or use the sync commands:

```bash
php artisan app:sync-permissions
php artisan app:sync-role-permissions
```

### 5. Register Middleware

**Laravel 11** - Add to `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'identify.company' => \App\Http\Middleware\IdentifyCompany::class,
        'has.company' => \App\Http\Middleware\EnsureHasCompany::class,
    ]);
})
```

**Laravel 10** - Add to `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    'identify.company' => \App\Http\Middleware\IdentifyCompany::class,
    'has.company' => \App\Http\Middleware\EnsureHasCompany::class,
];
```

### 6. Update AuthServiceProvider

Make sure the `Gate::before` callback is registered for super admin bypass.

### 7. Create Super Admin (Optional)

```bash
php artisan app:create-super-admin admin@example.com
```

## Usage

### Creating a Company

```php
use App\Services\CompanyService;

$service = app(CompanyService::class);
$company = $service->createForUser($user, ['name' => 'My Company']);
```

### Checking Permissions in Controllers

```php
public function update(Request $request, Company $company, Invoice $invoice)
{
    $this->authorize('update', $invoice);
    
    // or manually:
    if (!$request->user()->can('accounts_invoice_update')) {
        abort(403);
    }
}
```

### Checking Permissions in Blade

```blade
@can('accounts_invoice_create')
    <button>Create Invoice</button>
@endcan
```

### Checking Permissions in Vue (Inertia)

```vue
<script setup>
import { usePermissions } from '@/composables/usePermissions'
const { can, isOwner } = usePermissions()
</script>

<template>
    <button v-if="can('accounts_invoice_create')">Create Invoice</button>
    <div v-if="isOwner">Owner only content</div>
</template>
```

### Inviting Members

```php
use App\Services\InvitationService;

$service = app(InvitationService::class);
$invitation = $service->invite($company, 'user@example.com', 'accountant', $currentUser);
```

### Changing Roles

```php
use App\Services\CompanyService;

$service = app(CompanyService::class);
$service->changeRole($company, $user, 'viewer');
```

## Adding New Permissions

1. Add to `config/permissions.php`:

```php
'accounts' => [
    'payment' => [
        'accounts_payment_view',
        'accounts_payment_create',
        'accounts_payment_update',
        'accounts_payment_delete',
    ],
],
```

2. Add to role matrix in `config/role-permissions.php`:

```php
'owner' => [
    // ... existing
    'accounts_payment_view',
    'accounts_payment_create',
    'accounts_payment_update',
    'accounts_payment_delete',
],
'accountant' => [
    // ... existing
    'accounts_payment_view',
    'accounts_payment_create',
    'accounts_payment_update',
],
```

3. Sync:

```bash
php artisan app:sync-permissions
php artisan app:sync-role-permissions
```

## API Authentication (Mobile)

The system supports Sanctum for mobile API authentication.

```php
// Login and get token
$token = $user->createToken('mobile-app')->plainTextToken;

// Use in requests
Authorization: Bearer {token}
```

## Audit Trail

Critical actions are logged automatically:
- Company created
- Member added/removed
- Role changed
- Invitation sent/accepted

Query audits:

```php
Audit::forCompany($company)->recent(30)->get();
```

## Important Notes

1. **Permissions are GLOBAL** - never created per-company
2. **Roles are PER-COMPANY** - scoped via Spatie teams
3. **Company context is set via route** - middleware handles this
4. **Status rules are in policies** - not separate permissions
5. **Super admin bypasses everything** - via Gate::before

## File Structure

```
app/
├── Console/Commands/
│   ├── SyncPermissions.php
│   ├── SyncRolePermissions.php
│   └── CreateSuperAdmin.php
├── Http/Middleware/
│   ├── IdentifyCompany.php
│   └── EnsureHasCompany.php
├── Models/
│   ├── Company.php
│   ├── User.php
│   ├── Invitation.php
│   └── Audit.php
├── Policies/
│   ├── InvoicePolicy.php
│   └── CompanyPolicy.php
├── Providers/
│   ├── AppServiceProvider.php
│   └── AuthServiceProvider.php
├── Services/
│   ├── CurrentCompany.php
│   ├── AuditService.php
│   ├── CompanyService.php
│   └── InvitationService.php
└── Notifications/
    └── InvitationNotification.php

config/
├── permission.php
├── permissions.php
└── role-permissions.php

database/
├── migrations/
│   ├── create_companies_table.php
│   ├── create_company_user_table.php
│   ├── create_invitations_table.php
│   └── create_audits_table.php
└── seeders/
    └── RbacSeeder.php

resources/js/
├── composables/
│   └── usePermissions.js
└── directives/
    └── can.js
```
