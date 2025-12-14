<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auth.company_currencies')) {
            return;
        }

        Schema::create('auth.company_currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->char('currency_code', 3);
            $table->boolean('is_base')->default(false);
            $table->timestamp('enabled_at')->useCurrent();
            $table->timestamps();

            $table->unique(['company_id', 'currency_code']);
        });

        Schema::table('auth.company_currencies', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete();
            $table->foreign('currency_code')
                ->references('code')->on('currencies')
                ->restrictOnDelete();
        });

        DB::statement("ALTER TABLE auth.company_currencies ADD CONSTRAINT company_currencies_currency_code_format CHECK (currency_code ~ '^[A-Z]{3}$')");
        DB::statement("CREATE UNIQUE INDEX idx_company_base_currency ON auth.company_currencies (company_id) WHERE is_base = true");

        DB::statement('ALTER TABLE auth.company_currencies ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY company_currencies_isolation ON auth.company_currencies
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            );
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS company_currencies_isolation ON auth.company_currencies');
        Schema::dropIfExists('auth.company_currencies');
    }
};
