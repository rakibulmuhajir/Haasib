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
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('contact_id')->primary();
            $table->uuid('company_id');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->uuid('customer_id')->nullable();
            $table->uuid('vendor_id')->nullable();
            $table->string('position', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');
            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->onDelete('cascade');
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
