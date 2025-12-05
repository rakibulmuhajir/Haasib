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
        if (Schema::hasTable('acct.payment_allocations')) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        Schema::create('acct.payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('payment_id');
            $table->uuid('invoice_id');
            $table->decimal('amount_allocated', 18, 6);
            $table->decimal('base_amount_allocated', 15, 2)->default(0.00);
            $table->timestamp('applied_at')->default(DB::raw('now()'));
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('payment_id')->references('id')->on('acct.payments')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->restrictOnDelete();

            $table->index('company_id');
            $table->index('payment_id');
            $table->index('invoice_id');
        });

        // Constraints
        DB::statement("ALTER TABLE acct.payment_allocations ADD CONSTRAINT payment_allocations_amount_positive CHECK (amount_allocated > 0)");
        DB::statement("ALTER TABLE acct.payment_allocations ADD CONSTRAINT payment_allocations_base_amount_non_negative CHECK (base_amount_allocated >= 0)");

        // Enable RLS
        DB::statement('ALTER TABLE acct.payment_allocations ENABLE ROW LEVEL SECURITY');
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
