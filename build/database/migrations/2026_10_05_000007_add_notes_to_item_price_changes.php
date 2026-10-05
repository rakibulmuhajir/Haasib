<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* The price entry's note, kept on its change-log row so a deleted entry's note is still readable. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv.item_price_changes', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inv.item_price_changes', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
