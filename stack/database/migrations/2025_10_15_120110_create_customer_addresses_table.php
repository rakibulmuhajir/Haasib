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
        Schema::create('acct.customer_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('company_id');
            $table->string('label', 100);
            $table->enum('type', ['billing', 'shipping', 'statement', 'other']);
            $table->string('line1', 255);
            $table->string('line2', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->char('country', 2);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
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

            // Indexes
            $table->index(['customer_id', 'company_id']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'country']);
            $table->index(['customer_id', 'type', 'is_default']);
        });

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE acct.customer_addresses ENABLE ROW LEVEL SECURITY');

        // Create RLS policy to enforce tenancy
        DB::statement('
            CREATE POLICY customer_addresses_company_policy 
            ON acct.customer_addresses 
            FOR ALL 
            TO authenticated_user 
            USING (company_id = current_setting(\'app.current_company_id\')::uuid)
        ');

        // Create partial unique index for single default address per type per customer
        DB::statement('
            CREATE UNIQUE INDEX customer_addresses_default_unique 
            ON acct.customer_addresses (customer_id, type) 
            WHERE is_default = true AND deleted_at IS NULL
        ');

        // Create audit trigger for address changes
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.customer_addresses_audit_trigger()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = \'INSERT\' THEN
                    PERFORM audit_log(
                        \'customer_address_created\',
                        json_build_object(
                            \'id\', NEW.id,
                            \'customer_id\', NEW.customer_id,
                            \'company_id\', NEW.company_id,
                            \'type\', NEW.type,
                            \'country\', NEW.country,
                            \'is_default\', NEW.is_default
                        ),
                        current_setting(\'app.current_user_id\', true)::uuid
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'UPDATE\' THEN
                    PERFORM audit_log(
                        \'customer_address_updated\',
                        json_build_object(
                            \'id\', NEW.id,
                            \'customer_id\', NEW.customer_id,
                            \'company_id\', NEW.company_id,
                            \'type\', NEW.type,
                            \'old_is_default\', OLD.is_default,
                            \'new_is_default\', NEW.is_default,
                            \'old_country\', OLD.country,
                            \'new_country\', NEW.country
                        ),
                        current_setting(\'app.current_user_id\', true)::uuid
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'DELETE\' THEN
                    PERFORM audit_log(
                        \'customer_address_deleted\',
                        json_build_object(
                            \'id\', OLD.id,
                            \'customer_id\', OLD.customer_id,
                            \'company_id\', OLD.company_id,
                            \'type\', OLD.type,
                            \'country\', OLD.country
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
            CREATE TRIGGER customer_addresses_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE
            ON acct.customer_addresses
            FOR EACH ROW EXECUTE FUNCTION acct.customer_addresses_audit_trigger()
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger and function
        DB::statement('DROP TRIGGER IF EXISTS customer_addresses_audit_trigger ON acct.customer_addresses');
        DB::statement('DROP FUNCTION IF EXISTS acct.customer_addresses_audit_trigger()');

        // Drop RLS policy
        DB::statement('DROP POLICY IF EXISTS customer_addresses_company_policy ON acct.customer_addresses');

        // Drop indexes
        DB::statement('DROP INDEX IF EXISTS customer_addresses_default_unique');

        Schema::dropIfExists('acct.customer_addresses');
    }
};
