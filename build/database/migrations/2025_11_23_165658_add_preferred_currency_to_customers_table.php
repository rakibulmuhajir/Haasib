<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.customers', function (Blueprint $table) {
            // Add preferred currency column
            $table->char('preferred_currency_code', 3)->nullable()->after('email');
            
            // Add index for better performance
            $table->index(['company_id', 'preferred_currency_code']);
        });

        // Add foreign key constraint to ensure currency exists in company's currencies
        DB::statement('
            ALTER TABLE acct.customers 
            ADD CONSTRAINT fk_customers_preferred_currency 
            FOREIGN KEY (company_id, preferred_currency_code) 
            REFERENCES auth.company_currencies (company_id, currency_code)
            ON DELETE SET NULL
        ');

        // Set default preferred currency to company base currency for existing customers
        DB::statement('
            UPDATE acct.customers 
            SET preferred_currency_code = (
                SELECT currency_code 
                FROM auth.company_currencies 
                WHERE company_currencies.company_id = customers.company_id 
                AND is_base_currency = true 
                LIMIT 1
            )
            WHERE preferred_currency_code IS NULL
        ');
    }

    public function down(): void
    {
        // Drop foreign key constraint first
        DB::statement('ALTER TABLE acct.customers DROP CONSTRAINT IF EXISTS fk_customers_preferred_currency');
        
        // Drop the column and index
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'preferred_currency_code']);
            $table->dropColumn('preferred_currency_code');
        });
    }
};