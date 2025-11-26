<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Clear team context for global operations
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->seedPermissions();
        $this->seedSuperAdminRole();
    }

    private function seedPermissions(): void
    {
        $modules = config('permissions', []);

        foreach ($modules as $moduleName => $models) {
            foreach ($models as $modelName => $permissions) {
                foreach ($permissions as $permissionName) {
                    Permission::firstOrCreate([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                    ]);
                }
            }
        }

        $this->command->info('Permissions seeded.');
    }

    private function seedSuperAdminRole(): void
    {
        // Super admin is a global role (no team)
        Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $this->command->info('Super admin role created.');
    }
}
