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
        Schema::create('invoicing.customer_aging_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('company_id');
            $table->date('snapshot_date');
            $table->decimal('bucket_current', 15, 2);
            $table->decimal('bucket_1_30', 15, 2);
            $table->decimal('bucket_31_60', 15, 2);
            $table->decimal('bucket_61_90', 15, 2);
            $table->decimal('bucket_90_plus', 15, 2);
            $table->integer('total_invoices');
            $table->enum('generated_via', ['scheduled', 'on_demand']);
            $table->uuid('generated_by_user_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('customer_id')
                ->references('id')
                ->on('invoicing.customers')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');

            $table->foreign('generated_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes for performance
            $table->index(['customer_id', 'snapshot_date'], 'idx_customer_snapshot_date');
            $table->index(['company_id', 'snapshot_date'], 'idx_company_snapshot_date');
            $table->index(['snapshot_date'], 'idx_snapshot_date');
            $table->index(['generated_via', 'snapshot_date'], 'idx_generated_via_date');

            // Ensure uniqueness: one snapshot per customer per date
            $table->unique(['customer_id', 'snapshot_date'], 'uniq_customer_snapshot_date');
        });

        // Create trigger for audit logging
        $this->createAuditTrigger();

        // Enable Row Level Security
        $this->enableRLS();

        // Create RLS policies
        $this->createRLSPolicies();

        // Create indexes for aging analysis queries
        $this->createAnalysisIndexes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing.customer_aging_snapshots');
    }

    /**
     * Create audit trigger for customer aging snapshots.
     */
    private function createAuditTrigger(): void
    {
        $trigger = <<<'SQL'
        CREATE OR REPLACE FUNCTION invoicing.customer_aging_snapshots_audit_trigger()
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
                    'snapshot_id', NEW.id,
                    'customer_id', NEW.customer_id,
                    'snapshot_date', NEW.snapshot_date,
                    'bucket_current', NEW.bucket_current,
                    'bucket_1_30', NEW.bucket_1_30,
                    'bucket_31_60', NEW.bucket_31_60,
                    'bucket_61_90', NEW.bucket_61_90,
                    'bucket_90_plus', NEW.bucket_90_plus,
                    'total_invoices', NEW.total_invoices,
                    'generated_via', NEW.generated_via,
                    'total_outstanding', NEW.bucket_current + NEW.bucket_1_30 + NEW.bucket_31_60 + NEW.bucket_61_90 + NEW.bucket_90_plus
                );
                INSERT INTO invoicing.audit_logs (
                    table_name, 
                    record_id, 
                    action, 
                    details, 
                    user_id, 
                    company_id, 
                    created_at
                ) VALUES (
                    'customer_aging_snapshots',
                    NEW.id,
                    audit_action,
                    details,
                    audit_user,
                    NEW.company_id,
                    NOW()
                );
                RETURN NEW;
            
            ELSIF TG_OP = 'UPDATE' THEN
                audit_action := 'updated';
                audit_user := COALESCE(NEW.generated_by_user_id, OLD.generated_by_user_id);
                details := jsonb_build_object(
                    'snapshot_id', NEW.id,
                    'customer_id', NEW.customer_id,
                    'changes', jsonb_build_object(
                        'bucket_current', CASE WHEN OLD.bucket_current IS DISTINCT FROM NEW.bucket_current THEN 
                            jsonb_build_object('old', OLD.bucket_current, 'new', NEW.bucket_current) ELSE NULL END,
                        'bucket_1_30', CASE WHEN OLD.bucket_1_30 IS DISTINCT FROM NEW.bucket_1_30 THEN 
                            jsonb_build_object('old', OLD.bucket_1_30, 'new', NEW.bucket_1_30) ELSE NULL END,
                        'bucket_31_60', CASE WHEN OLD.bucket_31_60 IS DISTINCT FROM NEW.bucket_31_60 THEN 
                            jsonb_build_object('old', OLD.bucket_31_60, 'new', NEW.bucket_31_60) ELSE NULL END,
                        'bucket_61_90', CASE WHEN OLD.bucket_61_90 IS DISTINCT FROM NEW.bucket_61_90 THEN 
                            jsonb_build_object('old', OLD.bucket_61_90, 'new', NEW.bucket_61_90) ELSE NULL END,
                        'bucket_90_plus', CASE WHEN OLD.bucket_90_plus IS DISTINCT FROM NEW.bucket_90_plus THEN 
                            jsonb_build_object('old', OLD.bucket_90_plus, 'new', NEW.bucket_90_plus) ELSE NULL END,
                        'total_invoices', CASE WHEN OLD.total_invoices IS DISTINCT FROM NEW.total_invoices THEN 
                            jsonb_build_object('old', OLD.total_invoices, 'new', NEW.total_invoices) ELSE NULL END
                    )
                );
                INSERT INTO invoicing.audit_logs (
                    table_name, 
                    record_id, 
                    action, 
                    details, 
                    user_id, 
                    company_id, 
                    created_at
                ) VALUES (
                    'customer_aging_snapshots',
                    NEW.id,
                    audit_action,
                    details,
                    audit_user,
                    NEW.company_id,
                    NOW()
                );
                RETURN NEW;
            
            ELSIF TG_OP = 'DELETE' THEN
                audit_action := 'deleted';
                details := jsonb_build_object(
                    'snapshot_id', OLD.id,
                    'customer_id', OLD.customer_id,
                    'snapshot_date', OLD.snapshot_date,
                    'deleted_data', jsonb_build_object(
                        'bucket_current', OLD.bucket_current,
                        'bucket_1_30', OLD.bucket_1_30,
                        'bucket_31_60', OLD.bucket_31_60,
                        'bucket_61_90', OLD.bucket_61_90,
                        'bucket_90_plus', OLD.bucket_90_plus,
                        'total_invoices', OLD.total_invoices,
                        'generated_via', OLD.generated_via
                    )
                );
                INSERT INTO invoicing.audit_logs (
                    table_name, 
                    record_id, 
                    action, 
                    details, 
                    user_id, 
                    company_id, 
                    created_at
                ) VALUES (
                    'customer_aging_snapshots',
                    OLD.id,
                    audit_action,
                    details,
                    OLD.generated_by_user_id,
                    OLD.company_id,
                    NOW()
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
            CREATE TRIGGER customer_aging_snapshots_audit
                AFTER INSERT OR UPDATE OR DELETE ON invoicing.customer_aging_snapshots
                FOR EACH ROW EXECUTE FUNCTION invoicing.customer_aging_snapshots_audit_trigger();
        ');
    }

    /**
     * Enable Row Level Security on customer_aging_snapshots table.
     */
    private function enableRLS(): void
    {
        DB::unprepared('ALTER TABLE invoicing.customer_aging_snapshots ENABLE ROW LEVEL SECURITY;');
    }

    /**
     * Create Row Level Security policies for customer_aging_snapshots.
     */
    private function createRLSPolicies(): void
    {
        $policies = [
            // Company users can view aging snapshots for their company
            'CREATE POLICY customer_aging_snapshots_view_company ON invoicing.customer_aging_snapshots
                FOR SELECT USING (
                    company_id = current_setting(\'app.current_company_id\')::uuid
                );',

            // Users with permission can manage aging snapshots
            'CREATE POLICY customer_aging_snapshots_manage ON invoicing.customer_aging_snapshots
                FOR ALL USING (
                    company_id = current_setting(\'app.current_company_id\')::uuid AND
                    EXISTS (
                        SELECT 1 FROM user_company_permissions ucp
                        WHERE ucp.user_id = current_setting(\'app.current_user_id\')::uuid
                        AND ucp.company_id = current_setting(\'app.current_company_id\')::uuid
                        AND (ucp.permission = \'accounting.customers.generate_statements\' OR ucp.permission = \'accounting.customers.manage_credit\')
                    )
                );',

            // System admins can access all aging snapshots
            'CREATE POLICY customer_aging_snapshots_system_admin ON invoicing.customer_aging_snapshots
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

    /**
     * Create additional indexes for aging analysis queries.
     */
    private function createAnalysisIndexes(): void
    {
        $indexes = [
            // Index for finding high-risk customers (high 90+ bucket)
            'CREATE INDEX idx_aging_90_plus_risk ON invoicing.customer_aging_snapshots 
                (company_id, bucket_90_plus DESC NULLS LAST) 
                WHERE bucket_90_plus > 0;',

            // Index for aging trend analysis
            'CREATE INDEX idx_aging_trend_analysis ON invoicing.customer_aging_snapshots 
                (customer_id, snapshot_date DESC) 
                WHERE snapshot_date >= CURRENT_DATE - INTERVAL \'90 days\';',

            // Index for scheduled vs on-demand comparison
            'CREATE INDEX idx_aging_generation_method ON invoicing.customer_aging_snapshots 
                (generated_via, snapshot_date DESC);',

            // Partial index for customers with significant outstanding balances
            'CREATE INDEX idx_aging_significant_balance ON invoicing.customer_aging_snapshots 
                (company_id, (bucket_current + bucket_1_30 + bucket_31_60 + bucket_61_90 + bucket_90_plus) DESC) 
                WHERE (bucket_current + bucket_1_30 + bucket_31_60 + bucket_61_90 + bucket_90_plus) > 1000;',
        ];

        foreach ($indexes as $index) {
            DB::unprepared($index);
        }
    }
};
