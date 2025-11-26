<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth.company_currencies', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Tenant Isolation
            $table->uuid('company_id');

            // Currency Information
            $table->char('currency_code', 3); // ISO 4217 (USD, EUR, GBP)
            $table->string('currency_name', 100); // US Dollar, Euro, British Pound
            $table->string('currency_symbol', 10); // $, €, £
            $table->boolean('is_base_currency')->default(false);
            $table->decimal('default_exchange_rate', 12, 6)->default(1.000000);
            $table->boolean('is_active')->default(true);

            // Timestamps
            $table->timestamps();

            // Foreign Keys
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');

            // Unique Constraints
            $table->unique(['company_id', 'currency_code'], 'company_currency_unique');

            // Indexes
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'is_base_currency']);

        });

        // Check Constraints
        DB::statement('ALTER TABLE auth.company_currencies ADD CONSTRAINT check_positive_exchange_rate CHECK (default_exchange_rate > 0)');

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE auth.company_currencies ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE auth.company_currencies FORCE ROW LEVEL SECURITY');

        // Create RLS Policy
        DB::statement("
            CREATE POLICY company_currencies_company_policy ON auth.company_currencies
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // Ensure only one base currency per company
        DB::statement('
            CREATE UNIQUE INDEX company_base_currency_unique 
            ON auth.company_currencies (company_id) 
            WHERE is_base_currency = true
        ');

        // Insert common currencies for reference
        $this->insertCommonCurrencies();
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS company_base_currency_unique');
        DB::statement('DROP POLICY IF EXISTS company_currencies_company_policy ON auth.company_currencies');
        Schema::dropIfExists('auth.company_currencies');
    }

    /**
     * Insert common currencies for easy selection
     */
    private function insertCommonCurrencies(): void
    {
        // These will be available as templates when companies add currencies
        // Not directly inserted into company_currencies, but used for reference
        DB::statement("
            CREATE TABLE IF NOT EXISTS auth.currency_catalog (
                currency_code CHAR(3) PRIMARY KEY,
                currency_name VARCHAR(100) NOT NULL,
                currency_symbol VARCHAR(10) NOT NULL,
                decimal_places SMALLINT DEFAULT 2,
                is_popular BOOLEAN DEFAULT FALSE
            )
        ");

        DB::table('auth.currency_catalog')->insert([
            // Major currencies
            ['currency_code' => 'USD', 'currency_name' => 'US Dollar', 'currency_symbol' => '$', 'decimal_places' => 2, 'is_popular' => true],
            ['currency_code' => 'EUR', 'currency_name' => 'Euro', 'currency_symbol' => '€', 'decimal_places' => 2, 'is_popular' => true],
            ['currency_code' => 'GBP', 'currency_name' => 'British Pound Sterling', 'currency_symbol' => '£', 'decimal_places' => 2, 'is_popular' => true],
            ['currency_code' => 'CAD', 'currency_name' => 'Canadian Dollar', 'currency_symbol' => 'C$', 'decimal_places' => 2, 'is_popular' => true],
            ['currency_code' => 'AUD', 'currency_name' => 'Australian Dollar', 'currency_symbol' => 'A$', 'decimal_places' => 2, 'is_popular' => true],
            ['currency_code' => 'CHF', 'currency_name' => 'Swiss Franc', 'currency_symbol' => 'CHF', 'decimal_places' => 2, 'is_popular' => true],
            ['currency_code' => 'JPY', 'currency_name' => 'Japanese Yen', 'currency_symbol' => '¥', 'decimal_places' => 0, 'is_popular' => true],
            
            // Other common currencies
            ['currency_code' => 'CNY', 'currency_name' => 'Chinese Yuan', 'currency_symbol' => '¥', 'decimal_places' => 2, 'is_popular' => false],
            ['currency_code' => 'INR', 'currency_name' => 'Indian Rupee', 'currency_symbol' => '₹', 'decimal_places' => 2, 'is_popular' => false],
            ['currency_code' => 'BRL', 'currency_name' => 'Brazilian Real', 'currency_symbol' => 'R$', 'decimal_places' => 2, 'is_popular' => false],
            ['currency_code' => 'MXN', 'currency_name' => 'Mexican Peso', 'currency_symbol' => '$', 'decimal_places' => 2, 'is_popular' => false],
            ['currency_code' => 'ZAR', 'currency_name' => 'South African Rand', 'currency_symbol' => 'R', 'decimal_places' => 2, 'is_popular' => false],
            ['currency_code' => 'SGD', 'currency_name' => 'Singapore Dollar', 'currency_symbol' => 'S$', 'decimal_places' => 2, 'is_popular' => false],
            ['currency_code' => 'HKD', 'currency_name' => 'Hong Kong Dollar', 'currency_symbol' => 'HK$', 'decimal_places' => 2, 'is_popular' => false],
            ['currency_code' => 'NOK', 'currency_name' => 'Norwegian Krone', 'currency_symbol' => 'kr', 'decimal_places' => 2, 'is_popular' => false],
            ['currency_code' => 'SEK', 'currency_name' => 'Swedish Krona', 'currency_symbol' => 'kr', 'decimal_places' => 2, 'is_popular' => false],
            ['currency_code' => 'DKK', 'currency_name' => 'Danish Krone', 'currency_symbol' => 'kr', 'decimal_places' => 2, 'is_popular' => false],
        ]);
    }
};