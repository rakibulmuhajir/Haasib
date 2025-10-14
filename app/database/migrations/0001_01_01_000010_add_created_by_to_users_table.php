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
        if (!Schema::hasColumn('auth.users', 'created_by_user_id')) {
            Schema::table('auth.users', function (Blueprint $table) {
                $table->uuid('created_by_user_id')->nullable()->after('system_role');

                // Add foreign key constraint
                $table->foreign('created_by_user_id')
                    ->references('id')->on('auth.users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth.users', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn('created_by_user_id');
        });
    }
};
