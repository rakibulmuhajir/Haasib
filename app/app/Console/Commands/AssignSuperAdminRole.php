<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignSuperAdminRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rbac:assign-super-admin 
                            {email : Email of the user to assign super_admin role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign super_admin role and permissions to a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email '{$email}' not found.");

            return Command::FAILURE;
        }

        // Ensure super_admin role exists
        $role = Role::firstOrCreate(['name' => 'super_admin']);

        // Assign role without team context (system-wide)
        $user->assignRole($role);

        // Get all system permissions
        $systemPermissions = \Spatie\Permission\Models\Permission::where('name', 'like', 'system.%')->get();

        // Grant all system permissions explicitly
        foreach ($systemPermissions as $permission) {
            $user->givePermissionTo($permission);
        }

        $this->info('✅ Super admin role assigned successfully!');
        $this->line("User: {$user->name}");
        $this->line("Email: {$user->email}");
        $this->line("System permissions: {$systemPermissions->count()}");

        return Command::SUCCESS;
    }
}
