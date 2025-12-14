<?php

namespace App\Modules\Accounting\Actions\Payment;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Modules\Accounting\Models\Customer;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IndexAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'invoice' => 'nullable|string|max:255',
            'customer' => 'nullable|string|max:255',
            'method' => 'nullable|string|in:cash,check,card,bank_transfer,other',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function permission(): ?string
    {
        return null; // Any authenticated user can list
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $limit = $params['limit'] ?? 50;

        $query = Payment::with(['paymentAllocations.invoice.customer'])
            ->where('company_id', $company->id)
            ->orderBy('payment_date', 'desc');

        // Invoice filter
        if (!empty($params['invoice'])) {
            $invoice = $this->resolveInvoice($params['invoice'], $company->id);
            $query->whereHas('paymentAllocations', function ($q) use ($invoice) {
                $q->where('invoice_id', $invoice->id);
            });
        }

        // Customer filter
        if (!empty($params['customer'])) {
            $query->whereHas('paymentAllocations.invoice.customer', function ($q) use ($params) {
                $q->where('name', 'ilike', "%{$params['customer']}%")
                  ->orWhere('customer_number', 'ilike', "%{$params['customer']}%")
                  ->orWhere('email', 'ilike', "%{$params['customer']}%");
            });
        }

        // Method filter
        if (!empty($params['method'])) {
            $query->where('payment_method', $params['method']);
        }

        // Date range
        if (!empty($params['from'])) {
            $query->where('payment_date', '>=', $params['from']);
        }
        if (!empty($params['to'])) {
            $query->where('payment_date', '<=', $params['to']);
        }

        $payments = $query->limit($limit)->get();
        $totalAmount = $payments->sum('amount');

        return [
            'data' => PaletteFormatter::table(
                headers: ['Date', 'Invoice', 'Customer', 'Method', 'Amount'],
                rows: $payments->map(function ($p) {
                    $allocation = $p->paymentAllocations->first();
                    $invoice = $allocation?->invoice;
                    $customer = $invoice?->customer;

                    return [
                        $p->payment_date->format('M j, Y'),
                        $invoice?->invoice_number ?? '—',
                        $customer ? Str::limit($customer->name, 15) : '—',
                        ucfirst(str_replace('_', ' ', $p->payment_method)),
                        PaletteFormatter::money($p->amount, $p->currency),
                    ];
                })->toArray(),
                footer: $payments->count() . ' payments · ' .
                        PaletteFormatter::money($totalAmount, $company->base_currency) . ' total',
                rowIds: $payments->pluck('id')->toArray()
            ),
        ];
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
}
