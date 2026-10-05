<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The reference (the slip number off the paper receipt) is a label with no money in it, like the
 * vehicle: it joins the fields fuel.protect_close_credit_invoice lets change on a posted daily
 * close's credit invoice, so the slip number can be written on after the close.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->swap("'total_amount','unit_id']", "'total_amount','unit_id','reference']");
    }

    public function down(): void
    {
        $this->swap("'total_amount','unit_id','reference']", "'total_amount','unit_id']");
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
