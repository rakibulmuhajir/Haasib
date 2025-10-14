<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Core seeders
            PermissionSeeder::class,
            ModuleSeeder::class,

            // Company seeders
            ProfessionalServicesCompanySeeder::class,
            RetailCompanySeeder::class,
            HospitalityCompanySeeder::class,

            // Command Palette seeders
            CommandSeeder::class,
            CommandConfigurationSeeder::class,

            // Demo data seeders
            DemoDataSeeder::class,
            CompanyDemoSeeder::class,
        ]);
    }
}
