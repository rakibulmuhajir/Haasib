<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION generate_uuid()
            RETURNS uuid AS $$
            BEGIN
                RETURN gen_random_uuid();
            END;
            $$ LANGUAGE plpgsql;
        ");
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS generate_uuid()');
    }
};
