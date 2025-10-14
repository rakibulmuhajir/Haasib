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
        Schema::table('hrm.customers', function (Blueprint $table) {
            $table->string('customer_number')->nullable()->after('name');
            $table->string('customer_type')->default('individual')->after('customer_number');
            $table->string('status')->default('active')->after('customer_type');
            $table->decimal('credit_limit', 15, 2)->default(0.00)->after('currency_id');
            $table->string('payment_terms')->nullable()->after('credit_limit');
            $table->boolean('tax_exempt')->default(false)->after('tax_number');
            $table->text('notes')->nullable()->after('tax_exempt');

            // Add indexes
            $table->unique(['company_id', 'customer_number']);
            $table->index('customer_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hrm.customers', function (Blueprint $table) {
            $table->dropColumn([
                'customer_number',
                'customer_type',
                'status',
                'credit_limit',
                'payment_terms',
                'tax_exempt',
                'notes',
            ]);
        });
    }
};
