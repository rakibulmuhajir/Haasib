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
        Schema::create('acct.customer_communications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('company_id');
            $table->uuid('contact_id')->nullable();
            $table->enum('channel', ['email', 'phone', 'meeting', 'note']);
            $table->enum('direction', ['inbound', 'outbound', 'internal']);
            $table->string('subject', 255)->nullable();
            $table->text('body');
            $table->uuid('logged_by_user_id');
            $table->timestamp('occurred_at');
            $table->jsonb('attachments')->nullable();
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

            $table->foreign('contact_id')
                ->references('id')
                ->on('acct.customer_contacts')
                ->onDelete('set null');

            $table->foreign('logged_by_user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('cascade');

            // Indexes
            $table->index(['customer_id', 'company_id']);
            $table->index(['company_id', 'channel']);
            $table->index(['company_id', 'direction']);
            $table->index(['customer_id', 'occurred_at']);
            $table->index(['logged_by_user_id', 'occurred_at']);
            $table->index(['contact_id', 'occurred_at']);
        });

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE acct.customer_communications ENABLE ROW LEVEL SECURITY');

        // Create RLS policy to enforce tenancy
        DB::statement('
            CREATE POLICY customer_communications_company_policy 
            ON acct.customer_communications 
            FOR ALL 
            TO authenticated_user 
            USING (company_id = current_setting(\'app.current_company_id\')::uuid)
        ');

        // Create audit trigger for communication logs
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.customer_communications_audit_trigger()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = \'INSERT\' THEN
                    PERFORM audit_log(
                        \'customer_communication_logged\',
                        json_build_object(
                            \'id\', NEW.id,
                            \'customer_id\', NEW.customer_id,
                            \'company_id\', NEW.company_id,
                            \'contact_id\', NEW.contact_id,
                            \'channel\', NEW.channel,
                            \'direction\', NEW.direction,
                            \'subject\', NEW.subject,
                            \'occurred_at\', NEW.occurred_at,
                            \'logged_by_user_id\', NEW.logged_by_user_id
                        ),
                        NEW.logged_by_user_id
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'UPDATE\' THEN
                    PERFORM audit_log(
                        \'customer_communication_updated\',
                        json_build_object(
                            \'id\', NEW.id,
                            \'customer_id\', NEW.customer_id,
                            \'company_id\', NEW.company_id,
                            \'old_subject\', OLD.subject,
                            \'new_subject\', NEW.subject,
                            \'old_body\', OLD.body,
                            \'new_body\', NEW.body
                        ),
                        current_setting(\'app.current_user_id\', true)::uuid
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'DELETE\' THEN
                    PERFORM audit_log(
                        \'customer_communication_deleted\',
                        json_build_object(
                            \'id\', OLD.id,
                            \'customer_id\', OLD.customer_id,
                            \'company_id\', OLD.company_id,
                            \'channel\', OLD.channel,
                            \'direction\', OLD.direction,
                            \'occurred_at\', OLD.occurred_at
                        ),
                        current_setting(\'app.current_user_id\', true)::uuid
                    );
                    RETURN OLD;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Attach the trigger
        DB::statement('
            CREATE TRIGGER customer_communications_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE
            ON acct.customer_communications
            FOR EACH ROW EXECUTE FUNCTION acct.customer_communications_audit_trigger()
        ');

        // Create function to get communication timeline for a customer
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.get_customer_communication_timeline(
                p_customer_id UUID,
                p_company_id UUID,
                p_limit INTEGER DEFAULT 50,
                p_offset INTEGER DEFAULT 0
            )
            RETURNS TABLE (
                id UUID,
                channel TEXT,
                direction TEXT,
                subject TEXT,
                body TEXT,
                occurred_at TIMESTAMP,
                logged_by_user_id UUID,
                logged_by_user_name TEXT,
                contact_id UUID,
                contact_name TEXT,
                attachments JSONB
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT 
                    comm.id,
                    comm.channel::TEXT,
                    comm.direction::TEXT,
                    comm.subject,
                    comm.body,
                    comm.occurred_at,
                    comm.logged_by_user_id,
                    CONCAT(u.first_name, \' \', u.last_name) as logged_by_user_name,
                    comm.contact_id,
                    CASE 
                        WHEN comm.contact_id IS NOT NULL THEN 
                            CONCAT(cc.first_name, \' \', cc.last_name)
                        ELSE NULL
                    END as contact_name,
                    comm.attachments
                FROM acct.customer_communications comm
                LEFT JOIN auth.users u ON u.id = comm.logged_by_user_id
                LEFT JOIN acct.customer_contacts cc ON cc.id = comm.contact_id
                WHERE comm.customer_id = p_customer_id 
                  AND comm.company_id = p_company_id
                ORDER BY comm.occurred_at DESC
                LIMIT p_limit OFFSET p_offset;
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        ');

        // Grant execute permission to authenticated users
        DB::statement('GRANT EXECUTE ON FUNCTION acct.get_customer_communication_timeline TO authenticated_user');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop function and permissions
        DB::statement('REVOKE EXECUTE ON FUNCTION acct.get_customer_communication_timeline FROM authenticated_user');
        DB::statement('DROP FUNCTION IF EXISTS acct.get_customer_communication_timeline');

        // Drop trigger and function
        DB::statement('DROP TRIGGER IF EXISTS customer_communications_audit_trigger ON acct.customer_communications');
        DB::statement('DROP FUNCTION IF EXISTS acct.customer_communications_audit_trigger()');

        // Drop RLS policy
        DB::statement('DROP POLICY IF EXISTS customer_communications_company_policy ON acct.customer_communications');

        Schema::dropIfExists('acct.customer_communications');
    }
};
