<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('unallocated_cash', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->uuid('customer_id');
            $table->uuid('company_id');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('available');
            $table->decimal('allocated_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'currency']);
            $table->index(['payment_id']);
            $table->index(['customer_id']);
            $table->index(['status']);
            $table->index(['created_at']);

            // Foreign keys
            $table->foreign('payment_id')
                  ->references('id')
                  ->on('payments')
                  ->onDelete('cascade');
                  
            $table->foreign('customer_id')
                  ->references('id')
                  ->on('hrm.customers')
                  ->onDelete('cascade');
                  
            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->onDelete('cascade');

            // Unique constraint to prevent duplicate unallocated cash for same payment
            $table->unique(['payment_id'], 'unallocated_cash_payment_unique');
        });

        // Create trigger for automatic RLS
        DB::unprepared("
            CREATE TRIGGER unallocated_cash_rls_trigger
            BEFORE INSERT ON unallocated_cash
            FOR EACH ROW
            EXECUTE FUNCTION set_current_company_id();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unallocated_cash');
    }
};