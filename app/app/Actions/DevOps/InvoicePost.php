<?php

namespace App\Actions\DevOps;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class InvoicePost
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $data = Validator::make($p, [
            'id' => 'required|string|exists:invoices,invoice_id',
        ])->validate();

        $invoice = Invoice::findOrFail($data['id']);

        // Can only post draft invoices
        abort_if($invoice->status !== 'draft', 422, 'Can only post draft invoices');

        $invoice->status = 'posted';
        $invoice->posted_at = now();
        $invoice->posted_by = $actor->id;
        $invoice->updated_by = $actor->id;
        $invoice->save();

        return [
            'message' => 'Invoice posted',
            'data' => [
                'id' => $invoice->invoice_id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
            ],
        ];
    }
}
