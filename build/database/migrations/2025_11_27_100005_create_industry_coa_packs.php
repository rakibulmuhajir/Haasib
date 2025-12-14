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
        // Industry definitions table
        Schema::create('acct.industry_coa_packs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->string('code', 50)->unique(); // e.g., 'accountant', 'manufacturing'
            $table->string('name', 255); // e.g., 'Accountant / CPA Firm'
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Industry-specific account templates
        Schema::create('acct.industry_coa_templates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->string('code', 50); // Account code, e.g., '1000'
            $table->string('name', 255); // Account name
            $table->string('type', 30); // asset, liability, equity, revenue, expense, cogs, other_income, other_expense
            $table->string('subtype', 50); // bank, cash, accounts_receivable, etc.
            $table->string('normal_balance', 6); // debit or credit
            $table->boolean('is_contra')->default(false);
            $table->boolean('is_system')->default(false); // AR, AP, Retained Earnings
            $table->string('system_identifier')->nullable(); // 'ar_control', 'ap_control', 'retained_earnings', etc.
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreignUuid('industry_pack_id')
                ->references('id')
                ->on('acct.industry_coa_packs')
                ->cascadeOnDelete();

            $table->index(['industry_pack_id', 'type', 'subtype']);
            $table->index(['industry_pack_id', 'is_system']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.industry_coa_templates');
        Schema::dropIfExists('acct.industry_coa_packs');
    }
};
