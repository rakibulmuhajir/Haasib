<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Retire nozzle rows whose pump was already soft-deleted or removed before
     * pump deletion started cascading soft deletes to its nozzles.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE fuel.nozzles AS nozzles
            SET deleted_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE nozzles.deleted_at IS NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM fuel.pumps AS pumps
                  WHERE pumps.id = nozzles.pump_id
                    AND pumps.deleted_at IS NULL
              )
        SQL);
    }

    public function down(): void
    {
        // The affected rows cannot be safely distinguished from intentional
        // nozzle deletions, so this data repair is intentionally irreversible.
    }
};
