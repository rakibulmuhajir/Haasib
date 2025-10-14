<?php

namespace App\Actions\DevOps;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class InvoiceUpdate
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $data = Validator::make($p, [
            'id' => 'required|string|exists:invoices,invoice_id',
            'customer_id' => 'sometimes|required|string|exists:customers,customer_id',
            'invoice_number' => 'sometimes|required|string|max:50',
            'reference_number' => 'nullable|string|max:50',
            'invoice_date' => 'sometimes|required|date',
            'due_date' => 'sometimes|required|date|after_or_equal:invoice_date',
            'currency_id' => 'sometimes|required|string|max:3',
            'exchange_rate' => 'sometimes|required|numeric|min:0',
            'notes' => 'nullable|string',
        ])->validate();

        $invoice = Invoice::findOrFail($data['id']);

        // Don't allow updates to posted invoices
        abort_if($invoice->status === 'posted', 422, 'Cannot update a posted invoice');

        $invoice->update(array_filter($data, function ($value, $key) {
            return in_array($key, [
                'customer_id', 'invoice_number', 'reference_number', 'invoice_date',
                'due_date', 'currency_id', 'exchange_rate', 'notes',
            ]);
        }, ARRAY_FILTER_USE_BOTH));

        $invoice->updated_by = $actor->id;
        $invoice->save();

        return [
            'message' => 'Invoice updated',
            'data' => [
                'id' => $invoice->invoice_id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
            ],
        ];
    }
}
