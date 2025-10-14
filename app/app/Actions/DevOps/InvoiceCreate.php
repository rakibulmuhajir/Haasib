<?php

namespace App\Actions\DevOps;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InvoiceCreate
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $data = Validator::make($p, [
            'company_id' => 'required|uuid|exists:auth.companies,id',
            'customer_id' => 'required|string|exists:customers,customer_id',
            'invoice_number' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:50',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'currency_id' => 'required|string|max:3',
            'exchange_rate' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_id' => 'nullable|string|exists:taxes,tax_id',
            'idempotency_key' => 'nullable|string|max:255',
        ])->validate();

        $invoice = DB::transaction(function () use ($data, $actor) {
            // Calculate totals
            $subtotal = 0;
            $taxAmount = 0;
            $discountAmount = 0;

            foreach ($data['items'] as $item) {
                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $itemDiscount = $item['discount'] ?? 0;
                $subtotal += $itemSubtotal;
                $discountAmount += $itemDiscount;
            }

            $totalAmount = $subtotal - $discountAmount + $taxAmount;

            $invoiceData = [
                'invoice_id' => $data['idempotency_key'] ?
                    Invoice::generateIdFromIdempotencyKey($data['idempotency_key']) :
                    (string) Str::uuid(),
                'company_id' => $data['company_id'],
                'customer_id' => $data['customer_id'],
                'invoice_number' => $data['invoice_number'] ?? Invoice::generateNextNumber($data['company_id']),
                'reference_number' => $data['reference_number'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $data['exchange_rate'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'balance_due' => $totalAmount,
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ];

            if ($data['idempotency_key']) {
                $invoiceData['idempotency_key'] = $data['idempotency_key'];
            }

            $invoice = Invoice::create($invoiceData);

            // Create invoice items
            foreach ($data['items'] as $index => $item) {
                $invoice->items()->create([
                    'item_id' => (string) Str::uuid(),
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'tax_id' => $item['tax_id'] ?? null,
                    'line_number' => $index + 1,
                ]);
            }

            return $invoice;
        });

        return [
            'message' => 'Invoice created',
            'data' => [
                'id' => $invoice->invoice_id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'total_amount' => $invoice->total_amount,
                'status' => $invoice->status,
            ],
        ];
    }
}
