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
        Schema::table('acct.payments', function (Blueprint $table) {
            // Add missing columns for PaymentService integration
            $table->uuid('invoice_id')->nullable()->after('customer_id');
            $table->uuid('created_by_user_id')->nullable()->after('metadata');
            $table->decimal('refunded_amount', 15, 2)->default(0)->after('status');
            $table->timestamp('refunded_at')->nullable()->after('refunded_amount');
            $table->text('refunded_reason')->nullable()->after('refunded_at');
            $table->timestamp('reversed_at')->nullable()->after('refunded_reason');
            $table->text('reversed_reason')->nullable()->after('reversed_at');

            // Rename payment_reference to reference_number for consistency
            $table->renameColumn('payment_reference', 'reference_number');

            // Add foreign key for invoice relationship
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->onDelete('set null');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->onDelete('set null');
        });

        // Update RLS policy to include new columns
        DB::statement('DROP POLICY IF EXISTS payments_company_policy ON acct.payments');
        DB::statement("
            CREATE POLICY payments_company_policy ON acct.payments
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Add new constraints
        DB::statement('
            ALTER TABLE acct.payments
            ADD CONSTRAINT payments_refunded_amount_valid
            CHECK (refunded_amount >= 0 AND refunded_amount <= amount)
        ');

        DB::statement('
            ALTER TABLE acct.payments
            ADD CONSTRAINT payments_valid_status_extended
            CHECK (status IN (\'pending\', \'completed\', \'failed\', \'cancelled\', \'refunded\', \'partially_refunded\', \'reversed\'))
        ');

        // Add indexes for new columns
        Schema::table('acct.payments', function (Blueprint $table) {
            $table->index(['invoice_id']);
            $table->index(['created_by_user_id']);
            $table->index(['company_id', 'invoice_id']);
            $table->index(['refunded_at']);
            $table->index(['reversed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acct.payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['created_by_user_id']);
            $table->dropIndex(['invoice_id']);
            $table->dropIndex(['created_by_user_id']);
            $table->dropIndex(['company_id', 'invoice_id']);
            $table->dropIndex(['refunded_at']);
            $table->dropIndex(['reversed_at']);

            $table->dropColumn('invoice_id');
            $table->dropColumn('created_by_user_id');
            $table->dropColumn('refunded_amount');
            $table->dropColumn('refunded_at');
            $table->dropColumn('refunded_reason');
            $table->dropColumn('reversed_at');
            $table->dropColumn('reversed_reason');

            $table->renameColumn('reference_number', 'payment_reference');
        });

        DB::statement('DROP CONSTRAINT IF EXISTS payments_refunded_amount_valid');
        DB::statement('DROP CONSTRAINT IF EXISTS payments_valid_status_extended');

        // Recreate original RLS policy
        DB::statement('DROP POLICY IF EXISTS payments_company_policy ON acct.payments');
        DB::statement("
            CREATE POLICY payments_company_policy ON acct.payments
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
    }
};