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
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        Schema::create('auth.company_modules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('module_id');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->uuid('enabled_by_user_id')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->uuid('disabled_by_user_id')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['company_id', 'module_id']);
            $table->index(['company_id', 'is_active']);
            $table->index(['module_id', 'is_active']);
            $table->index('enabled_by_user_id');
            $table->index('disabled_by_user_id');
            $table->unique(['company_id', 'module_id'], 'uniq_company_module');

            // Foreign key constraints
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->onDelete('cascade');

            $table->foreign('module_id')
                ->references('id')->on('auth.modules')
                ->onDelete('cascade');

            $table->foreign('enabled_by_user_id')
                ->references('id')->on('auth.users')
                ->onDelete('set null');

            $table->foreign('disabled_by_user_id')
                ->references('id')->on('auth.users')
                ->onDelete('set null');
        });

        // Add RLS policy for company_modules table
        DB::statement('
            ALTER TABLE auth.company_modules ENABLE ROW LEVEL SECURITY;
        ');

        // Policy: Users can see modules for companies they belong to
        DB::statement("
            CREATE POLICY company_modules_select_policy ON auth.company_modules
            FOR SELECT
            USING (
                company_id IN (
                    SELECT company_id
                    FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND is_active = true
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Drop existing policies to avoid duplicates
        DB::statement('DROP POLICY IF EXISTS company_modules_insert_policy ON auth.company_modules');
        DB::statement('DROP POLICY IF EXISTS company_modules_update_policy ON auth.company_modules');
        DB::statement('DROP POLICY IF EXISTS company_modules_delete_policy ON auth.company_modules');

        // Policy: Company owners/admins can insert modules
        DB::statement("
            CREATE POLICY company_modules_insert_policy ON auth.company_modules
            FOR INSERT
            WITH CHECK (
                company_id IN (
                    SELECT company_id
                    FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Policy: Company owners/admins can update modules
        DB::statement("
            CREATE POLICY company_modules_update_policy ON auth.company_modules
            FOR UPDATE
            USING (
                company_id IN (
                    SELECT company_id
                    FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Policy: Company owners/admins can delete modules
        DB::statement("
            CREATE POLICY company_modules_delete_policy ON auth.company_modules
            FOR DELETE
            USING (
                company_id IN (
                    SELECT company_id
                    FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Create trigger for updated_at
        DB::statement('
            CREATE TRIGGER company_modules_updated_at
                BEFORE UPDATE ON auth.company_modules
                FOR EACH ROW
                EXECUTE FUNCTION auth.set_updated_by();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger first
        DB::statement('DROP TRIGGER IF EXISTS company_modules_updated_at ON auth.company_modules');

        // Drop table
        Schema::dropIfExists('auth.company_modules');
    }
};
