<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Console\Command;

class PaymentAllocate extends Command
{
    protected $signature = 'payment:allocate 
                        {payment : Payment ID or number}
                        {--invoices= : Comma-separated invoice IDs for manual allocation}
                        {--amounts= : Comma-separated allocation amounts matching invoice IDs}
                        {--strategy= : Automatic allocation strategy (fifo, proportional, overdue_first, largest_first)}
                        {--auto : Enable automatic allocation if possible}
                        {--force : Force allocation even with warnings}
                        {--json : Output results in JSON format}
                        {--dry-run : Preview allocation without executing}';

    protected $description = 'Allocate payment amount across multiple invoices with various strategies';

    public function handle(PaymentService $paymentService): int
    {
        $this->info('💰 Payment Allocation Command');
        $this->info('===========================');

        try {
            // Get company from context
            $company = $this->getCompanyFromContext();
            $user = $this->getUserFromContext();

            // Find payment
            $payment = $this->findPayment($this->argument('payment'), $company);

            $this->displayPaymentInfo($payment);

            // Check if payment can be allocated
            if ($payment->remaining_amount <= 0) {
                $this->error('❌ Payment has no remaining amount to allocate');

                return self::FAILURE;
            }

            $this->info("Available amount: {$payment->remaining_amount}");

            // Determine allocation method
            $allocationMethod = $this->determineAllocationMethod();

            if ($allocationMethod === 'automatic') {
                return $this->handleAutomaticAllocation($payment, $paymentService, $user);
            } else {
                return $this->handleManualAllocation($payment, $paymentService, $user);
            }

        } catch (\Throwable $e) {
            $this->error('❌ Allocation Error: '.$e->getMessage());
            $this->line('Stack trace:', 'error');
            $this->line($e->getTraceAsString(), 'error');

            return self::FAILURE;
        }
    }

    private function handleAutomaticAllocation(Payment $payment, PaymentService $paymentService, User $user): int
    {
        $strategy = $this->option('strategy') ?? 'fifo';

        if (! $this->option('force')) {
            $this->info("\n🤖 Automatic Allocation Preview");
            $this->info("Strategy: {$strategy}");
            $this->info('Available unpaid invoices: '.$this->getUnpaidInvoicesCount($payment));
        }

        if ($this->option('dry-run')) {
            $this->line("\n📋 DRY RUN MODE - No allocations will be executed");

            return self::SUCCESS;
        }

        // Confirm allocation
        if (! $this->option('auto') && ! $this->confirm('Proceed with automatic allocation?', true)) {
            $this->info('Automatic allocation cancelled.');

            return self::SUCCESS;
        }

        $this->info('Processing automatic allocation...');

        $startTime = microtime(true);
        $results = $paymentService->processPaymentCompletion($payment, $user, [
            'strategy' => $strategy,
            'strategy_options' => $this->getStrategyOptions($strategy),
        ]);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->displayAllocationResults($results, $duration);

        return self::SUCCESS;
    }

    private function handleManualAllocation(Payment $payment, PaymentService $paymentService, User $user): int
    {
        $invoicesOption = $this->option('invoices');
        $amountsOption = $this->option('amounts');

        if (! $invoicesOption) {
            $this->error('❌ Manual allocation requires --invoices parameter');
            $this->line('Usage: --invoices=1,2,3 --amounts=100,200,300');

            return self::FAILURE;
        }

        // Parse invoice IDs
        $invoiceIds = array_map('trim', explode(',', $invoicesOption));
        $allocations = [];

        // Parse amounts if provided
        if ($amountsOption) {
            $amounts = array_map('trim', explode(',', $amountsOption));

            if (count($amounts) !== count($invoiceIds)) {
                $this->error('❌ Number of amounts must match number of invoices');

                return self::FAILURE;
            }

            foreach ($invoiceIds as $index => $invoiceId) {
                $allocations[] = [
                    'invoice_id' => $invoiceId,
                    'amount' => (float) $amounts[$index],
                ];
            }
        } else {
            // Interactive amount entry
            foreach ($invoiceIds as $invoiceId) {
                $invoice = $this->findInvoice($invoiceId, $payment->company);
                $maxAmount = min($invoice->balance_due, $payment->remaining_amount);

                $amount = $this->ask("Enter amount for invoice {$invoice->invoice_number} (max: {$maxAmount}):");
                $allocations[] = [
                    'invoice_id' => $invoiceId,
                    'amount' => (float) $amount,
                ];
            }
        }

        if ($this->option('dry-run')) {
            $this->line("\n📋 DRY RUN MODE - No allocations will be executed");
            $this->displayManualAllocationPreview($allocations);

            return self::SUCCESS;
        }

        // Validate allocations
        $validation = $this->validateManualAllocations($allocations, $payment);
        if (! $validation['valid']) {
            $this->error('❌ Allocation validation failed:');
            foreach ($validation['errors'] as $error) {
                $this->line("  • {$error}");
            }

            return self::FAILURE;
        }

        // Confirm allocation
        if (! $this->option('force') && ! $this->confirm('Proceed with manual allocation?', true)) {
            $this->info('Manual allocation cancelled.');

            return self::SUCCESS;
        }

        $this->info('Processing manual allocation...');

        $startTime = microtime(true);
        $results = $paymentService->allocatePaymentAcrossInvoices(
            $payment,
            $allocations,
            $user,
            'manual'
        );
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->displayAllocationResults($results, $duration);

        return self::SUCCESS;
    }

    private function determineAllocationMethod(): string
    {
        if ($this->option('strategy') || $this->option('auto')) {
            return 'automatic';
        }

        if ($this->option('invoices')) {
            return 'manual';
        }

        // Interactive mode
        $choice = $this->choice(
            'How would you like to allocate this payment?',
            [
                'automatic' => 'Use automatic allocation strategy',
                'manual' => 'Specify invoices and amounts manually',
            ],
            'automatic'
        );

        return $choice;
    }

    private function getUnpaidInvoicesCount(Payment $payment): int
    {
        return Invoice::where('company_id', $payment->company_id)
            ->where('customer_id', $payment->customer_id)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0)
            ->count();
    }

    private function getStrategyOptions(string $strategy): array
    {
        return match ($strategy) {
            'fifo' => [],
            'proportional' => [],
            'overdue_first' => [],
            'largest_first' => [],
            'percentage_based' => [
                'percentages' => [25, 25, 25, 25], // Default equal distribution
            ],
            'equal_distribution' => [],
            'custom_priority' => [],
            default => [],
        };
    }

    private function displayPaymentInfo(Payment $payment): void
    {
        $this->info("Payment: {$payment->payment_number}");
        $this->info("Customer: {$payment->customer->name}");
        $this->info('Amount: '.number_format($payment->amount, 2));
        $this->info("Status: {$payment->status}");
        $this->info("Date: {$payment->payment_date->format('Y-m-d')}");
        $this->line(str_repeat('-', 50));
    }

    private function displayAllocationResults(array $results, float $duration): void
    {
        $this->info("\n✅ Allocation completed successfully!");
        $this->info("⏱️  Completed in: {$duration}ms");

        if (isset($results['remaining_amount'])) {
            $this->info("💰 Remaining amount: {$results['remaining_amount']}");
        }

        if (isset($results['is_fully_allocated'])) {
            $this->info('✅ Payment fully allocated: '.($results['is_fully_allocated'] ? 'Yes' : 'No'));
        }

        if (! empty($results['allocations'])) {
            $this->info("\nAllocation Details:");
            foreach ($results['allocations'] as $allocation) {
                $this->line("  • Invoice {$allocation['invoice_number']}: ".number_format($allocation['allocated_amount'], 2));
            }
        }

        if ($this->option('json')) {
            $this->line("\n".json_encode($results, JSON_PRETTY_PRINT));
        }
    }

    private function displayManualAllocationPreview(array $allocations): void
    {
        $this->info("\n📋 Manual Allocation Preview:");
        $totalAmount = 0;

        foreach ($allocations as $allocation) {
            $invoice = $this->findInvoice($allocation['invoice_id'], null);
            $this->line("  • Invoice {$invoice->invoice_number}: ".number_format($allocation['amount'], 2));
            $totalAmount += $allocation['amount'];
        }

        $this->info('Total to allocate: '.number_format($totalAmount, 2));
    }

    private function validateManualAllocations(array $allocations, Payment $payment): array
    {
        $errors = [];
        $totalAmount = 0;

        foreach ($allocations as $index => $allocation) {
            try {
                $invoice = $this->findInvoice($allocation['invoice_id'], $payment->company);

                // Check if invoice belongs to same customer
                if ($invoice->customer_id !== $payment->customer_id) {
                    $errors[] = "Invoice {$invoice->invoice_number} belongs to different customer";

                    continue;
                }

                // Check allocation amount
                if ($allocation['amount'] <= 0) {
                    $errors[] = "Invoice {$invoice->invoice_number}: allocation amount must be greater than 0";

                    continue;
                }

                if ($allocation['amount'] > $invoice->balance_due) {
                    $errors[] = "Invoice {$invoice->invoice_number}: allocation amount ({$allocation['amount']}) exceeds balance due ({$invoice->balance_due})";

                    continue;
                }

                $totalAmount += $allocation['amount'];

            } catch (\Exception $e) {
                $errors[] = "Invoice {$allocation['invoice_id']}: ".$e->getMessage();
            }
        }

        if ($totalAmount > $payment->remaining_amount) {
            $errors[] = "Total allocation amount ({$totalAmount}) exceeds remaining payment amount ({$payment->remaining_amount})";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'total_amount' => $totalAmount,
        ];
    }

    private function findPayment(string $identifier, Company $company): Payment
    {
        $query = Payment::where('company_id', $company->id);

        // Try by UUID
        if ($this->isUuid($identifier)) {
            $payment = $query->where('id', $identifier)->first();
            if ($payment) {
                return $payment;
            }
        }

        // Try by payment number
        $payment = $query->where('payment_number', $identifier)->first();
        if ($payment) {
            return $payment;
        }

        throw new \InvalidArgumentException("Payment '{$identifier}' not found");
    }

    private function findInvoice(string $identifier, ?Company $company): Invoice
    {
        $query = $company ? Invoice::where('company_id', $company->id) : Invoice::query();

        // Try by UUID
        if ($this->isUuid($identifier)) {
            $invoice = $query->where('id', $identifier)->first();
            if ($invoice) {
                return $invoice;
            }
        }

        // Try by invoice number
        $invoice = $query->where('invoice_number', $identifier)->first();
        if ($invoice) {
            return $invoice;
        }

        throw new \InvalidArgumentException("Invoice '{$identifier}' not found");
    }

    private function getCompanyFromContext(): Company
    {
        // This would be implemented based on your context system
        // For now, return the first company
        $company = Company::first();
        if (! $company) {
            throw new \RuntimeException('No company found. Please specify --company=<id>');
        }

        return $company;
    }

    private function getUserFromContext(): User
    {
        // This would be implemented based on your auth system
        // For now, return the first user
        $user = User::first();
        if (! $user) {
            throw new \RuntimeException('No authenticated user found');
        }

        return $user;
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }
}
