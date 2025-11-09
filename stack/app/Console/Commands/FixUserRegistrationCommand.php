<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixUserRegistrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:fix-registration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix RLS policies to allow user registration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Attempting to fix user registration RLS policies...');

        try {
            // Check if INSERT policy already exists
            $existingPolicy = DB::selectOne("
                SELECT policyname 
                FROM pg_policies 
                WHERE tablename = 'users' 
                AND schemaname = 'auth' 
                AND cmd = 'INSERT'
            ");

            if ($existingPolicy) {
                $this->info('INSERT policy already exists for users table.');

                return 0;
            }

            // Try to create the missing INSERT policy
            DB::statement("
                CREATE POLICY users_insert_policy ON auth.users
                FOR INSERT WITH CHECK (
                    -- Allow superadmins to insert any user
                    (current_setting('app.is_super_admin', true))::boolean = true
                    OR system_role = 'superadmin'
                    OR (
                        -- Allow user registration when no user context is set (public registration)
                        current_setting('app.current_user_id', true) IS NULL
                        AND system_role IN ('company_owner', 'company_admin', 'manager', 'employee', 'viewer')
                    )
                )
            ");

            // Create DELETE policy for completeness
            DB::statement("
                CREATE POLICY users_delete_policy ON auth.users
                FOR DELETE USING (
                    system_role = 'superadmin' 
                    OR (current_setting('app.is_super_admin', true))::boolean = true
                )
            ");

            $this->info('✅ Successfully created missing RLS policies for user registration.');
            $this->info('User registration should now work properly.');

            Log::info('RLS policies for user registration have been fixed');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Failed to create RLS policies: '.$e->getMessage());
            $this->line('');
            $this->line('To fix this manually, connect to the database as a superuser and run:');
            $this->line('');
            $this->comment('CREATE POLICY users_insert_policy ON auth.users');
            $this->comment('FOR INSERT WITH CHECK (true);');
            $this->comment('');
            $this->comment('CREATE POLICY users_delete_policy ON auth.users');
            $this->comment('FOR DELETE USING (true);');

            Log::error('Failed to fix RLS policies', ['error' => $e->getMessage()]);

            return 1;
        }
    }
}
