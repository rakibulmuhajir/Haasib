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
        Schema::create('acct.customer_credit_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('company_id');
            $table->decimal('limit_amount', 15, 2);
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'revoked'])->default('approved');
            $table->text('reason')->nullable();
            $table->uuid('changed_by_user_id');
            $table->string('approval_reference', 100)->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('customer_id')
                ->references('id')
                ->on('acct.customers')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');

            $table->foreign('changed_by_user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('restrict');

            // Indexes for performance
            $table->index(['customer_id', 'effective_at'], 'idx_customer_effective');
            $table->index(['company_id', 'status'], 'idx_company_status');
            $table->index(['expires_at'], 'idx_expires');

            // Ensure only one active limit per customer at any given time
            $table->unique(['customer_id', 'effective_at'], 'uniq_customer_effective');
        });

        // Create trigger for audit logging
        $this->createAuditTrigger();

        // Enable Row Level Security
        $this->enableRLS();

        // Create RLS policies
        $this->createRLSPolicies();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS customer_credit_limits_system_admin ON acct.customer_credit_limits');
        DB::statement('DROP POLICY IF EXISTS customer_credit_limits_manage ON acct.customer_credit_limits');
        DB::statement('DROP POLICY IF EXISTS customer_credit_limits_view_company ON acct.customer_credit_limits');
        DB::statement('ALTER TABLE acct.customer_credit_limits DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP TRIGGER IF EXISTS customer_credit_limits_audit_trigger ON acct.customer_credit_limits');
        Schema::dropIfExists('acct.customer_credit_limits');
    }

    /**
     * Create audit trigger for credit limit changes
     */
    private function createAuditTrigger(): void
    {
        $triggerSql = <<<'SQL'
        CREATE OR REPLACE FUNCTION audit_customer_credit_limits()
        RETURNS TRIGGER AS $$
        BEGIN
            IF TG_OP = 'INSERT' THEN
                PERFORM audit_log(
                    'credit_limit_created',
                    jsonb_build_object(
                        'limit_amount', NEW.limit_amount,
                        'effective_at', NEW.effective_at,
                        'expires_at', NEW.expires_at,
                        'status', NEW.status,
                        'reason', NEW.reason
                    ),
                    NEW.changed_by_user_id,
                    'App\\Models\\Customer',
                    NEW.customer_id,
                    NEW.company_id
                );
                RETURN NEW;
            ELSIF TG_OP = 'UPDATE' THEN
                PERFORM audit_log(
                    'credit_limit_updated',
                    jsonb_build_object(
                        'old_values', jsonb_build_object(
                            'limit_amount', OLD.limit_amount,
                            'effective_at', OLD.effective_at,
                            'expires_at', OLD.expires_at,
                            'status', OLD.status,
                            'reason', OLD.reason
                        ),
                        'new_values', jsonb_build_object(
                            'limit_amount', NEW.limit_amount,
                            'effective_at', NEW.effective_at,
                            'expires_at', NEW.expires_at,
                            'status', NEW.status,
                            'reason', NEW.reason
                        )
                    ),
                    NEW.changed_by_user_id,
                    'App\\Models\\Customer',
                    NEW.customer_id,
                    NEW.company_id
                );
                RETURN NEW;
            ELSIF TG_OP = 'DELETE' THEN
                PERFORM audit_log(
                    'credit_limit_deleted',
                    jsonb_build_object(
                        'limit_amount', OLD.limit_amount,
                        'effective_at', OLD.effective_at,
                        'expires_at', OLD.expires_at,
                        'status', OLD.status,
                        'reason', OLD.reason
                    ),
                    OLD.changed_by_user_id,
                    'App\\Models\\Customer',
                    OLD.customer_id,
                    OLD.company_id
                );
                RETURN OLD;
            END IF;
            RETURN NULL;
        END;
        $$ LANGUAGE plpgsql;

        DROP TRIGGER IF EXISTS customer_credit_limits_audit_trigger ON acct.customer_credit_limits;
        CREATE TRIGGER customer_credit_limits_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON acct.customer_credit_limits
            FOR EACH ROW EXECUTE FUNCTION audit_customer_credit_limits();
        SQL;

        DB::unprepared($triggerSql);
    }

    /**
     * Enable Row Level Security
     */
    private function enableRLS(): void
    {
        DB::unprepared('ALTER TABLE acct.customer_credit_limits ENABLE ROW LEVEL SECURITY;');
    }

    /**
     * Create RLS policies
     */
    private function createRLSPolicies(): void
    {
        $policies = [
            // Company users can view credit limits for their company
            'CREATE POLICY customer_credit_limits_view_company ON acct.customer_credit_limits
                FOR SELECT USING (
                    company_id = current_setting(\'app.current_company_id\')::uuid
                );',

            // Users with permission can manage credit limits
            'CREATE POLICY customer_credit_limits_manage ON acct.customer_credit_limits
                FOR ALL USING (
                    company_id = current_setting(\'app.current_company_id\')::uuid AND
                    EXISTS (
                        SELECT 1 FROM user_company_permissions ucp
                        WHERE ucp.user_id = current_setting(\'app.current_user_id\')::uuid
                        AND ucp.company_id = current_setting(\'app.current_company_id\')::uuid
                        AND ucp.permission = \'accounting.customers.manage_credit\'
                    )
                );',

            // System admins can access all
            'CREATE POLICY customer_credit_limits_system_admin ON acct.customer_credit_limits
                FOR ALL USING (
                    EXISTS (
                        SELECT 1 FROM users
                        WHERE id = current_setting(\'app.current_user_id\')::uuid
                        AND is_system_admin = true
                    )
                );',
        ];

        foreach ($policies as $policy) {
            DB::unprepared($policy);
        }
    }
};
