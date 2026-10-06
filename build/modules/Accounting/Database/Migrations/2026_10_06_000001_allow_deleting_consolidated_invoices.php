<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A saved consolidated invoice still cannot be changed, but it may be deleted (one made by
 * mistake). It bills nothing itself -- the invoices it covered stay the receivable -- so deleting
 * it touches no money; its items go with it (ON DELETE CASCADE).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION acct.consolidated_invoice_is_final() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RAISE EXCEPTION 'A consolidated invoice is a record of what was sent and cannot be changed';
END $$;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION acct.consolidated_invoice_is_final() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'A consolidated invoice is a record of what was sent and cannot be changed';
END $$;
SQL);
    }
};
