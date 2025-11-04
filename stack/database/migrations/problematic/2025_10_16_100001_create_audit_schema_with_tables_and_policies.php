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
        // Create audit schema first
        DB::statement('CREATE SCHEMA IF NOT EXISTS audit');

        // Create audit.entries table
        Schema::create('audit.entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('action', 50)->comment('created, updated, deleted, etc.');
            $table->string('entity_type', 100)->comment('Model or table name');
            $table->uuid('entity_id')->nullable()->comment('ID of the entity being audited');
            $table->uuid('user_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->nullable();
            $table->json('location')->nullable()->comment('Geolocation data');
            $table->json('metadata')->nullable()->comment('Additional audit data');
            $table->boolean('is_system_action')->default(false);
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['entity_type', 'entity_id']);
            $table->index('user_id');
            $table->index('company_id');
            $table->index('action');
            $table->index('is_system_action');
            $table->index('created_at');
            $table->index(['created_at', 'action']);
            $table->index(['user_id', 'created_at']);
            $table->index(['company_id', 'created_at']);
            $table->index(['entity_type', 'created_at']);

            // Foreign key constraints
            $table->foreign('user_id')
                ->references('id')->on('auth.users')
                ->onDelete('set null');

            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->onDelete('set null');
        });

        // Create audit.financial_transactions table
        Schema::create('audit.financial_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('transaction_type', 50)->comment('payment, invoice, credit_note, etc.');
            $table->uuid('transaction_id')->nullable()->comment('ID of the financial transaction');
            $table->uuid('related_entity_id')->nullable()->comment('Related entity (customer, vendor, etc.)');
            $table->string('related_entity_type', 100)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->json('payload')->nullable()->comment('Transaction details and metadata');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            // TODO: Add additional columns as needed:
            // - payment_method_id (UUID)
            // - ledger_account_id (UUID)
            // - reconciliation_status (string)
            // - approved_by (UUID)
            // - approved_at (timestamp)

            // Indexes
            $table->index('company_id');
            $table->index('user_id');
            $table->index('transaction_type');
            $table->index(['transaction_type', 'occurred_at']);
            $table->index(['company_id', 'occurred_at']);

            // Foreign key constraints
            $table->foreign('user_id')
                ->references('id')->on('auth.users')
                ->onDelete('set null');

            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->onDelete('set null');
        });

        // Create audit.permission_changes table
        Schema::create('audit.permission_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable();
            $table->uuid('user_id')->nullable()->comment('Who made the change');
            $table->uuid('target_user_id')->nullable()->comment('User whose permissions were changed');
            $table->string('change_type', 50)->comment('role_granted, role_revoked, permission_updated, etc.');
            $table->json('payload')->nullable()->comment('Permission change details');
            $table->json('old_permissions')->nullable();
            $table->json('new_permissions')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            // TODO: Add additional columns as needed:
            // - permission_id (UUID)
            // - role_id (UUID)
            // - scope_type (string)
            // - scope_id (UUID)
            // - expires_at (timestamp)

            // Indexes
            $table->index('company_id');
            $table->index('user_id');
            $table->index('target_user_id');
            $table->index('change_type');
            $table->index(['target_user_id', 'occurred_at']);
            $table->index(['company_id', 'occurred_at']);

            // Foreign key constraints
            $table->foreign('user_id')
                ->references('id')->on('auth.users')
                ->onDelete('set null');

            $table->foreign('target_user_id')
                ->references('id')->on('auth.users')
                ->onDelete('set null');

            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->onDelete('set null');
        });

        // Enable RLS and create policies for audit.entries
        DB::statement('ALTER TABLE audit.entries ENABLE ROW LEVEL SECURITY');

        // Policy: Users can see audit entries for their own actions or entries related to their companies
        DB::statement("
            CREATE POLICY audit_entries_select_policy ON audit.entries
            FOR SELECT
            USING (
                user_id = current_setting('app.current_user_id', true)::uuid
                OR
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

        // TODO: Create RLS policies for audit.financial_transactions
        // For now, just stub with a comment for future implementation
        DB::comment('TODO: Implement RLS policies for audit.financial_transactions based on access patterns');

        // TODO: Create RLS policies for audit.permission_changes
        // For now, just stub with a comment for future implementation
        DB::comment('TODO: Implement RLS policies for audit.permission_changes based on access patterns');

        // Create trigger for updated_at on audit.entries
        DB::statement('
            CREATE TRIGGER audit_entries_updated_at
                BEFORE UPDATE ON audit.entries
                FOR EACH ROW
                EXECUTE FUNCTION auth.set_updated_by();
        ');

        // Create or replace audit_log function that targets audit.entries
        DB::statement("
            CREATE OR REPLACE FUNCTION audit_log(
                p_action TEXT,
                p_payload JSONB,
                p_user_id UUID DEFAULT NULL,
                p_entity_type TEXT DEFAULT NULL,
                p_entity_id UUID DEFAULT NULL,
                p_company_id UUID DEFAULT NULL
            ) RETURNS VOID AS $$
            DECLARE
                v_user_id UUID := p_user_id;
                v_company_id UUID := p_company_id;
                v_ip_address TEXT := NULL;
                v_user_agent TEXT := NULL;
            BEGIN
                BEGIN
                    IF v_user_id IS NULL THEN
                        v_user_id := current_setting('app.current_user_id', true)::uuid;
                    END IF;
                EXCEPTION WHEN others THEN
                    -- leave NULL if context not set
                    v_user_id := p_user_id;
                END;

                BEGIN
                    IF v_company_id IS NULL THEN
                        v_company_id := current_setting('app.current_company_id', true)::uuid;
                    END IF;
                EXCEPTION WHEN others THEN
                    v_company_id := p_company_id;
                END;

                BEGIN
                    v_ip_address := current_setting('app.ip_address', true);
                EXCEPTION WHEN others THEN
                    v_ip_address := NULL;
                END;

                BEGIN
                    v_user_agent := current_setting('app.user_agent', true);
                EXCEPTION WHEN others THEN
                    v_user_agent := NULL;
                END;

                INSERT INTO audit.entries (
                    action,
                    entity_type,
                    entity_id,
                    user_id,
                    company_id,
                    new_values,
                    metadata,
                    ip_address,
                    user_agent,
                    is_system_action,
                    created_at,
                    updated_at
                ) VALUES (
                    p_action,
                    COALESCE(p_entity_type, 'system.event'),
                    p_entity_id,
                    v_user_id,
                    v_company_id,
                    p_payload,
                    NULL,
                    v_ip_address,
                    v_user_agent,
                    false,
                    NOW(),
                    NOW()
                );
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger and function first
        DB::statement('DROP TRIGGER IF EXISTS audit_entries_updated_at ON audit.entries');
        DB::statement('DROP FUNCTION IF EXISTS audit_log(text, jsonb, uuid, text, uuid, uuid)');

        // Drop RLS policies
        DB::statement('DROP POLICY IF EXISTS audit_entries_select_policy ON audit.entries');

        // Drop tables and schema
        Schema::dropIfExists('audit.permission_changes');
        Schema::dropIfExists('audit.financial_transactions');
        Schema::dropIfExists('audit.entries');
        DB::statement('DROP SCHEMA IF EXISTS audit CASCADE');
    }
};
