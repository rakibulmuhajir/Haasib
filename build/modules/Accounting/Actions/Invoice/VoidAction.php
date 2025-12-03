<?php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;
use App\Support\PaletteFormatter;
use Illuminate\Support\Str;

class VoidAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::INVOICE_VOID;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $invoice = $this->resolveInvoice($params['id'], $company->id);

        // Validate current status
        if ($invoice->status === 'cancelled') {
            throw new \Exception("Invoice is already cancelled");
        }

        if ($invoice->status === 'paid') {
            throw new \Exception("Cannot void a paid invoice. Create a credit note instead.");
        }

        // Check for payments
        $amountPaid = $invoice->total_amount - $invoice->balance_due;
        if ($amountPaid > 0) {
            throw new \Exception(
                "Invoice has payments totaling " .
                PaletteFormatter::money($amountPaid, $invoice->currency) .
                ". Refund payments first."
            );
        }

        // Update invoice status
        $invoice->update([
            'status' => 'cancelled',
            'balance_due' => 0,
            'payment_status' => 'cancelled',
            'cancelled_at' => now(),
            'notes' => ($invoice->notes ?? '') .
                ($params['reason'] ? "\n\nVoid reason: {$params['reason']}" : ''),
        ]);

        return [
            'message' => "Invoice {$invoice->invoice_number} cancelled",
            'data' => [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'reason' => $params['reason'] ?? null,
            ],
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
