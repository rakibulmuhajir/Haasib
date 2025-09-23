<?php

namespace App\Actions\Invoicing;

use App\Models\User;
use App\Services\InvoiceService;

class InvoiceCreate
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function handle(array $p, User $actor): array
    {
        // Extract company_id from user context
        $companyId = $p['company_id'] ?? $actor->current_company_id;
        
        // Prepare data for service
        $invoiceData = [
            'customer_id' => $p['customer_id'],
            'invoice_date' => $p['invoice_date'],
            'due_date' => $p['due_date'],
            'currency_id' => $p['currency_id'],
            'exchange_rate' => $p['exchange_rate'] ?? 1.0,
            'reference_number' => $p['reference_number'] ?? null,
            'notes' => $p['notes'] ?? null,
            'items' => $p['items'] ?? [],
        ];

        // Let the service handle complex logic
        $invoice = $this->invoiceService->createInvoice($companyId, $invoiceData);

        return [
            'message' => 'Invoice created successfully',
            'data' => [
                'id' => $invoice->invoice_id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'total_amount' => $invoice->total_amount,
            ]
        ];
    }
}