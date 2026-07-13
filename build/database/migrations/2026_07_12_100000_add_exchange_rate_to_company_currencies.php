<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth.company_currencies', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 8)->default(1)->after('is_base');
        });

        DB::statement('INSERT INTO auth.company_currencies (id, company_id, currency_code, is_base, exchange_rate, enabled_at, created_at, updated_at)
            SELECT public.gen_random_uuid(), companies.id, companies.base_currency, true, 1, now(), now(), now()
            FROM auth.companies AS companies
            WHERE NOT EXISTS (
                SELECT 1 FROM auth.company_currencies AS enabled
                WHERE enabled.company_id = companies.id AND enabled.currency_code = companies.base_currency
            )');

        DB::statement('ALTER TABLE auth.company_currencies ADD CONSTRAINT company_currencies_exchange_rate_check CHECK (exchange_rate > 0)');
        DB::statement('ALTER TABLE auth.company_currencies ADD CONSTRAINT company_currencies_base_rate_check CHECK (is_base = false OR exchange_rate = 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE auth.company_currencies DROP CONSTRAINT IF EXISTS company_currencies_base_rate_check');
        DB::statement('ALTER TABLE auth.company_currencies DROP CONSTRAINT IF EXISTS company_currencies_exchange_rate_check');
        Schema::table('auth.company_currencies', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
