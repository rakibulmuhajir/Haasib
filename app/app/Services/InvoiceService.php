<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemTax;
use App\Models\Item;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDF;

class InvoiceService
{
    private function logAudit(string $action, array $params, ?User $user = null, ?string $companyId = null, ?string $idempotencyKey = null, ?array $result = null): void
    {
        try {
            DB::transaction(function () use ($action, $params, $user, $companyId, $idempotencyKey, $result) {
                DB::table('audit_logs')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user?->id,
                    'company_id' => $companyId,
                    'action' => $action,
                    'params' => json_encode($params),
                    'result' => $result ? json_encode($result) : null,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to write audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function createInvoice(
        Company $company,
        Customer $customer,
        array $items,
        ?Currency $currency = null,
        ?string $invoiceDate = null,
        ?string $dueDate = null,
        ?string $notes = null,
        ?string $terms = null,
        ?string $idempotencyKey = null
    ): Invoice {
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
                    $invoice->save();
                    break;
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($e->getCode() === '23505' && $attempts < 5) {
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

        $this->logAudit('invoice.create', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency_id' => $result->currency_id,
            'items_count' => count($items),
            'invoice_number' => $result->invoice_number,
        ], auth()->user(), $company->id, $idempotencyKey, ['invoice_id' => $result->getKey()]);

        return $result;
    }

    public function updateInvoice(
        Invoice $invoice,
        ?Customer $customer = null,
        ?array $items = null,
        ?string $invoiceDate = null,
        ?string $dueDate = null,
        ?string $notes = null,
        ?string $terms = null
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
        ], auth()->user(), $invoice->company_id, result: ['updated_at' => $result->updated_at]);

        return $result;
    }

    public function deleteInvoice(Invoice $invoice, ?string $reason = null): void
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
        ], auth()->user(), $invoice->company_id);
    }

    public function markAsSent(Invoice $invoice): Invoice
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
        ], auth()->user(), $invoice->company_id, result: ['sent_at' => $result->sent_at]);

        return $result;
    }

    public function markAsPosted(Invoice $invoice): Invoice
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
        ], auth()->user(), $invoice->company_id, result: ['posted_at' => $result->posted_at]);

        return $result;
    }

    /**
     * Back-compat wrapper for controllers expecting postToLedger().
     */
    public function postToLedger(Invoice $invoice): Invoice
    {
        return $this->markAsPosted($invoice);
    }

    public function markAsCancelled(Invoice $invoice, ?string $reason = null): Invoice
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
        ], auth()->user(), $invoice->company_id, result: ['cancelled_at' => $result->cancelled_at]);

        return $result;
    }

    public function generatePDF(Invoice $invoice): string
    {
        $pdfData = [
            'invoice' => $invoice,
            'company' => $invoice->company,
            'customer' => $invoice->customer,
            'items' => $invoice->items,
            'subtotal' => $invoice->subtotal,
            'total_tax' => $invoice->tax_amount,
            'total_amount' => $invoice->total_amount,
            'balance_due' => $invoice->balance_due,
            'generated_at' => now(),
        ];

        $pdf = PDF::loadView('invoices.pdf', $pdfData);

        $filename = "invoice_{$invoice->invoice_number}_".now()->format('Y-m-d').'.pdf';
        $path = storage_path("app/public/invoices/{$filename}");

        // Ensure directory exists before saving
        File::ensureDirectoryExists(dirname($path));
        if (! File::exists(public_path('storage'))) {
            try {
                Artisan::call('storage:link');
            } catch (\Throwable $e) { /* non-fatal */
            }
        }
        $pdf->save($path);

        $this->logAudit('invoice.pdf_generated', [
            'invoice_id' => $invoice->getKey(),
            'filename' => $filename,
        ], auth()->user(), $invoice->company_id);

        return $path;
    }

    public function sendInvoiceByEmail(Invoice $invoice, string $email, ?string $message = null): void
    {
        if (! $invoice->canBeSent()) {
            throw new \InvalidArgumentException('Invoice cannot be sent');
        }

        $pdfPath = $this->generatePDF($invoice);
        // Actual mailing integration deferred; mark as sent for now
        $this->markAsSent($invoice);

        $this->logAudit('invoice.emailed', [
            'invoice_id' => $invoice->getKey(),
            'email' => $email,
            'pdf_path' => $pdfPath,
        ], auth()->user(), $invoice->company_id);
    }

    /**
     * Simplified email flow used by controller; generates PDF and marks as sent.
     */
    public function sendEmail(Invoice $invoice, ?string $email = null, ?string $subject = null, ?string $message = null): void
    {
        $this->generatePDF($invoice);
        $this->markAsSent($invoice);

        $this->logAudit('invoice.email_requested', [
            'invoice_id' => $invoice->getKey(),
            'email' => $email,
            'subject' => $subject,
        ], auth()->user(), $invoice->company_id);
    }

    public function duplicateInvoice(Invoice $originalInvoice, ?string $newNotes = null): Invoice
    {
        $result = DB::transaction(function () use ($originalInvoice, $newNotes) {
            $newInvoice = $originalInvoice->replicate([
                'invoice_id', 'invoice_number', 'status', 'created_at', 'updated_at',
                'sent_at', 'posted_at', 'cancelled_at',
            ]);

            $newInvoice->status = 'draft';
            $newInvoice->invoice_date = now()->toDateString();
            $newInvoice->due_date = now()->addDays($originalInvoice->customer->payment_terms)->toDateString();
            $newInvoice->notes = $newNotes ?? $originalInvoice->notes;
            $newInvoice->paid_amount = 0;
            $newInvoice->balance_due = $originalInvoice->total_amount;

            $newInvoice->save();

            foreach ($originalInvoice->items as $originalItem) {
                $newItem = $originalItem->replicate(['invoice_item_id', 'invoice_id', 'created_at', 'updated_at']);
                $newItem->invoice_id = $newInvoice->getKey();
                $newItem->save();

                foreach ($originalItem->taxes as $originalTax) {
                    $newTax = $originalTax->replicate(['id', 'invoice_item_id', 'created_at', 'updated_at']);
                    $newTax->invoice_item_id = $newItem->id;
                    $newTax->save();
                }
            }

            $newInvoice->calculateTotals();
            $newInvoice->save();

            return $newInvoice->fresh(['items', 'customer', 'currency']);
        });

        $this->logAudit('invoice.duplicated', [
            'original_invoice_id' => $originalInvoice->getKey(),
            'new_invoice_id' => $result->getKey(),
            'original_invoice_number' => $originalInvoice->invoice_number,
            'new_invoice_number' => $result->invoice_number,
        ], auth()->user(), $originalInvoice->company_id, result: ['new_invoice_id' => $result->getKey()]);

        return $result;
    }

    public function calculateInvoiceTotals(Invoice $invoice): array
    {
        $subtotal = Money::of(0, $invoice->currency->code);
        $totalTax = Money::of(0, $invoice->currency->code);

        foreach ($invoice->items as $item) {
            $itemSubtotal = Money::of($item->quantity * $item->unit_price, $invoice->currency->code);
            $itemTax = $item->getTotalTax();

            $subtotal = $subtotal->plus($itemSubtotal);
            $totalTax = $totalTax->plus($itemTax);
        }

        $totalAmount = $subtotal->plus($totalTax);
        $balanceDue = $totalAmount->minus(Money::of($invoice->paid_amount, $invoice->currency->code));

        return [
            'subtotal' => $subtotal->getAmount()->toFloat(),
            'total_tax' => $totalTax->getAmount()->toFloat(),
            'total_amount' => $totalAmount->getAmount()->toFloat(),
            'balance_due' => max(0, $balanceDue->getAmount()->toFloat()),
        ];
    }

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

    public function getInvoiceStatistics(Company $company, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Invoice::where('company_id', $company->id);

        if ($startDate) {
            $query->where('invoice_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('invoice_date', '<=', $endDate);
        }

        $invoices = $query->get();

        return [
            'total_invoices' => $invoices->count(),
            'total_amount' => $invoices->sum('total_amount'),
            'total_paid' => $invoices->sum('paid_amount'),
            'total_outstanding' => $invoices->sum('balance_due'),
            'average_invoice_value' => $invoices->avg('total_amount'),
            'status_breakdown' => [
                'draft' => $invoices->where('status', 'draft')->count(),
                'sent' => $invoices->where('status', 'sent')->count(),
                'posted' => $invoices->where('status', 'posted')->count(),
                'partial' => $invoices->where('status', 'partial')->count(),
                'paid' => $invoices->where('status', 'paid')->count(),
                'cancelled' => $invoices->where('status', 'cancelled')->count(),
            ],
        ];
    }

    public function bulkUpdateStatus(array $invoiceIds, string $newStatus): array
    {
        $results = [];

        foreach ($invoiceIds as $invoiceId) {
            try {
                $invoice = Invoice::findOrFail($invoiceId);

                switch ($newStatus) {
                    case 'sent':
                        $invoice = $this->markAsSent($invoice);
                        break;
                    case 'posted':
                        $invoice = $this->markAsPosted($invoice);
                        break;
                    case 'cancelled':
                        $invoice = $this->markAsCancelled($invoice);
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
}
