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
            $table->string('currency', 3)->default('USD')->after('slug');
            $table->string('timezone')->default('UTC')->after('currency');
            
            // Add indexes for performance
            $table->index('currency');
            $table->index('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->dropIndex(['currency']);
            $table->dropIndex(['timezone']);
            $table->dropColumn(['currency', 'timezone']);
        });
    }
};
