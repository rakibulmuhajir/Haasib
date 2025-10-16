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
        Schema::create('acct.customer_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('company_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('generated_at');
            $table->uuid('generated_by_user_id')->nullable();
            $table->decimal('opening_balance', 15, 2);
            $table->decimal('total_invoiced', 15, 2);
            $table->decimal('total_paid', 15, 2);
            $table->decimal('total_credit_notes', 15, 2);
            $table->decimal('closing_balance', 15, 2);
            $table->jsonb('aging_bucket_summary');
            $table->string('document_path', 255)->nullable();
            $table->string('checksum', 64)->nullable();
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

            $table->foreign('generated_by_user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('set null');

            // Indexes for performance
            $table->index(['customer_id', 'period_start', 'period_end'], 'idx_customer_period');
            $table->index(['company_id', 'generated_at'], 'idx_company_generated');
            $table->index(['generated_at'], 'idx_generated_at');
            $table->index('checksum', 'idx_checksum');

            // Ensure uniqueness: one statement per customer per period
            $table->unique(['customer_id', 'period_start', 'period_end'], 'uniq_customer_period');
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
        Schema::dropIfExists('acct.customer_statements');
    }

    /**
     * Create audit trigger for customer statements.
     */
    private function createAuditTrigger(): void
    {
        $trigger = <<<'SQL'
        CREATE OR REPLACE FUNCTION acct.customer_statements_audit_trigger()
        RETURNS TRIGGER AS $$
        DECLARE
            audit_action TEXT;
            audit_user UUID;
            details JSONB;
        BEGIN
            -- Determine action
            IF TG_OP = 'INSERT' THEN
                audit_action := 'created';
                audit_user := NEW.generated_by_user_id;
                details := jsonb_build_object(
                    'statement_id', NEW.id,
                    'customer_id', NEW.customer_id,
                    'period_start', NEW.period_start,
                    'period_end', NEW.period_end,
                    'opening_balance', NEW.opening_balance,
                    'total_invoiced', NEW.total_invoiced,
                    'total_paid', NEW.total_paid,
                    'total_credit_notes', NEW.total_credit_notes,
                    'closing_balance', NEW.closing_balance,
                    'document_path', NEW.document_path
                );
                PERFORM audit_log(
                    'customer_statements_created',
                    details,
                    audit_user,
                    'customer_statements',
                    NEW.id,
                    NEW.company_id
                );
                RETURN NEW;
            
            ELSIF TG_OP = 'UPDATE' THEN
                audit_action := 'updated';
                audit_user := COALESCE(NEW.generated_by_user_id, OLD.generated_by_user_id);
                details := jsonb_build_object(
                    'statement_id', NEW.id,
                    'customer_id', NEW.customer_id,
                    'changes', jsonb_build_object(
                        'document_path', CASE WHEN OLD.document_path IS DISTINCT FROM NEW.document_path THEN NEW.document_path ELSE NULL END,
                        'checksum', CASE WHEN OLD.checksum IS DISTINCT FROM NEW.checksum THEN NEW.checksum ELSE NULL END
                    )
                );
                PERFORM audit_log(
                    'customer_statements_updated',
                    details,
                    audit_user,
                    'customer_statements',
                    NEW.id,
                    NEW.company_id
                );
                RETURN NEW;
            
            ELSIF TG_OP = 'DELETE' THEN
                audit_action := 'deleted';
                details := jsonb_build_object(
                    'statement_id', OLD.id,
                    'customer_id', OLD.customer_id,
                    'period_start', OLD.period_start,
                    'period_end', OLD.period_end,
                    'deleted_data', jsonb_build_object(
                        'opening_balance', OLD.opening_balance,
                        'total_invoiced', OLD.total_invoiced,
                        'total_paid', OLD.total_paid,
                        'total_credit_notes', OLD.total_credit_notes,
                        'closing_balance', OLD.closing_balance
                    )
                );
                PERFORM audit_log(
                    'customer_statements_deleted',
                    details,
                    OLD.generated_by_user_id,
                    'customer_statements',
                    OLD.id,
                    OLD.company_id
                );
                RETURN OLD;
            END IF;
            
            RETURN NULL;
        END;
        $$ LANGUAGE plpgsql;
        SQL;

        // Create trigger function
        DB::unprepared($trigger);

        // Create trigger
        DB::unprepared('
            CREATE TRIGGER customer_statements_audit
                AFTER INSERT OR UPDATE OR DELETE ON acct.customer_statements
                FOR EACH ROW EXECUTE FUNCTION acct.customer_statements_audit_trigger();
        ');
    }

    /**
     * Enable Row Level Security on customer_statements table.
     */
    private function enableRLS(): void
    {
        DB::unprepared('ALTER TABLE acct.customer_statements ENABLE ROW LEVEL SECURITY;');
    }

    /**
     * Create Row Level Security policies for customer_statements.
     */
    private function createRLSPolicies(): void
    {
        $policies = [
            // Company users can view statements for their company
            'CREATE POLICY customer_statements_view_company ON acct.customer_statements
                FOR SELECT USING (
                    company_id = current_setting(\'app.current_company_id\')::uuid
                );',

            // Users with permission can manage statements
            'CREATE POLICY customer_statements_manage ON acct.customer_statements
                FOR ALL USING (
                    company_id = current_setting(\'app.current_company_id\')::uuid AND
                    EXISTS (
                        SELECT 1 FROM user_company_permissions ucp
                        WHERE ucp.user_id = current_setting(\'app.current_user_id\')::uuid
                        AND ucp.company_id = current_setting(\'app.current_company_id\')::uuid
                        AND ucp.permission = \'accounting.customers.generate_statements\'
                    )
                );',

            // System admins can access all statements
            'CREATE POLICY customer_statements_system_admin ON acct.customer_statements
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
