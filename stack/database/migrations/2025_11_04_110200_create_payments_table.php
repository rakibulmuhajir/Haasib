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
        Schema::create('acct.payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->string('payment_number')->unique();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('payment_method', 50); // cash, check, credit_card, bank_transfer, etc.
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending, completed, failed, cancelled
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('customer_id')->references('id')->on('acct.customers')->onDelete('restrict');

            // Indexes
            $table->index(['company_id']);
            $table->index(['customer_id']);
            $table->index(['company_id', 'payment_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'payment_date']);
            $table->index(['status']);
            $table->index(['payment_date']);
            $table->index(['payment_method']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.payments ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
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

        // Add constraints
        DB::statement('
            ALTER TABLE acct.payments
            ADD CONSTRAINT payments_amount_positive
            CHECK (amount >= 0)
        ');

        DB::statement('
            ALTER TABLE acct.payments
            ADD CONSTRAINT payments_valid_status
            CHECK (status IN (\'pending\', \'completed\', \'failed\', \'cancelled\'))
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS payments_company_policy ON acct.payments');
        DB::statement('ALTER TABLE acct.payments DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.payments');
    }
};