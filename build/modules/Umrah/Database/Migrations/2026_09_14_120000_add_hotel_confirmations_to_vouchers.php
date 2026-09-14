<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.vouchers', function (Blueprint $table) {
            // The parent voucher's existing company RLS policy covers this metadata.
            $table->jsonb('hotel_confirmations')->default('{}');
        });
    }

    public function down(): void
    {
        Schema::table('umrah.vouchers', fn (Blueprint $table) => $table->dropColumn('hotel_confirmations'));
    }
};
