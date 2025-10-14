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
        Schema::create('acct.bill_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bill_id')->constrained('acct.bills')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('auth.companies')->cascadeOnDelete();

            $table->string('description');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('total', 15, 4);
            $table->unsignedInteger('display_order')->default(0);

            $table->jsonb('tax_rates')->nullable(); // e.g., [{ "name": "VAT", "rate": 5.00, "amount": 12.50 }]

            $table->timestamps();
        });

        DB::statement('ALTER TABLE acct.bill_items ADD CONSTRAINT bill_items_amounts_check CHECK (quantity >= 0 AND unit_price >= 0 AND total >= 0)');

        // Add indexes for better query performance
        DB::statement('CREATE INDEX idx_bill_items_bill_order ON acct.bill_items(bill_id, display_order)');
        DB::statement('CREATE INDEX idx_bill_items_company_bill ON acct.bill_items(company_id, bill_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.bill_items');
    }
};
