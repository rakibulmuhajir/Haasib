<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin {email}';

    protected $description = 'Grant super_admin role to a user';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return self::FAILURE;
        }

        // Clear team context for global role
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        // Create super_admin role if not exists
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        // Assign role without team context
        $user->assignRole($role);

        $this->info("User {$email} is now a super admin.");

        return self::SUCCESS;
    }
}
