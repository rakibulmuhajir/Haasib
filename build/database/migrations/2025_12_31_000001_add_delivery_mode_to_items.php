<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv.items', function (Blueprint $table) {
            $table->string('delivery_mode', 30)->default('requires_receiving');
        });

        DB::statement("ALTER TABLE inv.items ADD CONSTRAINT items_delivery_mode_check
            CHECK (delivery_mode IN ('immediate', 'requires_receiving'))");

        DB::statement("UPDATE inv.items
            SET delivery_mode = 'immediate'
            WHERE track_inventory = false OR item_type IN ('service', 'non_inventory')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inv.items DROP CONSTRAINT IF EXISTS items_delivery_mode_check');

        Schema::table('inv.items', function (Blueprint $table) {
            $table->dropColumn('delivery_mode');
        });
    }
};
