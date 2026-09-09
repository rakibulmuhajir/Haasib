<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['umrah.agents', 'umrah.visa_vendors'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->jsonb('voucher_settings')->nullable());
        }
        Schema::table('umrah.vouchers', fn (Blueprint $table) => $table->jsonb('print_details')->nullable());
        // Existing company isolation/RLS policies cover the new columns.
    }

    public function down(): void
    {
        Schema::table('umrah.vouchers', fn (Blueprint $table) => $table->dropColumn('print_details'));
        foreach (['umrah.agents', 'umrah.visa_vendors'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('voucher_settings'));
        }
    }
};
