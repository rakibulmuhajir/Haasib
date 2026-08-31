<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Closes the Jeddah-Madinah pair in the default transport catalogue.
 *
 * A group moves between three cities, which is six ordered pairs, and the
 * catalogue shipped four of them plus the two Madinah airport transfers.
 * The gap showed on the ordinary itinerary: land at Jeddah, Makkah, then
 * Madinah, fly home from Jeddah -- and there was no sector for that last
 * drive, so every company had to hand-create one before it could price
 * the journey.
 *
 * Backfilled into existing companies the same way the original six were,
 * so a company set up before today gains them without anyone opening the
 * settings page. ON CONFLICT DO NOTHING leaves alone any company that
 * already created its own sector under either code.
 */
return new class extends Migration
{
    private const SECTORS = [
        ['JED-MED', 'Jeddah Airport to Madinah Hotel', 'Jeddah Airport', 'Madinah Hotel', 7],
        ['MED-JED', 'Madinah Hotel to Jeddah Airport', 'Madinah Hotel', 'Jeddah Airport', 8],
    ];

    public function up(): void
    {
        DB::statement("SELECT set_config('app.is_super_admin', 'true', true)");

        foreach (self::SECTORS as [$code, $name, $origin, $destination, $sortOrder]) {
            DB::statement(
                'INSERT INTO umrah.transport_sectors (id, company_id, code, name, origin, destination, sort_order, is_active, created_at, updated_at)
                 SELECT public.gen_random_uuid(), id, ?, ?, ?, ?, ?, true, NOW(), NOW() FROM auth.companies
                 ON CONFLICT (company_id, code) DO NOTHING',
                [$code, $name, $origin, $destination, $sortOrder]
            );
        }

        DB::statement("SELECT set_config('app.is_super_admin', 'false', true)");
    }

    public function down(): void
    {
        DB::statement("SELECT set_config('app.is_super_admin', 'true', true)");

        // Only the untouched ones. A sector a company has priced a fare
        // against, or renamed, is theirs now and stays.
        DB::statement("
            DELETE FROM umrah.transport_sectors s
            WHERE s.code IN ('JED-MED', 'MED-JED')
              AND s.deleted_at IS NULL
              AND NOT EXISTS (SELECT 1 FROM umrah.transport_fares f WHERE f.transport_sector_id = s.id)
              AND NOT EXISTS (SELECT 1 FROM umrah.transport_package_sectors p WHERE p.transport_sector_id = s.id)
        ");

        DB::statement("SELECT set_config('app.is_super_admin', 'false', true)");
    }
};
