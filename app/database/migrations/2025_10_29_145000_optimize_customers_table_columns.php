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
        Schema::table('acct.customers', function (Blueprint $table) {
            // Reduce string column lengths to more reasonable sizes
            $table->string('name', 150)->nullable()->change();
            $table->string('email', 150)->nullable()->change();
            $table->string('phone', 50)->nullable()->change();
            $table->string('tax_id', 50)->nullable()->change();
            $table->string('website', 150)->nullable()->change();
            $table->string('customer_number', 50)->nullable()->change();
            $table->string('city', 100)->nullable()->change();
            $table->string('state', 100)->nullable()->change();
            $table->string('postal_code', 20)->nullable()->change();
            $table->string('country', 100)->nullable()->change();
            $table->string('payment_terms', 50)->nullable()->change();

            // Convert text fields to varchar with reasonable limits
            $table->string('address', 500)->nullable()->change();
            $table->string('notes', 1000)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acct.customers', function (Blueprint $table) {
            // Revert to original lengths
            $table->string('name', 255)->change();
            $table->string('email', 255)->change();
            $table->string('phone', 255)->change();
            $table->string('tax_id', 255)->change();
            $table->string('website', 255)->change();
            $table->string('customer_number', 255)->change();
            $table->string('city', 255)->change();
            $table->string('state', 255)->change();
            $table->string('postal_code', 255)->change();
            $table->string('country', 255)->change();
            $table->string('payment_terms', 255)->change();

            // Revert to text fields
            $table->text('address')->nullable()->change();
            $table->text('notes')->nullable()->change();
        });
    }
};
