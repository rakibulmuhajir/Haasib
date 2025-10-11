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
        Schema::create('auth.audit_entries', function (Blueprint $table) {
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

        // Add RLS policy for audit_entries table
        DB::statement('
            ALTER TABLE auth.audit_entries ENABLE ROW LEVEL SECURITY;
        ');

        // Policy: Users can see audit entries for their own actions or entries related to their companies
        DB::statement("
            CREATE POLICY audit_entries_select_policy ON auth.audit_entries
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

        // Create trigger for updated_at
        DB::statement('
            CREATE TRIGGER audit_entries_updated_at
                BEFORE UPDATE ON auth.audit_entries
                FOR EACH ROW
                EXECUTE FUNCTION auth.set_updated_by();
        ');

        // Create function to automatically log changes
        DB::statement("
            CREATE OR REPLACE FUNCTION auth.audit_trigger()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    INSERT INTO auth.audit_entries (
                        action,
                        entity_type,
                        entity_id,
                        user_id,
                        company_id,
                        new_values,
                        ip_address,
                        user_agent,
                        is_system_action
                    ) VALUES (
                        'created',
                        TG_TABLE_NAME,
                        NEW.id,
                        current_setting('app.current_user_id', true)::uuid,
                        current_setting('app.current_company_id', true)::uuid,
                        row_to_json(NEW),
                        current_setting('app.ip_address', true),
                        current_setting('app.user_agent', true),
                        false
                    );
                    RETURN NEW;
                ELSIF TG_OP = 'UPDATE' THEN
                    -- Only log if there are actual changes to important fields
                    IF NEW IS DISTINCT FROM OLD THEN
                        INSERT INTO auth.audit_entries (
                            action,
                            entity_type,
                            entity_id,
                            user_id,
                            company_id,
                            old_values,
                            new_values,
                            ip_address,
                            user_agent,
                            is_system_action
                        ) VALUES (
                            'updated',
                            TG_TABLE_NAME,
                            NEW.id,
                            current_setting('app.current_user_id', true)::uuid,
                            current_setting('app.current_company_id', true)::uuid,
                            row_to_json(OLD),
                            row_to_json(NEW),
                            current_setting('app.ip_address', true),
                            current_setting('app.user_agent', true),
                            false
                        );
                    END IF;
                    RETURN NEW;
                ELSIF TG_OP = 'DELETE' THEN
                    INSERT INTO auth.audit_entries (
                        action,
                        entity_type,
                        entity_id,
                        user_id,
                        company_id,
                        old_values,
                        ip_address,
                        user_agent,
                        is_system_action
                    ) VALUES (
                        'deleted',
                        TG_TABLE_NAME,
                        OLD.id,
                        current_setting('app.current_user_id', true)::uuid,
                        current_setting('app.current_company_id', true)::uuid,
                        row_to_json(OLD),
                        current_setting('app.ip_address', true),
                        current_setting('app.user_agent', true),
                        false
                    );
                    RETURN OLD;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger and function first
        DB::statement('DROP TRIGGER IF EXISTS audit_entries_updated_at ON auth.audit_entries');
        DB::statement('DROP FUNCTION IF EXISTS auth.audit_trigger()');

        // Drop table
        Schema::dropIfExists('auth.audit_entries');
    }
};
