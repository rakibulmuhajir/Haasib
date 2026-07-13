<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->string('logo_url', 500)->nullable()->after('country');
        });
        Schema::table('umrah.hotel_vendors', function (Blueprint $table) {
            $table->string('logo_url', 500)->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->dropColumn('logo_url');
        });
        Schema::table('umrah.hotel_vendors', function (Blueprint $table) {
            $table->dropColumn('logo_url');
        });
    }
};
