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
        Schema::create('auth.company_user', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->string('role')->default('member');
            $table->uuid('invited_by_user_id')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Composite primary key
            $table->primary(['company_id', 'user_id']);

            // Indexes
            $table->index(['user_id', 'role']);
            $table->index(['company_id', 'role']);
            $table->index('invited_by_user_id');
            $table->index('is_active');
            $table->index(['company_id', 'user_id', 'is_active']);

            // Foreign key constraints
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')->on('auth.users')
                ->onDelete('cascade');

            $table->foreign('invited_by_user_id')
                ->references('id')->on('auth.users')
                ->onDelete('set null');

            // Add check constraint for valid roles will be added as raw SQL
        });

        // Add check constraint for valid roles
        DB::statement("ALTER TABLE auth.company_user ADD CONSTRAINT company_user_role_check CHECK (role IN ('owner', 'admin', 'accountant', 'viewer', 'member'))");

        // Add RLS policy for company_user table
        DB::statement('
            ALTER TABLE auth.company_user ENABLE ROW LEVEL SECURITY;
        ');

        // Drop existing policies to avoid duplicates
        DB::statement('DROP POLICY IF EXISTS company_user_select_policy ON auth.company_user');
        DB::statement('DROP POLICY IF EXISTS company_user_insert_policy ON auth.company_user');
        DB::statement('DROP POLICY IF EXISTS company_user_update_policy ON auth.company_user');
        DB::statement('DROP POLICY IF EXISTS company_user_delete_policy ON auth.company_user');

        // Policy: Users can see their own company memberships
        DB::statement("
            CREATE POLICY company_user_select_policy ON auth.company_user
            FOR SELECT
            USING (
                user_id = current_setting('app.current_user_id', true)::uuid
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Policy: Company owners/admins can insert memberships in their company.
        // Allow the creator to seed the first owner record when no privileged members exist yet.
        DB::statement("
            CREATE POLICY company_user_insert_policy ON auth.company_user
            FOR INSERT
            WITH CHECK (
                company_id IN (
                    SELECT company_id
                    FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                )
                OR
                (
                    user_id = current_setting('app.current_user_id', true)::uuid
                    AND role = 'owner'
                    AND NOT EXISTS (
                        SELECT 1
                        FROM auth.company_user existing
                        WHERE existing.company_id = company_id
                        AND existing.role IN ('owner', 'admin')
                    )
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Policy: Company owners/admins can update memberships in their company
        DB::statement("
            CREATE POLICY company_user_update_policy ON auth.company_user
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

        // Policy: Company owners/admins can delete memberships in their company
        DB::statement("
            CREATE POLICY company_user_delete_policy ON auth.company_user
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
            CREATE TRIGGER company_user_updated_at
                BEFORE UPDATE ON auth.company_user
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
        DB::statement('DROP TRIGGER IF EXISTS company_user_updated_at ON auth.company_user');

        // Drop table
        Schema::dropIfExists('auth.company_user');
    }
};
