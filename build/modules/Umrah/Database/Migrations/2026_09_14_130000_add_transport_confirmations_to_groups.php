<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->jsonb('transport_confirmations')->default('{}');
            $table->uuid('transport_booking_revision')->nullable();
        });
        Schema::table('umrah.group_transport_items', fn (Blueprint $table) => $table->uuid('booking_revision')->nullable());
    }

    public function down(): void
    {
        Schema::table('umrah.group_transport_items', fn (Blueprint $table) => $table->dropColumn('booking_revision'));
        Schema::table('umrah.visa_groups', fn (Blueprint $table) => $table->dropColumn(['transport_confirmations', 'transport_booking_revision']));
    }
};
