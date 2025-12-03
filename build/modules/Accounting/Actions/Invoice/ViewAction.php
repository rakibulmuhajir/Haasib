<?php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\PaletteFormatter;
use Illuminate\Support\Str;

class ViewAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
        ];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $invoice = $this->resolveInvoice($params['id'], $company->id);
        $invoice->load('customer', 'lineItems');

        // Get payment history
        $payments = Payment::where('paymentable_type', Invoice::class)
            ->where('paymentable_id', $invoice->id)
            ->where('is_voided', false)
            ->orderBy('payment_date', 'desc')
            ->get();

        $rows = [
            ['Invoice Number', $invoice->invoice_number],
            ['Customer', $invoice->customer->name],
            ['Status', $this->formatStatusLong($invoice)],
            ['Issue Date', $invoice->issue_date->format('M j, Y')],
            ['Due Date', $invoice->due_date->format('M j, Y') .
                ($invoice->due_date->isPast() && $invoice->balance_due > 0 ? ' {error}(OVERDUE){/}' : '')],
            ['Reference', $invoice->reference ?? '—'],
            ['', ''],  // Spacer
            ['Financial Summary', ''],
            ['Subtotal', PaletteFormatter::money($invoice->subtotal, $invoice->currency)],
            ['Tax', PaletteFormatter::money($invoice->tax_amount, $invoice->currency)],
            ['Discount', $invoice->discount_amount > 0
                ? '-' . PaletteFormatter::money($invoice->discount_amount, $invoice->currency)
                : '—'],
            ['{bold}Total{/}', '{bold}' . PaletteFormatter::money($invoice->total_amount, $invoice->currency) . '{/}'],
            ['Amount Paid', PaletteFormatter::money($invoice->total_amount - $invoice->balance_due, $invoice->currency)],
            ['{bold}Balance Due{/}', $invoice->balance_due > 0
                ? '{warning}' . PaletteFormatter::money($invoice->balance_due, $invoice->currency) . '{/}'
                : '{success}$0.00{/}'],
        ];

        // Add line items if present
        if ($invoice->lineItems->isNotEmpty()) {
            $rows[] = ['', ''];  // Spacer
            $rows[] = ['{bold}Line Items{/}', ''];
            foreach ($invoice->lineItems as $i => $line) {
                $rows[] = [
                    "  " . ($i + 1) . ". " . Str::limit($line->description, 30),
                    PaletteFormatter::money($line->total, $invoice->currency),
                ];
            }
        }

        // Add payment history if present
        if ($payments->isNotEmpty()) {
            $rows[] = ['', ''];
            $rows[] = ['{bold}Payments{/}', ''];
            foreach ($payments as $payment) {
                $rows[] = [
                    "  " . $payment->payment_date->format('M j') . " ({$payment->method})",
                    PaletteFormatter::money($payment->amount, $payment->currency),
                ];
            }
        }

        return [
            'data' => PaletteFormatter::table(
                headers: ['Field', 'Value'],
                rows: $rows,
                footer: "Invoice ID: {$invoice->id}"
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

    private function formatStatusLong(Invoice $invoice): string
    {
        $isOverdue = !in_array($invoice->status, [
            'paid',
            'cancelled',
            'draft',
        ]) && $invoice->due_date->isPast();

        if ($isOverdue) {
            $days = $invoice->due_date->diffInDays(now());
            return "{error}Overdue by {$days} days{/}";
        }

        return match ($invoice->status) {
            'draft' => '{secondary}Draft{/}',
            'sent' => '{warning}Sent{/}',
            'posted' => '{accent}Posted{/}',
            'overdue' => '{warning}Overdue{/}',
            'paid' => '{success}Paid in full{/}',
            'cancelled' => '{secondary}Cancelled{/}',
            default => $invoice->status,
        };
    }
}