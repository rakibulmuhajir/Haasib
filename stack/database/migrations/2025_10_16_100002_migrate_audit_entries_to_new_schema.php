<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if source table exists
        if (! Schema::hasTable('auth.audit_entries')) {
            return;
        }

        // Check if destination table is ready
        if (! Schema::hasTable('audit.entries')) {
            throw new Exception('audit.entries table must exist before running this migration');
        }

        // Start transaction for data migration
        DB::transaction(function () {
            // Check for existing data in destination to avoid duplicates
            $existingCount = DB::table('audit.entries')->count();
            if ($existingCount > 0) {
                throw new Exception('audit.entries already contains data. Cannot migrate to avoid duplicates.');
            }

            // Copy all data from auth.audit_entries to audit.entries
            DB::statement('
                INSERT INTO audit.entries (
                    id,
                    action,
                    entity_type,
                    entity_id,
                    user_id,
                    company_id,
                    old_values,
                    new_values,
                    ip_address,
                    user_agent,
                    device_type,
                    location,
                    metadata,
                    is_system_action,
                    created_at,
                    updated_at
                )
                SELECT 
                    id,
                    action,
                    entity_type,
                    entity_id,
                    user_id,
                    company_id,
                    old_values,
                    new_values,
                    ip_address,
                    user_agent,
                    device_type,
                    location,
                    metadata,
                    is_system_action,
                    created_at,
                    updated_at
                FROM auth.audit_entries
            ');

            // Verify the migration was successful
            $sourceCount = DB::table('auth.audit_entries')->count();
            $destCount = DB::table('audit.entries')->count();

            if ($sourceCount !== $destCount) {
                throw new Exception("Data migration failed: source has {$sourceCount} rows but destination has {$destCount} rows");
            }

            // Log successful migration
            $migratedRows = $destCount;
            DB::statement("DO $$
            BEGIN
                RAISE NOTICE 'Successfully migrated % audit entries from auth.audit_entries to audit.entries', $1;
            END $$;", [$migratedRows]);
        });

        // After successful migration, drop the old table
        // Commenting out the drop for safety - let the product owner decide
        // Schema::dropIfExists('auth.audit_entries');

        // Alternative: Rename to legacy table for backup purposes
        // DB::statement('ALTER TABLE auth.audit_entries RENAME TO auth.audit_entries_legacy');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // In case of rollback, we would need to restore the old table
        // This is complex and should be handled carefully

        // If we renamed to legacy:
        // DB::statement('ALTER TABLE auth.audit_entries_legacy RENAME TO auth.audit_entries');

        // If we dropped the table, we would need to recreate it
        // For safety, this rollback is left as an exercise for the DBA

        throw new Exception('This migration cannot be automatically rolled back. Please restore from backup if needed.');
    }
};
