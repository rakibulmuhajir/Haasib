<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE umrah.visa_groups DROP CONSTRAINT IF EXISTS visa_groups_transport_mode_check');
        DB::statement("ALTER TABLE umrah.visa_groups ADD CONSTRAINT visa_groups_transport_mode_check CHECK (transport_mode IN ('none', 'standard_bus', 'specialized'))");
    }

    /**
     * Refuses rather than rewrites.
     *
     * This used to UPDATE every self-arranged group to standard_bus with
     * transport_required = true, so a rollback would quietly invent a bus
     * booking for pilgrims who had arranged their own transport -- and the
     * groups it rewrote were indistinguishable afterwards from ones really
     * sold that way. A schema rollback is not a licence to edit the books;
     * if real groups depend on the value being removed, a human decides what
     * happens to them.
     */
    public function down(): void
    {
        $stranded = DB::table('umrah.visa_groups')->where('transport_mode', 'none')->count();

        if ($stranded > 0) {
            throw new \RuntimeException(
                "Cannot roll back: {$stranded} visa group(s) use transport_mode 'none'. "
                .'Reassign them deliberately before removing the value.'
            );
        }

        DB::statement('ALTER TABLE umrah.visa_groups DROP CONSTRAINT IF EXISTS visa_groups_transport_mode_check');
        DB::statement("ALTER TABLE umrah.visa_groups ADD CONSTRAINT visa_groups_transport_mode_check CHECK (transport_mode IN ('standard_bus', 'specialized'))");
    }
};
