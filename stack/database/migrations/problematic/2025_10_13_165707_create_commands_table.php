<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create schema if not exists
        DB::statement('CREATE SCHEMA IF NOT EXISTS ops');

        Schema::create('ops.commands', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Tenant Isolation
            $table->uuid('company_id');

            // Business Data
            $table->string('name');
            $table->text('description');
            $table->json('parameters');
            $table->json('required_permissions');
            $table->boolean('is_active')->default(true);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys (cross-schema)
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');

            // Unique Constraints (per-tenant)
            $table->unique(['company_id', 'name']);

            // Indexes for Performance
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'name']);
        });

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE ops.commands ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ops.commands FORCE ROW LEVEL SECURITY');

        // Create RLS Policy
        DB::statement("
            CREATE POLICY commands_company_policy ON ops.commands
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // Create Audit Trigger (for operations tables)
        DB::statement('
            CREATE TRIGGER commands_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON ops.commands
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP TRIGGER IF EXISTS commands_audit_trigger ON ops.commands');
        DB::statement('DROP POLICY IF EXISTS commands_company_policy ON ops.commands');
        Schema::dropIfExists('ops.commands');
    }
};
