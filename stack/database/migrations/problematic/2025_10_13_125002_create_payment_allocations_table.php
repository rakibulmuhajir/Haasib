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
            $table->decimal('allocated_amount', 15, 2);
            $table->timestamp('allocation_date');
            $table->string('allocation_method', 50)->default('manual'); // manual, automatic, fifo, proportional
            $table->string('allocation_strategy', 50)->nullable(); // fifo, due_date, amount, custom
            $table->text('notes')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->uuid('reversed_by_user_id')->nullable();
            $table->uuid('created_by_user_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('acct.payments')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->onDelete('restrict');
            $table->foreign('reversed_by_user_id')->references('id')->on('auth.users')->onDelete('set null');

            // Indexes
            $table->index(['company_id']);
            $table->index(['payment_id']);
            $table->index(['invoice_id']);
            $table->index(['company_id', 'payment_id']);
            $table->index(['company_id', 'invoice_id']);
            $table->index(['allocation_date']);
            $table->index(['allocation_method']);
            $table->index(['allocation_strategy']);
            $table->index(['reversed_at']);
        });

        // Add soft deletes
        Schema::table('acct.payment_allocations', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::statement('
            CREATE UNIQUE INDEX payment_allocations_unique_active
            ON acct.payment_allocations (payment_id, invoice_id)
            WHERE reversed_at IS NULL
        ');

        DB::statement('
            ALTER TABLE acct.payment_allocations
            ADD CONSTRAINT payment_allocations_amount_positive
            CHECK (allocated_amount >= 0)
        ');

        DB::statement('ALTER TABLE acct.payment_allocations ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY payment_allocations_company_policy
            ON acct.payment_allocations
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
        DB::statement('DROP POLICY IF EXISTS payment_allocations_company_policy ON acct.payment_allocations');
        DB::statement('ALTER TABLE acct.payment_allocations DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.payment_allocations');
    }
};
