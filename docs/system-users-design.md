# System Users Design Document

## Overview
This document outlines the design for system users with hierarchical permissions and unique identification for logging and audit purposes.

## System User Types

### 1. Super Admin
- **Role Name**: `super_admin`
- **Team ID Pattern**: `00000000-0000-0000-0000-000000000000` (shared role)
- **Description**: Full system access without restrictions
- **Can**: Manage other system users, modify permissions, access all system functions

### 2. System Admin
- **Role Name**: `systemadmin`
- **Team ID**: `00000000-0000-0000-0000-000000000001`
- **Description**: System management with restrictions
- **Cannot**: Add/delete/enable/disable other system admins, modify system-wide permissions

### 3. Additional System Roles (Future)
- **Role Name**: Custom (e.g., `sysops`, `auditor`)
- **Team ID Pattern**: Incrementing from `00000000-0000-0000-0000-000000000002`
- **Description**: Specialized system roles with specific permissions

## Implementation Approaches

### Approach 1: Shared Role with Unique User UUIDs (Recommended)
```php
// Multiple users can have the same super_admin role
// User activities are tracked by user.id (unique per user)
// Role permissions are consistent across all super admins

$user1 = User::create([
    'id' => '00000000-0000-0000-0000-000000000001',
    'name' => 'Super Admin One',
    'email' => 'sa1@example.com'
]);
$user1->assignRole('super_admin'); // team_id = 00000000-0000-0000-0000-000000000000

$user2 = User::create([
    'id' => '00000000-0000-0000-0000-000000000002',
    'name' => 'Super Admin Two',
    'email' => 'sa2@example.com'
]);
$user2->assignRole('super_admin'); // team_id = 00000000-0000-0000-0000-000000000000
```

### Approach 2: Individual Roles with Unique Team IDs
```php
// Each super admin gets their own role with unique team_id
// Useful if you need to track activities by role rather than user

// Create unique super admin roles
Role::create([
    'name' => 'super_admin',
    'team_id' => '00000000-0000-0000-0000-000000000000',
    'guard_name' => 'web'
]);

Role::create([
    'name' => 'super_admin_sa2',
    'team_id' => '00000000-0000-0000-0000-000000000002',
    'guard_name' => 'web'
]);

// Assign different roles to different super admins
$user1->assignRole('super_admin'); // team_id = 00000000-0000-0000-0000-000000000000
$user2->assignRole('super_admin_sa2'); // team_id = 00000000-0000-0000-0000-000000000002
```

## Activity Logging Recommendations

### User-Based Logging (Preferred)
```php
// Log activities with user_id, not role team_id
AuditLog::create([
    'user_id' => $user->id,        // Unique per user
    'user_role' => 'super_admin',  // Role name for context
    'action' => 'system.setting.updated',
    'details' => [...]
]);
```

### Role-Based Logging
```php
// Log activities with team_id context
AuditLog::create([
    'user_id' => $user->id,
    'team_id' => $user->getRoleIdByRole('super_admin'), // Role's team_id
    'action' => 'system.user.created',
    'details' => [...]
]);
```

## Best Practices

1. **Use Approach 1 (Shared Role)** for simplicity and consistency
2. **Always use user UUIDs** for activity tracking, not role team_ids
3. **Implement audit trails** that capture both user context and role context
4. **Document each super admin** with metadata about their access level
5. **Consider implementing MFA** for all system users
6. **Regular audits** of system user activities

## Creating Multiple Super Admins

### Method 1: Manual Creation (Recommended)
```php
// Create a super admin with a specific UUID
$superAdmin = User::create([
    'id' => '00000000-0000-0000-0000-00000000XXXX', // Custom UUID
    'name' => 'Custom Super Admin',
    'email' => 'custom@example.com',
    'password' => Hash::make('secure-password'),
    'email_verified_at' => now(),
    'is_system_user' => true // Flag to identify system users
]);

// Assign super admin role
$superAdmin->assignRole('super_admin');
```

### Method 2: Using a Factory
```php
// Create a SuperAdminFactory for testing
class SuperAdminFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'id' => $this->faker->unique()->uuid(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_system_user' => true
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('super_admin');
        });
    }
}
```

## Database Schema Considerations

### users table
```sql
ALTER TABLE auth.users ADD COLUMN is_system_user BOOLEAN DEFAULT FALSE;
ALTER TABLE auth.users ADD COLUMN system_user_type VARCHAR(50); -- 'super_admin', 'systemadmin', etc.
CREATE INDEX idx_users_system_user ON auth.users(is_system_user);
```

### audit_logs table
```sql
CREATE TABLE auth.audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id),
    user_role VARCHAR(100),
    team_id UUID,
    action VARCHAR(255) NOT NULL,
    details JSONB,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_audit_logs_user_id ON auth.audit_logs(user_id);
CREATE INDEX idx_audit_logs_created_at ON auth.audit_logs(created_at);
```