<?php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\InvoiceLineItem;
use App\Modules\Accounting\Models\Transaction;
use Illuminate\Support\Facades\DB;

class DeleteAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|uuid',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::INVOICE_DELETE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        return DB::transaction(function () use ($params, $company) {
            // Get the invoice
            $invoice = Invoice::where('id', $params['id'])
                ->where('company_id', $company->id)
                ->firstOrFail();

            // Prevent deletion of invoices with payments
            if ($invoice->paid_amount > 0) {
                throw new \Exception('Cannot delete invoice that has payments applied.');
            }

            // Prevent deletion of posted invoices
            if (in_array($invoice->status, ['posted', 'paid', 'sent', 'viewed', 'overdue', 'partial'], true)) {
                throw new \Exception('Cannot delete ' . $invoice->status . ' invoice. Void it to reverse the GL entry.');
            }

            $postedTx = null;
            if ($invoice->transaction_id) {
                $postedTx = Transaction::where('company_id', $company->id)
                    ->where('id', $invoice->transaction_id)
                    ->whereNull('deleted_at')
                    ->first();
            }

            if (! $postedTx) {
                $postedTx = Transaction::where('company_id', $company->id)
                    ->where('reference_type', 'acct.invoices')
                    ->where('reference_id', $invoice->id)
                    ->whereNull('reversal_of_id')
                    ->whereNull('deleted_at')
                    ->orderByDesc('created_at')
                    ->first();
            }

            if ($postedTx) {
                throw new \Exception('Cannot delete a posted invoice. Void it to reverse the GL entry.');
            }

            $invoiceNumber = $invoice->invoice_number;
            $customerName = $invoice->customer->name;

            // Delete line items first
            InvoiceLineItem::where('invoice_id', $invoice->id)->delete();

            // Delete the invoice
            $invoice->delete();

            return [
                'message' => "Invoice {$invoiceNumber} deleted for {$customerName}",
                'data' => [
                    'id' => $invoice->id,
                    'number' => $invoiceNumber,
                    'customer' => $customerName,
                ],
                'redirect' => "/{$company->slug}/invoices",
            ];
        });
    }
}
