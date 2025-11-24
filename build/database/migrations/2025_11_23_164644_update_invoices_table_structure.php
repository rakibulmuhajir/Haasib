<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing columns and update existing ones
        Schema::table('acct.invoices', function (Blueprint $table) {
            // Add missing payment_status column
            $table->enum('payment_status', ['unpaid', 'partially_paid', 'paid', 'overdue'])->default('unpaid')->after('status');
            
            // Add missing multi-currency columns
            $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal');
            $table->decimal('exchange_rate', 12, 6)->default(1.000000)->after('currency');
            $table->decimal('base_currency_total', 12, 2)->nullable()->after('exchange_rate');
            $table->decimal('shipping_amount', 12, 2)->default(0)->after('base_currency_total');
            $table->string('po_number')->nullable()->after('shipping_amount');
        });

        // Rename columns to match expected structure
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->renameColumn('invoice_date', 'issue_date');
            $table->renameColumn('created_by', 'created_by_user_id');
            $table->renameColumn('currency', 'currency_code');
        });

        // Update currency column to be char(3) instead of string
        DB::statement('ALTER TABLE acct.invoices ALTER COLUMN currency_code TYPE char(3)');
        
        // Add new indexes for the new columns
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->index(['company_id', 'payment_status']);
            $table->index(['company_id', 'currency_code']);
            $table->index(['payment_status', 'due_date']);
        });

        // Update the constraint to handle the renamed balance_due field correctly
        DB::statement('
            ALTER TABLE acct.invoices 
            DROP CONSTRAINT IF EXISTS check_balance_due
        ');
        
        DB::statement('
            ALTER TABLE acct.invoices 
            ADD CONSTRAINT check_balance_due CHECK (balance_due = total_amount - paid_amount)
        ');
    }

    public function down(): void
    {
        // Remove added columns
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'discount_amount',
                'exchange_rate',
                'base_currency_total',
                'shipping_amount',
                'po_number'
            ]);
        });

        // Rename columns back
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->renameColumn('issue_date', 'invoice_date');
            $table->renameColumn('created_by_user_id', 'created_by');
            $table->renameColumn('currency_code', 'currency');
        });

        // Drop the added indexes
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'payment_status']);
            $table->dropIndex(['company_id', 'currency_code']);
            $table->dropIndex(['payment_status', 'due_date']);
        });
    }
};