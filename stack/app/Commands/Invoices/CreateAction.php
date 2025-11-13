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

class CreateAction extends BaseCommand
{
    public function handle(): Invoice
    {
        return $this->executeInTransaction(function () {
            $companyId = $this->context->getCompanyId();
            $userId = $this->context->getUserId();
            
            if (!$companyId || !$userId) {
                throw new Exception('Invalid service context: missing company or user');
            }

            // Validate customer exists and belongs to company
            $customer = Customer::where('id', $this->getValue('customer_id'))
                ->where('company_id', $companyId)
                ->firstOrFail();

            // Generate invoice number if not provided
            $invoiceNumber = $this->generateInvoiceNumber($companyId);

            // Calculate totals
            $lineItems = $this->getValue('line_items', []);
            $subtotal = $this->calculateSubtotal($lineItems);
            $taxTotal = $this->calculateTaxTotal($lineItems);
            $total = $this->calculateTotal($lineItems);

            // Create invoice
            $invoice = Invoice::create([
                'id' => Str::uuid(),
                'company_id' => $companyId,
                'customer_id' => $customer->id,
                'invoice_number' => $invoiceNumber,
                'issue_date' => $this->getValue('issue_date'),
                'due_date' => $this->getValue('due_date'),
                'currency' => $this->getValue('currency'),
                'notes' => $this->getValue('notes'),
                'terms' => $this->getValue('terms'),
                'status' => 'draft',
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $total,
                'created_by_user_id' => $userId,
            ]);

            // Create line items
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

            $this->audit('invoice.created', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $customer->id,
                'total' => $total,
            ]);

            return $invoice->load(['customer', 'lineItems']);
        });
    }

    private function generateInvoiceNumber(string $companyId): string
    {
        $maxNumber = Invoice::where('company_id', $companyId)
            ->whereNotNull('invoice_number')
            ->max('invoice_number');

        $nextNumber = (int)str_replace('INV-', '', $maxNumber ?? 'INV-000000') + 1;

        return 'INV-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
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