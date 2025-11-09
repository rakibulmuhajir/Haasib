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
        // Create ledger schema if it doesn't exist
        DB::statement('CREATE SCHEMA IF NOT EXISTS ledger');

        // Create the set_updated_by function for triggers
        DB::statement('
            CREATE OR REPLACE FUNCTION ledger.set_updated_by()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ language plpgsql;
        ');

        Schema::create('ledger.period_closes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable();
            $table->uuid('accounting_period_id')->unique();
            $table->uuid('template_id')->nullable();
            $table->enum('status', ['pending', 'in_review', 'awaiting_approval', 'locked', 'closed', 'reopened'])->default('pending');
            $table->decimal('trial_balance_variance', 18, 2)->default(0);
            $table->jsonb('unposted_documents')->nullable();
            $table->uuid('adjusting_entry_id')->nullable();
            $table->text('closing_summary')->nullable();
            $table->uuid('started_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->uuid('reopened_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['status', 'closed_at'])->where(function ($query) {
                $query->whereNotNull('closed_at');
            });

            // Foreign keys will be added in separate migrations once referenced tables exist

            // Constraints will be added via raw SQL
        });

        // Enable RLS for period_closes
        DB::statement('ALTER TABLE ledger.period_closes ENABLE ROW LEVEL SECURITY');

        // RLS policy for period_closes
        DB::statement("
            CREATE POLICY period_closes_rls_policy ON ledger.period_closes
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        Schema::create('ledger.period_close_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('period_close_id');
            $table->uuid('template_task_id')->nullable();
            $table->string('code', 64);
            $table->string('title', 120);
            $table->enum('category', ['trial_balance', 'subledger', 'compliance', 'reporting', 'misc']);
            $table->integer('sequence');
            $table->enum('status', ['pending', 'in_progress', 'blocked', 'completed', 'waived'])->default('pending');
            $table->boolean('is_required')->default(true);
            $table->uuid('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('attachment_manifest')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['period_close_id', 'sequence']);
            $table->index(['period_close_id', 'status']);

            // Foreign keys will be added in separate migrations once referenced tables exist

            // Constraints will be added via raw SQL
        });

        // Enable RLS for period_close_tasks
        DB::statement('ALTER TABLE ledger.period_close_tasks ENABLE ROW LEVEL SECURITY');

        // RLS policy for period_close_tasks (inherits from period_closes)
        DB::statement("
            CREATE POLICY period_close_tasks_rls_policy ON ledger.period_close_tasks
            FOR ALL
            USING (
                period_close_id IN (
                    SELECT id FROM ledger.period_closes 
                    WHERE company_id = current_setting('app.current_company_id', true)::uuid
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        Schema::create('ledger.period_close_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('name', 120);
            $table->enum('frequency', ['monthly', 'quarterly', 'annual'])->default('monthly');
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['company_id', 'active']);
            $table->index(['company_id'])->where(function ($query) {
                $query->where('is_default', true);
            });

            // Foreign keys will be added in separate migrations once referenced tables exist

            // Unique constraint for default templates per company
            $table->unique(['company_id', 'is_default'], 'unique_default_per_company');
        });

        // Enable RLS for period_close_templates
        DB::statement('ALTER TABLE ledger.period_close_templates ENABLE ROW LEVEL SECURITY');

        // RLS policy for period_close_templates
        DB::statement("
            CREATE POLICY period_close_templates_rls_policy ON ledger.period_close_templates
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        Schema::create('ledger.period_close_template_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->string('code', 64);
            $table->string('title', 120);
            $table->enum('category', ['trial_balance', 'subledger', 'compliance', 'reporting', 'misc']);
            $table->integer('sequence');
            $table->boolean('is_required')->default(true);
            $table->text('default_notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['template_id', 'sequence']);
            $table->unique(['template_id', 'code']);

            // Foreign keys will be added in separate migrations once referenced tables exist

            // Constraints will be added via raw SQL
        });

        // Enable RLS for period_close_template_tasks
        DB::statement('ALTER TABLE ledger.period_close_template_tasks ENABLE ROW LEVEL SECURITY');

        // RLS policy for period_close_template_tasks (inherits from templates)
        DB::statement("
            CREATE POLICY period_close_template_tasks_rls_policy ON ledger.period_close_template_tasks
            FOR ALL
            USING (
                template_id IN (
                    SELECT id FROM ledger.period_close_templates 
                    WHERE company_id = current_setting('app.current_company_id', true)::uuid
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Create trigger for updated_at on period_closes
        DB::statement('
            CREATE TRIGGER period_closes_updated_at
                BEFORE UPDATE ON ledger.period_closes
                FOR EACH ROW
                EXECUTE FUNCTION ledger.set_updated_by();
        ');

        // Create trigger for updated_at on period_close_tasks
        DB::statement('
            CREATE TRIGGER period_close_tasks_updated_at
                BEFORE UPDATE ON ledger.period_close_tasks
                FOR EACH ROW
                EXECUTE FUNCTION ledger.set_updated_by();
        ');

        // Create trigger for updated_at on period_close_templates
        DB::statement('
            CREATE TRIGGER period_close_templates_updated_at
                BEFORE UPDATE ON ledger.period_close_templates
                FOR EACH ROW
                EXECUTE FUNCTION ledger.set_updated_by();
        ');

        // Create trigger for updated_at on period_close_template_tasks
        DB::statement('
            CREATE TRIGGER period_close_template_tasks_updated_at
                BEFORE UPDATE ON ledger.period_close_template_tasks
                FOR EACH ROW
                EXECUTE FUNCTION ledger.set_updated_by();
        ');

        // Add check constraints via raw SQL
        DB::statement('ALTER TABLE ledger.period_closes ADD CONSTRAINT check_trial_balance_variance_non_negative CHECK (trial_balance_variance >= 0)');
        DB::statement('ALTER TABLE ledger.period_close_tasks ADD CONSTRAINT check_sequence_positive CHECK (sequence >= 1)');
        DB::statement('ALTER TABLE ledger.period_close_template_tasks ADD CONSTRAINT check_template_sequence_positive CHECK (sequence >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers
        DB::statement('DROP TRIGGER IF EXISTS period_close_template_tasks_updated_at ON ledger.period_close_template_tasks');
        DB::statement('DROP TRIGGER IF EXISTS period_close_templates_updated_at ON ledger.period_close_templates');
        DB::statement('DROP TRIGGER IF EXISTS period_close_tasks_updated_at ON ledger.period_close_tasks');
        DB::statement('DROP TRIGGER IF EXISTS period_closes_updated_at ON ledger.period_closes');

        // Drop constraints
        DB::statement('ALTER TABLE ledger.period_close_template_tasks DROP CONSTRAINT IF EXISTS check_template_sequence_positive');
        DB::statement('ALTER TABLE ledger.period_close_tasks DROP CONSTRAINT IF EXISTS check_sequence_positive');
        DB::statement('ALTER TABLE ledger.period_closes DROP CONSTRAINT IF EXISTS check_trial_balance_variance_non_negative');

        // Drop policies
        DB::statement('DROP POLICY IF EXISTS period_close_template_tasks_rls_policy ON ledger.period_close_template_tasks');
        DB::statement('DROP POLICY IF EXISTS period_close_templates_rls_policy ON ledger.period_close_templates');
        DB::statement('DROP POLICY IF EXISTS period_close_tasks_rls_policy ON ledger.period_close_tasks');
        DB::statement('DROP POLICY IF EXISTS period_closes_rls_policy ON ledger.period_closes');

        // Drop tables
        Schema::dropIfExists('ledger.period_close_template_tasks');
        Schema::dropIfExists('ledger.period_close_templates');
        Schema::dropIfExists('ledger.period_close_tasks');
        Schema::dropIfExists('ledger.period_closes');

        // Drop the set_updated_by function
        DB::statement('DROP FUNCTION IF EXISTS ledger.set_updated_by()');

        // Drop the ledger schema
        DB::statement('DROP SCHEMA IF EXISTS ledger CASCADE');
    }
};
