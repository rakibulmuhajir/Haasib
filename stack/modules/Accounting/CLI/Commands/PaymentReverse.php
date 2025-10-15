<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Modules\Accounting\Domain\Payments\Actions\ReversePaymentAction;
use Modules\Accounting\Domain\Payments\Actions\ReverseAllocationAction;
use App\Models\Payment;
use App\Models\PaymentAllocation;

class PaymentReverse extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payment:reverse 
                            {payment-id : UUID of the payment to reverse}
                            {--reason= : Reason for the reversal}
                            {--amount= : Amount to reverse (defaults to full amount)}
                            {--method=void : Reversal method (void|refund|chargeback)}
                            {--allocation-id= : Reverse specific allocation instead of entire payment}
                            {--refund-amount= : Refund amount for allocation reversal}';

    /**
     * The console command description.
     */
    protected $description = 'Reverse a payment or payment allocation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $paymentId = $this->argument('payment-id');
            $allocationId = $this->option('allocation-id');

            if ($allocationId) {
                return $this->reverseAllocation($paymentId, $allocationId);
            }

            return $this->reversePayment($paymentId);

        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Reverse an entire payment.
     */
    private function reversePayment(string $paymentId): int
    {
        $reason = $this->option('reason') ?? $this->ask('What is the reason for this reversal?');
        $amount = $this->option('amount');
        $method = $this->option('method');

        // Validate reversal method
        if (!in_array($method, ['void', 'refund', 'chargeback'])) {
            $this->error('Invalid reversal method. Must be: void, refund, or chargeback');
            return 1;
        }

        // Confirm the action
        $payment = Payment::findOrFail($paymentId);
        $this->info("Payment #{$payment->payment_number}");
        $this->info("Amount: {$payment->currency->symbol}{$payment->amount}");
        $this->info("Entity: {$payment->entity?->name}");
        $this->info("Status: {$payment->status_label}");

        if ($amount) {
            $this->warn("Partial reversal amount: {$payment->currency->symbol}{$amount}");
        }

        if (!$this->confirm("Are you sure you want to reverse this payment?")) {
            $this->info('Payment reversal cancelled.');
            return 0;
        }

        // Execute reversal
        $action = new ReversePaymentAction();
        $result = $action->execute($paymentId, [
            'reason' => $reason,
            'amount' => $amount,
            'method' => $method,
            'company_id' => $payment->company_id,
            'created_by_user_id' => auth()->id(),
        ]);

        $this->info('✅ Payment reversed successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['Payment ID', $result['payment_id']],
                ['Reversal ID', $result['reversal_id']],
                ['Reversed Amount', $payment->currency->symbol . $result['reversed_amount']],
                ['Original Amount', $payment->currency->symbol . $result['original_amount']],
                ['Status', $result['status']],
            ]
        );

        return 0;
    }

    /**
     * Reverse a specific payment allocation.
     */
    private function reverseAllocation(string $paymentId, string $allocationId): int
    {
        $reason = $this->option('reason') ?? $this->ask('What is the reason for this allocation reversal?');
        $refundAmount = $this->option('refund-amount');

        // Validate allocation exists and belongs to payment
        $allocation = PaymentAllocation::where('payment_id', $paymentId)
            ->findOrFail($allocationId);

        $payment = $allocation->payment;
        $invoice = $allocation->invoice;

        $this->info("Payment #{$payment->payment_number}");
        $this->info("Invoice #{$invoice?->invoice_number}");
        $this->info("Allocated Amount: {$payment->currency->symbol}{$allocation->allocated_amount}");

        if ($refundAmount) {
            $this->warn("Partial refund amount: {$payment->currency->symbol}{$refundAmount}");
        }

        if (!$this->confirm("Are you sure you want to reverse this allocation?")) {
            $this->info('Allocation reversal cancelled.');
            return 0;
        }

        // Execute allocation reversal
        $action = new ReverseAllocationAction();
        $result = $action->execute($allocationId, [
            'reason' => $reason,
            'refund_amount' => $refundAmount,
            'company_id' => $payment->company_id,
            'created_by_user_id' => auth()->id(),
        ]);

        $this->info('✅ Allocation reversed successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['Allocation ID', $result['allocation_id']],
                ['Reversal ID', $result['reversal_id']],
                ['Refunded Amount', $payment->currency->symbol . $result['refunded_amount']],
                ['Original Amount', $payment->currency->symbol . $result['original_amount']],
                ['Invoice Balance Restored', $payment->currency->symbol . $result['invoice_balance_restored']],
                ['Status', $result['status']],
            ]
        );

        return 0;
    }
}