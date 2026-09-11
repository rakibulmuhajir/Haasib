<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->uuid('leader_passenger_id')->nullable();
            $table->foreign('leader_passenger_id')->references('id')->on('umrah.passengers')->nullOnDelete();
        });
        // Existing tenant RLS covers the new field. Do not invent a leader for
        // legacy vouchers based on their current passenger ordering.
    }

    public function down(): void
    {
        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->dropForeign(['leader_passenger_id']);
            $table->dropColumn('leader_passenger_id');
        });
    }
};
