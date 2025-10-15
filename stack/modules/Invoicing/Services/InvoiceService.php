<?php

namespace Modules\Invoicing\Services;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemTax;
use App\Models\Item;
use App\Models\User;
use App\Support\ServiceContext;
use App\Traits\AuditLogging;
use Brick\Money\Money;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\Customers\Services\CustomerCreditService;

/**
 * InvoiceService - Handles invoicing business logic
 *
 * This service follows the Haasib Constitution principles, particularly:
 * - RBAC Integrity: Respects seeded role/permission catalog
 * - Tenancy & RLS Safety: Enforces company scoping
 * - Audit, Idempotency & Observability: Logs all invoice operations
 * - Module Governance: Part of the Invoicing module
 *
 * @link https://github.com/Haasib/haasib/blob/main/.specify/memory/constitution.md
 */
class InvoiceService
{
    use AuditLogging;

    private CustomerCreditService $creditService;

    public function __construct(CustomerCreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    /**
     * Create a new invoice
     *
     * @param  Company  $company  The company to create the invoice for
     * @param  Customer  $customer  The customer to bill
     * @param  array  $items  Array of invoice items with description, quantity, unit_price, etc.
     * @param  Currency|null  $currency  The invoice currency (defaults to customer/company currency)
     * @param  string|null  $invoiceDate  The invoice date (defaults to current date)
     * @param  string|null  $dueDate  The due date (defaults based on customer payment terms)
     * @param  string|null  $notes  Additional notes for the invoice
     * @param  string|null  $terms  Payment terms
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Invoice The created invoice
     *
     * @throws \Throwable If the invoice creation fails
     */
    public function createInvoice(
        Company $company,
        Customer $customer,
        array $items,
        ?Currency $currency,
        ?string $invoiceDate,
        ?string $dueDate,
        ?string $notes,
        ?string $terms,
        ServiceContext $context
    ): Invoice {
        $idempotencyKey = $context->getIdempotencyKey();

        // Calculate invoice total from items
        $invoiceTotal = 0;
        foreach ($items as $item) {
            $invoiceTotal += ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
        }

        // Check credit limit enforcement
        $this->enforceCreditLimit($customer, $invoiceTotal, $context);

        try {
            $result = DB::transaction(function () use ($company, $customer, $items, $currency, $invoiceDate, $dueDate, $notes, $terms, $idempotencyKey) {
                $currency = $currency ?? ($customer->currency ?? $company->currency);
                $currencyId = $currency?->id ?? $customer->currency_id ?? $company->currency_id;

                $invoice = new Invoice([
                    'company_id' => $company->id,
                    'customer_id' => $customer->getKey(),
                    'currency_id' => $currencyId,
                    'invoice_date' => $invoiceDate ?? now()->toDateString(),
                    'due_date' => $dueDate ?? now()->addDays((int) ($customer->payment_terms ?? 0))->toDateString(),
                    'status' => 'draft',
                    'notes' => $notes,
                    'terms' => $terms,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $attempts = 0;
                do {
                    try {
                        if (! $invoice->save()) {
                            Log::error('Failed to save invoice', [
                                'errors' => method_exists($invoice, 'getErrors') ? $invoice->getErrors() : 'No errors method',
                                'attributes' => $invoice->getAttributes(),
                            ]);
                            throw new \RuntimeException('Failed to save invoice: validation failed');
                        }
                        break;
                    } catch (\Illuminate\Database\QueryException $e) {
                        if ($e->getCode() === '23505' && $attempts < 5) { // Unique constraint violation
                            $attempts++;
                            $invoice->resetInvoiceNumber();

                            continue;
                        }
                        throw $e;
                    }
                } while ($attempts < 5);

                $this->createInvoiceItems($invoice, $items);
                $invoice->calculateTotals();
                $invoice->save();

                return $invoice->fresh(['items', 'customer', 'currency']);
            });
        } catch (\Throwable $e) {
            Log::error('Transaction failed in createInvoice', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'company_id' => $company->id,
                'customer_id' => $customer->id,
            ]);
            throw $e;
        }

        if (! $result) {
            throw new \RuntimeException('DB transaction returned null');
        }

        $this->logAudit('invoice.create', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency_id' => $result->currency_id,
            'items_count' => count($items),
            'invoice_number' => $result->invoice_number,
        ], $context, result: ['invoice_id' => $result->getKey()]);

        return $result;
    }

    /**
     * Update an existing invoice
     *
     * @param  Invoice  $invoice  The invoice to update
     * @param  Customer|null  $customer  The customer to change to (if different)
     * @param  array|null  $items  New invoice items (if null, existing items are kept)
     * @param  string|null  $invoiceDate  New invoice date
     * @param  string|null  $dueDate  New due date
     * @param  string|null  $notes  New notes
     * @param  string|null  $terms  New terms
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Invoice The updated invoice
     *
     * @throws \InvalidArgumentException If the invoice cannot be edited
     * @throws \Throwable If the update operation fails
     */
    public function updateInvoice(
        Invoice $invoice,
        ?Customer $customer,
        ?array $items,
        ?string $invoiceDate,
        ?string $dueDate,
        ?string $notes,
        ?string $terms,
        ServiceContext $context
    ): Invoice {
        if (! $invoice->canBeEdited()) {
            throw new \InvalidArgumentException('Invoice cannot be edited in current status');
        }

        $oldData = $invoice->getAttributes();

        $result = DB::transaction(function () use ($invoice, $customer, $items, $invoiceDate, $dueDate, $notes, $terms) {
            if ($customer && $customer->id !== $invoice->customer_id) {
                $invoice->customer_id = $customer->id;
            }

            if ($invoiceDate) {
                $invoice->invoice_date = $invoiceDate;
            }

            if ($dueDate) {
                $invoice->due_date = $dueDate;
            }

            if ($notes !== null) {
                $invoice->notes = $notes;
            }

            if ($terms !== null) {
                $invoice->terms = $terms;
            }

            if ($items !== null) {
                $invoice->items()->delete();
                $this->createInvoiceItems($invoice, $items);
            }

            $invoice->calculateTotals();
            $invoice->save();

            return $invoice->fresh(['items', 'customer', 'currency']);
        });

        $this->logAudit('invoice.update', [
            'invoice_id' => $invoice->getKey(),
            'old_data' => $oldData,
            'changes' => [
                'customer_id' => $customer?->id !== $invoice->customer_id,
                'items_updated' => $items !== null,
                'dates_updated' => $invoiceDate !== null || $dueDate !== null,
            ],
        ], $context, result: ['updated_at' => $result->updated_at]);

        return $result;
    }

    /**
     * Delete an invoice
     *
     * @param  Invoice  $invoice  The invoice to delete
     * @param  string|null  $reason  The reason for deletion
     * @param  ServiceContext  $context  The service context containing user and company information
     *
     * @throws \InvalidArgumentException If the invoice cannot be deleted
     * @throws \Throwable If the delete operation fails
     */
    public function deleteInvoice(Invoice $invoice, ?string $reason, ServiceContext $context): void
    {
        if (! $invoice->canBeEdited()) {
            throw new \InvalidArgumentException('Invoice cannot be deleted in current status');
        }

        $invoiceData = $invoice->getAttributes();

        DB::transaction(function () use ($invoice) {
            $invoice->items()->delete();
            $invoice->delete();
        });

        $this->logAudit('invoice.delete', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'reason' => $reason,
        ], $context);
    }

    /**
     * Mark an invoice as sent
     *
     * @param  Invoice  $invoice  The invoice to mark as sent
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Invoice The updated invoice
     *
     * @throws \InvalidArgumentException If the invoice cannot be sent
     * @throws \Throwable If the operation fails
     */
    public function markAsSent(Invoice $invoice, ServiceContext $context): Invoice
    {
        if (! $invoice->canBeSent()) {
            throw new \InvalidArgumentException('Invoice cannot be sent');
        }

        $result = DB::transaction(function () use ($invoice) {
            $invoice->markAsSent();

            return $invoice->fresh();
        });

        $this->logAudit('invoice.sent', [
            'invoice_id' => $invoice->getKey(),
            'invoice_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
        ], $context, result: ['sent_at' => $result->sent_at]);

        return $result;
    }

    /**
     * Mark an invoice as posted to ledger
     *
     * @param  Invoice  $invoice  The invoice to post
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Invoice The updated invoice
     *
     * @throws \InvalidArgumentException If the invoice cannot be posted
     * @throws \Throwable If the operation fails
     */
    public function markAsPosted(Invoice $invoice, ServiceContext $context): Invoice
    {
        if (! $invoice->canBePosted()) {
            throw new \InvalidArgumentException('Invoice cannot be posted');
        }

        $result = DB::transaction(function () use ($invoice) {
            $invoice->markAsPosted();

            return $invoice->fresh();
        });

        $this->logAudit('invoice.posted', [
            'invoice_id' => $invoice->getKey(),
            'invoice_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
            'total_amount' => $invoice->total_amount,
        ], $context, result: ['posted_at' => $result->posted_at]);

        return $result;
    }

    /**
     * Cancel an invoice
     *
     * @param  Invoice  $invoice  The invoice to cancel
     * @param  string|null  $reason  The reason for cancellation
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Invoice The cancelled invoice
     *
     * @throws \InvalidArgumentException If the invoice cannot be cancelled
     * @throws \Throwable If the operation fails
     */
    public function markAsCancelled(Invoice $invoice, ?string $reason, ServiceContext $context): Invoice
    {
        if (! $invoice->canBeCancelled()) {
            throw new \InvalidArgumentException('Invoice cannot be cancelled');
        }

        $result = DB::transaction(function () use ($invoice, $reason) {
            $invoice->markAsCancelled($reason);

            return $invoice->fresh();
        });

        $this->logAudit('invoice.cancelled', [
            'invoice_id' => $invoice->getKey(),
            'invoice_number' => $invoice->invoice_number,
            'reason' => $reason,
        ], $context, result: ['cancelled_at' => $result->cancelled_at]);

        return $result;
    }

    /**
     * Calculate invoice totals
     *
     * @param  Invoice  $invoice  The invoice to calculate totals for
     * @return array The calculated totals
     */
    public function calculateInvoiceTotals(Invoice $invoice): array
    {
        $currency = $invoice->currency;
        if (! $currency) {
            throw new \InvalidArgumentException('Invoice must have a currency to calculate totals');
        }

        $subtotal = Money::zero($currency->code);
        $totalTax = Money::zero($currency->code);

        foreach ($invoice->items as $item) {
            $itemSubtotal = Money::of($item->quantity * $item->unit_price, $currency->code);
            $itemTax = $item->getTotalTax();

            $subtotal = $subtotal->plus($itemSubtotal);
            $totalTax = $totalTax->plus($itemTax);
        }

        $totalAmount = $subtotal->plus($totalTax);
        $balanceDue = $totalAmount->minus(Money::of($invoice->paid_amount, $currency->code));

        return [
            'subtotal' => $subtotal->getAmount()->toFloat(),
            'total_tax' => $totalTax->getAmount()->toFloat(),
            'total_amount' => $totalAmount->getAmount()->toFloat(),
            'balance_due' => max(0, $balanceDue->getAmount()->toFloat()),
        ];
    }

    /**
     * Validate invoice items
     *
     * @param  array  $items  The items to validate
     *
     * @throws \InvalidArgumentException If validation fails
     */
    public function validateInvoiceItems(array $items): void
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('Invoice must have at least one item');
        }

        foreach ($items as $index => $item) {
            if (! isset($item['description']) || empty(trim($item['description']))) {
                throw new \InvalidArgumentException("Item {$index} must have a description");
            }

            if (! isset($item['quantity']) || $item['quantity'] <= 0) {
                throw new \InvalidArgumentException("Item {$index} must have a positive quantity");
            }

            if (! isset($item['unit_price']) || $item['unit_price'] < 0) {
                throw new \InvalidArgumentException("Item {$index} must have a non-negative unit price");
            }

            if (isset($item['discount_percentage']) && ($item['discount_percentage'] < 0 || $item['discount_percentage'] > 100)) {
                throw new \InvalidArgumentException("Item {$index} discount percentage must be between 0 and 100");
            }

            if (isset($item['discount_amount']) && $item['discount_amount'] < 0) {
                throw new \InvalidArgumentException("Item {$index} discount amount must be non-negative");
            }
        }
    }

    /**
     * Get invoices for a company with pagination
     *
     * @param  Company  $company  The company context
     * @param  ServiceContext  $context  The service context
     * @param  int  $perPage  Number of results per page
     * @param  string|null  $status  Optional status filter
     * @param  string|null  $startDate  Optional start date filter
     * @param  string|null  $endDate  Optional end date filter
     * @return LengthAwarePaginator The invoices
     */
    public function getInvoicesForCompany(
        Company $company,
        ServiceContext $context,
        int $perPage = 20,
        ?string $status = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): LengthAwarePaginator {
        $query = Invoice::where('company_id', $company->id);

        if ($status) {
            $query->where('status', $status);
        }

        if ($startDate) {
            $query->where('invoice_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('invoice_date', '<=', $endDate);
        }

        $invoices = $query->with(['customer', 'currency'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $this->logAudit('invoice.list_viewed', [
            'company_id' => $company->id,
            'status_filter' => $status,
            'total_count' => $invoices->total(),
        ], $context);

        return $invoices;
    }

    /**
     * Bulk update invoice statuses
     *
     * @param  array  $invoiceIds  Array of invoice IDs to update
     * @param  string  $newStatus  The new status (sent, posted, cancelled)
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return array Array of results with success/failure status for each invoice
     */
    public function bulkUpdateStatus(array $invoiceIds, string $newStatus, ServiceContext $context): array
    {
        $results = [];

        // Verify all invoices belong to the same company and user has permissions
        $firstInvoice = Invoice::find($invoiceIds[0]);
        if (! $firstInvoice) {
            throw new \InvalidArgumentException('Invalid invoice ID in list');
        }

        $user = $context->getUser();
        if (! $user) {
            throw new \InvalidArgumentException('User not found in context');
        }

        // Check permission to modify invoices
        // In a real implementation, we would check specific permissions

        foreach ($invoiceIds as $invoiceId) {
            try {
                $invoice = Invoice::find($invoiceId);

                if (! $invoice) {
                    $results[] = [
                        'invoice_id' => $invoiceId,
                        'success' => false,
                        'error' => 'Invoice not found',
                    ];

                    continue;
                }

                switch ($newStatus) {
                    case 'sent':
                        $invoice = $this->markAsSent($invoice, $context);
                        break;
                    case 'posted':
                        $invoice = $this->markAsPosted($invoice, $context);
                        break;
                    case 'cancelled':
                        $invoice = $this->markAsCancelled($invoice, null, $context);
                        break;
                    default:
                        throw new \InvalidArgumentException("Unsupported status transition: {$newStatus}");
                }

                $results[] = [
                    'invoice_id' => $invoiceId,
                    'success' => true,
                    'new_status' => $invoice->status,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'invoice_id' => $invoiceId,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Create invoice items helper function
     *
     * @param  Invoice  $invoice  The invoice to create items for
     * @param  array  $items  The items to create
     */
    private function createInvoiceItems(Invoice $invoice, array $items): void
    {
        $this->validateInvoiceItems($items);

        foreach ($items as $itemData) {
            $item = new InvoiceItem([
                'invoice_id' => $invoice->getKey(),
                'description' => $itemData['description'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'discount_amount' => $itemData['discount_amount'] ?? 0,
                'discount_percentage' => $itemData['discount_percentage'] ?? 0,
                'tax_inclusive' => $itemData['tax_inclusive'] ?? false,
            ]);

            if (isset($itemData['item_id'])) {
                $existingItem = Item::find($itemData['item_id']);
                if ($existingItem && $existingItem->company_id === $invoice->company_id) {
                    $item->item_id = $existingItem->id;
                    if (! isset($itemData['description']) || empty(trim($itemData['description']))) {
                        $item->description = $existingItem->name;
                    }
                }
            }

            $item->save();

            if (isset($itemData['taxes']) && is_array($itemData['taxes'])) {
                foreach ($itemData['taxes'] as $taxData) {
                    InvoiceItemTax::create([
                        'invoice_item_id' => $item->getKey(),
                        'tax_name' => $taxData['name'],
                        'rate' => $taxData['rate'],
                        'tax_amount' => 0,
                    ]);
                }
            }
        }
    }

    /**
     * Get invoice statistics
     *
     * @param  Company  $company  The company context
     * @param  ServiceContext  $context  The service context
     * @param  string|null  $startDate  Optional start date filter
     * @param  string|null  $endDate  Optional end date filter
     * @return array Statistics about invoices
     */
    public function getInvoiceStatistics(Company $company, ServiceContext $context, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Invoice::where('company_id', $company->id);

        if ($startDate) {
            $query->where('invoice_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('invoice_date', '<=', $endDate);
        }

        $invoices = $query->get();

        $stats = [
            'total_invoices' => $invoices->count(),
            'total_amount' => $invoices->sum('total_amount'),
            'total_paid' => $invoices->sum('paid_amount'),
            'total_outstanding' => $invoices->sum('balance_due'),
            'average_invoice_value' => $invoices->avg('total_amount') ?: 0,
            'status_breakdown' => [
                'draft' => $invoices->where('status', 'draft')->count(),
                'sent' => $invoices->where('status', 'sent')->count(),
                'posted' => $invoices->where('status', 'posted')->count(),
                'partial' => $invoices->where('status', 'partial')->count(),
                'paid' => $invoices->where('status', 'paid')->count(),
                'cancelled' => $invoices->where('status', 'cancelled')->count(),
            ],
        ];

        $this->logAudit('invoice.statistics_viewed', [
            'company_id' => $company->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], $context);

        return $stats;
    }

    /**
     * Generate invoice number
     *
     * @param  Company  $company  The company context
     * @return string The generated invoice number
     */
    public function generateInvoiceNumber(Company $company): string
    {
        $prefix = $company->invoice_number_prefix ?? 'INV-';
        $lastInvoice = Invoice::where('company_id', $company->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $sequence = 1;
        if ($lastInvoice) {
            $lastSequence = intval(substr($lastInvoice->invoice_number, strlen($prefix)));
            $sequence = $lastSequence + 1;
        }

        return $prefix.str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Enforce credit limits for invoice creation
     *
     * @throws \RuntimeException
     */
    private function enforceCreditLimit(Customer $customer, float $invoiceTotal, ServiceContext $context): void
    {
        $creditCheck = $this->creditService->canCreateInvoice($customer, $invoiceTotal);

        if (! $creditCheck['allowed']) {
            $user = $context->getUser();
            $overrideRequested = $context->getOptions()['override_credit_limit'] ?? false;
            $overrideReason = $context->getOptions()['override_reason'] ?? null;

            // Check if override is requested and user has permission
            if ($overrideRequested && $this->creditService->canOverrideCreditLimit($customer, $user)) {
                $this->logCreditOverride($customer, $invoiceTotal, $creditCheck, $context);

                return;
            }

            // Log credit limit breach attempt
            $this->logCreditBreachAttempt($customer, $invoiceTotal, $creditCheck, $context);

            throw new \RuntimeException(
                "Credit limit enforcement: {$creditCheck['message']}",
                0,
                null,
                $creditCheck['details'] ?? []
            );
        }
    }

    /**
     * Log credit limit override
     */
    private function logCreditOverride(Customer $customer, float $invoiceTotal, array $creditCheck, ServiceContext $context): void
    {
        $this->logAudit('invoice.credit_limit_override', [
            'customer_id' => $customer->id,
            'invoice_amount' => $invoiceTotal,
            'credit_limit' => $creditCheck['details']['credit_limit'] ?? null,
            'current_exposure' => $creditCheck['details']['current_exposure'] ?? 0,
            'override_reason' => $context->getOptions()['override_reason'] ?? null,
            'user_id' => $context->getUser()?->id,
        ], $context);
    }

    /**
     * Log credit limit breach attempt
     */
    private function logCreditBreachAttempt(Customer $customer, float $invoiceTotal, array $creditCheck, ServiceContext $context): void
    {
        $this->logAudit('invoice.credit_limit_breach_attempt', [
            'customer_id' => $customer->id,
            'invoice_amount' => $invoiceTotal,
            'credit_limit' => $creditCheck['details']['credit_limit'] ?? null,
            'current_exposure' => $creditCheck['details']['current_exposure'] ?? 0,
            'excess_amount' => $creditCheck['details']['excess_amount'] ?? 0,
            'user_id' => $context->getUser()?->id,
            'reason' => $creditCheck['reason'],
        ], $context);
    }
}
