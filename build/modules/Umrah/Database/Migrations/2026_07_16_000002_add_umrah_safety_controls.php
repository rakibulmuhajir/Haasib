<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->boolean('can_edit_group')->default(false)->after('can_approve_voucher');
        });

        Schema::create('umrah.change_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('user_id')->nullable();
            $table->string('entity_type', 30);
            $table->uuid('entity_id');
            $table->string('action', 50);
            $table->text('reason')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->index(['company_id', 'entity_type', 'entity_id', 'created_at'], 'umrah_change_logs_entity_idx');
            $table->index(['company_id', 'user_id', 'created_at'], 'umrah_change_logs_user_idx');
        });

        DB::statement("ALTER TABLE umrah.change_logs ADD CONSTRAINT change_logs_entity_type_check CHECK (entity_type IN ('visa_group', 'voucher', 'passenger'))");
        DB::statement('ALTER TABLE umrah.change_logs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.change_logs FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY change_logs_company_isolation ON umrah.change_logs
            FOR ALL
            USING (
                company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true
            )
            WITH CHECK (
                company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true
            )
        SQL);
        DB::statement(<<<'SQL'
            CREATE FUNCTION umrah.prevent_change_log_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Travel change logs are immutable';
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement('CREATE TRIGGER prevent_change_log_mutation BEFORE UPDATE OR DELETE ON umrah.change_logs FOR EACH ROW EXECUTE FUNCTION umrah.prevent_change_log_mutation()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS prevent_change_log_mutation ON umrah.change_logs');
        DB::statement('DROP FUNCTION IF EXISTS umrah.prevent_change_log_mutation()');
        Schema::dropIfExists('umrah.change_logs');
        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->dropColumn('can_edit_group');
        });
    }
};
