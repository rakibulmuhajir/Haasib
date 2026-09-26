<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * fuel.capture_post_close_activity found "the posted close for this date" without excluding
 * soft-deleted closes. Edit day (DailyCloseReopenService) soft-deletes the close it reopens,
 * so re-posting that day hit the old, deleted close and refused the new tank/nozzle readings
 * ("Physical observations on a posted Daily Close are immutable"). Both date lookups now skip
 * deleted closes; everything else in the function is left exactly as it is.
 */
return new class extends Migration
{
    private const PAIRS = [
        "AND c.transaction_date IN (before_date, after_date)) THEN" =>
            "AND c.deleted_at IS NULL AND c.transaction_date IN (before_date, after_date)) THEN",
        "AND (c.transaction_date = before_date OR c.transaction_date = after_date);" =>
            "AND c.deleted_at IS NULL AND (c.transaction_date = before_date OR c.transaction_date = after_date);",
    ];

    public function up(): void
    {
        $this->rewrite(self::PAIRS);
    }

    public function down(): void
    {
        $this->rewrite(array_flip(self::PAIRS));
    }

    private function rewrite(array $pairs): void
    {
        $definition = DB::selectOne(
            "select pg_get_functiondef('fuel.capture_post_close_activity()'::regprocedure) as def"
        )->def;

        foreach ($pairs as $from => $to) {
            if (substr_count($definition, $from) !== 1) {
                throw new \RuntimeException("capture_post_close_activity: expected exactly one '{$from}'");
            }
            $definition = str_replace($from, $to, $definition);
        }

        DB::unprepared($definition);
    }
};
