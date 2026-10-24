<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correcting an amanat movement: a standalone entry with a wrong amount is voided (its journal
 * reversed) and replaced by a corrected one; the voided row stays, pointing at its replacement,
 * and is excluded from every balance and total. fuel.amanat_transaction_changes is the
 * append-only record of who changed what (holder moves and amount corrections).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel.amanat_transactions', function (Blueprint $table) {
            $table->timestampTz('voided_at')->nullable();
            $table->uuid('voided_by_user_id')->nullable();
            $table->uuid('replaced_by_id')->nullable();

            $table->foreign('voided_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('replaced_by_id')->references('id')->on('fuel.amanat_transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'customer_id', 'voided_at']);
        });

        Schema::create('fuel.amanat_transaction_changes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            // Nullable: a reopen deletes a close's amanat rows; the audit row outlives them (before/after say what it was).
            $table->uuid('amanat_transaction_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('action', 30); // holder_changed, amount_corrected
            $table->jsonb('before');
            $table->jsonb('after');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('amanat_transaction_id')->references('id')->on('fuel.amanat_transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'amanat_transaction_id']);
        });

        DB::statement("ALTER TABLE fuel.amanat_transaction_changes ADD CONSTRAINT amanat_transaction_changes_action_check
            CHECK (action IN ('holder_changed', 'amount_corrected'))");
        // Append-only. The one permitted update is the FK's SET NULL when the movement row is deleted.
        DB::unprepared(<<<'SQL'
CREATE FUNCTION fuel.prevent_amanat_change_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'UPDATE' AND NEW.amanat_transaction_id IS NULL AND OLD.amanat_transaction_id IS NOT NULL
       AND (to_jsonb(NEW) - 'amanat_transaction_id') = (to_jsonb(OLD) - 'amanat_transaction_id') THEN
        RETURN NEW;
    END IF;
    RAISE EXCEPTION 'Amanat change history is append-only';
END $$;
CREATE TRIGGER prevent_amanat_change_mutation BEFORE UPDATE OR DELETE ON fuel.amanat_transaction_changes
FOR EACH ROW EXECUTE FUNCTION fuel.prevent_amanat_change_mutation();
SQL);
        DB::statement('ALTER TABLE fuel.amanat_transaction_changes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fuel.amanat_transaction_changes FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY amanat_transaction_changes_company_isolation ON fuel.amanat_transaction_changes
            FOR ALL
            USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true)
            WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true)");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS prevent_amanat_change_mutation ON fuel.amanat_transaction_changes');
        DB::statement('DROP FUNCTION IF EXISTS fuel.prevent_amanat_change_mutation()');
        DB::statement('DROP POLICY IF EXISTS amanat_transaction_changes_company_isolation ON fuel.amanat_transaction_changes');
        Schema::dropIfExists('fuel.amanat_transaction_changes');

        Schema::table('fuel.amanat_transactions', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'customer_id', 'voided_at']);
            $table->dropForeign(['replaced_by_id']);
            $table->dropForeign(['voided_by_user_id']);
            $table->dropColumn(['voided_at', 'voided_by_user_id', 'replaced_by_id']);
        });
    }
};
