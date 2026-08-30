<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A refund now says which of the three refundable things it paid back, and
 * tickets were missing from that list.
 *
 * 'visa' stays valid here even though nothing may choose it any more. A
 * group is built after the visas come back approved and only the approved
 * ones are imported, so there is no visa left to refund -- but refunds
 * already recorded against one are history, and history does not stop
 * being true because the rule changed. The refusal belongs in the form,
 * where it can say why; the column's job is to keep what happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE umrah.refunds DROP CONSTRAINT IF EXISTS umrah_refunds_service_check');
        DB::statement("ALTER TABLE umrah.refunds ADD CONSTRAINT umrah_refunds_service_check
            CHECK (service IN ('visa', 'transport', 'hotel', 'ticket', 'other'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE umrah.refunds SET service = 'other' WHERE service = 'ticket'");
        DB::statement('ALTER TABLE umrah.refunds DROP CONSTRAINT IF EXISTS umrah_refunds_service_check');
        DB::statement("ALTER TABLE umrah.refunds ADD CONSTRAINT umrah_refunds_service_check
            CHECK (service IN ('visa', 'transport', 'hotel', 'other'))");
    }
};
