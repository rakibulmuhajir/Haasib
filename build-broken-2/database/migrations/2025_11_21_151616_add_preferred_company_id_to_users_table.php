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
        Schema::table('auth.users', function (Blueprint $table) {
            $table->uuid('preferred_company_id')->nullable()->after('settings');
            $table->index('preferred_company_id');
            
            // Add foreign key constraint
            $table->foreign('preferred_company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth.users', function (Blueprint $table) {
            $table->dropForeign(['preferred_company_id']);
            $table->dropIndex(['preferred_company_id']);
            $table->dropColumn('preferred_company_id');
        });
    }
};
