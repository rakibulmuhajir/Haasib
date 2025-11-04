<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the conflicting original constraint
        DB::statement('ALTER TABLE acct.payments DROP CONSTRAINT IF EXISTS payments_valid_status');

        // The extended constraint already exists and is correct
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the original constraint for rollback
        DB::statement('
            ALTER TABLE acct.payments
            ADD CONSTRAINT payments_valid_status
            CHECK (status IN (\'pending\', \'completed\', \'failed\', \'cancelled\'))
        ');
    }
};