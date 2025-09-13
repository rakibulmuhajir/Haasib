<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_item_taxes', function (Blueprint $table) {
            $table->foreignId('invoice_item_id')->constrained('invoice_items', 'invoice_item_id');
            $table->foreignId('tax_rate_id')->constrained('currencies'); // Placeholder, will be FK to tax_rates when available
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->timestamps();
            
            $table->primary(['invoice_item_id', 'tax_rate_id']);
        });
        
        // Add check constraint
        DB::statement('ALTER TABLE invoice_item_taxes ADD CONSTRAINT chk_tax_nonneg CHECK (tax_amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_item_taxes');
    }
};