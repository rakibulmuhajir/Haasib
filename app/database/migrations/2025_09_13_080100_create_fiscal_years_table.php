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
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id('fiscal_year_id');
            $table->uuid('company_id');
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->string('status', 50)->default('open');
            $table->timestamps();

            $table->foreignId('created_by')->nullable()->constrained('user_accounts', 'user_id');
            $table->foreignId('updated_by')->nullable()->constrained('user_accounts', 'user_id');

            $table->unique(['company_id', 'name']);
        });

        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
        });

        // Add check constraint for end_date > start_date
        DB::statement('ALTER TABLE fiscal_years ADD CONSTRAINT chk_fiscal_year_dates CHECK (end_date > start_date)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};
