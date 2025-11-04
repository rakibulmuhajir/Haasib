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
        // Add company_id foreign keys to all accounting tables
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
        });

        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
        });

        Schema::table('acct.payments', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
        });

        Schema::table('acct.payment_allocations', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
        });

        Schema::table('acct.journal_entries', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
        });

        Schema::table('acct.journal_lines', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('acct.payments', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('acct.payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('acct.journal_entries', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('acct.journal_lines', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });
    }
};