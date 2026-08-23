<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->boolean('includes_visa')->default(true);
        });

        // A group that sells neither a visa nor transport is not a group.
        DB::statement("ALTER TABLE umrah.visa_groups ADD CONSTRAINT visa_groups_sells_something_check
            CHECK (includes_visa OR transport_mode <> 'none')");

        // passports_received, submitted and visa_approved describe a visa
        // application. A transport-only group has none, so those states are
        // rejected rather than merely hidden -- otherwise a group can be
        // parked where no one can act on it.
        DB::statement("ALTER TABLE umrah.visa_groups ADD CONSTRAINT visa_groups_status_matches_kind_check
            CHECK (includes_visa OR status IN ('draft', 'delivered', 'closed', 'cancelled'))");
    }

    public function down(): void
    {
        $stranded = DB::table('umrah.visa_groups')->where('includes_visa', false)->count();

        if ($stranded > 0) {
            throw new \RuntimeException(
                "Cannot roll back: {$stranded} transport-only group(s) exist. "
                .'Each would silently become a visa group with no visa vendor. Reassign them deliberately first.'
            );
        }

        DB::statement('ALTER TABLE umrah.visa_groups DROP CONSTRAINT IF EXISTS visa_groups_status_matches_kind_check');
        DB::statement('ALTER TABLE umrah.visa_groups DROP CONSTRAINT IF EXISTS visa_groups_sells_something_check');

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropColumn('includes_visa');
        });
    }
};
