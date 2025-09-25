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
        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('auth.companies')->cascadeOnDelete();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('tax_number')->nullable();

            $table->foreignUuid('currency_code')->nullable()->constrained(table: 'currencies', column: 'code')->nullOnDelete();

            // For tracking credit notes and balances
            $table->decimal('credit_balance', 15, 4)->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE vendors ADD CONSTRAINT vendors_credit_balance_check CHECK (credit_balance >= 0)');

        // Add indexes for better query performance
        DB::statement('CREATE INDEX idx_vendors_company_active ON vendors(company_id, is_active) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_vendors_company_currency ON vendors(company_id, currency_code) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
