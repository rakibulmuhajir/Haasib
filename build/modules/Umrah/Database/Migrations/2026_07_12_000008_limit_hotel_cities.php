<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE umrah.hotels SET city = 'Makkah' WHERE lower(trim(city)) IN ('makkah', 'mecca')");
        DB::statement("UPDATE umrah.hotels SET city = 'Madinah' WHERE lower(trim(city)) IN ('madinah', 'madina', 'medina', 'medinah')");
        DB::statement("ALTER TABLE umrah.hotels ADD CONSTRAINT hotels_city_check CHECK (city IN ('Makkah', 'Madinah')) NOT VALID");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE umrah.hotels DROP CONSTRAINT IF EXISTS hotels_city_check');
    }
};
