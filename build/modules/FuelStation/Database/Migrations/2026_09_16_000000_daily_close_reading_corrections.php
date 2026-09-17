<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel.daily_close_reading_corrections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->references('id')->on('auth.companies');
            $table->foreignUuid('close_transaction_id')->references('id')->on('acct.transactions');
            $table->string('reading_type', 10); // 'tank' | 'nozzle'
            $table->uuid('reading_id');
            $table->decimal('original_value', 18, 4);
            $table->decimal('corrected_value', 18, 4);
            $table->text('reason');
            $table->unsignedInteger('revision');
            $table->jsonb('effects');
            $table->foreignUuid('transaction_id')->nullable()->references('id')->on('acct.transactions');
            $table->unique(['company_id', 'close_transaction_id', 'reading_id', 'revision'], 'close_reading_revision');
            $table->foreignUuid('created_by_user_id')->references('id')->on('auth.users');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['company_id', 'close_transaction_id'], 'daily_close_reading_corrections_lookup');
        });
        DB::statement("ALTER TABLE fuel.daily_close_reading_corrections ADD CONSTRAINT daily_close_reading_corrections_type_check CHECK (reading_type IN ('tank', 'nozzle') AND corrected_value >= 0 AND revision > 0)");
        DB::statement('ALTER TABLE fuel.daily_close_reading_corrections ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fuel.daily_close_reading_corrections FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY daily_close_reading_corrections_company ON fuel.daily_close_reading_corrections USING (
            company_id = nullif(current_setting('app.current_company_id', true), '')::uuid
            OR current_setting('app.is_super_admin', true) = 'true')");
        // Append-only: this table is itself audit evidence of a correction, so it
        // reuses the same guard as fuel.daily_close_activity.
        DB::statement('CREATE TRIGGER prevent_correction_mutation BEFORE UPDATE OR DELETE ON fuel.daily_close_reading_corrections
            FOR EACH ROW EXECUTE FUNCTION fuel.prevent_audit_mutation()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS prevent_correction_mutation ON fuel.daily_close_reading_corrections');
        Schema::dropIfExists('fuel.daily_close_reading_corrections');
    }
};
