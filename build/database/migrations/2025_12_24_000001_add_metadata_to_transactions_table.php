<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.transactions', function (Blueprint $table) {
            $table->jsonb('metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('acct.transactions', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
