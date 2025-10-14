<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auth.company_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('email', 255);
            $table->string('role', 20);
            $table->string('token', 255)->unique();
            $table->uuid('invited_by_user_id');
            $table->uuid('accepted_by_user_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->onDelete('cascade');

            $table->foreign('invited_by_user_id')
                ->references('id')->on('auth.users')
                ->onDelete('restrict');

            $table->foreign('accepted_by_user_id')
                ->references('id')->on('auth.users')
                ->onDelete('set null');

            // Indexes
            $table->index('company_id');
            $table->index('email');
            $table->index('token');
            $table->index('status');
            $table->index('expires_at');
            $table->index(['company_id', 'status']);
            $table->index(['email', 'status']);
        });

        // Add check constraints
        DB::statement("
            ALTER TABLE auth.company_invitations 
            ADD CONSTRAINT valid_role 
            CHECK (role IN ('owner', 'admin', 'accountant', 'viewer'))
        ");

        DB::statement("
            ALTER TABLE auth.company_invitations 
            ADD CONSTRAINT valid_status 
            CHECK (status IN ('pending', 'accepted', 'rejected', 'expired'))
        ");

        // Add RLS policy
        DB::statement('
            ALTER TABLE auth.company_invitations ENABLE ROW LEVEL SECURITY;
        ');

        // Policy: Users can see invitations for companies they belong to or invitations sent to them
        DB::statement("
            CREATE POLICY company_invitations_select_policy ON auth.company_invitations
            FOR SELECT
            USING (
                company_id IN (
                    SELECT company_id FROM auth.company_user 
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND is_active = true
                )
                OR
                email = (
                    SELECT email FROM auth.users 
                    WHERE id = current_setting('app.current_user_id', true)::uuid
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Policy: Company owners/admins can create invitations
        DB::statement("
            CREATE POLICY company_invitations_insert_policy ON auth.company_invitations
            FOR INSERT
            WITH CHECK (
                company_id IN (
                    SELECT company_id FROM auth.company_user 
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                    AND is_active = true
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Policy: Users can update their own invitations (accept/reject)
        DB::statement("
            CREATE POLICY company_invitations_update_policy ON auth.company_invitations
            FOR UPDATE
            USING (
                email = (
                    SELECT email FROM auth.users 
                    WHERE id = current_setting('app.current_user_id', true)::uuid
                )
                OR
                company_id IN (
                    SELECT company_id FROM auth.company_user 
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                    AND is_active = true
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Policy: Company owners/admins can delete invitations
        DB::statement("
            CREATE POLICY company_invitations_delete_policy ON auth.company_invitations
            FOR DELETE
            USING (
                company_id IN (
                    SELECT company_id FROM auth.company_user 
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                    AND is_active = true
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Create trigger for updated_at
        DB::statement('
            CREATE TRIGGER company_invitations_updated_at
                BEFORE UPDATE ON auth.company_invitations
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
        DB::statement('DROP TRIGGER IF EXISTS company_invitations_updated_at ON auth.company_invitations');

        // Drop table
        Schema::dropIfExists('auth.company_invitations');
    }
};
