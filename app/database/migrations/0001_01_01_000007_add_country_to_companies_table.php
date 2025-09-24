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
        // Check if the table exists before trying to modify it
        if (Schema::hasTable('auth.companies')) {
            Schema::table('auth.companies', function (Blueprint $table) {
                $table->string('country', 2)->nullable()->after('name');
                $table->uuid('country_id')->nullable()->after('country');

                // Add foreign key constraint for country_id
                $table->foreign('country_id')
                    ->references('id')->on('countries')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if the table exists before trying to modify it
        if (Schema::hasTable('auth.companies')) {
            Schema::table('auth.companies', function (Blueprint $table) {
                try {
                    $table->dropForeign(['country_id']);
                } catch (\Throwable $e) {
                    // Foreign key might not exist
                }

                try {
                    $table->dropColumn(['country_id', 'country']);
                } catch (\Throwable $e) {
                    // Columns might not exist
                }
            });
        }
    }
};
