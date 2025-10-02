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
        Schema::table('acct.invoices', function (Blueprint $table) {
            // Add unique index for idempotency_key and company_id
            $table->unique(['idempotency_key', 'company_id'], 'invoices_idempotency_key_company_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_idempotency_key_company_unique');
        });
    }
};
