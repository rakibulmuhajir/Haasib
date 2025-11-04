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
        Schema::create('acct.payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('payment_id');
            $table->uuid('invoice_id');
            $table->decimal('amount', 15, 2);
            $table->date('allocation_date');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('payment_id')->references('id')->on('acct.payments')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->onDelete('cascade');

            // Indexes
            $table->index(['company_id']);
            $table->index(['payment_id']);
            $table->index(['invoice_id']);
            $table->index(['company_id', 'payment_id']);
            $table->index(['company_id', 'invoice_id']);
            $table->index(['allocation_date']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.payment_allocations ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY payment_allocations_company_policy ON acct.payment_allocations
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
            ALTER TABLE acct.payment_allocations
            ADD CONSTRAINT payment_allocations_amount_positive
            CHECK (amount > 0)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS payment_allocations_company_policy ON acct.payment_allocations');
        DB::statement('ALTER TABLE acct.payment_allocations DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.payment_allocations');
    }
};