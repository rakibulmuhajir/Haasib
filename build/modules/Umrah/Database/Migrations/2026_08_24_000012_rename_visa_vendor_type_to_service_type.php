<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * umrah.visa_vendors.vendor_type becomes service_type.
 *
 * The column never held a vendor type. It holds what the supplier provides to
 * a group -- government, visa provider, transport provider, hotel -- which is
 * a different question from the one acct.vendors.vendor_type answers, namely
 * how a supplier is bought from and posted.
 *
 * Two columns of the same name answering different questions was survivable
 * while the tables were strangers. 2026_08_24_000011 made umrah.visa_vendors
 * an extension of acct.vendors, so the two now sit on either side of a join
 * and a query naming vendor_type has to be read twice to see which it means.
 * That is the kind of ambiguity that eventually selects the wrong one.
 *
 * The values do not change -- this renames the question, not the answers -- so
 * no row is rewritten and the constraint is recreated over the same set.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dropped before the rename: Postgres carries a CHECK constraint's
        // expression through a column rename, so the constraint would survive
        // reading service_type while still being named for vendor_type.
        DB::statement('ALTER TABLE umrah.visa_vendors DROP CONSTRAINT IF EXISTS visa_vendors_type_check');

        // Qualified, because the Blueprint's index rename is not: an index
        // lives in its table's schema and umrah is not on the search_path.
        // 2026_08_24_000001 and 000010 took the same way round it.
        DB::statement('ALTER INDEX IF EXISTS umrah.visa_vendors_company_id_vendor_type_index
            RENAME TO visa_vendors_company_id_service_type_index');

        DB::statement('ALTER TABLE umrah.visa_vendors RENAME COLUMN vendor_type TO service_type');

        DB::statement("ALTER TABLE umrah.visa_vendors ADD CONSTRAINT visa_vendors_service_type_check
            CHECK (service_type IN ('government', 'visa_provider', 'transport_provider', 'hotel', 'other'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE umrah.visa_vendors DROP CONSTRAINT IF EXISTS visa_vendors_service_type_check');

        DB::statement('ALTER INDEX IF EXISTS umrah.visa_vendors_company_id_service_type_index
            RENAME TO visa_vendors_company_id_vendor_type_index');

        DB::statement('ALTER TABLE umrah.visa_vendors RENAME COLUMN service_type TO vendor_type');

        DB::statement("ALTER TABLE umrah.visa_vendors ADD CONSTRAINT visa_vendors_type_check
            CHECK (vendor_type IN ('government', 'visa_provider', 'transport_provider', 'hotel', 'other'))");
    }
};
