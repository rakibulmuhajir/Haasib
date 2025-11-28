<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auth.company_user')) {
            return;
        }

        Schema::create('auth.company_user', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->string('role')->default('member');
            $table->uuid('invited_by_user_id')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['company_id', 'user_id']);

            $table->index(['user_id', 'role']);
            $table->index(['company_id', 'role']);
            $table->index('invited_by_user_id');
            $table->index('is_active');
            $table->index(['company_id', 'user_id', 'is_active']);
        });

        Schema::table('auth.company_user', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')->on('auth.users')
                ->cascadeOnDelete();

            $table->foreign('invited_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete();
        });

        DB::statement("ALTER TABLE auth.company_user ADD CONSTRAINT company_user_role_check CHECK (role IN ('owner', 'admin', 'accountant', 'viewer', 'member'))");

        DB::statement('ALTER TABLE auth.company_user ENABLE ROW LEVEL SECURITY');

        DB::statement('DROP POLICY IF EXISTS company_user_select_policy ON auth.company_user');
        DB::statement('DROP POLICY IF EXISTS company_user_insert_policy ON auth.company_user');
        DB::statement('DROP POLICY IF EXISTS company_user_update_policy ON auth.company_user');
        DB::statement('DROP POLICY IF EXISTS company_user_delete_policy ON auth.company_user');

        DB::statement("
            CREATE POLICY company_user_select_policy ON auth.company_user
            FOR SELECT USING (
                user_id = current_setting('app.current_user_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        DB::statement("
            CREATE POLICY company_user_insert_policy ON auth.company_user
            FOR INSERT WITH CHECK (
                company_id IN (
                    SELECT company_id FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                )
                OR (
                    user_id = current_setting('app.current_user_id', true)::uuid
                    AND role = 'owner'
                    AND NOT EXISTS (
                        SELECT 1 FROM auth.company_user existing
                        WHERE existing.company_id = company_id
                        AND existing.role IN ('owner', 'admin')
                    )
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        DB::statement("
            CREATE POLICY company_user_update_policy ON auth.company_user
            FOR UPDATE USING (
                company_id IN (
                    SELECT company_id FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        DB::statement("
            CREATE POLICY company_user_delete_policy ON auth.company_user
            FOR DELETE USING (
                company_id IN (
                    SELECT company_id FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            );
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS company_user_select_policy ON auth.company_user');
        DB::statement('DROP POLICY IF EXISTS company_user_insert_policy ON auth.company_user');
        DB::statement('DROP POLICY IF EXISTS company_user_update_policy ON auth.company_user');
        DB::statement('DROP POLICY IF EXISTS company_user_delete_policy ON auth.company_user');
        Schema::dropIfExists('auth.company_user');
    }
};
