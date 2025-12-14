<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the company_onboarding table for tracking onboarding wizard progress.
     * This migration must run AFTER the companies table is created.
     */
    public function up(): void
    {
        Schema::create('auth.company_onboarding', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->string('current_step', 50)->default('company-identity'); // company-identity, fiscal-year, bank-accounts, etc.
            $table->integer('step_number')->default(1);
            $table->jsonb('completed_steps')->default('[]');
            $table->jsonb('step_data')->nullable(); // Store temporary data between steps
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreignUuid('company_id')
                ->references('id')
                ->on('auth.companies')
                ->cascadeOnDelete();

            $table->unique('company_id');
        });

        // Enable Row Level Security
        DB::statement('ALTER TABLE auth.company_onboarding ENABLE ROW LEVEL SECURITY');

        // Create RLS policy for company isolation
        DB::statement("
            CREATE POLICY company_onboarding_company_isolation ON auth.company_onboarding
            FOR ALL
            USING (company_id = current_setting('app.current_company_id')::uuid)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS company_onboarding_company_isolation ON auth.company_onboarding');
        Schema::dropIfExists('auth.company_onboarding');
    }
};
