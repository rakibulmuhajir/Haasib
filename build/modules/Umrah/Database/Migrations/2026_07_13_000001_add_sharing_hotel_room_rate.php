<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE umrah.hotel_room_rates DROP CONSTRAINT IF EXISTS hotel_room_rates_type_check');
        DB::statement("ALTER TABLE umrah.hotel_room_rates ADD CONSTRAINT hotel_room_rates_type_check CHECK (room_type IN ('sharing', 'double', 'triple', 'quad', 'quint'))");
    }

    public function down(): void
    {
        DB::table('umrah.hotel_room_rates')->where('room_type', 'sharing')->delete();
        DB::statement('ALTER TABLE umrah.hotel_room_rates DROP CONSTRAINT IF EXISTS hotel_room_rates_type_check');
        DB::statement("ALTER TABLE umrah.hotel_room_rates ADD CONSTRAINT hotel_room_rates_type_check CHECK (room_type IN ('double', 'triple', 'quad', 'quint'))");
    }
};
