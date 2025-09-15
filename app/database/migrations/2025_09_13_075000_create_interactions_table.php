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
        Schema::create('interactions', function (Blueprint $table) {
            $table->uuid('interaction_id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id')->nullable();
            $table->uuid('vendor_id')->nullable();
            $table->uuid('contact_id')->nullable();
            $table->string('interaction_type', 50);
            $table->string('subject', 255)->nullable();
            $table->text('details')->nullable();
            $table->timestamp('interaction_date')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');
            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->onDelete('cascade');
            $table->foreign('contact_id')->references('contact_id')->on('contacts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
