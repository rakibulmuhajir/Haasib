<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The date on the customer's physical slip, when it differs from the day the sale was booked
 * (a slip logged in the next morning's close). A label for the documents the customer receives:
 * the invoice date, the journal and every report keep the booking date. Like the vehicle and the
 * reference it may be written on a posted daily close's credit invoice, so it joins the fields
 * fuel.protect_close_credit_invoice lets change. Existing table, existing RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->date('slip_date')->nullable();
        });
        $this->swap("'unit_id','reference']", "'unit_id','reference','slip_date']");
    }

    public function down(): void
    {
        $this->swap("'unit_id','reference','slip_date']", "'unit_id','reference']");
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->dropColumn('slip_date');
        });
    }

    private function swap(string $from, string $to): void
    {
        if (! DB::selectOne("SELECT to_regproc('fuel.protect_close_credit_invoice') IS NOT NULL AS present")->present) {
            return;
        }
        $def = DB::selectOne("SELECT pg_get_functiondef('fuel.protect_close_credit_invoice'::regproc) AS d")->d;
        if (substr_count($def, $from) !== 2) {
            throw new RuntimeException('fuel.protect_close_credit_invoice is not in the expected shape; not changed.');
        }
        DB::unprepared(str_replace($from, $to, $def));
    }
};
