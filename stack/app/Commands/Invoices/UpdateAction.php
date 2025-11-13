<?php

namespace App\Commands\Invoices;

use App\Commands\BaseCommand;
use App\Services\ServiceContext;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class UpdateAction extends BaseCommand
{
    public function handle(): Invoice
    {
        return $this->executeInTransaction(function () {
            $companyId = $this->context->getCompanyId();
            $invoiceId = $this->getValue('id');
            
            if (!$companyId || !$invoiceId) {
                throw new Exception('Invalid service context: missing company or invoice ID');
            }

            // Find and validate invoice
            $invoice = Invoice::where('id', $invoiceId)
                ->where('company_id', $companyId)
                ->firstOrFail();

            if ($invoice->status !== 'draft') {
                throw new Exception('Only draft invoices can be updated');
            }

            // Validate customer exists and belongs to company
            $customer = Customer::where('id', $this->getValue('customer_id'))
                ->where('company_id', $companyId)
                ->firstOrFail();

            // Calculate new totals
            $lineItems = $this->getValue('line_items', []);
            $subtotal = $this->calculateSubtotal($lineItems);
            $taxTotal = $this->calculateTaxTotal($lineItems);
            $total = $this->calculateTotal($lineItems);

            // Update invoice
            $invoice->update([
                'customer_id' => $customer->id,
                'invoice_number' => $this->getValue('invoice_number'),
                'issue_date' => $this->getValue('issue_date'),
                'due_date' => $this->getValue('due_date'),
                'currency' => $this->getValue('currency'),
                'notes' => $this->getValue('notes'),
                'terms' => $this->getValue('terms'),
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $total,
            ]);

            // Remove existing line items and create new ones
            $invoice->lineItems()->delete();
            
            foreach ($lineItems as $index => $item) {
                $lineTotal = $this->calculateLineTotal($item);
                
                InvoiceLine::create([
                    'id' => Str::uuid(),
                    'invoice_id' => $invoice->id,
                    'line_number' => $index + 1,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'discount_percentage' => $item['discount_percentage'] ?? 0,
                    'line_total' => $lineTotal,
                    'account_id' => $item['account_id'] ?? null,
                ]);
            }

            $this->audit('invoice.updated', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $customer->id,
                'total' => $total,
            ]);

            return $invoice->load(['customer', 'lineItems']);
        });
    }

    private function calculateSubtotal(array $lineItems): float
    {
        return array_reduce($lineItems, function ($total, $item) {
            $itemTotal = $item['quantity'] * $item['unit_price'];
            $discount = $itemTotal * (($item['discount_percentage'] ?? 0) / 100);
            return $total + ($itemTotal - $discount);
        }, 0);
    }

    private function calculateTaxTotal(array $lineItems): float
    {
        return array_reduce($lineItems, function ($total, $item) {
            $itemTotal = $item['quantity'] * $item['unit_price'];
            $discount = $itemTotal * (($item['discount_percentage'] ?? 0) / 100);
            $netAmount = $itemTotal - $discount;
            return $total + ($netAmount * (($item['tax_rate'] ?? 0) / 100));
        }, 0);
    }

    private function calculateTotal(array $lineItems): float
    {
        return $this->calculateSubtotal($lineItems) + $this->calculateTaxTotal($lineItems);
    }

    private function calculateLineTotal(array $item): float
    {
        $itemTotal = $item['quantity'] * $item['unit_price'];
        $discount = $itemTotal * (($item['discount_percentage'] ?? 0) / 100);
        $netAmount = $itemTotal - $discount;
        $tax = $netAmount * (($item['tax_rate'] ?? 0) / 100);
        
        return $netAmount + $tax;
    }
}