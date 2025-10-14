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
        Schema::create('invoicing.invoice_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->string('currency', 3);
            $table->jsonb('template_data');
            $table->jsonb('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id');
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');

            $table->foreign('customer_id')
                ->references('id')
                ->on('invoicing.customers')
                ->onDelete('set null');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('cascade');

            // Indexes for performance
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['currency']);
            $table->index(['is_active']);
            $table->index(['created_by_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing.invoice_templates');
    }
};
