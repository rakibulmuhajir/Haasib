<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saved Calculator formulas: a JSON tree of values, numbers and operators, evaluated by the server
 * only. Personal by default; is_shared makes one visible to everyone in the company (only its
 * owner edits or deletes it -- the application enforces that, RLS isolates the company).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel.calculator_formulas', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->string('name', 120);
            $table->jsonb('formula');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id')->references('id')->on('auth.users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'user_id']);
            $table->index(['company_id', 'is_shared']);
        });

        DB::statement('ALTER TABLE fuel.calculator_formulas ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fuel.calculator_formulas FORCE ROW LEVEL SECURITY');

        DB::statement("CREATE POLICY calculator_formulas_super_admin ON fuel.calculator_formulas
            FOR ALL
            USING (
                current_setting('app.current_user_id', true) IS NOT NULL
                AND current_setting('app.current_user_id', true)::text LIKE '00000000-0000-0000-0000-%'
            )
            WITH CHECK (
                current_setting('app.current_user_id', true) IS NOT NULL
                AND current_setting('app.current_user_id', true)::text LIKE '00000000-0000-0000-0000-%'
            )
        ");

        DB::statement("CREATE POLICY calculator_formulas_company_isolation ON fuel.calculator_formulas
            FOR ALL
            USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)
            WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel.calculator_formulas');
    }
};
