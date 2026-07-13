<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('umrah.passengers', 'imported_age')) {
            return;
        }

        Schema::table('umrah.passengers', function (Blueprint $table) {
            $table->integer('imported_age')->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('umrah.passengers', 'imported_age')) {
            return;
        }

        Schema::table('umrah.passengers', function (Blueprint $table) {
            $table->dropColumn('imported_age');
        });
    }
};
