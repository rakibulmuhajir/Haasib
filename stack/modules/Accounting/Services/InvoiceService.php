<?php

namespace Modules\Accounting\Services;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    /**
     * Create an invoice with optional line items.
     *
     * @param  array<int, array>  $lineItems
     */
    public function createInvoice(array $invoiceData, array $lineItems = []): Invoice
    {
        $validator = Validator::make($invoiceData, [
            'company_id' => ['required', 'uuid'],
            'customer_id' => ['required', 'uuid'],
            'invoice_number' => ['required', 'string', 'max:50', 'unique:invoicing.invoices,invoice_number'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'max:3'],
            'status' => ['sometimes', 'string'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($validator, $lineItems) {
            $invoice = Invoice::create(array_merge([
                'status' => 'draft',
                'payment_status' => 'unpaid',
            ], $validator->validated()));

            foreach ($lineItems as $itemData) {
                $this->addLineItem($invoice, $itemData);
            }

            $invoice->calculateTotals();

            return $invoice->fresh(['lineItems']);
        });
    }

    public function addLineItem(Invoice $invoice, array $itemData): InvoiceLineItem
    {
        $validator = Validator::make($itemData, [
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'tax_amount' => ['sometimes', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $payload = $validator->validated();
        $payload['total'] = ($payload['quantity'] ?? 0) * ($payload['unit_price'] ?? 0)
            - ($payload['discount_amount'] ?? 0)
            + ($payload['tax_amount'] ?? 0);

        return $invoice->lineItems()->create($payload);
    }

    public function markAsSent(Invoice $invoice, ?User $performedBy = null): Invoice
    {
        $invoice->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'payment_status' => $invoice->payment_status === 'paid' ? 'paid' : 'unpaid',
        ])->save();

        return $invoice->fresh();
    }

    public function markAsPaid(Invoice $invoice, ?User $performedBy = null): Invoice
    {
        $invoice->forceFill([
            'status' => 'paid',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'balance_due' => 0,
        ])->save();

        return $invoice->fresh();
    }

    public function recordPayment(Invoice $invoice, array $paymentData): Payment
    {
        $validator = Validator::make($paymentData, [
            'company_id' => ['required', 'uuid'],
            'customer_id' => ['required', 'uuid'],
            'payment_method' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'max:3'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($invoice, $validator) {
            $payload = $validator->validated();
            $payload['payment_number'] = Payment::generatePaymentNumber($invoice->company_id);
            $payload['status'] = 'completed';
            $payload['paymentable_type'] = Invoice::class;
            $payload['paymentable_id'] = $invoice->id;

            $payment = $invoice->payments()->create($payload);

            $invoice->calculateTotals();
            $invoice->refresh();

            if ($invoice->balance_due <= 0) {
                $this->markAsPaid($invoice);
            }

            return $payment;
        });
    }

    public function cancelInvoice(Invoice $invoice, ?User $performedBy = null): Invoice
    {
        $invoice->forceFill([
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
        ])->save();

        return $invoice->fresh();
    }
}
