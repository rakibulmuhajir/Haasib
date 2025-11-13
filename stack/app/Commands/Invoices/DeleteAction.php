<?php

namespace App\Commands\Invoices;

use App\Commands\BaseCommand;
use App\Services\ServiceContext;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Exception;

class DeleteAction extends BaseCommand
{
    public function handle(): bool
    {
        return $this->executeInTransaction(function () {
            $companyId = $this->context->getCompanyId();
            $invoiceId = $this->getValue('id');
            
            if (!$companyId || !$invoiceId) {
                throw new Exception('Invalid service context: missing company or invoice ID');
            }

            // Find and validate invoice
            $invoice = Invoice::where('id', $invoiceId)
                ->where('company_id', $companyId)
                ->firstOrFail();

            if ($invoice->status !== 'draft') {
                throw new Exception('Only draft invoices can be deleted');
            }

            $invoiceId = $invoice->id;
            $invoiceNumber = $invoice->invoice_number;

            // Delete invoice (this will cascade delete line items)
            $invoice->delete();

            $this->audit('invoice.deleted', [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
            ]);

            return true;
        });
    }
}