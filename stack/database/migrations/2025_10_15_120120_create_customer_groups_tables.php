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
        // Create customer groups table
        Schema::create('invoicing.customer_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            // Foreign key
            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');

            // Indexes
            $table->unique(['company_id', 'name']); // Unique name per company
            $table->index(['company_id', 'is_default']);
        });

        // Enable RLS for customer groups
        DB::statement('ALTER TABLE invoicing.customer_groups ENABLE ROW LEVEL SECURITY');

        // Create RLS policy for customer groups
        DB::statement('
            CREATE POLICY customer_groups_company_policy 
            ON invoicing.customer_groups 
            FOR ALL 
            TO authenticated_user 
            USING (company_id = current_setting(\'app.current_company_id\')::uuid)
        ');

        // Create customer group members join table
        Schema::create('invoicing.customer_group_members', function (Blueprint $table) {
            $table->uuid('customer_id');
            $table->uuid('group_id');
            $table->uuid('company_id');
            $table->timestamp('joined_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('added_by_user_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('customer_id')
                ->references('id')
                ->on('invoicing.customers')
                ->onDelete('cascade');

            $table->foreign('group_id')
                ->references('id')
                ->on('invoicing.customer_groups')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');

            $table->foreign('added_by_user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('set null');

            // Primary key and unique constraints
            $table->primary(['customer_id', 'group_id']);
            $table->unique(['customer_id', 'group_id']); // Explicit unique constraint
            $table->index(['group_id', 'company_id']);
            $table->index(['customer_id', 'company_id']);
        });

        // Enable RLS for customer group members
        DB::statement('ALTER TABLE invoicing.customer_group_members ENABLE ROW LEVEL SECURITY');

        // Create RLS policy for customer group members
        DB::statement('
            CREATE POLICY customer_group_members_company_policy 
            ON invoicing.customer_group_members 
            FOR ALL 
            TO authenticated_user 
            USING (company_id = current_setting(\'app.current_company_id\')::uuid)
        ');

        // Create audit trigger for customer groups
        DB::statement('
            CREATE OR REPLACE FUNCTION invoicing.customer_groups_audit_trigger()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = \'INSERT\' THEN
                    PERFORM audit_log(
                        \'customer_group_created\',
                        json_build_object(
                            \'id\', NEW.id,
                            \'company_id\', NEW.company_id,
                            \'name\', NEW.name,
                            \'is_default\', NEW.is_default
                        ),
                        current_setting(\'app.current_user_id\', true)::uuid
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'UPDATE\' THEN
                    PERFORM audit_log(
                        \'customer_group_updated\',
                        json_build_object(
                            \'id\', NEW.id,
                            \'company_id\', NEW.company_id,
                            \'name\', NEW.name,
                            \'old_name\', OLD.name,
                            \'old_description\', OLD.description,
                            \'new_description\', NEW.description
                        ),
                        current_setting(\'app.current_user_id\', true)::uuid
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'DELETE\' THEN
                    PERFORM audit_log(
                        \'customer_group_deleted\',
                        json_build_object(
                            \'id\', OLD.id,
                            \'company_id\', OLD.company_id,
                            \'name\', OLD.name
                        ),
                        current_setting(\'app.current_user_id\', true)::uuid
                    );
                    RETURN OLD;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Attach the trigger to customer groups
        DB::statement('
            CREATE TRIGGER customer_groups_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE
            ON invoicing.customer_groups
            FOR EACH ROW EXECUTE FUNCTION invoicing.customer_groups_audit_trigger()
        ');

        // Create audit trigger for customer group membership changes
        DB::statement('
            CREATE OR REPLACE FUNCTION invoicing.customer_group_members_audit_trigger()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = \'INSERT\' THEN
                    PERFORM audit_log(
                        \'customer_group_member_added\',
                        json_build_object(
                            \'customer_id\', NEW.customer_id,
                            \'group_id\', NEW.group_id,
                            \'company_id\', NEW.company_id
                        ),
                        NEW.added_by_user_id
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'DELETE\' THEN
                    PERFORM audit_log(
                        \'customer_group_member_removed\',
                        json_build_object(
                            \'customer_id\', OLD.customer_id,
                            \'group_id\', OLD.group_id,
                            \'company_id\', OLD.company_id
                        ),
                        current_setting(\'app.current_user_id\', true)::uuid
                    );
                    RETURN OLD;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Attach the trigger to customer group members
        DB::statement('
            CREATE TRIGGER customer_group_members_audit_trigger
            AFTER INSERT OR DELETE
            ON invoicing.customer_group_members
            FOR EACH ROW EXECUTE FUNCTION invoicing.customer_group_members_audit_trigger()
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers and functions
        DB::statement('DROP TRIGGER IF EXISTS customer_group_members_audit_trigger ON invoicing.customer_group_members');
        DB::statement('DROP FUNCTION IF EXISTS invoicing.customer_group_members_audit_trigger()');

        DB::statement('DROP TRIGGER IF EXISTS customer_groups_audit_trigger ON invoicing.customer_groups');
        DB::statement('DROP FUNCTION IF EXISTS invoicing.customer_groups_audit_trigger()');

        // Drop RLS policies
        DB::statement('DROP POLICY IF EXISTS customer_group_members_company_policy ON invoicing.customer_group_members');
        DB::statement('DROP POLICY IF EXISTS customer_groups_company_policy ON invoicing.customer_groups');

        // Drop tables
        Schema::dropIfExists('invoicing.customer_group_members');
        Schema::dropIfExists('invoicing.customer_groups');
    }
};
