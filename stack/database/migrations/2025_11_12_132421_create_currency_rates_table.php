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
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        Schema::create('acct.currency_rates', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Tenant Isolation
            $table->uuid('company_id');

            // Business Data
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 15, 6);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->string('provider')->nullable();
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys (cross-schema)
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');

            // Unique Constraints (per-tenant)
            $table->unique(['company_id', 'from_currency', 'to_currency', 'valid_from'], 'unique_currency_rate_period');

            // Indexes for Performance
            $table->index(['company_id', 'from_currency', 'to_currency']);
            $table->index(['company_id', 'from_currency', 'to_currency', 'valid_from']);
            $table->index(['company_id', 'valid_from']);
            $table->index(['company_id', 'valid_until']);
            $table->index(['company_id', 'provider']);
        });

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE acct.currency_rates ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.currency_rates FORCE ROW LEVEL SECURITY');

        // Create RLS Policy
        DB::statement("
            CREATE POLICY currency_rates_company_policy ON acct.currency_rates
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // Create Audit Trigger (for financial/business tables)
        DB::statement('
            CREATE TRIGGER currency_rates_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON acct.currency_rates
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP TRIGGER IF EXISTS currency_rates_audit_trigger ON acct.currency_rates');
        DB::statement('DROP POLICY IF EXISTS currency_rates_company_policy ON acct.currency_rates');
        Schema::dropIfExists('acct.currency_rates');
    }
};
