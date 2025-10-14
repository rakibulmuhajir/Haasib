<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('payment_allocations', 'deleted_at')) {
            Schema::table('payment_allocations', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('payment_allocations', 'deleted_at')) {
            Schema::table('payment_allocations', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
