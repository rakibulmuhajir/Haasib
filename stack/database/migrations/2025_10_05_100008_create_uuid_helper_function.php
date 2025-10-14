<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable pgcrypto extension if not already enabled
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        // Create a helper function for UUID generation
        DB::statement('
            CREATE OR REPLACE FUNCTION generate_uuid()
            RETURNS UUID AS $$
            BEGIN
                RETURN gen_random_uuid();
            END;
            $$ LANGUAGE plpgsql;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS generate_uuid()');
    }
};
