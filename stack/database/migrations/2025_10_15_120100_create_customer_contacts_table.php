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
        Schema::create('acct.customer_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('company_id');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255);
            $table->string('phone', 50)->nullable();
            $table->string('role', 100);
            $table->boolean('is_primary')->default(false);
            $table->enum('preferred_channel', ['email', 'phone', 'sms', 'portal'])->default('email');
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            // Foreign keys
            $table->foreign('customer_id')
                ->references('id')
                ->on('acct.customers')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('set null');

            // Indexes
            $table->index(['customer_id', 'company_id']);
            $table->index(['company_id', 'role']);
            $table->index(['company_id', 'email']);
            $table->unique(['customer_id', 'email']); // Unique email per customer
        });

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE acct.customer_contacts ENABLE ROW LEVEL SECURITY');

        // Create RLS policy to enforce tenancy
        DB::statement('
            CREATE POLICY customer_contacts_company_policy 
            ON acct.customer_contacts 
            FOR ALL 
            TO authenticated_user 
            USING (company_id = current_setting(\'app.current_company_id\')::uuid)
        ');

        // Create partial unique index for single primary contact per role per customer
        DB::statement('
            CREATE UNIQUE INDEX customer_contacts_primary_unique 
            ON acct.customer_contacts (customer_id, role) 
            WHERE is_primary = true AND deleted_at IS NULL
        ');

        // Create audit trigger for contact changes
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.customer_contacts_audit_trigger()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = \'INSERT\' THEN
                    PERFORM audit_log(
                        \'customer_contact_created\',
                        json_build_object(
                            \'id\', NEW.id,
                            \'customer_id\', NEW.customer_id,
                            \'company_id\', NEW.company_id,
                            \'email\', NEW.email,
                            \'role\', NEW.role,
                            \'is_primary\', NEW.is_primary
                        ),
                        NEW.created_by_user_id
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'UPDATE\' THEN
                    PERFORM audit_log(
                        \'customer_contact_updated\',
                        json_build_object(
                            \'id\', NEW.id,
                            \'customer_id\', NEW.customer_id,
                            \'company_id\', NEW.company_id,
                            \'old_email\', OLD.email,
                            \'new_email\', NEW.email,
                            \'old_role\', OLD.role,
                            \'new_role\', NEW.role,
                            \'old_is_primary\', OLD.is_primary,
                            \'new_is_primary\', NEW.is_primary
                        ),
                        COALESCE(current_setting(\'app.current_user_id\', true)::uuid, NEW.created_by_user_id)
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'DELETE\' THEN
                    PERFORM audit_log(
                        \'customer_contact_deleted\',
                        json_build_object(
                            \'id\', OLD.id,
                            \'customer_id\', OLD.customer_id,
                            \'company_id\', OLD.company_id,
                            \'email\', OLD.email,
                            \'role\', OLD.role
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
            CREATE TRIGGER customer_contacts_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE
            ON acct.customer_contacts
            FOR EACH ROW EXECUTE FUNCTION acct.customer_contacts_audit_trigger()
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger and function
        DB::statement('DROP TRIGGER IF EXISTS customer_contacts_audit_trigger ON acct.customer_contacts');
        DB::statement('DROP FUNCTION IF EXISTS acct.customer_contacts_audit_trigger()');

        // Drop RLS policy
        DB::statement('DROP POLICY IF EXISTS customer_contacts_company_policy ON acct.customer_contacts');

        // Drop indexes
        DB::statement('DROP INDEX IF EXISTS customer_contacts_primary_unique');

        Schema::dropIfExists('acct.customer_contacts');
    }
};
