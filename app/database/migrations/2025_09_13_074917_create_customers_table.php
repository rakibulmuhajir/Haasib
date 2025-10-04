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
        Schema::create('hrm.customers', function (Blueprint $table) {
            $table->uuid('customer_id')->primary();
            $table->uuid('company_id');
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->uuid('currency_id')->nullable();
            $table->boolean('is_active')->default(true);
            // Idempotency: prevent duplicate customer creation on retry
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->unique(['company_id', 'name']);
        });

        Schema::table('hrm.customers', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('currency_id')->references('id')->on('public.currencies')->onDelete('set null');
        });

        // Idempotency unique scope within company
        try {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS customers_idemp_unique ON hrm.customers (company_id, idempotency_key) WHERE idempotency_key IS NOT NULL');
        } catch (\Throwable $e) { /* ignore */
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrm.customers');
    }
};
