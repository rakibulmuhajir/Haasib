<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE umrah.group_payments DISABLE ROW LEVEL SECURITY');

        Schema::table('umrah.group_payments', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->after('amount');
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency');
            $table->char('base_currency', 3)->nullable()->after('exchange_rate');
            $table->decimal('base_amount', 15, 2)->nullable()->after('base_currency');
        });

        DB::statement('UPDATE umrah.group_payments AS payment SET currency = company.base_currency, base_currency = company.base_currency, base_amount = payment.amount FROM auth.companies AS company WHERE company.id = payment.company_id');
        DB::statement('ALTER TABLE umrah.group_payments ALTER COLUMN amount TYPE numeric(18,6) USING amount::numeric(18,6)');
        DB::statement('ALTER TABLE umrah.group_payments ALTER COLUMN currency SET NOT NULL');
        DB::statement('ALTER TABLE umrah.group_payments ALTER COLUMN base_currency SET NOT NULL');
        DB::statement('ALTER TABLE umrah.group_payments ALTER COLUMN base_amount SET NOT NULL');
        DB::statement('ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_currency_fk FOREIGN KEY (currency) REFERENCES public.currencies(code) ON UPDATE CASCADE');
        DB::statement('ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_base_currency_fk FOREIGN KEY (base_currency) REFERENCES public.currencies(code) ON UPDATE CASCADE');
        DB::statement('ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_exchange_rate_check CHECK ((currency = base_currency AND exchange_rate IS NULL AND base_amount = round(amount, 2)) OR (currency <> base_currency AND exchange_rate > 0 AND base_amount = round(amount * exchange_rate, 2)))');
        DB::statement('ALTER TABLE umrah.group_payments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.group_payments FORCE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_exchange_rate_check');
        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_currency_fk');
        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_base_currency_fk');
        DB::statement('ALTER TABLE umrah.group_payments ALTER COLUMN amount TYPE numeric(15,2) USING amount::numeric(15,2)');

        Schema::table('umrah.group_payments', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'base_currency', 'base_amount']);
        });
    }
};
