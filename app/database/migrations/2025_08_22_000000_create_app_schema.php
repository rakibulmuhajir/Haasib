<?php

// database/migrations/2025_08_24_000000_create_app_schema.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create schema if not exists app;
            -- optional: make sure the connected role owns it (safe on dev)
            alter schema app owner to current_user;
        SQL);
    }

    public function down(): void
    {
        // In prod you likely DON'T want to drop this.
        // If you insist, uncomment the next line (this will drop any functions in app.*)
        // DB::unprepared('drop schema if exists app cascade;');
    }
};
