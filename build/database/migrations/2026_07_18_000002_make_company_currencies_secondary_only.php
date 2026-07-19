<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DELETE FROM auth.company_currencies AS currencies
            USING auth.companies AS companies
            WHERE currencies.company_id = companies.id
              AND (currencies.is_base = true OR currencies.currency_code = companies.base_currency)');
        DB::statement('DROP INDEX IF EXISTS auth.idx_company_base_currency');
        DB::statement('ALTER TABLE auth.company_currencies DROP CONSTRAINT IF EXISTS company_currencies_base_rate_check');

        Schema::table('auth.company_currencies', function (Blueprint $table) {
            $table->dropColumn('is_base');
        });
    }

    public function down(): void
    {
        Schema::table('auth.company_currencies', function (Blueprint $table) {
            $table->boolean('is_base')->default(false)->after('currency_code');
        });

        DB::statement('INSERT INTO auth.company_currencies (id, company_id, currency_code, is_base, exchange_rate, enabled_at, created_at, updated_at)
            SELECT public.gen_random_uuid(), companies.id, companies.base_currency, true, 1, now(), now(), now()
            FROM auth.companies AS companies');
        DB::statement('CREATE UNIQUE INDEX idx_company_base_currency ON auth.company_currencies (company_id) WHERE is_base = true');
        DB::statement('ALTER TABLE auth.company_currencies ADD CONSTRAINT company_currencies_base_rate_check CHECK (is_base = false OR exchange_rate = 1)');
    }
};
