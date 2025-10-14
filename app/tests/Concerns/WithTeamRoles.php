<?php

namespace Tests\Concerns;

trait WithTeamRoles
{
    /**
     * Special UUIDs for system-wide roles.
     *
     * We use incrementing UUIDs because:
     * 1. The database constraint requires team_id to be non-null
     * 2. System roles don't belong to any company
     * 3. Different system roles need different team_ids for proper isolation
     * 4. This approach aligns with Spatie permission package's team feature
     */
    private const SYSTEM_SUPER_ADMIN_UUID = '00000000-0000-0000-0000-000000000000';
    private const SYSTEM_ADMIN_UUID = '00000000-0000-0000-0000-000000000001';

    /**
     * Assign a role to a user with proper team context
     */
    protected function assignRoleWithTeam($user, string $roleName, ?string $teamId = null): void
    {
        // For system roles (when team_id is null), use the special system UUID
        if ($teamId === null) {
            $teamId = self::SYSTEM_TEAM_ID;
        }

        // Find or create the role with the specified team
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            'team_id' => $teamId,
        ]);

        // If this is a newly created company role, copy permissions from the system role
        if ($role->wasRecentlyCreated && $teamId !== self::SYSTEM_SUPER_ADMIN_UUID && $teamId !== self::SYSTEM_ADMIN_UUID) {
            $systemRole = \Spatie\Permission\Models\Role::where('name', $roleName)
                ->where('team_id', self::SYSTEM_SUPER_ADMIN_UUID) // Always copy from super admin permissions
                ->first();

            if ($systemRole) {
                $role->syncPermissions($systemRole->permissions);
            }
        }

        // Set the team context before assigning the role
        setPermissionsTeamId($teamId);

        // Assign the role
        $user->assignRole($role);
    }

    /**
     * Assign a system role (no company/team context)
     */
    protected function assignSystemRole($user, string $roleName): void
    {
        // Determine the system team ID based on role name
        $systemTeamId = match ($roleName) {
            'super_admin' => self::SYSTEM_SUPER_ADMIN_UUID,
            'systemadmin' => self::SYSTEM_ADMIN_UUID,
            default => self::SYSTEM_SUPER_ADMIN_UUID, // Default to super admin UUID
        };

        $this->assignRoleWithTeam($user, $roleName, $systemTeamId);
    }

    /**
     * Assign a company role with specific team (company)
     */
    protected function assignCompanyRole($user, string $roleName, $company): void
    {
        $this->assignRoleWithTeam($user, $roleName, $company->id);
    }

    /**
     * Set team context for permission checks by ID
     */
    protected function setTeamContextById(?string $teamId): void
    {
        setPermissionsTeamId($teamId);
    }

    /**
     * Clear team context (useful for system operations)
     * Note: If you're also using HasCompanyContext trait, that trait provides this method
     */
    protected function clearSystemTeamContext(): void
    {
        setPermissionsTeamId(null);
    }

    /**
     * Execute a callback with system team context
     */
    protected function withSystemContext(callable $callback, string $role = 'super_admin')
    {
        $originalTeamId = getPermissionsTeamId();
        $systemTeamId = $role === 'systemadmin' ? self::SYSTEM_ADMIN_UUID : self::SYSTEM_SUPER_ADMIN_UUID;
        setPermissionsTeamId($systemTeamId);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($originalTeamId);
        }
    }

    /**
     * Execute a callback with company team context
     */
    protected function withCompanyContext($company, callable $callback)
    {
        $originalTeamId = getPermissionsTeamId();
        setPermissionsTeamId($company->id);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($originalTeamId);
        }
    }
}