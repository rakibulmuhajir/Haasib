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
        Schema::create('interactions', function (Blueprint $table) {
            $table->id('interaction_id');
            $table->uuid('company_id');
            $table->foreignId('customer_id')->nullable()->constrained('customers', 'customer_id');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors', 'vendor_id');
            $table->foreignId('contact_id')->nullable()->constrained('contacts', 'contact_id');
            $table->string('interaction_type', 50);
            $table->string('subject', 255)->nullable();
            $table->text('details')->nullable();
            $table->timestamp('interaction_date')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        // Add foreign key constraint to auth.companies
        DB::statement('ALTER TABLE interactions ADD CONSTRAINT fk_interactions_company_id FOREIGN KEY (company_id) REFERENCES auth.companies(id) ON DELETE CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
