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
        Schema::create('invoicing.payment_receipt_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('batch_number', 50);
            $table->string('status', 20)->default('pending');
            $table->integer('receipt_count')->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->char('currency', 3);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_finished_at')->nullable();
            $table->uuid('created_by_user_id');
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')
                ->on('public.companies')
                ->onDelete('cascade');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('restrict');

            // Indexes
            $table->unique(['company_id', 'batch_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['processed_at']);
        });

        // Add batch_id to payments table
        Schema::table('invoicing.payments', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('id');

            $table->foreign('batch_id')
                ->references('id')
                ->on('invoicing.payment_receipt_batches')
                ->onDelete('set null');

            $table->index(['batch_id']);
        });

        // Create trigger for batch number generation
        DB::statement('
            CREATE OR REPLACE FUNCTION invoicing.generate_batch_number()
            RETURNS TRIGGER AS $$
            DECLARE
                batch_seq bigint;
                batch_number text;
            BEGIN
                -- Get next sequence number for the company
                SELECT COALESCE(MAX(CAST(SUBSTRING(batch_number FROM \'BATCH-(\\d+)\') AS bigint)), 0) + 1
                INTO batch_seq
                FROM invoicing.payment_receipt_batches
                WHERE company_id = NEW.company_id;

                -- Generate batch number: BATCH-{YYYYMMDD}-{SEQUENCE}
                batch_number := \'BATCH-\' || TO_CHAR(NOW(), \'YYYYMMDD\') || \'-\' || LPAD(batch_seq::text, 3, \'0\');
                
                NEW.batch_number := batch_number;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Create trigger for batch number generation
        DB::statement('
            CREATE TRIGGER payment_batch_number_trigger
            BEFORE INSERT ON invoicing.payment_receipt_batches
            FOR EACH ROW
            WHEN (NEW.batch_number IS NULL)
            EXECUTE FUNCTION invoicing.generate_batch_number();
        ');

        // Enable Row Level Security
        DB::statement('ALTER TABLE invoicing.payment_receipt_batches ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement('
            CREATE POLICY payment_batches_company_policy ON invoicing.payment_receipt_batches
            FOR ALL TO authenticated_role
            USING (company_id = current_setting(\'app.current_company\', true)::uuid)
            WITH CHECK (company_id = current_setting(\'app.current_company\', true)::uuid);
        ');

        // Create audit trigger function for batches
        DB::statement('
            CREATE OR REPLACE FUNCTION invoicing.audit_payment_batches()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = \'INSERT\' THEN
                    INSERT INTO invoicing.payment_batch_audit (
                        batch_id, company_id, action, old_values, new_values, created_by, created_at
                    ) VALUES (
                        NEW.id, NEW.company_id, \'INSERT\', NULL, 
                        row_to_json(NEW), NEW.created_by_user_id, NOW()
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'UPDATE\' THEN
                    INSERT INTO invoicing.payment_batch_audit (
                        batch_id, company_id, action, old_values, new_values, created_by, created_at
                    ) VALUES (
                        NEW.id, NEW.company_id, \'UPDATE\', 
                        row_to_json(OLD), row_to_json(NEW), NEW.created_by_user_id, NOW()
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'DELETE\' THEN
                    INSERT INTO invoicing.payment_batch_audit (
                        batch_id, company_id, action, old_values, new_values, created_by, created_at
                    ) VALUES (
                        OLD.id, OLD.company_id, \'DELETE\', 
                        row_to_json(OLD), NULL, OLD.created_by_user_id, NOW()
                    );
                    RETURN OLD;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Create audit table for batches
        Schema::create('invoicing.payment_batch_audit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->uuid('company_id');
            $table->string('action', 10);
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->uuid('created_by');
            $table->timestamp('created_at');

            $table->foreign('batch_id')
                ->references('id')
                ->on('invoicing.payment_receipt_batches')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('public.companies')
                ->onDelete('cascade');

            $table->index(['batch_id', 'action']);
            $table->index(['company_id', 'created_at']);
        });

        // Create audit trigger
        DB::statement('
            CREATE TRIGGER payment_batch_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON invoicing.payment_receipt_batches
            FOR EACH ROW EXECUTE FUNCTION invoicing.audit_payment_batches();
        ');

        // Create status transition validation
        DB::statement('
            CREATE OR REPLACE FUNCTION invoicing.validate_batch_status_transition()
            RETURNS TRIGGER AS $$
            DECLARE
                valid_transitions text[][] := ARRAY[
                    [\'pending\', \'processing\'],
                    [\'processing\', \'completed\'],
                    [\'processing\', \'failed\'],
                    [\'failed\', \'pending\'],
                    [\'completed\', \'archived\'],
                    [\'failed\', \'archived\']
                ];
                transition_found boolean := false;
            BEGIN
                -- Only validate status changes
                IF OLD.status IS NOT NULL AND NEW.status != OLD.status THEN
                    -- Check if transition is valid
                    FOR transition IN SELECT * FROM unnest(valid_transitions) AS t LOOP
                        IF transition[1] = OLD.status AND transition[2] = NEW.status THEN
                            transition_found := true;
                            EXIT;
                        END IF;
                    END LOOP;
                    
                    IF NOT transition_found THEN
                        RAISE EXCEPTION \'Invalid status transition from % to %\', OLD.status, NEW.status;
                    END IF;
                END IF;
                
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Create status validation trigger
        DB::statement('
            CREATE TRIGGER payment_batch_status_validation
            BEFORE UPDATE ON invoicing.payment_receipt_batches
            FOR EACH ROW
            WHEN (OLD.status IS DISTINCT FROM NEW.status)
            EXECUTE FUNCTION invoicing.validate_batch_status_transition();
        ');

        // Create indexes for batch processing queries
        Schema::table('invoicing.payment_receipt_batches', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'created_at']);
            $table->index(['status', 'processing_started_at']);
        });

        // Create view for batch statistics
        DB::statement('
            CREATE VIEW invoicing.batch_statistics AS
            SELECT 
                company_id,
                status,
                COUNT(*) as batch_count,
                SUM(receipt_count) as total_receipts,
                SUM(total_amount) as total_amount,
                AVG(EXTRACT(EPOCH FROM (processing_finished_at - processing_started_at))/60) as avg_processing_time_minutes,
                DATE_TRUNC(\'day\', created_at) as date_bucket
            FROM invoicing.payment_receipt_batches
            WHERE deleted_at IS NULL
            GROUP BY company_id, status, DATE_TRUNC(\'day\', created_at)
            ORDER BY date_bucket DESC, status;
        ');

        // Grant permissions
        DB::statement('GRANT SELECT, INSERT, UPDATE ON invoicing.payment_receipt_batches TO authenticated_role');
        DB::statement('GRANT USAGE, SELECT ON SEQUENCE invoicing.payment_receipt_batches_id_seq TO authenticated_role');
        DB::statement('GRANT SELECT ON invoicing.batch_statistics TO authenticated_role');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop views
        DB::statement('DROP VIEW IF EXISTS invoicing.batch_statistics');

        // Drop triggers
        DB::statement('DROP TRIGGER IF EXISTS payment_batch_status_validation ON invoicing.payment_receipt_batches');
        DB::statement('DROP TRIGGER IF EXISTS payment_batch_audit_trigger ON invoicing.payment_receipt_batches');
        DB::statement('DROP TRIGGER IF EXISTS payment_batch_number_trigger ON invoicing.payment_receipt_batches');

        // Drop functions
        DB::statement('DROP FUNCTION IF EXISTS invoicing.validate_batch_status_transition()');
        DB::statement('DROP FUNCTION IF EXISTS invoicing.audit_payment_batches()');
        DB::statement('DROP FUNCTION IF EXISTS invoicing.generate_batch_number()');

        // Drop policies
        DB::statement('DROP POLICY IF EXISTS payment_batches_company_policy ON invoicing.payment_receipt_batches');

        // Disable RLS
        DB::statement('ALTER TABLE invoicing.payment_receipt_batches DISABLE ROW LEVEL SECURITY');

        // Drop foreign keys and indexes
        Schema::table('invoicing.payments', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });

        // Drop audit table
        Schema::dropIfExists('invoicing.payment_batch_audit');

        // Drop main table
        Schema::dropIfExists('invoicing.payment_receipt_batches');
    }
};