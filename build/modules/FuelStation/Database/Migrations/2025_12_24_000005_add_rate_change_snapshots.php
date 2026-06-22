<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel.rate_changes', function (Blueprint $table) {
            $table->uuid('snapshot_tank_id')->nullable()->after('journal_entry_id');
            $table->decimal('snapshot_stick_reading', 12, 2)->nullable()->after('snapshot_tank_id');
            $table->decimal('snapshot_dip_liters', 12, 2)->nullable()->after('snapshot_stick_reading');
            $table->jsonb('snapshot_nozzle_readings')->nullable()->after('snapshot_dip_liters');

            $table->foreign('snapshot_tank_id')
                ->references('id')->on('inv.warehouses')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('fuel.rate_changes', function (Blueprint $table) {
            $table->dropForeign(['snapshot_tank_id']);
            $table->dropColumn([
                'snapshot_tank_id',
                'snapshot_stick_reading',
                'snapshot_dip_liters',
                'snapshot_nozzle_readings',
            ]);
        });
    }
};
