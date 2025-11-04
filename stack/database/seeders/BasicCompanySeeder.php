<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BasicCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test company
        $companyId = Str::uuid();
        DB::table('auth.companies')->insert([
            'id' => $companyId,
            'name' => 'Test Company Ltd',
            'industry' => 'Technology',
            'slug' => 'test-company-ltd',
            'country' => 'US',
            'base_currency' => 'USD',
            'currency' => 'USD',
            'language' => 'en',
            'locale' => 'en_US',
            'timezone' => 'America/New_York',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Created test company: Test Company Ltd');

        // Create a test user
        $userId = Str::uuid();
        DB::table('auth.users')->insert([
            'id' => $userId,
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'admin@testcompany.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'email_verified_at' => now(),
            'system_role' => 'user',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Created test user: admin@testcompany.com');

        // Link user to company
        DB::table('auth.company_user')->insert([
            'company_id' => $companyId,
            'user_id' => $userId,
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Linked user to company as admin');

        $this->command->info('Basic company and user seeding completed!');
    }
}