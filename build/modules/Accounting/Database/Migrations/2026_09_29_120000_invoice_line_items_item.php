<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Invoice lines record the product they sold (acct.invoice_line_items.item_id).
 *
 * A daily close's credit and direct sales knew their fuel but the invoice line never kept it,
 * so a document built from invoices (the consolidated invoice) had nothing to show. From now on
 * invoice.create stores it; existing lines are filled once from what each posted close recorded
 * for its sales: credit_sale_details (invoice -> item) and the direct-sale rows it declared,
 * in order, against the invoices it created for them. Nothing is inferred.
 *
 * Posted close invoices are frozen by triggers; the fill switches exactly those three off for
 * its own UPDATEs (and back on), so it is neither refused nor recorded as post-close activity.
 */
return new class extends Migration
{
    private const TRIGGERS = ['protect_close_credit_invoice', 'capture_post_close_activity', 'protect_locked_opening'];

    public function up(): void
    {
        DB::statement('ALTER TABLE acct.invoice_line_items ADD COLUMN IF NOT EXISTS item_id uuid NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS invoice_line_items_item_id_index ON acct.invoice_line_items (item_id)');

        $fills = []; // invoice_id => item_id
        foreach (DB::table('acct.transactions')->where('transaction_type', 'fuel_daily_close')->whereNull('deleted_at')->pluck('metadata') as $raw) {
            $metadata = json_decode((string) $raw, true) ?: [];
            foreach ($metadata['credit_sale_details'] ?? [] as $sale) {
                if (! empty($sale['invoice_id']) && ! empty($sale['item_id'])) {
                    $fills[$sale['invoice_id']] = $sale['item_id'];
                }
            }
            // Direct sales: the declared rows that made an invoice, in order (same filter as
            // DailyCloseService), paired with the invoices recorded for them.
            $declared = array_values(array_filter(
                $metadata['direct_sales'] ?? ($metadata['form_input']['direct_sales'] ?? []),
                fn ($row) => empty($row['kept_invoice_id']) && ! empty($row['item_id'])
                    && (float) ($row['litres'] ?? 0) > 0 && (float) ($row['rate'] ?? 0) > 0,
            ));
            $made = $metadata['direct_sale_details'] ?? [];
            if (count($declared) === count($made)) {
                foreach ($made as $i => $detail) {
                    if (! empty($detail['invoice_id'])) {
                        $fills[$detail['invoice_id']] = $declared[$i]['item_id'];
                    }
                }
            }
        }
        if (! $fills) {
            return;
        }

        foreach (self::TRIGGERS as $trigger) {
            DB::statement("ALTER TABLE acct.invoice_line_items DISABLE TRIGGER {$trigger}");
        }
        try {
            foreach ($fills as $invoiceId => $itemId) {
                DB::table('acct.invoice_line_items')->where('invoice_id', $invoiceId)->whereNull('item_id')->update(['item_id' => $itemId]);
            }
        } finally {
            foreach (self::TRIGGERS as $trigger) {
                DB::statement("ALTER TABLE acct.invoice_line_items ENABLE TRIGGER {$trigger}");
            }
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS acct.invoice_line_items_item_id_index');
        DB::statement('ALTER TABLE acct.invoice_line_items DROP COLUMN IF EXISTS item_id');
    }
};
