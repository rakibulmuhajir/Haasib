<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth.exchange_rates', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Tenant Isolation
            $table->uuid('company_id');

            // Exchange Rate Information
            $table->char('from_currency_code', 3);
            $table->char('to_currency_code', 3);
            $table->decimal('rate', 12, 6);
            $table->date('effective_date');
            $table->enum('source', ['manual', 'api', 'bank', 'system'])->default('manual');
            $table->text('notes')->nullable();

            // Audit Information
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');
                  
            $table->foreign('created_by_user_id')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('set null');

            // Unique Constraints - one rate per currency pair per date per company
            $table->unique(['company_id', 'from_currency_code', 'to_currency_code', 'effective_date'], 'exchange_rate_unique');

            // Indexes for Performance
            $table->index(['company_id', 'from_currency_code']);
            $table->index(['company_id', 'to_currency_code']);
            $table->index(['company_id', 'effective_date']);
            $table->index(['effective_date', 'from_currency_code', 'to_currency_code']);

        });

        // Check Constraints
        DB::statement('ALTER TABLE auth.exchange_rates ADD CONSTRAINT check_positive_rate CHECK (rate > 0)');
        DB::statement('ALTER TABLE auth.exchange_rates ADD CONSTRAINT check_different_currencies CHECK (from_currency_code != to_currency_code)');

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE auth.exchange_rates ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE auth.exchange_rates FORCE ROW LEVEL SECURITY');

        // Create RLS Policy
        DB::statement("
            CREATE POLICY exchange_rates_company_policy ON auth.exchange_rates
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // Create function to get latest exchange rate
        DB::statement('
            CREATE OR REPLACE FUNCTION auth.get_latest_exchange_rate(
                p_company_id UUID,
                p_from_currency CHAR(3),
                p_to_currency CHAR(3),
                p_as_of_date DATE DEFAULT CURRENT_DATE
            ) RETURNS DECIMAL(12,6) AS $$
            DECLARE
                rate DECIMAL(12,6);
            BEGIN
                -- If same currency, return 1
                IF p_from_currency = p_to_currency THEN
                    RETURN 1.000000;
                END IF;
                
                -- Get the most recent rate on or before the specified date
                SELECT er.rate INTO rate
                FROM auth.exchange_rates er
                WHERE er.company_id = p_company_id
                  AND er.from_currency_code = p_from_currency
                  AND er.to_currency_code = p_to_currency
                  AND er.effective_date <= p_as_of_date
                ORDER BY er.effective_date DESC, er.created_at DESC
                LIMIT 1;
                
                -- If no direct rate found, try inverse rate
                IF rate IS NULL THEN
                    SELECT 1.0 / er.rate INTO rate
                    FROM auth.exchange_rates er
                    WHERE er.company_id = p_company_id
                      AND er.from_currency_code = p_to_currency
                      AND er.to_currency_code = p_from_currency
                      AND er.effective_date <= p_as_of_date
                    ORDER BY er.effective_date DESC, er.created_at DESC
                    LIMIT 1;
                END IF;
                
                -- If still no rate, check company default rates
                IF rate IS NULL THEN
                    SELECT cc.default_exchange_rate INTO rate
                    FROM auth.company_currencies cc
                    WHERE cc.company_id = p_company_id
                      AND cc.currency_code = p_from_currency
                      AND cc.is_active = true;
                END IF;
                
                RETURN COALESCE(rate, 1.000000);
            END;
            $$ LANGUAGE plpgsql STABLE;
        ');
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS auth.get_latest_exchange_rate(UUID, CHAR(3), CHAR(3), DATE)');
        DB::statement('DROP POLICY IF EXISTS exchange_rates_company_policy ON auth.exchange_rates');
        Schema::dropIfExists('auth.exchange_rates');
    }
};