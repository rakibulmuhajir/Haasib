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
        Schema::create('auth.dashboard_layouts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('user_id');
            $table->uuid('company_id');
            $table->string('dashboard_key', 50);
            $table->jsonb('tabs')->default('[]');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')->on('auth.users')
                ->cascadeOnDelete();

            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'company_id', 'dashboard_key']);
            $table->index('company_id');
        });

        // Enable Row Level Security
        DB::statement('ALTER TABLE auth.dashboard_layouts ENABLE ROW LEVEL SECURITY');

        // Create RLS policy for company isolation
        DB::statement("
            CREATE POLICY dashboard_layouts_company_isolation ON auth.dashboard_layouts
            FOR ALL
            USING (company_id = current_setting('app.current_company_id')::uuid)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS dashboard_layouts_company_isolation ON auth.dashboard_layouts');
        Schema::dropIfExists('auth.dashboard_layouts');
    }
};
