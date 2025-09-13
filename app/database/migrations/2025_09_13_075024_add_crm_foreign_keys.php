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
        Schema::table('customers', function (Blueprint $table) {
            $table->index('company_id', 'idx_customers_company');
            $table->index(['company_id', 'name'], 'idx_customers_company_name');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->index('company_id', 'idx_vendors_company');
            $table->index(['company_id', 'name'], 'idx_vendors_company_name');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->index('company_id', 'idx_contacts_company');
            $table->index('customer_id', 'idx_contacts_customer');
            $table->index('vendor_id', 'idx_contacts_vendor');
        });

        Schema::table('interactions', function (Blueprint $table) {
            $table->index('company_id', 'idx_interactions_company');
            $table->index('customer_id', 'idx_interactions_customer');
            $table->index('vendor_id', 'idx_interactions_vendor');
            $table->index('contact_id', 'idx_interactions_contact');
            $table->index('created_by', 'idx_interactions_created_by');
            $table->index(['company_id', 'interaction_date'], 'idx_interactions_company_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_company');
            $table->dropIndex('idx_customers_company_name');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex('idx_vendors_company');
            $table->dropIndex('idx_vendors_company_name');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_contacts_company');
            $table->dropIndex('idx_contacts_customer');
            $table->dropIndex('idx_contacts_vendor');
        });

        Schema::table('interactions', function (Blueprint $table) {
            $table->dropIndex('idx_interactions_company');
            $table->dropIndex('idx_interactions_customer');
            $table->dropIndex('idx_interactions_vendor');
            $table->dropIndex('idx_interactions_contact');
            $table->dropIndex('idx_interactions_created_by');
            $table->dropIndex('idx_interactions_company_date');
        });
    }
};
