<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Modules\Accounting\Domain\Payments\Actions\ReverseAllocationAction;
use App\Models\PaymentAllocation;

class PaymentAllocationReverse extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payment:allocation:reverse 
                            {allocation-id : UUID of the allocation to reverse}
                            {--reason= : Reason for the reversal}
                            {--refund-amount= : Refund amount (defaults to full allocated amount)}';

    /**
     * The console command description.
     */
    protected $description = 'Reverse a specific payment allocation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $allocationId = $this->argument('allocation-id');
            $reason = $this->option('reason') ?? $this->ask('What is the reason for this allocation reversal?');
            $refundAmount = $this->option('refund-amount');

            // Find the allocation with its relationships
            $allocation = PaymentAllocation::with(['payment', 'invoice'])
                ->findOrFail($allocationId);

            // Display allocation details
            $this->info("Allocation Details:");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Allocation ID', $allocation->id],
                    ['Payment #', $allocation->payment->payment_number],
                    ['Invoice #', $allocation->invoice?->invoice_number ?? 'N/A'],
                    ['Allocated Amount', $allocation->payment->currency->symbol . $allocation->allocated_amount],
                    ['Allocation Date', $allocation->allocation_date?->format('Y-m-d')],
                    ['Status', $allocation->status],
                ]
            );

            if ($refundAmount) {
                $this->warn("Partial refund amount: {$allocation->payment->currency->symbol}{$refundAmount}");
            }

            // Confirm the action
            if (!$this->confirm("Are you sure you want to reverse this allocation?")) {
                $this->info('Allocation reversal cancelled.');
                return 0;
            }

            // Validate allocation can be reversed
            if (!$allocation->canBeReversed()) {
                $this->error('This allocation cannot be reversed. Status: ' . $allocation->status);
                return 1;
            }

            // Execute allocation reversal
            $action = new ReverseAllocationAction();
            $result = $action->execute($allocationId, [
                'reason' => $reason,
                'refund_amount' => $refundAmount,
                'company_id' => $allocation->payment->company_id,
                'created_by_user_id' => auth()->id(),
            ]);

            $this->info('✅ Allocation reversed successfully!');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Allocation ID', $result['allocation_id']],
                    ['Reversal ID', $result['reversal_id']],
                    ['Refunded Amount', $allocation->payment->currency->symbol . $result['refunded_amount']],
                    ['Original Amount', $allocation->payment->currency->symbol . $result['original_amount']],
                    ['Invoice Balance Restored', $allocation->payment->currency->symbol . $result['invoice_balance_restored']],
                    ['Status', $result['status']],
                ]
            );

            // Show updated invoice status if applicable
            if ($allocation->invoice) {
                $this->info("Updated Invoice #{$allocation->invoice->invoice_number}:");
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Balance Due', $allocation->invoice->currency->symbol . $allocation->invoice->balance_due],
                        ['Status', $allocation->invoice->status],
                    ]
                );
            }

            return 0;

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("Error: Allocation not found");
            return 1;
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}