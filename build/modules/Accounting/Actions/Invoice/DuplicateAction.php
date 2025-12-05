<?php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\InvoiceLineItem;
use App\Modules\Accounting\Domain\Customers\Models\Customer;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DuplicateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
            'customer' => 'nullable|string|max:255',
            'draft' => 'nullable|boolean',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::INVOICE_CREATE; // Use create permission for duplicate
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $source = $this->resolveInvoice($params['id'], $company->id);
        $source->load('lineItems');

        // Determine customer
        $customer = !empty($params['customer'])
            ? $this->resolveCustomer($params['customer'], $company->id)
            : $source->customer;

        return DB::transaction(function () use ($params, $company, $source, $customer) {
            $newNumber = Invoice::generateInvoiceNumber($company->id);

            $status = ($params['draft'] ?? false)
                ? 'draft'
                : 'sent';

            // Create new invoice
            $invoice = Invoice::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_number' => $newNumber,
                'invoice_date' => now(),
                'due_date' => now()->addDays(30), // Default 30 days
                'subtotal' => $source->subtotal,
                'tax_amount' => $source->tax_amount,
                'discount_amount' => $source->discount_amount,
                'total_amount' => $source->total_amount,
                'paid_amount' => 0,
                'balance' => $source->total_amount,
                'currency' => $source->currency,
                'base_currency' => $source->base_currency,
                'exchange_rate' => $source->exchange_rate,
                'base_amount' => $source->base_amount,
                'status' => $status,
                'notes' => $source->notes,
                'created_by_user_id' => Auth::id(),
            ]);

            // Copy line items
            foreach ($source->lineItems as $idx => $line) {
                $invoice->lineItems()->create([
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'line_number' => $line->line_number ?? ($idx + 1),
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => $line->tax_rate ?? 0,
                    'discount_rate' => $line->discount_rate ?? 0,
                    'line_total' => $line->line_total ?? $line->total,
                    'tax_amount' => $line->tax_amount ?? 0,
                    'total' => $line->total,
                ]);
            }

            return [
                'message' => "Invoice duplicated: {$source->invoice_number} → {$newNumber}",
                'data' => [
                    'id' => $invoice->id,
                    'number' => $newNumber,
                    'source_number' => $source->invoice_number,
                    'customer' => $customer->name,
                    'total' => PaletteFormatter::money($invoice->total_amount, $invoice->currency),
                ],
                'redirect' => "/{$company->slug}/invoices/{$invoice->id}",
            ];
        });
    }

    private function resolveInvoice(string $identifier, string $companyId): Invoice
    {
        // Try UUID
        if (Str::isUuid($identifier)) {
            $invoice = Invoice::where('id', $identifier)
                ->where('company_id', $companyId)
                ->first();
            if ($invoice) return $invoice;
        }

        // Try invoice number (exact)
        $invoice = Invoice::where('company_id', $companyId)
            ->where('invoice_number', $identifier)
            ->first();
        if ($invoice) return $invoice;

        // Try partial number match (e.g., "00001" matches "INV-2024-00001")
        $invoice = Invoice::where('company_id', $companyId)
            ->where('invoice_number', 'like', "%{$identifier}")
            ->first();
        if ($invoice) return $invoice;

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Invoice not found: {$identifier}");
    }

    private function resolveCustomer(string $identifier, string $companyId): Customer
    {
        // Try UUID
        if (Str::isUuid($identifier)) {
            $customer = Customer::where('id', $identifier)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->first();
            if ($customer) return $customer;
        }

        // Try exact customer number
        $customer = Customer::where('company_id', $companyId)
            ->where('customer_number', $identifier)
            ->where('is_active', true)
            ->first();
        if ($customer) return $customer;

        // Try exact email
        $customer = Customer::where('company_id', $companyId)
            ->where('email', $identifier)
            ->where('is_active', true)
            ->first();
        if ($customer) return $customer;

        // Try exact name (case-insensitive)
        $customer = Customer::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($identifier)])
            ->where('is_active', true)
            ->first();
        if ($customer) return $customer;

        // Try fuzzy match
        $customer = Customer::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereRaw('similarity(name, ?) > 0.3', [$identifier])
            ->orderByRaw('similarity(name, ?) DESC', [$identifier])
            ->first();
        if ($customer) return $customer;

        throw new \Exception("Customer not found: {$identifier}. Create with: customer create \"{$identifier}\"");
    }
}
