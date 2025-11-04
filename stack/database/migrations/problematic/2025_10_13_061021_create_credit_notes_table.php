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
            $table->uuid('invoice_id');
            $table->string('credit_note_number', 50);
            $table->text('reason')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('draft'); // draft, posted, cancelled
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->uuid('created_by_user_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->onDelete('restrict');

            // Indexes
            $table->unique(['company_id', 'credit_note_number']);
            $table->index(['company_id']);
            $table->index(['invoice_id']);
            $table->index(['company_id', 'invoice_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'posted_at']);
            $table->index(['company_id', 'created_at']);
        });

        DB::statement('ALTER TABLE acct.credit_notes ENABLE ROW LEVEL SECURITY');
        DB::statement('
            ALTER TABLE acct.credit_notes
            ADD CONSTRAINT credit_notes_amounts_positive
            CHECK (
                amount >= 0
                AND tax_amount >= 0
                AND total_amount >= 0
            )
        ');
        DB::statement("
            CREATE POLICY credit_notes_company_policy
            ON acct.credit_notes
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id')::uuid
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id')::uuid
            )
        ");
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
