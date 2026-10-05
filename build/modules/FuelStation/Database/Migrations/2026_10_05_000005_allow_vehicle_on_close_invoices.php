<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A posted daily close's credit invoices are guarded (fuel.protect_close_credit_invoice): only
 * payment fields may change. The vehicle (unit_id) is a label with no money in it, so it joins
 * the allowed fields -- the owner can name the vehicle on an invoice after the close.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->swap("'total_amount']", "'total_amount','unit_id']");
    }

    public function down(): void
    {
        $this->swap("'total_amount','unit_id']", "'total_amount']");
    }

    private function swap(string $from, string $to): void
    {
        $def = DB::selectOne("SELECT pg_get_functiondef('fuel.protect_close_credit_invoice'::regproc) AS d")->d;
        if (substr_count($def, $from) !== 2) {
            throw new RuntimeException('fuel.protect_close_credit_invoice is not in the expected shape; not changed.');
        }
        DB::unprepared(str_replace($from, $to, $def));
    }
};
