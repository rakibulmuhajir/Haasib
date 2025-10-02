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
        Schema::create('acct.accounting_periods', function (Blueprint $table) {
            $table->id('period_id');
            $table->uuid('company_id');
            $table->foreignId('fiscal_year_id')->constrained('acct.fiscal_years', 'fiscal_year_id');
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('period_type', 50)->default('monthly');
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreignId('closed_by')->nullable()->constrained('acct.user_accounts', 'user_id');
            $table->foreignId('created_by')->nullable()->constrained('acct.user_accounts', 'user_id');
            $table->foreignId('updated_by')->nullable()->constrained('acct.user_accounts', 'user_id');

            $table->unique(['company_id', 'fiscal_year_id', 'name']);
        });

        Schema::table('acct.accounting_periods', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
        });

        // Add check constraint for end_date > start_date
        DB::statement('ALTER TABLE acct.accounting_periods ADD CONSTRAINT chk_period_dates CHECK (end_date > start_date)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.accounting_periods');
    }
};
