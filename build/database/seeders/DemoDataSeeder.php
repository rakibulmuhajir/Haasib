<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoFuelStationSeeder;
use Database\Seeders\Demo\DemoTradingCompanySeeder;
use Database\Seeders\Demo\DemoTravelAgencySeeder;
use Illuminate\Database\Seeder;

/**
 * Populated demo data for screenshots, demos and manual QA.
 *
 *   php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder
 *
 * Creates three companies under one login (demo@haasib.app / demo-password),
 * one per industry module, each carrying a full year of posted activity:
 *
 *   Meridian Trading Co.   wholesale     GL, AR/AP, trial balance, P&L
 *   Crescent Fuel Station  fuel_station  pumps, nozzles, daily close, investors
 *   Bab-al-Salam Travel    travel        Umrah groups, hotels, transport, vouchers
 *
 * Safe to re-run: each seeder purges its own company by slug first, scoped
 * strictly by company_id. It never touches companies it did not create.
 *
 * Nothing here is faked into the tables — every figure is posted through the
 * real onboarding and GL posting services, so the books balance because the
 * posting engine balanced them.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding demo companies…');

        $this->call([
            DemoTradingCompanySeeder::class,
            DemoFuelStationSeeder::class,
            DemoTravelAgencySeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Demo login:  demo@haasib.app  /  demo-password');
    }
}
