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
        Schema::create('acct.credit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->uuid('invoice_id')->nullable();
            $table->string('credit_note_number', 50);
            $table->date('credit_date')->default(now());
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->char('base_currency', 3)->default('USD');
            $table->string('reason', 255);
            $table->string('status', 20)->default('draft'); // draft, issued, applied, void
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('acct.customers')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            // Indexes
            $table->index(['company_id']);
            $table->index(['customer_id']);
            $table->index(['invoice_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'credit_note_number']);
            $table->index(['status']);
            $table->index(['credit_date']);

            // Unique constraints
            $table->unique(['company_id', 'credit_note_number'], 'unique_credit_note_per_company');
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.credit_notes ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY credit_notes_company_policy ON acct.credit_notes
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
            ALTER TABLE acct.credit_notes
            ADD CONSTRAINT credit_notes_amount_positive
            CHECK (amount > 0)
        ');

        DB::statement('
            ALTER TABLE acct.credit_notes
            ADD CONSTRAINT credit_notes_valid_status
            CHECK (status IN (\'draft\', \'issued\', \'applied\', \'void\'))
        ');

        DB::statement('
            ALTER TABLE acct.credit_notes
            ADD CONSTRAINT credit_notes_valid_currency
            CHECK (length(trim(base_currency)) = 3 AND base_currency ~ \'^[A-Z]{3}$\')
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS credit_notes_company_policy ON acct.credit_notes');
        DB::statement('ALTER TABLE acct.credit_notes DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.credit_notes');
    }
};