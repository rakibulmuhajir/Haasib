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
        Schema::create('acct.credit_note_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('credit_note_id');
            $table->uuid('invoice_id');
            $table->decimal('amount_applied', 15, 2);
            $table->timestamp('applied_at')->default(now());
            $table->uuid('user_id')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('invoice_balance_before', 15, 2);
            $table->decimal('invoice_balance_after', 15, 2);
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('credit_note_id')->references('id')->on('acct.credit_notes')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('auth.users')->nullOnDelete();

            // Indexes
            $table->index(['company_id']);
            $table->index(['credit_note_id']);
            $table->index(['invoice_id']);
            $table->index(['company_id', 'credit_note_id']);
            $table->index(['company_id', 'invoice_id']);
            $table->index(['applied_at']);

            // Unique constraint: credit note can only be applied once per invoice
            $table->unique(['credit_note_id', 'invoice_id'], 'unique_credit_note_invoice_application');
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.credit_note_applications ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY credit_note_applications_company_policy ON acct.credit_note_applications
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
            ALTER TABLE acct.credit_note_applications
            ADD CONSTRAINT credit_note_applications_amount_positive
            CHECK (amount_applied > 0)
        ');

        DB::statement('
            ALTER TABLE acct.credit_note_applications
            ADD CONSTRAINT credit_note_applications_balance_consistency
            CHECK (invoice_balance_before >= invoice_balance_after)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS credit_note_applications_company_policy ON acct.credit_note_applications');
        DB::statement('ALTER TABLE acct.credit_note_applications DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.credit_note_applications');
    }
};