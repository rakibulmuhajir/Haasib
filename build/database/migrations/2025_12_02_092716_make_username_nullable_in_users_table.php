<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auth.users', function (Blueprint $table) {
            $table->string('username')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('auth.users')
            ->whereNull('username')
            ->update(['username' => DB::raw("concat('user-', id::text)")]);

        Schema::table('auth.users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
        });
    }
};
