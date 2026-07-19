<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pay.payslips DISABLE ROW LEVEL SECURITY');

        Schema::table('pay.payslips', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency');
            $table->char('base_currency', 3)->nullable()->after('exchange_rate');
            $table->decimal('base_gross_pay', 15, 2)->default(0)->after('net_pay');
            $table->decimal('base_total_earnings', 15, 2)->default(0)->after('base_gross_pay');
            $table->decimal('base_total_deductions', 15, 2)->default(0)->after('base_total_earnings');
            $table->decimal('base_employer_costs', 15, 2)->default(0)->after('base_total_deductions');
            $table->decimal('base_net_pay', 15, 2)->default(0)->after('base_employer_costs');
        });

        DB::statement('ALTER TABLE pay.payslips ALTER COLUMN gross_pay TYPE numeric(18,6) USING gross_pay::numeric(18,6)');
        DB::statement('ALTER TABLE pay.payslips ALTER COLUMN total_earnings TYPE numeric(18,6) USING total_earnings::numeric(18,6)');
        DB::statement('ALTER TABLE pay.payslips ALTER COLUMN total_deductions TYPE numeric(18,6) USING total_deductions::numeric(18,6)');
        DB::statement('ALTER TABLE pay.payslips ALTER COLUMN employer_costs TYPE numeric(18,6) USING employer_costs::numeric(18,6)');
        DB::statement('ALTER TABLE pay.payslips ALTER COLUMN net_pay TYPE numeric(18,6) USING net_pay::numeric(18,6)');

        // Establish a valid fallback before resolving each tenant's actual base currency.
        DB::statement('UPDATE pay.payslips SET base_currency = currency, exchange_rate = NULL');
        DB::statement("SELECT set_config('app.current_user_id', '00000000-0000-0000-0000-000000000000', false)");

        DB::statement(<<<'SQL'
            UPDATE pay.payslips AS payslip
            SET base_currency = company.base_currency,
                exchange_rate = CASE
                    WHEN payslip.currency = company.base_currency THEN NULL
                    ELSE COALESCE((
                        SELECT company_currency.exchange_rate
                        FROM auth.company_currencies AS company_currency
                        WHERE company_currency.company_id = company.id
                          AND company_currency.currency_code = payslip.currency
                    ), 1)
                END
            FROM auth.companies AS company
            WHERE company.id = payslip.company_id
            SQL);

        DB::statement(<<<'SQL'
            UPDATE pay.payslips
            SET base_gross_pay = round(gross_pay * COALESCE(exchange_rate, 1), 2),
                base_total_earnings = round(total_earnings * COALESCE(exchange_rate, 1), 2),
                base_total_deductions = round(total_deductions * COALESCE(exchange_rate, 1), 2),
                base_employer_costs = round(employer_costs * COALESCE(exchange_rate, 1), 2),
                base_net_pay = round(net_pay * COALESCE(exchange_rate, 1), 2)
            SQL);

        DB::statement('ALTER TABLE pay.payslips ALTER COLUMN base_currency SET NOT NULL');
        DB::statement('ALTER TABLE pay.payslips ADD CONSTRAINT payslips_base_currency_fk FOREIGN KEY (base_currency) REFERENCES public.currencies(code) ON UPDATE CASCADE');
        DB::statement('ALTER TABLE pay.payslips ADD CONSTRAINT payslips_exchange_rate_check CHECK ((currency = base_currency AND exchange_rate IS NULL) OR (currency <> base_currency AND exchange_rate > 0))');
        DB::statement('ALTER TABLE pay.payslips ADD CONSTRAINT payslips_base_totals_check CHECK (base_gross_pay = round(gross_pay * COALESCE(exchange_rate, 1), 2) AND base_total_earnings = round(total_earnings * COALESCE(exchange_rate, 1), 2) AND base_total_deductions = round(total_deductions * COALESCE(exchange_rate, 1), 2) AND base_employer_costs = round(employer_costs * COALESCE(exchange_rate, 1), 2) AND base_net_pay = round(net_pay * COALESCE(exchange_rate, 1), 2))');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION pay.update_payslip_totals()
            RETURNS TRIGGER AS $$
            DECLARE
                target_payslip_id uuid := COALESCE(NEW.payslip_id, OLD.payslip_id);
                rate numeric(18,8);
            BEGIN
                SELECT COALESCE(exchange_rate, 1) INTO rate
                FROM pay.payslips WHERE id = target_payslip_id;

                UPDATE pay.payslips
                SET total_earnings = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = target_payslip_id AND line_type = 'earning'), 0),
                    total_deductions = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = target_payslip_id AND line_type = 'deduction'), 0),
                    employer_costs = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = target_payslip_id AND line_type = 'employer'), 0),
                    gross_pay = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = target_payslip_id AND line_type = 'earning'), 0),
                    net_pay = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = target_payslip_id AND line_type = 'earning'), 0)
                        - COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = target_payslip_id AND line_type = 'deduction'), 0),
                    base_total_earnings = round(COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = target_payslip_id AND line_type = 'earning'), 0) * rate, 2),
                    base_total_deductions = round(COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = target_payslip_id AND line_type = 'deduction'), 0) * rate, 2),
                    base_employer_costs = round(COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = target_payslip_id AND line_type = 'employer'), 0) * rate, 2),
                    base_gross_pay = round(COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = target_payslip_id AND line_type = 'earning'), 0) * rate, 2),
                    base_net_pay = round((COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = target_payslip_id AND line_type = 'earning'), 0)
                        - COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = target_payslip_id AND line_type = 'deduction'), 0)) * rate, 2),
                    updated_at = NOW()
                WHERE id = target_payslip_id;

                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
            SQL);

        DB::statement('ALTER TABLE pay.payslips ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pay.payslips FORCE ROW LEVEL SECURITY');
        DB::statement('RESET app.current_user_id');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pay.payslips DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pay.payslips DROP CONSTRAINT IF EXISTS payslips_base_totals_check');
        DB::statement('ALTER TABLE pay.payslips DROP CONSTRAINT IF EXISTS payslips_exchange_rate_check');
        DB::statement('ALTER TABLE pay.payslips DROP CONSTRAINT IF EXISTS payslips_base_currency_fk');

        DB::statement('ALTER TABLE pay.payslips ALTER COLUMN gross_pay TYPE numeric(15,2) USING gross_pay::numeric(15,2)');
        DB::statement('ALTER TABLE pay.payslips ALTER COLUMN total_earnings TYPE numeric(15,2) USING total_earnings::numeric(15,2)');
        DB::statement('ALTER TABLE pay.payslips ALTER COLUMN total_deductions TYPE numeric(15,2) USING total_deductions::numeric(15,2)');
        DB::statement('ALTER TABLE pay.payslips ALTER COLUMN employer_costs TYPE numeric(15,2) USING employer_costs::numeric(15,2)');
        DB::statement('ALTER TABLE pay.payslips ALTER COLUMN net_pay TYPE numeric(15,2) USING net_pay::numeric(15,2)');

        Schema::table('pay.payslips', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate', 'base_currency', 'base_gross_pay', 'base_total_earnings', 'base_total_deductions', 'base_employer_costs', 'base_net_pay']);
        });

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION pay.update_payslip_totals()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE pay.payslips
                SET total_earnings = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id) AND line_type = 'earning'), 0),
                    total_deductions = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id) AND line_type = 'deduction'), 0),
                    employer_costs = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id) AND line_type = 'employer'), 0),
                    gross_pay = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id) AND line_type = 'earning'), 0),
                    net_pay = COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id) AND line_type = 'earning'), 0)
                        - COALESCE((SELECT SUM(amount) FROM pay.payslip_lines WHERE payslip_id = COALESCE(NEW.payslip_id, OLD.payslip_id) AND line_type = 'deduction'), 0),
                    updated_at = NOW()
                WHERE id = COALESCE(NEW.payslip_id, OLD.payslip_id);
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
            SQL);

        DB::statement('ALTER TABLE pay.payslips ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pay.payslips FORCE ROW LEVEL SECURITY');
    }
};
