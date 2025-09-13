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
        Schema::create('companies', function (Blueprint $table) {
            $table->id('company_id');
            $table->string('name', 255);
            $table->string('legal_name', 255)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries');
            $table->foreignId('primary_currency_id')->constrained('currencies');
            $table->smallInteger('fiscal_year_start_month')->default(1);
            $table->string('schema_name', 63)->unique();
            $table->string('industry', 100)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
