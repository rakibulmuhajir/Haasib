<?php

namespace Modules\Accounting\CLI\Commands;

use App\Services\ContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PaymentRecord extends Command
{
    protected $signature = 'acc:payment:record
                            {invoice : Invoice number or ID}
                            {amount : Payment amount}
                            {--method= : Payment method (cash, bank, card, other)}
                            {--date= : Payment date (Y-m-d, defaults to today)}
                            {--reference= : Payment reference number}
                            {--notes= : Payment notes}';

    protected $description = 'Record a payment for an invoice (Accounting module)';

    public function handle(ContextService $contextService): int
    {
        $invoice = $this->argument('invoice');
        $amount = (float) $this->argument('amount');
        $method = $this->option('method') ?: 'cash';
        $date = $this->option('date') ?: now()->format('Y-m-d');
        $reference = $this->option('reference');
        $notes = $this->option('notes');

        // Check for user context
        $currentUser = $contextService->getCurrentUser();
        if (! $currentUser) {
            $this->error('No active user context. Please set user context first.');

            return 1;
        }

        // Check for company context
        $currentCompany = $contextService->getCurrentCompany();
        if (! $currentCompany) {
            $this->error('No active company context. Please set company context first.');

            return 1;
        }

        // Validate payment method
        $validMethods = ['cash', 'bank', 'card', 'other'];
        if (! in_array($method, $validMethods)) {
            $this->error('Invalid payment method. Use one of: '.implode(', ', $validMethods));

            return 1;
        }

        // Validate amount
        if ($amount <= 0) {
            $this->error('Amount must be greater than 0.');

            return 1;
        }

        // Validate date
        if (! strtotime($date)) {
            $this->error('Invalid payment date format. Use Y-m-d format.');

            return 1;
        }

        // Find the invoice
        $invoiceRecord = DB::table('acct.invoices')
            ->where('company_id', $currentCompany->id)
            ->where(function ($query) use ($invoice) {
                $query->where('invoice_number', $invoice)
                    ->orWhere('id', $invoice);
            })
            ->first();

        if (! $invoiceRecord) {
            $this->error("Invoice '{$invoice}' not found.");

            return 1;
        }

        // Check if invoice is already paid
        if ($invoiceRecord->status === 'paid') {
            $this->warn("Invoice '{$invoiceRecord->invoice_number}' is already fully paid.");

            return 0;
        }

        // Check if payment amount exceeds due amount
        $remainingBalance = $invoiceRecord->total_amount - $invoiceRecord->paid_amount;
        if ($amount > $remainingBalance) {
            $this->error("Payment amount ({$amount}) exceeds remaining balance ({$remainingBalance}).");

            return 1;
        }

        try {
            DB::beginTransaction();

            // Generate payment receipt number
            $receiptNumber = 'PAY-'.date('Y').'-'.str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Create payment record
            $paymentId = DB::table('acct.payments')->insertGetId([
                'company_id' => $currentCompany->id,
                'invoice_id' => $invoiceRecord->id,
                'payment_number' => $receiptNumber,
                'amount' => $amount,
                'payment_method' => $method,
                'payment_date' => $date,
                'reference_number' => $reference,
                'notes' => $notes,
                'currency' => $currentCompany->base_currency,
                'status' => 'completed',
                'created_by_user_id' => $currentUser->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update invoice paid amount
            $newPaidAmount = $invoiceRecord->paid_amount + $amount;
            $newStatus = ($newPaidAmount >= $invoiceRecord->total_amount) ? 'paid' : 'partial';

            DB::table('acct.invoices')
                ->where('id', $invoiceRecord->id)
                ->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => $newStatus,
                    'updated_at' => now(),
                ]);

            DB::commit();

            $this->info('✅ Payment recorded successfully!');
            $this->info("  Receipt Number: {$receiptNumber}");
            $this->info("  Invoice: {$invoiceRecord->invoice_number}");
            $this->info("  Payment Amount: {$currentCompany->base_currency} ".number_format($amount, 2));
            $this->info('  Payment Method: '.ucfirst($method));
            $this->info("  Payment Date: {$date}");
            if ($reference) {
                $this->info("  Reference: {$reference}");
            }
            $this->info("  Previous Paid: {$currentCompany->base_currency} ".number_format($invoiceRecord->paid_amount, 2));
            $this->info("  Total Paid: {$currentCompany->base_currency} ".number_format($newPaidAmount, 2));
            $this->info("  Remaining Balance: {$currentCompany->base_currency} ".number_format($invoiceRecord->total_amount - $newPaidAmount, 2));
            $this->info('  Status: '.ucfirst($newStatus));

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to record payment: {$e->getMessage()}");

            return 1;
        }
    }
}
