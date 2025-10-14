<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MigrateUsersToRbac extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rbac:migrate-users 
                            {--force : Force migration even if users already have roles}
                            {--dry-run : Show what would be migrated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing company_user relationships to RBAC roles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('dry-run')) {
            $this->info('=== DRY RUN MODE - No changes will be made ===');
        }

        // Check if roles and permissions exist
        $rolesCount = Role::count();
        $permissionsCount = Permission::count();

        if ($rolesCount === 0 || $permissionsCount === 0) {
            $this->error('Roles or permissions not found. Please run RbacSeeder first:');
            $this->line('php artisan db:seed --class=RbacSeeder');

            return Command::FAILURE;
        }

        $this->info("Found {$rolesCount} roles and {$permissionsCount} permissions");

        // Get users without roles
        $usersWithoutRoles = User::whereDoesntHave('roles')->get();

        if ($usersWithoutRoles->isEmpty()) {
            $this->info('All users already have roles assigned.');

            if (! $this->option('force')) {
                $this->line('Use --force to reassign all users based on company_user relationships.');

                return Command::SUCCESS;
            }
        }

        // Get company_user relationships using DB query
        $companyUsers = DB::table('auth.company_user as cu')
            ->join('users', 'cu.user_id', '=', 'users.id')
            ->join('auth.companies', 'cu.company_id', '=', 'auth.companies.id')
            ->select('cu.*', 'users.email as user_email', 'auth.companies.name as company_name')
            ->get();

        if ($companyUsers->isEmpty()) {
            $this->warn('No company_user relationships found to migrate.');

            return Command::SUCCESS;
        }

        $this->info("Found {$companyUsers->count()} company_user relationships to process");
        $this->newLine();

        // Create a progress bar
        $progressBar = $this->output->createProgressBar($companyUsers->count());
        $progressBar->start();

        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        DB::transaction(function () use ($companyUsers, &$migrated, &$skipped, &$errors, $progressBar) {
            foreach ($companyUsers as $companyUser) {
                try {
                    $user = User::find($companyUser->user_id);
                    $company = Company::find($companyUser->company_id);
                    $role = $companyUser->role;

                    if (! $user || ! $company || ! $role) {
                        $skipped++;
                        $progressBar->advance();

                        continue;
                    }

                    // Map old role to new RBAC role
                    $rbacRole = $this->mapRoleToRbac($role);

                    if (! $rbacRole) {
                        $this->newLine();
                        $this->warn("  Skipped: Unknown role '{$role}' for user {$user->id}");
                        $skipped++;
                        $progressBar->advance();

                        continue;
                    }

                    // Check if user already has this role for the company
                    $existingRole = $user->roles()
                        ->wherePivot('team_id', $company->id)
                        ->first();

                    if ($existingRole && ! $this->option('force')) {
                        $skipped++;
                        $progressBar->advance();

                        continue;
                    }

                    if (! $this->option('dry-run')) {
                        // Set team context
                        setPermissionsTeamId($company->id);

                        // Remove existing company role if forcing
                        if ($existingRole && $this->option('force')) {
                            $user->removeRole($existingRole);
                        }

                        // Assign the new role
                        $roleModel = Role::where('name', $rbacRole)->first();
                        if ($roleModel) {
                            $user->assignRole($roleModel);
                            $migrated++;
                        } else {
                            $this->newLine();
                            $this->error("  Error: Role '{$rbacRole}' not found in database");
                            $errors++;
                        }
                    } else {
                        // Dry run - just show what would happen
                        if ($existingRole) {
                            $this->newLine();
                            $this->line("  Would update: User {$companyUser->user_email} → Role: {$rbacRole} (Company: {$companyUser->company_name})");
                        } else {
                            $this->newLine();
                            $this->line("  Would assign: User {$companyUser->user_email} → Role: {$rbacRole} (Company: {$companyUser->company_name})");
                        }
                        $migrated++;
                    }

                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("  Error processing user {$companyUser->user_id}: {$e->getMessage()}");
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();
        $this->newLine();

        // Summary
        if ($this->option('dry-run')) {
            $this->info('=== DRY RUN SUMMARY ===');
        } else {
            $this->info('=== MIGRATION COMPLETE ===');
        }

        $this->line("Migrated: {$migrated} role assignments");
        $this->line("Skipped: {$skipped} assignments");
        if ($errors > 0) {
            $this->error("Errors: {$errors} assignments");
        }

        if (! $this->option('dry-run')) {
            $this->newLine();
            $this->info('Migration completed successfully!');

            // Verify results
            $usersWithRoles = User::whereHas('roles')->count();
            $this->line("Total users with roles now: {$usersWithRoles}");
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Map old role names to new RBAC role names
     */
    private function mapRoleToRbac(string $oldRole): ?string
    {
        $roleMapping = [
            'owner' => 'owner',
            'admin' => 'admin',
            'manager' => 'manager',
            'accountant' => 'accountant',
            'employee' => 'employee',
            'viewer' => 'viewer',
        ];

        return $roleMapping[$oldRole] ?? null;
    }
}
