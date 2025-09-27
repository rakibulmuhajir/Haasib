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
        Schema::create('bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();

            $table->string('bill_number');
            $table->string('reference_number')->nullable();

            $table->string('status')->default('draft'); // draft, received, approved, paid, void

            $table->date('bill_date');
            $table->date('due_date');

            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->decimal('exchange_rate', 15, 8)->default(1.00);

            $table->decimal('subtotal', 15, 4);
            $table->decimal('tax_total', 15, 4);
            $table->decimal('total', 15, 4);
            $table->decimal('amount_paid', 15, 4)->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'bill_number']);
        });

        DB::statement("ALTER TABLE bills ADD CONSTRAINT bills_status_check CHECK (status IN ('draft', 'received', 'approved', 'paid', 'void'))");
        DB::statement('ALTER TABLE bills ADD CONSTRAINT bills_amounts_check CHECK (subtotal >= 0 AND tax_total >= 0 AND total >= 0 AND amount_paid >= 0)');
        DB::statement('ALTER TABLE bills ADD CONSTRAINT bills_date_check CHECK (due_date >= bill_date)');

        // Add indexes for better query performance
        DB::statement('CREATE INDEX idx_bills_company_vendor ON bills(company_id, vendor_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_bills_company_status ON bills(company_id, status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_bills_company_dates ON bills(company_id, bill_date, due_date) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_bills_vendor_currency ON bills(vendor_id, currency_code) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
