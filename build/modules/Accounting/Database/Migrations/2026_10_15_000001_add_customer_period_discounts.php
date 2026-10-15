<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->string('discount_timing', 10)->nullable();
        });
        DB::statement("ALTER TABLE acct.customers ADD CONSTRAINT customers_discount_timing_check CHECK (discount_timing IN ('invoice', 'period'))");
        Schema::table('acct.credit_notes', function (Blueprint $table) {
            $table->jsonb('period_discount')->nullable();
        });
        DB::statement("CREATE INDEX credit_notes_period_invoice_ids ON acct.credit_notes USING gin ((period_discount->'invoice_ids')) WHERE deleted_at IS NULL AND period_discount IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS acct.credit_notes_period_invoice_ids');
        Schema::table('acct.credit_notes', fn (Blueprint $table) => $table->dropColumn('period_discount'));
        DB::statement('ALTER TABLE acct.customers DROP CONSTRAINT customers_discount_timing_check');
        Schema::table('acct.customers', fn (Blueprint $table) => $table->dropColumn('discount_timing'));
    }
};
