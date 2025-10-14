<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo users
        $users = $this->createDemoUsers();

        // Create demo companies
        $companies = $this->createDemoCompanies();

        // Create company relationships
        $this->createCompanyRelationships($users, $companies);

        // Create demo invitations
        $this->createDemoInvitations($companies);

        $this->command->info('✓ Company demo data seeded successfully!');
        $this->command->info('  Users: '.$users->count());
        $this->command->info('  Companies: '.$companies->count());
        $this->command->info('  Relationships created: '.DB::table('auth.company_user')->count());
        $this->command->info('  Invitations created: '.DB::table('auth.company_invitations')->count());
    }

    /**
     * Create demo users for testing.
     */
    private function createDemoUsers()
    {
        $demoUsers = [
            [
                'name' => 'Admin User',
                'email' => 'admin@demo.com',
                'password' => 'password',
                'system_role' => 'super_admin',
            ],
            [
                'name' => 'Ahmed Al-Rashid',
                'email' => 'ahmed@demo.com',
                'password' => 'password',
                'system_role' => 'user',
            ],
            [
                'name' => 'Sarah Mohammed',
                'email' => 'sarah@demo.com',
                'password' => 'password',
                'system_role' => 'user',
            ],
            [
                'name' => 'Khalid Al-Omar',
                'email' => 'khalid@demo.com',
                'password' => 'password',
                'system_role' => 'user',
            ],
            [
                'name' => 'Fatima Al-Saud',
                'email' => 'fatima@demo.com',
                'password' => 'password',
                'system_role' => 'user',
            ],
            [
                'name' => 'John Smith',
                'email' => 'john@demo.com',
                'password' => 'password',
                'system_role' => 'user',
            ],
            [
                'name' => 'Maria Garcia',
                'email' => 'maria@demo.com',
                'password' => 'password',
                'system_role' => 'user',
            ],
            [
                'name' => 'Li Wei',
                'email' => 'liwei@demo.com',
                'password' => 'password',
                'system_role' => 'user',
            ],
        ];

        $users = collect();
        foreach ($demoUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'system_role' => $userData['system_role'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $users->push($user);
        }

        return $users;
    }

    /**
     * Create demo companies.
     */
    private function createDemoCompanies()
    {
        $demoCompanies = [
            [
                'name' => 'Tech Solutions Arabia',
                'slug' => 'tech-solutions-arabia',
                'country' => 'SA',
                'base_currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'language' => 'ar',
                'locale' => 'ar_SA',
                'industry' => 'Technology',
                'settings' => [
                    'features' => ['accounting', 'reporting', 'invoicing', 'inventory'],
                    'preferences' => ['theme' => 'light', 'timezone' => 'Asia/Riyadh'],
                    'limits' => ['max_users' => 100, 'max_storage' => '10GB'],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Global Trading Co.',
                'slug' => 'global-trading-co',
                'country' => 'AE',
                'base_currency' => 'AED',
                'timezone' => 'Asia/Dubai',
                'language' => 'en',
                'locale' => 'en_AE',
                'industry' => 'Trading',
                'settings' => [
                    'features' => ['accounting', 'reporting', 'invoicing'],
                    'preferences' => ['theme' => 'dark', 'timezone' => 'Asia/Dubai'],
                    'limits' => ['max_users' => 50, 'max_storage' => '5GB'],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Riyadh Manufacturing',
                'slug' => 'riyadh-manufacturing',
                'country' => 'SA',
                'base_currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'language' => 'ar',
                'locale' => 'ar_SA',
                'industry' => 'Manufacturing',
                'settings' => [
                    'features' => ['accounting', 'reporting', 'invoicing', 'inventory', 'production'],
                    'preferences' => ['theme' => 'light', 'timezone' => 'Asia/Riyadh'],
                    'limits' => ['max_users' => 200, 'max_storage' => '20GB'],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Digital Agency KSA',
                'slug' => 'digital-agency-ksa',
                'country' => 'SA',
                'base_currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'language' => 'en',
                'locale' => 'en_US',
                'industry' => 'Digital Services',
                'settings' => [
                    'features' => ['accounting', 'reporting', 'invoicing', 'project_management'],
                    'preferences' => ['theme' => 'light', 'timezone' => 'Asia/Riyadh'],
                    'limits' => ['max_users' => 30, 'max_storage' => '3GB'],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Gulf Logistics LLC',
                'slug' => 'gulf-logistics-llc',
                'country' => 'SA',
                'base_currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'language' => 'en',
                'locale' => 'en_US',
                'industry' => 'Logistics',
                'settings' => [
                    'features' => ['accounting', 'reporting', 'invoicing', 'fleet_management'],
                    'preferences' => ['theme' => 'light', 'timezone' => 'Asia/Riyadh'],
                    'limits' => ['max_users' => 75, 'max_storage' => '8GB'],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Saudi Retail Group',
                'slug' => 'saudi-retail-group',
                'country' => 'SA',
                'base_currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'language' => 'ar',
                'locale' => 'ar_SA',
                'industry' => 'Retail',
                'settings' => [
                    'features' => ['accounting', 'reporting', 'invoicing', 'inventory', 'pos'],
                    'preferences' => ['theme' => 'light', 'timezone' => 'Asia/Riyadh'],
                    'limits' => ['max_users' => 150, 'max_storage' => '15GB'],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Jeddah Consulting',
                'slug' => 'jeddah-consulting',
                'country' => 'SA',
                'base_currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'language' => 'ar',
                'locale' => 'ar_SA',
                'industry' => 'Consulting',
                'settings' => [
                    'features' => ['accounting', 'reporting', 'invoicing'],
                    'preferences' => ['theme' => 'light', 'timezone' => 'Asia/Riyadh'],
                    'limits' => ['max_users' => 25, 'max_storage' => '2GB'],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Eastern Province Energy',
                'slug' => 'eastern-province-energy',
                'country' => 'SA',
                'base_currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'language' => 'en',
                'locale' => 'en_US',
                'industry' => 'Energy',
                'settings' => [
                    'features' => ['accounting', 'reporting', 'invoicing', 'asset_management'],
                    'preferences' => ['theme' => 'dark', 'timezone' => 'Asia/Riyadh'],
                    'limits' => ['max_users' => 300, 'max_storage' => '50GB'],
                ],
                'is_active' => false, // Inactive demo company
            ],
            [
                'name' => 'Mediterranean Shipping',
                'slug' => 'mediterranean-shipping',
                'country' => 'GR',
                'base_currency' => 'EUR',
                'timezone' => 'Europe/Athens',
                'language' => 'en',
                'locale' => 'en_US',
                'industry' => 'Shipping',
                'settings' => [
                    'features' => ['accounting', 'reporting', 'invoicing', 'fleet_management'],
                    'preferences' => ['theme' => 'light', 'timezone' => 'Europe/Athens'],
                    'limits' => ['max_users' => 180, 'max_storage' => '12GB'],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'InnovateTech Dubai',
                'slug' => 'innovatetech-dubai',
                'country' => 'AE',
                'base_currency' => 'AED',
                'timezone' => 'Asia/Dubai',
                'language' => 'en',
                'locale' => 'en_AE',
                'industry' => 'Technology',
                'settings' => [
                    'features' => ['accounting', 'reporting', 'invoicing', 'rd_management'],
                    'preferences' => ['theme' => 'dark', 'timezone' => 'Asia/Dubai'],
                    'limits' => ['max_users' => 60, 'max_storage' => '6GB'],
                ],
                'is_active' => true,
            ],
        ];

        $companies = collect();
        foreach ($demoCompanies as $companyData) {
            $company = Company::firstOrCreate(
                ['slug' => $companyData['slug']],
                array_merge(
                    $companyData,
                    [
                        'created_by_user_id' => User::where('email', 'admin@demo.com')->first()->id,
                    ]
                )
            );
            $companies->push($company);
        }

        return $companies;
    }

    /**
     * Create company-user relationships.
     */
    private function createCompanyRelationships($users, $companies)
    {
        $relationships = [
            // Tech Solutions Arabia (Ahmed's company)
            [
                'company_slug' => 'tech-solutions-arabia',
                'users' => [
                    ['email' => 'ahmed@demo.com', 'role' => 'owner'],
                    ['email' => 'sarah@demo.com', 'role' => 'admin'],
                    ['email' => 'khalid@demo.com', 'role' => 'accountant'],
                    ['email' => 'fatima@demo.com', 'role' => 'employee'],
                ],
            ],
            // Global Trading Co. (Sarah's company)
            [
                'company_slug' => 'global-trading-co',
                'users' => [
                    ['email' => 'sarah@demo.com', 'role' => 'owner'],
                    ['email' => 'khalid@demo.com', 'role' => 'admin'],
                    ['email' => 'john@demo.com', 'role' => 'viewer'],
                ],
            ],
            // Riyadh Manufacturing (Khalid's company)
            [
                'company_slug' => 'riyadh-manufacturing',
                'users' => [
                    ['email' => 'khalid@demo.com', 'role' => 'owner'],
                    ['email' => 'fatima@demo.com', 'role' => 'admin'],
                    ['email' => 'maria@demo.com', 'role' => 'accountant'],
                    ['email' => 'liwei@demo.com', 'role' => 'employee'],
                ],
            ],
            // Digital Agency KSA (Fatima's company)
            [
                'company_slug' => 'digital-agency-ksa',
                'users' => [
                    ['email' => 'fatima@demo.com', 'role' => 'owner'],
                    ['email' => 'ahmed@demo.com', 'role' => 'admin'],
                    ['email' => 'sarah@demo.com', 'role' => 'viewer'],
                ],
            ],
            // Gulf Logistics LLC (John's company)
            [
                'company_slug' => 'gulf-logistics-llc',
                'users' => [
                    ['email' => 'john@demo.com', 'role' => 'owner'],
                    ['email' => 'maria@demo.com', 'role' => 'admin'],
                    ['email' => 'liwei@demo.com', 'role' => 'accountant'],
                ],
            ],
            // Multiple companies for admin user
            [
                'company_slug' => 'saudi-retail-group',
                'users' => [
                    ['email' => 'admin@demo.com', 'role' => 'owner'],
                    ['email' => 'ahmed@demo.com', 'role' => 'admin'],
                ],
            ],
            [
                'company_slug' => 'jeddah-consulting',
                'users' => [
                    ['email' => 'admin@demo.com', 'role' => 'owner'],
                    ['email' => 'sarah@demo.com', 'role' => 'viewer'],
                ],
            ],
            // Cross-company participation
            [
                'company_slug' => 'eastern-province-energy',
                'users' => [
                    ['email' => 'admin@demo.com', 'role' => 'owner'],
                    ['email' => 'khalid@demo.com', 'role' => 'accountant'],
                    ['email' => 'john@demo.com', 'role' => 'viewer'],
                ],
            ],
            [
                'company_slug' => 'mediterranean-shipping',
                'users' => [
                    ['email' => 'admin@demo.com', 'role' => 'owner'],
                    ['email' => 'maria@demo.com', 'role' => 'admin'],
                    ['email' => 'liwei@demo.com', 'role' => 'accountant'],
                ],
            ],
            [
                'company_slug' => 'innovatetech-dubai',
                'users' => [
                    ['email' => 'admin@demo.com', 'role' => 'owner'],
                    ['email' => 'ahmed@demo.com', 'role' => 'admin'],
                    ['email' => 'fatima@demo.com', 'role' => 'viewer'],
                ],
            ],
        ];

        foreach ($relationships as $relationship) {
            $company = $companies->firstWhere('slug', $relationship['company_slug']);
            if (! $company) {
                continue;
            }

            foreach ($relationship['users'] as $userData) {
                $user = $users->firstWhere('email', $userData['email']);
                if (! $user) {
                    continue;
                }

                // Check if relationship already exists
                $exists = DB::table('auth.company_user')
                    ->where('company_id', $company->id)
                    ->where('user_id', $user->id)
                    ->exists();

                if (! $exists) {
                    DB::table('auth.company_user')->insert([
                        'company_id' => $company->id,
                        'user_id' => $user->id,
                        'role' => $userData['role'],
                        'invited_by_user_id' => $user->id, // Self-invited for demo
                        'is_active' => true,
                        'joined_at' => now()->subDays(rand(1, 30)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Create demo company invitations.
     */
    private function createDemoInvitations($companies)
    {
        $invitations = [
            // Pending invitations
            [
                'company_slug' => 'tech-solutions-arabia',
                'email' => 'newhire1@demo.com',
                'role' => 'employee',
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
            ],
            [
                'company_slug' => 'global-trading-co',
                'email' => 'trader@demo.com',
                'role' => 'admin',
                'status' => 'pending',
                'expires_at' => now()->addDays(14),
            ],
            [
                'company_slug' => 'riyadh-manufacturing',
                'email' => 'engineer@demo.com',
                'role' => 'accountant',
                'status' => 'pending',
                'expires_at' => now()->addDays(5),
            ],
            // Accepted invitations
            [
                'company_slug' => 'digital-agency-ksa',
                'email' => 'designer@demo.com',
                'role' => 'employee',
                'status' => 'accepted',
                'accepted_at' => now()->subDays(3),
                'expires_at' => now()->subDays(10),
            ],
            [
                'company_slug' => 'gulf-logistics-llc',
                'email' => 'logistics@demo.com',
                'role' => 'viewer',
                'status' => 'accepted',
                'accepted_at' => now()->subDays(7),
                'expires_at' => now()->subDays(14),
            ],
            // Rejected invitations
            [
                'company_slug' => 'saudi-retail-group',
                'email' => 'retailer@demo.com',
                'role' => 'admin',
                'status' => 'rejected',
                'expires_at' => now()->subDays(5),
            ],
            [
                'company_slug' => 'jeddah-consulting',
                'email' => 'consultant@demo.com',
                'role' => 'viewer',
                'status' => 'rejected',
                'expires_at' => now()->subDays(10),
            ],
            // Expired invitations
            [
                'company_slug' => 'eastern-province-energy',
                'email' => 'energy@demo.com',
                'role' => 'employee',
                'status' => 'expired',
                'expires_at' => now()->subDays(1),
            ],
        ];

        foreach ($invitations as $invitationData) {
            $company = $companies->firstWhere('slug', $invitationData['company_slug']);
            if (! $company) {
                continue;
            }

            $inviter = User::where('email', 'admin@demo.com')->first();

            DB::table('auth.company_invitations')->insert([
                'company_id' => $company->id,
                'email' => $invitationData['email'],
                'role' => $invitationData['role'],
                'token' => Str::random(64),
                'invited_by_user_id' => $inviter->id,
                'accepted_by_user_id' => $invitationData['status'] === 'accepted'
                    ? User::where('email', $invitationData['email'])->first()?->id
                    : null,
                'status' => $invitationData['status'],
                'expires_at' => $invitationData['expires_at'],
                'accepted_at' => $invitationData['accepted_at'] ?? null,
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now(),
            ]);
        }
    }
}
