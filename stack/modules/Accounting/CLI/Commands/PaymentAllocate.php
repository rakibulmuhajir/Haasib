<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentAllocate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:allocate 
                            {payment : Payment number or ID}
                            {--invoices= : Comma-separated invoice numbers (required if not --auto)}
                            {--amounts= : Comma-separated allocation amounts (required if not --auto)}
                            {--auto : Use auto-allocation instead of manual}
                            {--strategy=fifo : Allocation strategy (fifo, proportional, overdue_first, largest_first, percentage_based, custom_priority)}
                            {--dry-run : Show what would be allocated without doing it}
                            {--notes= : Allocation notes}
                            {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Allocate a payment to invoices manually or automatically';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Set company context from environment or prompt
            $companyId = $this->getCompanyId();
            if (! $companyId) {
                $this->error('Company context is required. Set APP_COMPANY_ID environment variable or use --company option.');

                return 1;
            }

            DB::statement('SET app.current_company = ?', [$companyId]);

            // Get payment ID
            $paymentId = $this->getPaymentId($this->argument('payment'));
            if (! $paymentId) {
                $this->error('Payment not found: '.$this->argument('payment'));

                return 1;
            }

            // Check if using auto-allocation
            if ($this->option('auto')) {
                return $this->handleAutoAllocation($paymentId, $companyId);
            } else {
                return $this->handleManualAllocation($paymentId, $companyId);
            }

        } catch (\Throwable $e) {
            $this->error('Allocation failed: '.$e->getMessage());

            if ($this->option('format') === 'json') {
                $this->line(json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], JSON_PRETTY_PRINT));
            }

            return 1;
        }
    }

    /**
     * Handle manual allocation.
     */
    private function handleManualAllocation(string $paymentId, string $companyId): int
    {
        // Parse invoice numbers and amounts
        $invoices = $this->parseOptionList($this->option('invoices'));
        $amounts = $this->parseOptionList($this->option('amounts'));

        if (empty($invoices) || empty($amounts)) {
            $this->error('Both --invoices and --amounts are required for manual allocation');

            return 1;
        }

        if (count($invoices) !== count($amounts)) {
            $this->error('Number of invoices and amounts must match');

            return 1;
        }

        // Build allocations array
        $allocations = [];
        foreach ($invoices as $i => $invoiceNumber) {
            $invoiceId = $this->getInvoiceId($invoiceNumber, $companyId);
            if (! $invoiceId) {
                $this->error('Invoice not found: '.$invoiceNumber);

                return 1;
            }

            $allocations[] = [
                'invoice_id' => $invoiceId,
                'amount' => (float) $amounts[$i],
                'notes' => $this->option('notes'),
            ];
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run - proposed allocations:');
            $this->table(
                ['Invoice Number', 'Amount', 'Notes'],
                array_map(function ($alloc) use ($invoices) {
                    return [
                        $invoices[array_search($alloc['invoice_id'], array_column($allocations, 'invoice_id'))],
                        number_format($alloc['amount'], 2),
                        $alloc['notes'] ?? '-',
                    ];
                }, $allocations)
            );

            return 0;
        }

        // Validate allocations
        $validator = Validator::make(['allocations' => $allocations], [
            'allocations' => 'required|array|min:1',
            'allocations.*.invoice_id' => 'required|uuid|exists:pgsql.acct.invoices,invoice_id',
            'allocations.*.amount' => 'required|numeric|min:0.01',
            'allocations.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error('  - '.$error);
            }

            return 1;
        }

        // Dispatch through command bus
        $this->info('Allocating payment...');
        $result = Bus::dispatch('payment.allocate', [
            'payment_id' => $paymentId,
            'allocations' => $allocations,
        ]);

        // Format output
        if ($this->option('format') === 'json') {
            $output = [
                'success' => true,
                'payment_id' => $result['payment_id'],
                'allocations_created' => $result['allocations_created'],
                'total_allocated' => $result['total_allocated'],
                'remaining_amount' => $result['remaining_amount'],
                'payment_status' => $result['payment_status'],
                'is_fully_allocated' => $result['is_fully_allocated'],
                'unallocated_cash_created' => $result['unallocated_cash_created'] ?? false,
                'message' => $result['message'],
            ];

            // Add discount information if available
            if (isset($result['total_discount_applied']) && $result['total_discount_applied'] > 0) {
                $output['total_discount_applied'] = $result['total_discount_applied'];
            }

            // Add receipt reference
            $output['receipt_url'] = "/api/payments/{$result['payment_id']}/receipt";
            $output['receipt_number'] = 'R-'.$result['payment_id'] ?? '';

            $this->line(json_encode($output, JSON_PRETTY_PRINT));
        } else {
            $this->info('✓ Payment allocated successfully');

            $tableData = [
                ['Payment ID', $result['payment_id']],
                ['Allocations Created', $result['allocations_created']],
                ['Total Allocated', number_format($result['total_allocated'], 2)],
                ['Remaining Amount', number_format($result['remaining_amount'], 2)],
                ['Payment Status', $result['payment_status']],
                ['Fully Allocated', $result['is_fully_allocated'] ? 'Yes' : 'No'],
            ];

            // Add discount information if available
            if (isset($result['total_discount_applied']) && $result['total_discount_applied'] > 0) {
                $tableData[] = ['Total Discount Applied', number_format($result['total_discount_applied'], 2)];
            }

            // Add unallocated cash info
            if ($result['unallocated_cash_created'] ?? false) {
                $tableData[] = ['Unallocated Cash Created', 'Yes'];
            }

            $this->table(['Field', 'Value'], $tableData);

            // Show receipt information
            $this->info('');
            $this->info('Receipt Information:');
            $this->line('  Receipt Number: R-'.($result['payment_id'] ?? ''));
            $this->line('  Download JSON: GET /api/payments/'.($result['payment_id'] ?? '').'/receipt?format=json');
            $this->line('  Download PDF:  GET /api/payments/'.($result['payment_id'] ?? '').'/receipt?format=pdf');
        }

        return 0;
    }

    /**
     * Handle auto-allocation.
     */
    private function handleAutoAllocation(string $paymentId, string $companyId): int
    {
        $strategy = $this->option('strategy');
        $options = []; // Could add strategy-specific options here

        if ($this->option('dry-run')) {
            $this->info('Dry run - would auto-allocate with strategy: '.$strategy);

            return 0;
        }

        // Dispatch through command bus
        $this->info('Auto-allocating payment...');
        $result = Bus::dispatch('payment.allocate.auto', [
            'payment_id' => $paymentId,
            'strategy' => $strategy,
            'options' => $options,
        ]);

        // Format output
        if ($this->option('format') === 'json') {
            $this->line(json_encode([
                'success' => true,
                'payment_id' => $result['payment_id'],
                'strategy_used' => $result['strategy_used'],
                'allocations_created' => $result['allocations_created'],
                'total_allocated' => $result['total_allocated'],
                'remaining_amount' => $result['remaining_amount'],
                'payment_status' => $result['payment_status'],
                'is_fully_allocated' => $result['is_fully_allocated'],
                'message' => $result['message'],
            ], JSON_PRETTY_PRINT));
        } else {
            $this->info('✓ Auto-allocation completed successfully');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Payment ID', $result['payment_id']],
                    ['Strategy Used', $result['strategy_used']],
                    ['Allocations Created', $result['allocations_created']],
                    ['Total Allocated', number_format($result['total_allocated'], 2)],
                    ['Remaining Amount', number_format($result['remaining_amount'], 2)],
                    ['Payment Status', $result['payment_status']],
                    ['Fully Allocated', $result['is_fully_allocated'] ? 'Yes' : 'No'],
                ]
            );
        }

        return 0;
    }

    /**
     * Get company ID from environment.
     */
    private function getCompanyId(): ?string
    {
        return $_ENV['APP_COMPANY_ID'] ?? null;
    }

    /**
     * Get payment ID from number or ID.
     */
    private function getPaymentId(string $paymentInput): ?string
    {
        // Try as UUID first
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $paymentInput)) {
            return DB::table('acct.payments')
                ->where('payment_id', $paymentInput)
                ->value('payment_id');
        }

        // Try as payment number
        return DB::table('acct.payments')
            ->where('payment_number', $paymentInput)
            ->value('payment_id');
    }

    /**
     * Get invoice ID from number.
     */
    private function getInvoiceId(string $invoiceNumber, string $companyId): ?string
    {
        return DB::table('acct.invoices')
            ->where('invoice_number', $invoiceNumber)
            ->where('company_id', $companyId)
            ->value('invoice_id');
    }

    /**
     * Parse comma-separated option values.
     */
    private function parseOptionList(?string $option): array
    {
        if (! $option) {
            return [];
        }

        return array_map('trim', explode(',', $option));
    }
}
