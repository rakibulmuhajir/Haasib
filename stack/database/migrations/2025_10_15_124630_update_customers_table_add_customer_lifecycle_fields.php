<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoicing.customers', function (Blueprint $table) {
            // Add legal_name field
            $table->string('legal_name')->nullable()->after('name');

            // Update customer_number to be unique per company
            $table->dropUnique(['customer_number']);
            $table->unique(['company_id', 'customer_number']);

            // Add default_currency field (required)
            $table->char('default_currency', 3)->after('phone');

            // Update credit_limit to be more precise
            $table->decimal('credit_limit', 15, 2)->change();

            // Add credit_limit_effective_at for tracking when limit was set
            $table->timestamp('credit_limit_effective_at')->nullable()->after('credit_limit');

            // Replace is_active with enum status
            $table->dropColumn('is_active');
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active')->after('email');

            // Add new indexes
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'email']);

            // Add trigram index for name search (PostgreSQL specific)
            DB::statement('CREATE INDEX customers_name_trigram_idx ON "invoicing.customers" USING gin(name gin_trgm_ops)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing.customers', function (Blueprint $table) {
            $table->dropColumn(['legal_name', 'default_currency', 'credit_limit_effective_at', 'status']);

            // Restore is_active
            $table->boolean('is_active')->default(true)->after('email');

            // Restore original customer_number uniqueness
            $table->dropUnique(['company_id', 'customer_number']);
            $table->unique(['customer_number']);

            // Restore original credit_limit precision
            $table->decimal('credit_limit', 10, 2)->change();

            // Drop indexes
            $table->dropIndex(['company_id', 'status']);
            $table->dropIndex(['company_id', 'email']);
        });

        // Drop trigram index
        DB::statement('DROP INDEX IF EXISTS customers_name_trigram_idx');
    }
};
