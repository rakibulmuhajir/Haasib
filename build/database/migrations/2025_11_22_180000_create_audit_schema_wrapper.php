<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure audit schema exists
        DB::statement("CREATE SCHEMA IF NOT EXISTS audit");

        // Create or replace audit.audit_log wrapper used by triggers in accounting migrations
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit.audit_log(
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

                INSERT INTO auth.audit_entries (
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
                    now(),
                    now()
                );
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        SQL);

        // Trigger-compatible overload with no args, builds payload from OLD/NEW
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit.audit_log()
            RETURNS trigger AS $$
            DECLARE
                v_payload JSONB;
                v_action TEXT;
                v_entity_id UUID;
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    v_payload := row_to_json(NEW)::jsonb;
                    v_action := 'insert';
                    v_entity_id := NEW.id;
                ELSIF TG_OP = 'UPDATE' THEN
                    v_payload := jsonb_build_object(
                        'old', row_to_json(OLD)::jsonb,
                        'new', row_to_json(NEW)::jsonb
                    );
                    v_action := 'update';
                    v_entity_id := NEW.id;
                ELSIF TG_OP = 'DELETE' THEN
                    v_payload := row_to_json(OLD)::jsonb;
                    v_action := 'delete';
                    v_entity_id := OLD.id;
                END IF;

                PERFORM audit.audit_log(
                    v_action,
                    v_payload,
                    NULL,
                    TG_TABLE_NAME,
                    v_entity_id,
                    current_setting('app.current_company_id', true)::uuid
                );
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS audit.audit_log()');
        DB::statement('DROP FUNCTION IF EXISTS audit.audit_log(TEXT, JSONB, UUID, TEXT, UUID, UUID)');
        DB::statement('DROP SCHEMA IF EXISTS audit CASCADE');
    }
};
