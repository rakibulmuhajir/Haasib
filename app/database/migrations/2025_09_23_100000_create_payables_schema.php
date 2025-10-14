<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schema creation removed as per request to use the 'public' schema.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed as the 'public' schema is not owned by this migration.
    }
};
