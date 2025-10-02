<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class MigrateExistingUsersToRbac extends Seeder
{
    public function run(): void
    {
        // Get existing company_user relationships
        $companyUsers = DB::table('auth.company_user')->get();

        $roleMapping = [
            'owner' => 'owner',
            'admin' => 'admin',
            'manager' => 'manager',
            'employee' => 'employee',
            'viewer' => 'viewer',
            'member' => 'employee', // Default for generic members
            'accountant' => 'accountant',
        ];

        $migrated = 0;
        $errors = [];

        foreach ($companyUsers as $cu) {
            try {
                $user = User::find($cu->user_id);
                if (! $user) {
                    $errors[] = "User {$cu->user_id} not found";

                    continue;
                }

                $company = Company::find($cu->company_id);
                if (! $company) {
                    $errors[] = "Company {$cu->company_id} not found";

                    continue;
                }

                // Get the new role name
                $newRole = $roleMapping[$cu->role] ?? 'employee';

                // Create role for this company if it doesn't exist
                $role = Role::firstOrCreate([
                    'name' => $newRole,
                    'guard_name' => 'web',
                    'team_id' => $company->id,
                ]);

                // If role is new, copy permissions from template
                if ($role->wasRecentlyCreated) {
                    $templateRole = Role::where('name', $newRole)
                        ->whereNull('team_id')
                        ->first();

                    if ($templateRole) {
                        $role->syncPermissions($templateRole->permissions);
                    }
                }

                // Set team context and assign role
                setPermissionsTeamId($company->id);
                $user->assignRole($newRole);

                // Clear team context for next iteration
                setPermissionsTeamId(null);

                $migrated++;
                $this->command->info("Migrated user {$cu->user_id} to role '{$newRole}' in company {$cu->company_id}");

            } catch (\Exception $e) {
                $errors[] = "Error migrating user {$cu->user_id}: ".$e->getMessage();
                $this->command->error($e->getMessage());
            }
        }

        $this->command->line("\nMigration Summary:");
        $this->command->info("✓ Successfully migrated: {$migrated} users");

        if (! empty($errors)) {
            $this->command->error('✗ Errors encountered: '.count($errors));
            foreach ($errors as $error) {
                $this->command->error("  - {$error}");
            }
        }
    }
}
