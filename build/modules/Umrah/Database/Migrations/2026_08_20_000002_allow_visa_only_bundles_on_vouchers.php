<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE umrah.vouchers DROP CONSTRAINT IF EXISTS vouchers_service_bundle_check');
        DB::statement("ALTER TABLE umrah.vouchers ADD CONSTRAINT vouchers_service_bundle_check
            CHECK (service_bundle IN ('visa_transport', 'visa_transport_hotel', 'transport', 'transport_hotel', 'hotel', 'visa', 'visa_hotel'))");

        // These rows were written before 'visa' and 'visa_hotel' existed as a
        // vocabulary. A self-arranged group (transport_mode = 'none') cannot
        // sell a transport-bearing bundle, so any voucher on one that carries
        // 'visa_transport' or 'visa_transport_hotel' is misrepresenting what
        // was actually sold -- it never had a bus. Correct those rows to the
        // value that was always meant. Vouchers on standard_bus or
        // specialized groups are untouched: their bus is real.
        DB::statement("UPDATE umrah.vouchers SET service_bundle = 'visa'
            WHERE service_bundle = 'visa_transport'
            AND visa_group_id IN (SELECT id FROM umrah.visa_groups WHERE transport_mode = 'none')");

        DB::statement("UPDATE umrah.vouchers SET service_bundle = 'visa_hotel'
            WHERE service_bundle = 'visa_transport_hotel'
            AND visa_group_id IN (SELECT id FROM umrah.visa_groups WHERE transport_mode = 'none')");
    }

    /**
     * Refuses rather than rewrites.
     *
     * Rewriting 'visa' back to 'visa_transport' would quietly invent a bus
     * booking for pilgrims who arranged their own transport -- exactly the
     * defect this migration exists to fix. A schema rollback is not a
     * licence to edit the books; if real vouchers depend on the value being
     * removed, a human decides what happens to them.
     */
    public function down(): void
    {
        $carrying = DB::table('umrah.vouchers')
            ->whereIn('service_bundle', ['visa', 'visa_hotel'])
            ->count();

        if ($carrying > 0) {
            throw new \RuntimeException(
                "Cannot roll back: {$carrying} voucher(s) use service_bundle 'visa' or 'visa_hotel'. "
                .'Reassign them deliberately before removing the value.'
            );
        }

        DB::statement('ALTER TABLE umrah.vouchers DROP CONSTRAINT IF EXISTS vouchers_service_bundle_check');
        DB::statement("ALTER TABLE umrah.vouchers ADD CONSTRAINT vouchers_service_bundle_check
            CHECK (service_bundle IN ('visa_transport', 'visa_transport_hotel', 'transport', 'transport_hotel', 'hotel'))");
    }
};
