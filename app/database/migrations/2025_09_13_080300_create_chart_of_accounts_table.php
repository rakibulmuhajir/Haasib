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
        Schema::create('acct.chart_of_accounts', function (Blueprint $table) {
            $table->id('account_id');
            $table->uuid('company_id');
            $table->foreignId('parent_account_id')->nullable()->constrained('chart_of_accounts', 'account_id');
            $table->string('account_code', 50);
            $table->string('account_name', 255);
            $table->string('account_type', 50); // asset, liability, equity, revenue, expense
            $table->string('account_subtype', 50)->nullable();
            $table->string('balance_type', 10)->default('debit');
            $table->boolean('is_system_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('created_by')->nullable()->constrained('acct.user_accounts', 'user_id');
            $table->foreignId('updated_by')->nullable()->constrained('acct.user_accounts', 'user_id');

            $table->unique(['company_id', 'account_code']);
        });

        Schema::table('acct.chart_of_accounts', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
        });

        // Add check constraint for opening_balance >= 0
        DB::statement('ALTER TABLE acct.chart_of_accounts ADD CONSTRAINT chk_opening_balance CHECK (opening_balance >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.chart_of_accounts');
    }
};
