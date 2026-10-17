<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "no discount" flag on an invoice is a label with no money in it, like the vehicle and the
 * slip number: it joins the fields fuel.protect_close_credit_invoice lets change on a posted daily
 * close's credit invoice. The allowed list differs between databases (later migrations appended
 * unit_id, reference, slip_date), so this appends to whatever list is there rather than matching
 * one exact string.
 */
return new class extends Migration
{
    private const COLUMNS = ",'discount_blocked','discount_block_reason','discount_blocked_by_user_id','discount_blocked_at'";

    public function up(): void
    {
        $def = $this->definition();
        if (str_contains($def, "'discount_blocked'")) {
            return;
        }
        $new = preg_replace("/('total_amount'(?:,'[a-z_]+')*)\\]/", '$1'.self::COLUMNS.']', $def, -1, $count);
        if ($count !== 2) {
            throw new RuntimeException('fuel.protect_close_credit_invoice is not in the expected shape; not changed.');
        }
        DB::unprepared($new);
    }

    public function down(): void
    {
        $def = $this->definition();
        $new = str_replace(self::COLUMNS, '', $def, $count);
        if ($count > 0) {
            DB::unprepared($new);
        }
    }

    private function definition(): string
    {
        return DB::selectOne("SELECT pg_get_functiondef('fuel.protect_close_credit_invoice'::regproc) AS d")->d;
    }
};
