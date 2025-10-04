<?php

namespace App\Actions\Invoicing;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use App\Support\ServiceContextHelper;
use Illuminate\Support\Facades\Log;

class InvoiceCreate
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function handle(array $p, User $actor): array
    {
        // Extract company_id from user context
        $companyId = $p['company_id'] ?? $actor->current_company_id;

        // Handle idempotency
        $idempotencyKey = $p['idempotency_key'] ?? null;

        if ($idempotencyKey) {
            // Check for existing invoice with same idempotency key
            $existingInvoice = Invoice::where('company_id', $companyId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingInvoice) {
                Log::info('Duplicate invoice request detected', [
                    'idempotency_key' => $idempotencyKey,
                    'company_id' => $companyId,
                    'existing_invoice_id' => $existingInvoice->invoice_id,
                    'actor_id' => $actor->id,
                ]);

                return [
                    'message' => 'Invoice already exists (idempotent request)',
                    'data' => [
                        'id' => $existingInvoice->invoice_id,
                        'invoice_number' => $existingInvoice->invoice_number,
                        'status' => $existingInvoice->status,
                        'total_amount' => $existingInvoice->total_amount,
                    ],
                    'idempotent' => true,
                ];
            }
        }

        // Extract variables for service call
        $items = $p['items'] ?? [];
        $invoiceDate = $p['invoice_date'];
        $dueDate = $p['due_date'];
        $notes = $p['notes'] ?? null;
        $terms = $p['terms'] ?? null;
        $invoiceNumber = $p['invoice_number'] ?? null;

        // Fetch the required models
        $company = Company::findOrFail($companyId);
        $customer = Customer::findOrFail($p['customer_id']);
        $currency = $p['currency_id'] ? Currency::findOrFail($p['currency_id']) : null;

        // Let the service handle complex logic
        $context = ServiceContextHelper::forUser($actor, $companyId, $idempotencyKey);

        try {
            $invoice = $this->invoiceService->createInvoice($company, $customer, $items, $currency, $invoiceDate, $dueDate, $notes, $terms, $context);
        } catch (\Throwable $e) {
            Log::error('Invoice service threw exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'company_id' => $companyId,
                'customer_id' => $p['customer_id'],
                'currency_id' => $p['currency_id'],
                'items' => $items,
                'actor_id' => $actor->id,
            ]);
            throw $e;
        }

        if (! $invoice) {
            Log::error('Invoice service returned null', [
                'company_id' => $companyId,
                'customer_id' => $p['customer_id'],
                'currency_id' => $p['currency_id'],
                'items' => $items,
                'actor_id' => $actor->id,
            ]);
            throw new \RuntimeException('Failed to create invoice: service returned null');
        }

        // Update invoice number if provided
        if ($invoiceNumber && $invoice->invoice_number !== $invoiceNumber) {
            $invoice->invoice_number = $invoiceNumber;
            $invoice->save();
        }

        return [
            'message' => 'Invoice created successfully',
            'data' => [
                'id' => $invoice->invoice_id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'total_amount' => $invoice->total_amount,
            ],
            'idempotent' => false,
        ];
    }
}
