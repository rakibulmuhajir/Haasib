<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->string('country', 100)->nullable()->after('city');
            $table->index(['company_id', 'country']);
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS umrah.umrah_agents_company_id_country_index');

        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
