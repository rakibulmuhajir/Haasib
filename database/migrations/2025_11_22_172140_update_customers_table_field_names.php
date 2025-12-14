<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.customers', function (Blueprint $table) {
            // Drop old foreign key constraint
            $table->dropForeign(['created_by']);
            
            // Rename column
            $table->renameColumn('created_by', 'created_by_user_id');
            
            // Add new foreign key constraint
            $table->foreign('created_by_user_id')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('acct.customers', function (Blueprint $table) {
            // Drop new foreign key constraint
            $table->dropForeign(['created_by_user_id']);
            
            // Rename column back
            $table->renameColumn('created_by_user_id', 'created_by');
            
            // Add old foreign key constraint
            $table->foreign('created_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('cascade');
        });
    }
};