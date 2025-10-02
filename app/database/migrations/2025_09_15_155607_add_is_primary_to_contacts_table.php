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
        Schema::table('hrm.contacts', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('notes');

            // Add index for faster primary contact lookup
            $table->index(['customer_id', 'is_primary']);
            $table->index(['vendor_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hrm.contacts', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'is_primary']);
            $table->dropIndex(['vendor_id', 'is_primary']);
            $table->dropColumn('is_primary');
        });
    }
};
