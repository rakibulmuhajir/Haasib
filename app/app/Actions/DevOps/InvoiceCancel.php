<?php

namespace App\Actions\DevOps;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class InvoiceCancel
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $data = Validator::make($p, [
            'id' => 'required|string|exists:invoices,invoice_id',
        ])->validate();

        $invoice = Invoice::findOrFail($data['id']);

        // Can only cancel posted or draft invoices
        abort_if(! in_array($invoice->status, ['draft', 'posted']), 422, 'Can only cancel draft or posted invoices');

        $invoice->status = 'cancelled';
        $invoice->cancelled_at = now();
        $invoice->cancelled_by = $actor->id;
        $invoice->updated_by = $actor->id;
        $invoice->save();

        return [
            'message' => 'Invoice cancelled',
            'data' => [
                'id' => $invoice->invoice_id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
            ],
        ];
    }
}
