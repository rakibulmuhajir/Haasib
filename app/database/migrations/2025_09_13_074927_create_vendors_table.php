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
            $table->id('vendor_id');
            $table->uuid('company_id');
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->text('address')->nullable();
            $table->uuid('currency_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->unique(['company_id', 'name']);
        });

        // Add foreign key constraint to auth.companies
        DB::statement('ALTER TABLE vendors ADD CONSTRAINT fk_vendors_company_id FOREIGN KEY (company_id) REFERENCES auth.companies(id) ON DELETE CASCADE');

        // Add foreign key constraint to currencies
        DB::statement('ALTER TABLE vendors ADD CONSTRAINT fk_vendors_currency_id FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE SET NULL');

        // Add index
        DB::statement('CREATE INDEX idx_vendors_company ON vendors(company_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
