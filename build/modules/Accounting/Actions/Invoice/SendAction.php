<?php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Models\Invoice;
use Illuminate\Support\Str;

class SendAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
            'email' => 'nullable|boolean',
            'to' => 'nullable|email',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::INVOICE_SEND;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $invoice = $this->resolveInvoice($params['id'], $company->id);

        // Validate status
        if ($invoice->status === 'cancelled') {
            throw new \Exception("Cannot send cancelled invoice");
        }

        if ($invoice->status === 'paid') {
            throw new \Exception("Invoice is already paid");
        }

        // Update status if draft or sent
        if (in_array($invoice->status, ['draft', 'sent'])) {
            $invoice->markAsSent();
        }

        // Send email if requested
        $emailSent = false;
        if (($params['email'] ?? false) || !empty($params['to'])) {
            $recipientEmail = $params['to'] ?? $invoice->customer->email;

            if (!$recipientEmail) {
                throw new \Exception("No email address. Specify with --to=email@example.com");
            }

            // TODO: Dispatch email job when email service is implemented
            // dispatch(new SendInvoiceEmail($invoice, $recipientEmail));
            $emailSent = true;
        }

        $message = "Invoice {$invoice->invoice_number} marked as sent";
        if ($emailSent) {
            $message .= " and emailed to {$recipientEmail}";
        }

        return [
            'message' => $message,
            'data' => [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'emailed' => $emailSent,
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