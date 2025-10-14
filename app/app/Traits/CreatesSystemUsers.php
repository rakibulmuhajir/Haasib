<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait CreatesSystemUsers
{
    /**
     * Create a super admin user with a specific UUID
     */
    protected function createSuperAdmin(array $attributes = []): User
    {
        return $this->createSystemUser('super_admin', $attributes);
    }

    /**
     * Create a system admin user with a specific UUID
     */
    protected function createSystemAdmin(array $attributes = []): User
    {
        return $this->createSystemUser('systemadmin', $attributes);
    }

    /**
     * Create a system user with a specific role and UUID
     */
    protected function createSystemUser(string $role, array $attributes = []): User
    {
        $user = User::create(array_merge([
            'id' => $attributes['id'] ?? Str::uuid(),
            'name' => $attributes['name'] ?? 'System User',
            'email' => $attributes['email'] => Str::random(10) . '@system.local',
            'password' => Hash::make($attributes['password'] ?? Str::random(32)),
            'email_verified_at' => now(),
            'is_system_user' => true,
            'system_user_type' => $role,
            'created_by_user_id' => null, // System created
        ], $attributes));

        // Remove password from attributes to avoid passing it to assignRole
        unset($attributes['password']);

        // Assign the role using the WithTeamRoles trait
        if (method_exists($this, 'assignSystemRole')) {
            $this->assignSystemRole($user, $role);
        } else {
            // Fallback if trait is not available
            $user->assignRole($role);
        }

        return $user;
    }

    /**
     * Create a super admin with a specific UUID for tracking
     */
    protected function createSuperAdminWithUuid(string $uuid, array $attributes = []): User
    {
        return $this->createSystemUser('super_admin', array_merge($attributes, ['id' => $uuid]));
    }

    /**
     * Create a system admin with a specific UUID for tracking
     */
    protected function createSystemAdminWithUuid(string $uuid, array $attributes = []): User
    {
        return $this->createSystemUser('systemadmin', array_merge($attributes, ['id' => $uuid]));
    }

    /**
     * Batch create multiple super admins with sequential UUIDs
     */
    protected function createMultipleSuperAdmins(int $count, array $baseAttributes = []): array
    {
        $users = [];
        for ($i = 1; $i <= $count; $i++) {
            $uuid = str_pad($i, 12, '0', STR_PAD_LEFT);
            $uuid = '00000000-0000-0000-0000-' . $uuid;

            $users[] = $this->createSuperAdminWithUuid($uuid, array_merge($baseAttributes, [
                'name' => ($baseAttributes['name'] ?? 'Super Admin') . " {$i}",
                'email' => 'superadmin' . $i . '@system.local',
            ]));
        }

        return $users;
    }
}