<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Invoices record their reference (acct.invoices.reference): the slip or order number the sale
 * was made against. A daily close's credit sale kept it only inside its notes text, so anything
 * reading invoices had to parse fuel-specific wording. invoice.create stores it from now on;
 * existing close invoices are filled once from each posted close's credit_sale_details.
 *
 * Posted close invoices are frozen by triggers; the fill switches the ones present off for its
 * own UPDATEs (and back on), so it is neither refused nor recorded as post-close activity.
 */
return new class extends Migration
{
    private const TRIGGERS = ['protect_close_credit_invoice', 'capture_post_close_activity', 'protect_locked_opening'];

    public function up(): void
    {
        DB::statement('ALTER TABLE acct.invoices ADD COLUMN IF NOT EXISTS reference varchar(100) NULL');

        $fills = [];
        foreach (DB::table('acct.transactions')->where('transaction_type', 'fuel_daily_close')->whereNull('deleted_at')->pluck('metadata') as $raw) {
            foreach ((json_decode((string) $raw, true)['credit_sale_details'] ?? []) as $sale) {
                $reference = trim((string) ($sale['reference'] ?? ''));
                if (! empty($sale['invoice_id']) && $reference !== '') {
                    $fills[$sale['invoice_id']] = mb_substr($reference, 0, 100);
                }
            }
        }
        if (! $fills) {
            return;
        }

        $present = array_column(DB::select(
            "SELECT t.tgname FROM pg_trigger t JOIN pg_class c ON c.oid = t.tgrelid JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'acct' AND c.relname = 'invoices' AND NOT t.tgisinternal"
        ), 'tgname');
        $triggers = array_values(array_intersect(self::TRIGGERS, $present));

        foreach ($triggers as $trigger) {
            DB::statement("ALTER TABLE acct.invoices DISABLE TRIGGER {$trigger}");
        }
        try {
            foreach ($fills as $invoiceId => $reference) {
                DB::table('acct.invoices')->where('id', $invoiceId)->whereNull('reference')->update(['reference' => $reference]);
            }
        } finally {
            foreach ($triggers as $trigger) {
                DB::statement("ALTER TABLE acct.invoices ENABLE TRIGGER {$trigger}");
            }
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE acct.invoices DROP COLUMN IF EXISTS reference');
    }
};
