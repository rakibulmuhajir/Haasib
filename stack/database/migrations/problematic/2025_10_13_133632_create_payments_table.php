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
            $table->string('payment_number', 50);
            $table->date('payment_date');
            $table->string('payment_method', 50);
            $table->string('reference_number', 100)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('pending'); // pending, completed, failed, cancelled
            $table->text('notes')->nullable();
            $table->uuid('paymentable_id')->nullable(); // For backward compatibility
            $table->string('paymentable_type')->nullable(); // For backward compatibility
            $table->uuid('created_by_user_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('acct.customers')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->onDelete('restrict');

            // Indexes
            $table->unique(['company_id', 'payment_number']);
            $table->index(['company_id']);
            $table->index(['customer_id']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'payment_date']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'payment_method']);
        });

        // Add soft deletes
        Schema::table('acct.payments', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::statement('
            ALTER TABLE acct.payments
            ADD CONSTRAINT payments_amount_positive
            CHECK (amount >= 0)
        ');

        // Enforce tenant isolation
        DB::statement('ALTER TABLE acct.payments ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY payments_company_policy
            ON acct.payments
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
        DB::statement('DROP POLICY IF EXISTS payments_company_policy ON acct.payments');
        DB::statement('ALTER TABLE acct.payments DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.payments');
    }
};
