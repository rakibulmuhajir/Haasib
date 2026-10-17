<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * What the station needed was the card charge the bank or the card's supplier keeps on swipes,
 * entered by hand on any date (usually month end) -- not a tax. The table had no rows anywhere
 * when this ran, so it is renamed in place: fuel.card_tax_deductions becomes fuel.card_charges,
 * its date column says so, and the tax section goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE fuel.card_tax_deductions RENAME TO card_charges');
        DB::statement('ALTER TABLE fuel.card_charges RENAME COLUMN deduction_date TO charge_date');
        DB::statement('ALTER TABLE fuel.card_charges DROP COLUMN section');
        DB::statement('ALTER POLICY card_tax_deductions_company_isolation ON fuel.card_charges RENAME TO card_charges_company_isolation');
    }

    public function down(): void
    {
        DB::statement('ALTER POLICY card_charges_company_isolation ON fuel.card_charges RENAME TO card_tax_deductions_company_isolation');
        DB::statement('ALTER TABLE fuel.card_charges ADD COLUMN section varchar(30) NULL');
        DB::statement('ALTER TABLE fuel.card_charges RENAME COLUMN charge_date TO deduction_date');
        DB::statement('ALTER TABLE fuel.card_charges RENAME TO card_tax_deductions');
    }
};
