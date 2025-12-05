<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth.companies', function (Blueprint $table) {
            if (!Schema::hasColumn('auth.companies', 'logo_url')) {
                $table->string('logo_url', 500)->nullable()->after('settings');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auth.companies', function (Blueprint $table) {
            if (Schema::hasColumn('auth.companies', 'logo_url')) {
                $table->dropColumn('logo_url');
            }
        });
    }
};
