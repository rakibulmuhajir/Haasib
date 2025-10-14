<?php

namespace App\Actions\DevOps;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceDelete
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $invoice = Invoice::findOrFail($p['id']);

        // Don't allow deletion of posted invoices
        abort_if($invoice->status === 'posted', 422, 'Cannot delete a posted invoice');

        DB::transaction(function () use ($invoice) {
            $invoice->items()->delete();
            $invoice->delete();
        });

        return [
            'message' => 'Invoice deleted',
            'data' => [
                'id' => $invoice->invoice_id,
                'invoice_number' => $invoice->invoice_number,
            ],
        ];
    }
}
