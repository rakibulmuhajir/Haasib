<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create the bank schema
        DB::statement('CREATE SCHEMA IF NOT EXISTS bank');
    }

    public function down(): void
    {
        // Drop the bank schema and all its objects
        DB::statement('DROP SCHEMA IF EXISTS bank CASCADE');
    }
};