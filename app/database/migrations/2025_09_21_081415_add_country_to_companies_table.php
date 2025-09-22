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
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('name');
            $table->uuid('country_id')->nullable()->after('country');

            // Add foreign key constraint for country_id
            $table->foreign('country_id')
                ->references('id')->on('countries')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn(['country_id', 'country']);
        });
    }
};
