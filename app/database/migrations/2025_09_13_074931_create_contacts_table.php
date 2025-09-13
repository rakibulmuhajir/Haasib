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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id('contact_id');
            $table->foreignId('company_id')->constrained('companies', 'company_id');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers', 'customer_id')->onDelete('cascade');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors', 'vendor_id')->onDelete('cascade');
            $table->string('position', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            });
        
        // Add check constraint using raw SQL
        DB::statement('ALTER TABLE contacts ADD CONSTRAINT chk_contact_single_entity CHECK ((customer_id IS NOT NULL AND vendor_id IS NULL) OR (customer_id IS NULL AND vendor_id IS NOT NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
