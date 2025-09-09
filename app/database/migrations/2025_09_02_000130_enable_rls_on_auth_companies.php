<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        try {
            DB::statement('alter table if exists auth.companies enable row level security');
        } catch (\Throwable $e) {
            // ignore on non-PgSQL
        }
    }

    public function down(): void
    {
        try {
            DB::statement('alter table if exists auth.companies disable row level security');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};

