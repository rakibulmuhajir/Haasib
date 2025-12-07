<?php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\InvoiceLineItem;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UpdateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|uuid',
            'customer' => 'required|string|max:255',
            'currency' => 'required|string|size:3|uppercase',
            'due' => 'nullable|date|after_or_equal:today',
            'draft' => 'nullable|boolean',
            'exchange_rate' => 'nullable|numeric|min:0.00000001|max:999999999',
            'payment_terms' => 'nullable|integer|min:0|max:365',
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string|max:500',
            'line_items.*.quantity' => 'required|numeric|min:0.01',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.discount_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.line_number' => 'nullable|integer|min:1',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::INVOICE_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        // Get the invoice
        $invoice = Invoice::where('id', $params['id'])
            ->where('company_id', $company->id)
            ->firstOrFail();

        // Prevent updates to paid invoices
        if ($invoice->paid_amount > 0) {
            throw new \Exception('Cannot update invoice that has payments applied.');
        }

        // Resolve customer (UUID, email, or fuzzy name match)
        $customer = $this->resolveCustomer($params['customer'], $company->id);

        return DB::transaction(function () use ($params, $company, $customer, $invoice) {
            // Calculate dates
            $paymentTerms = $params['payment_terms'] ?? $customer->payment_terms ?? 30;
            $dueDate = !empty($params['due'])
                ? Carbon::parse($params['due'])
                : $invoice->invoice_date->copy()->addDays($paymentTerms);

            // Determine status
            $status = ($params['draft'] ?? false)
                ? 'draft'
                : 'sent';

            $currency = strtoupper($params['currency']);
            $baseCurrency = strtoupper($company->base_currency ?? $customer->base_currency ?? $currency);
            $exchangeRate = ($currency === $baseCurrency) ? null : ($params['exchange_rate'] ?? null);
            if ($currency !== $baseCurrency && $exchangeRate === null) {
                throw new \InvalidArgumentException('exchange_rate is required when currency differs from base_currency.');
            }
            $exchangeRate = $exchangeRate ? (float) $exchangeRate : null;
            $lineItems = $params['line_items'];

            // Compute totals
            $subtotal = 0.0;
            $taxAmount = 0.0;
            $discountAmount = 0.0;
            foreach ($lineItems as $idx => $item) {
                $qty = (float) $item['quantity'];
                $unit = (float) $item['unit_price'];
                $taxRate = isset($item['tax_rate']) ? (float) $item['tax_rate'] : 0.0;
                $discountRate = isset($item['discount_rate']) ? (float) $item['discount_rate'] : 0.0;

                $lineTotal = $qty * $unit;
                $lineDiscount = $lineTotal * ($discountRate / 100);
                $lineTaxable = $lineTotal - $lineDiscount;
                $lineTax = $lineTaxable * ($taxRate / 100);
                $lineGrand = $lineTaxable + $lineTax;

                $subtotal += $lineTotal;
                $taxAmount += $lineTax;
                $discountAmount += $lineDiscount;

                $lineItems[$idx]['_line_total'] = $lineTotal;
                $lineItems[$idx]['_tax_amount'] = $lineTax;
                $lineItems[$idx]['_total'] = $lineGrand;
            }

            $total = $subtotal + $taxAmount - $discountAmount;
            $baseAmount = round($total * ($exchangeRate ?? 1), 2);

            // Update invoice
            $invoice->update([
                'customer_id' => $customer->id,
                'due_date' => $dueDate,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $total,
                'balance' => $total,
                'currency' => $currency,
                'base_currency' => $baseCurrency,
                'exchange_rate' => $exchangeRate,
                'base_amount' => $baseAmount,
                'payment_terms' => $paymentTerms,
                'status' => $status,
                'notes' => $params['description'] ?? null,
                'updated_by_user_id' => Auth::id(),
            ]);

            // Delete existing line items
            InvoiceLineItem::where('invoice_id', $invoice->id)->delete();

            // Persist new line items
            foreach ($lineItems as $idx => $item) {
                InvoiceLineItem::create([
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'line_number' => $item['line_number'] ?? ($idx + 1),
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'discount_rate' => $item['discount_rate'] ?? 0,
                    'line_total' => $item['_line_total'],
                    'tax_amount' => $item['_tax_amount'],
                    'total' => $item['_total'],
                    'created_by_user_id' => Auth::id(),
                ]);
            }

            $statusLabel = $status === 'draft' ? 'Draft' : 'Sent';

            return [
                'message' => "Invoice {$invoice->invoice_number} updated ({$statusLabel}) for {$customer->name}",
                'data' => [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'customer' => $customer->name,
                    'total' => PaletteFormatter::money($invoice->total_amount, $invoice->currency),
                    'due_date' => $dueDate->format('M j, Y'),
                    'status' => $status,
                ],
                'redirect' => "/{$company->slug}/invoices/{$invoice->id}",
            ];
        });
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