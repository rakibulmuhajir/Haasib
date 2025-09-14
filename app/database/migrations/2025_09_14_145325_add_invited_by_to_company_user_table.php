<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auth.company_user', function (Blueprint $table) {
            $table->uuid('invited_by_user_id')->nullable();
            $table->foreign('invited_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth.company_user', function (Blueprint $table) {
            $table->dropForeign(['invited_by_user_id']);
            $table->dropColumn('invited_by_user_id');
        });
    }
};
