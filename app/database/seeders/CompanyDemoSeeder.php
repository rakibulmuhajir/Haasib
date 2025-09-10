<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CompanyDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo users
        $users = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => Hash::make('password'),
                'system_role' => 'superadmin',
                'created_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'password' => Hash::make('password'),
                'system_role' => null,
                'created_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bob Johnson',
                'email' => 'bob@example.com',
                'password' => Hash::make('password'),
                'system_role' => null,
                'created_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            // Create companies for each user
            $companies = [
                [
                    'name' => $user->name.'\'s Company',
                    'slug' => strtolower(str_replace(' ', '-', $user->name)).'-company',
                    'locale' => 'en-US',
                    'base_currency' => 'USD',
                    'language' => 'en',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => $user->name.'\'s Second Company',
                    'slug' => strtolower(str_replace(' ', '-', $user->name)).'-second-company',
                    'locale' => 'en-US',
                    'base_currency' => 'USD',
                    'language' => 'en',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];

            foreach ($companies as $companyData) {
                $company = Company::updateOrCreate(
                    ['slug' => $companyData['slug']],
                    $companyData
                );

                // Assign user to company as owner
                DB::table('auth.company_user')->updateOrInsert(
                    ['company_id' => $company->id, 'user_id' => $user->id],
                    [
                        'role' => 'owner',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
