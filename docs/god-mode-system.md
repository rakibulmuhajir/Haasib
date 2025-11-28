# God-Mode System: Super Admin & System Admins

**Last Updated**: 2025-11-27  
**Purpose**: Complete guide to god-mode users, sentinel UUIDs, and system administration

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Sentinel UUID System](#sentinel-uuid-system)
4. [Authorization Flow](#authorization-flow)
5. [Creating System Admins](#creating-system-admins)
6. [Audit Trail](#audit-trail)
7. [Security Considerations](#security-considerations)
8. [Troubleshooting](#troubleshooting)

---

## Overview

Haasib uses a **sentinel UUID system** for god-mode users (super admins and system admins). This approach provides:

✅ **Accountability**: Each system admin has a unique, traceable ID  
✅ **Simplicity**: No complex role hierarchies for global access  
✅ **Audit Trail**: Know exactly which admin performed which action  
✅ **Scalability**: Can create unlimited system admins  

### Key Concepts

- **God-Mode Users**: Users with unrestricted access to all companies and features
- **Sentinel UUIDs**: Sequential UUIDs starting with `00000000-0000-0000-0000-`
- **No Companies**: God-mode users don't belong to any company
- **No Roles**: Authorization via `Gate::before()`, not Spatie roles

---

## Architecture

### User Types Comparison

| Type | User ID Pattern | Companies | Roles | Access |
|------|----------------|-----------|-------|--------|
| **Super Admin** | `00000000-0000-0000-0000-000000000000` | None | None | Everything + create system admins |
| **System Admin #1** | `00000000-0000-0000-0000-000000000001` | None | None | Everything except creating system admins |
| **System Admin #2** | `00000000-0000-0000-0000-000000000002` | None | None | Everything except creating system admins |
| **Regular User** | Random UUID (e.g., `019ac37a-...`) | 1+ companies | owner/admin/accountant/viewer | Company-scoped |

### Database Structure

```sql
-- God-mode user (Super Admin)
SELECT * FROM auth.users WHERE id = '00000000-0000-0000-0000-000000000000';
-- Result: name='Super Admin', email='admin@haasib.com'
-- NO records in company_user
-- NO records in model_has_roles

-- Regular user
SELECT * FROM auth.users WHERE id = '019ac37a-8e3f-7277-9664-9060820ba23a';
-- Result: name='Bob', email='bob@company.com'
-- HAS records in company_user (belongs to companies)
-- HAS records in model_has_roles (has roles in companies)
```

---

## Sentinel UUID System

### UUID Format

```
00000000-0000-0000-0000-XXXXXXXXXXXXXXXXX
│                       │
│                       └─ Sequential number (18 digits)
│
└─ Sentinel prefix (always zeros)
```

### Examples

```php
// Super Admin (all zeros)
00000000-0000-0000-0000-000000000000

// System Admin #1
00000000-0000-0000-0000-000000000001

// System Admin #2
00000000-0000-0000-0000-000000000002

// System Admin #42
00000000-0000-0000-0000-000000000042

// System Admin #999
00000000-0000-0000-0000-000000000999

// Regular User (random UUID, NOT a sentinel)
019ac37a-8e3f-7277-9664-9060820ba23a
```

### Why This Works

1. **UUID Collision Prevention**: Random UUIDs will never start with `00000000` (probability: ~1 in 2^128)
2. **Easy Detection**: Check if `user_id LIKE '00000000-0000-0000-0000-%'`
3. **Sequential Assignment**: Easy to track "System Admin #1", "System Admin #2", etc.
4. **Audit Trail**: Every action is tied to a specific admin number

---

## Authorization Flow

### 1. Gate::before() Check

**Location**: `app/Providers/AppServiceProvider.php`

```php
Gate::before(function ($user, $ability) {
    if (method_exists($user, 'isGodMode') && $user->isGodMode()) {
        return true; // Bypass ALL permission checks
    }
});
```

**How it works**:
- Runs BEFORE any specific permission check
- If user has sentinel UUID → return `true` (grant access)
- Regular users → continue to normal permission checks

### 2. User Model Helper Methods

**Location**: `app/Models/User.php`

```php
public function isGodMode(): bool
{
    return str_starts_with($this->id, '00000000-0000-0000-0000-');
}

public function isSuperAdmin(): bool
{
    return $this->id === '00000000-0000-0000-0000-000000000000';
}

public function isSystemAdmin(): bool
{
    return $this->isGodMode() && !$this->isSuperAdmin();
}

public function getSystemAdminNumber(): ?int
{
    if (!$this->isSystemAdmin()) {
        return null;
    }
    return (int) substr($this->id, -18); // Last 18 digits
}
```

### 3. Middleware: Company Access

**Location**: `app/Http/Middleware/IdentifyCompany.php`

```php
// Regular user: Check company membership
if (!$user->isGodMode() && !$this->userBelongsToCompany($user, $company)) {
    abort(403, 'You do not have access to this company.');
}

// God-mode user: Always allowed
if ($user->isGodMode()) {
    // Can access ANY company
}
```

### 4. Permission Flow Diagram

```
Request → Auth Middleware → IdentifyCompany Middleware
                                    ↓
                           Is user god-mode?
                                ↓        ↓
                              YES       NO
                                ↓        ↓
                          Allow access  Check company membership
                                         ↓
                                    Has access?
                                    ↓        ↓
                                  YES       NO
                                    ↓        ↓
                              Gate::before() → 403
                                    ↓
                              Is god-mode?
                                ↓        ↓
                              YES       NO
                                ↓        ↓
                           Grant access  Check Spatie permissions
```

---

## Creating System Admins

### Method 1: Artisan Command (Recommended)

```bash
php artisan admin:create-system "John Doe" john@haasib.com --password=secret123
```

**Output**:
```
System Administrator created successfully!

Name:       John Doe
Email:      john@haasib.com
Username:   john_doe
User ID:    00000000-0000-0000-0000-000000000001
Admin #:    1
Password:   secret123

Please save the password securely. It will not be shown again.
```

**Notes**:
- Only super admin can run this command
- If no password provided, generates random password
- Automatically assigns next sequential UUID
- Username auto-generated from name (lowercase, underscores)

### Method 2: Programmatically

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$systemAdmin = User::createSystemAdmin(
    name: 'Jane Smith',
    email: 'jane@haasib.com',
    password: Hash::make('password123')
);

echo "Created System Admin #{$systemAdmin->getSystemAdminNumber()}";
// Output: Created System Admin #2
```

### Method 3: Database Seeder

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    // Super Admin (always ID 00...000)
    $superAdmin = new User();
    $superAdmin->id = '00000000-0000-0000-0000-000000000000';
    $superAdmin->name = 'Super Admin';
    $superAdmin->username = 'superadmin';
    $superAdmin->email = 'admin@haasib.com';
    $superAdmin->password = Hash::make('password');
    $superAdmin->save();

    // System Admin #1 (use static method)
    User::createSystemAdmin(
        'Support Team',
        'support@haasib.com',
        Hash::make('password')
    );
}
```

---

## Audit Trail

### Identifying God-Mode Actions

```sql
-- All god-mode activity
SELECT 
    user_id,
    CASE 
        WHEN user_id = '00000000-0000-0000-0000-000000000000' THEN 'Super Admin'
        ELSE CONCAT('System Admin #', CAST(SUBSTRING(user_id, 30, 18) AS INTEGER))
    END as admin_type,
    action,
    target,
    created_at
FROM audit.entries
WHERE user_id LIKE '00000000-0000-0000-0000-%'
ORDER BY created_at DESC;
```

**Example Output**:
```
user_id                              | admin_type      | action          | target      | created_at
-------------------------------------|-----------------|-----------------|-------------|-------------------
00000000-0000-0000-0000-000000000002 | System Admin #2 | deleted_company | Acme Corp   | 2025-11-27 10:30
00000000-0000-0000-0000-000000000001 | System Admin #1 | updated_user    | john@ex.com | 2025-11-27 09:15
00000000-0000-0000-0000-000000000000 | Super Admin     | created_admin   | jane@ex.com | 2025-11-27 08:00
```

### Filtering by Specific Admin

```sql
-- Super Admin only
SELECT * FROM audit.entries 
WHERE user_id = '00000000-0000-0000-0000-000000000000';

-- System Admin #2 only
SELECT * FROM audit.entries 
WHERE user_id = '00000000-0000-0000-0000-000000000002';

-- All system admins (exclude super admin)
SELECT * FROM audit.entries 
WHERE user_id LIKE '00000000-0000-0000-0000-%'
  AND user_id != '00000000-0000-0000-0000-000000000000';
```

### Application-Level Logging

```php
// In your audit service
use App\Models\User;

public function logAction(User $user, string $action, array $data): void
{
    AuditEntry::create([
        'user_id' => $user->id,
        'user_type' => $user->isSuperAdmin() 
            ? 'super_admin' 
            : ($user->isSystemAdmin() ? "system_admin_{$user->getSystemAdminNumber()}" : 'regular'),
        'action' => $action,
        'data' => $data,
    ]);
}
```

---

## Security Considerations

### 1. Super Admin Protection

**Only super admin can create system admins:**

```php
// In CreateSystemAdmin command
$authenticatedUser = auth()->user();

if (!$authenticatedUser || !$authenticatedUser->isSuperAdmin()) {
    $this->error('Only the super admin can create system administrators.');
    return self::FAILURE;
}
```

### 2. Prevent Accidental God-Mode

**UUID validation** (optional, for extra safety):

```php
// In User model observer
public function creating(User $user): void
{
    // Prevent regular users from getting sentinel UUIDs
    if (str_starts_with($user->id, '00000000-0000-0000-0000-') && !app()->runningInConsole()) {
        throw new \Exception('Cannot manually assign sentinel UUIDs');
    }
}
```

### 3. Revoke God-Mode Access

**To remove a system admin:**

```bash
php artisan tinker
```

```php
$admin = User::find('00000000-0000-0000-0000-000000000002');
$admin->delete();

// Or soft delete if using SoftDeletes trait
$admin->delete(); // Sets deleted_at
```

### 4. Audit Super Admin Actions

```php
// Log every super admin action
if (auth()->user()->isSuperAdmin()) {
    Log::info('Super Admin Action', [
        'user_id' => auth()->id(),
        'action' => $request->route()->getActionName(),
        'ip' => $request->ip(),
    ]);
}
```

---

## Troubleshooting

### Issue: "Only the super admin can create system administrators"

**Cause**: You're not logged in as super admin

**Solution**:
```bash
# Check current user
php artisan tinker --execute="echo auth()->user()->id;"

# Should output: 00000000-0000-0000-0000-000000000000
# If not, login as super admin first
```

### Issue: System admin can't access companies

**Cause**: `IdentifyCompany` middleware not updated

**Check**: `app/Http/Middleware/IdentifyCompany.php`
```php
// Should have:
if (!$user->isGodMode() && !$this->userBelongsToCompany($user, $company)) {
    abort(403);
}
```

### Issue: Permissions still being checked for god-mode users

**Cause**: `Gate::before()` not configured

**Check**: `app/Providers/AppServiceProvider.php`
```php
// Should have in boot():
Gate::before(function ($user, $ability) {
    if (method_exists($user, 'isGodMode') && $user->isGodMode()) {
        return true;
    }
});
```

### Issue: Can't find system admin helper methods

**Cause**: Methods not defined in User model

**Solution**: Add methods to `app/Models/User.php`:
```php
public function isGodMode(): bool
{
    return str_starts_with($this->id, '00000000-0000-0000-0000-');
}
// ... (other methods)
```

### Issue: UUID collision (unlikely but possible)

**Symptom**: Regular user gets god-mode accidentally

**Solution**: Add validation
```php
// Validate UUIDs don't start with sentinel prefix
Rule::notIn(['00000000-0000-0000-0000-%']);
```

---

## Best Practices

### 1. Limit System Admins
- Create only when necessary
- Document each system admin's purpose
- Regular audits of active system admins

### 2. Use Descriptive Names
```php
// ✅ Good
User::createSystemAdmin('Support Team', 'support@haasib.com');
User::createSystemAdmin('Billing Department', 'billing@haasib.com');

// ❌ Bad
User::createSystemAdmin('Admin1', 'admin1@haasib.com');
```

### 3. Strong Passwords
```php
// Generate strong passwords
$password = Str::random(32);
User::createSystemAdmin($name, $email, Hash::make($password));
```

### 4. Regular Audits
```bash
# List all god-mode users
php artisan tinker --execute="
User::where('id', 'LIKE', '00000000-0000-0000-0000-%')
    ->get(['id', 'name', 'email'])
    ->each(fn(\$u) => echo \$u->name . ' - ' . \$u->email . PHP_EOL);
"
```

### 5. Monitor Activity
```sql
-- Daily god-mode activity report
SELECT 
    DATE(created_at) as date,
    COUNT(*) as actions,
    COUNT(DISTINCT user_id) as unique_admins
FROM audit.entries
WHERE user_id LIKE '00000000-0000-0000-0000-%'
GROUP BY DATE(created_at)
ORDER BY date DESC
LIMIT 30;
```

---

## Quick Reference

### Helper Methods

```php
$user->isGodMode();              // True for super + system admins
$user->isSuperAdmin();           // True only for super admin
$user->isSystemAdmin();          // True for system admins only
$user->getSystemAdminNumber();   // 1, 2, 3, etc. (null for non-system admins)
```

### Commands

```bash
# Create system admin
php artisan admin:create-system "Name" email@example.com --password=secret

# List all god-mode users
php artisan tinker --execute="User::where('id', 'LIKE', '00000000-0000-0000-0000-%')->get();"
```

### SQL Queries

```sql
-- All god-mode users
SELECT * FROM auth.users WHERE id LIKE '00000000-0000-0000-0000-%';

-- Super admin only
SELECT * FROM auth.users WHERE id = '00000000-0000-0000-0000-000000000000';

-- System admins only
SELECT * FROM auth.users 
WHERE id LIKE '00000000-0000-0000-0000-%' 
  AND id != '00000000-0000-0000-0000-000000000000';
```

---

## Related Documentation

- [RBAC System](../AI_PROMPTS/RBAC_SYSTEM.md) - Complete RBAC guide
- [CLAUDE.md](../CLAUDE.md) - Main development standards
- [Security & RBAC Section](../CLAUDE.md#security--rbac) - Quick reference

---

**Questions?** Check the troubleshooting section or review the implementation in:
- `app/Models/User.php` - Helper methods
- `app/Providers/AppServiceProvider.php` - Gate authorization
- `app/Console/Commands/CreateSystemAdmin.php` - Admin creation
