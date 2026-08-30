<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amending an approved voucher always failed on a duplicate key.
 *
 * Two unique indexes covered the same three columns. The one that belongs
 * -- voucher_passengers_active_assignment_unique -- is partial, WHERE
 * deleted_at IS NULL, so it says what it means: a passenger has one live
 * assignment in a group at a time. The other was the plain unique the
 * table was created with, and it counts soft-deleted rows too.
 *
 * 2026_07_16_000005_add_umrah_correction_workflows.php meant to replace
 * it and missed by a name: it dropped
 * voucher_passengers_company_id_visa_group_id_passenger_id_unique, while
 * the constraint actually on the table is
 * umrah_voucher_passengers_company_id_visa_group_id_passenger_id_. Right
 * intent, wrong name, so the old rule survived. It has to go as a
 * CONSTRAINT -- Postgres owns the index underneath it and refuses to drop
 * that on its own.
 *
 * createAmendment() soft-deletes the assignments and writes them again
 * against the new draft, which the surviving index reads as a duplicate.
 * Every amendment has been impossible since that migration ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE umrah.voucher_passengers DROP CONSTRAINT IF EXISTS umrah_voucher_passengers_company_id_visa_group_id_passenger_id_');
        DB::statement('DROP INDEX IF EXISTS umrah.umrah_voucher_passengers_company_id_visa_group_id_passenger_id_');

        // Belt and braces: the partial index is the rule now, and this is
        // cheap insurance against a database where the July migration was
        // the one that did not land.
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS voucher_passengers_active_assignment_unique
            ON umrah.voucher_passengers (company_id, visa_group_id, passenger_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        // Restores the state this migration found, duplicate rule and all.
        // Anything soft-deleted since would block the index being rebuilt,
        // which is the same fault described above.
        DB::statement('ALTER TABLE umrah.voucher_passengers ADD CONSTRAINT umrah_voucher_passengers_company_id_visa_group_id_passenger_id_
            UNIQUE (company_id, visa_group_id, passenger_id)');
    }
};
