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
        Schema::create('acct.invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 20)->default('draft'); // draft, sent, paid, overdue, void
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('customer_id')->references('id')->on('acct.customers')->onDelete('restrict');

            // Indexes
            $table->index(['company_id']);
            $table->index(['customer_id']);
            $table->index(['company_id', 'invoice_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'invoice_date']);
            $table->index(['company_id', 'due_date']);
            $table->index(['status']);
            $table->index(['invoice_date']);
            $table->index(['due_date']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.invoices ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY invoices_company_policy ON acct.invoices
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

        // Add constraints
        DB::statement('
            ALTER TABLE acct.invoices
            ADD CONSTRAINT invoices_amounts_positive
            CHECK (
                subtotal >= 0
                AND tax_amount >= 0
                AND discount_amount >= 0
                AND total_amount >= 0
                AND paid_amount >= 0
                AND balance_due >= 0
            )
        ');

        DB::statement('
            ALTER TABLE acct.invoices
            ADD CONSTRAINT invoices_valid_status
            CHECK (status IN (\'draft\', \'sent\', \'paid\', \'overdue\', \'void\'))
        ');

        // Balance due calculated trigger
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.calculate_balance_due()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.balance_due = NEW.total_amount - NEW.paid_amount;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('
            CREATE TRIGGER invoices_calculate_balance_due
            BEFORE INSERT OR UPDATE ON acct.invoices
            FOR EACH ROW EXECUTE FUNCTION acct.calculate_balance_due();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS invoices_calculate_balance_due ON acct.invoices');
        DB::statement('DROP FUNCTION IF EXISTS acct.calculate_balance_due()');
        DB::statement('DROP POLICY IF EXISTS invoices_company_policy ON acct.invoices');
        DB::statement('ALTER TABLE acct.invoices DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.invoices');
    }
};