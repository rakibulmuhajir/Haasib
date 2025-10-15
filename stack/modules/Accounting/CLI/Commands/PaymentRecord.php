<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Accounting\Domain\Payments\Actions\RecordPaymentAction;

class PaymentRecord extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:record 
                            {customer : Customer ID or name}
                            {amount : Payment amount}
                            {--method=bank_transfer : Payment method (cash, bank_transfer, card, cheque, other)}
                            {--date= : Payment date (Y-m-d, defaults to today)}
                            {--reference= : Reference number}
                            {--notes= : Payment notes}
                            {--auto-allocate : Auto-allocate to outstanding invoices}
                            {--strategy=fifo : Allocation strategy for auto-allocation}
                            {--format=table : Output format (table, json)}
                            {--currency= : Currency code (defaults to company base currency)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Record a customer payment receipt';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Set company context from environment or prompt
            $companyId = $this->getCompanyId();
            if (!$companyId) {
                $this->error('Company context is required. Set APP_COMPANY_ID environment variable or use --company option.');
                return 1;
            }

            DB::statement("SET app.current_company = ?", [$companyId]);

            // Get customer
            $customerId = $this->getCustomerId($this->argument('customer'));
            if (!$customerId) {
                $this->error('Customer not found: ' . $this->argument('customer'));
                return 1;
            }

            // Get currency
            $currencyCode = $this->option('currency') ?? $this->getDefaultCurrency($companyId);
            $currencyId = $this->getCurrencyId($currencyCode, $companyId);
            if (!$currencyId) {
                $this->error('Currency not found: ' . $currencyCode);
                return 1;
            }

            // Prepare payment data
            $paymentData = [
                'entity_id' => $customerId,
                'amount' => (float) $this->argument('amount'),
                'currency_id' => $currencyId,
                'payment_method' => $this->option('method'),
                'payment_date' => $this->option('date') ?? now()->toDateString(),
                'reference_number' => $this->option('reference'),
                'notes' => $this->option('notes'),
                'auto_allocate' => $this->option('auto-allocate'),
                'allocation_strategy' => $this->option('auto-allocate') ? $this->option('strategy') : null,
                'allocation_options' => [],
                'company_id' => $companyId,
                'created_by_user_id' => $this->getCurrentUserId(),
            ];

            // Validate payment data
            $validator = Validator::make($paymentData, [
                'entity_id' => 'required|uuid',
                'amount' => 'required|numeric|min:0.01',
                'currency_id' => 'required|uuid',
                'payment_method' => 'required|string|in:cash,bank_transfer,card,cheque,other',
                'payment_date' => 'required|date',
                'reference_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
                'auto_allocate' => 'boolean',
                'allocation_strategy' => 'nullable|string|in:fifo,proportional,overdue_first,largest_first,percentage_based,custom_priority',
            ]);

            if ($validator->fails()) {
                $this->error('Validation failed:');
                foreach ($validator->errors()->all() as $error) {
                    $this->error('  - ' . $error);
                }
                return 1;
            }

            // Dispatch through command bus
            $this->info('Recording payment...');
            $result = Bus::dispatch('payment.create', $paymentData);

            // Format output
            if ($this->option('format') === 'json') {
                $output = [
                    'success' => true,
                    'payment_id' => $result['payment_id'],
                    'payment_number' => $result['payment_number'],
                    'status' => $result['status'],
                    'amount' => $result['amount'],
                    'currency' => $result['currency'] ?? 'USD',
                    'message' => $result['message'],
                ];

                // Add allocation information if available
                if (isset($result['total_allocated'])) {
                    $output['total_allocated'] = $result['total_allocated'];
                    $output['remaining_amount'] = $result['remaining_amount'] ?? 0;
                    $output['unallocated_cash_created'] = ($result['remaining_amount'] ?? 0) > 0;
                }

                // Add receipt reference
                $output['receipt_url'] = "/api/payments/{$result['payment_id']}/receipt";
                $output['receipt_number'] = 'R-' . $result['payment_number'];

                $this->line(json_encode($output, JSON_PRETTY_PRINT));
            } else {
                $this->info('✓ Payment recorded successfully');
                
                $tableData = [
                    ['Payment ID', $result['payment_id']],
                    ['Payment Number', $result['payment_number']],
                    ['Status', $result['status']],
                    ['Amount', number_format($result['amount'], 2)],
                    ['Method', $this->option('method')],
                    ['Date', $this->option('date') ?? now()->toDateString()],
                    ['Reference', $this->option('reference') ?? '-'],
                    ['Auto Allocate', $this->option('auto-allocate') ? 'Yes' : 'No'],
                ];

                // Add allocation info if available
                if (isset($result['total_allocated'])) {
                    $tableData[] = ['Total Allocated', number_format($result['total_allocated'], 2)];
                    $tableData[] = ['Remaining Amount', number_format($result['remaining_amount'] ?? 0, 2)];
                    if (($result['remaining_amount'] ?? 0) > 0) {
                        $tableData[] = ['Unallocated Cash', 'Yes'];
                    }
                }

                $this->table(['Field', 'Value'], $tableData);
                
                // Show receipt information
                $this->info('');
                $this->info('Receipt Information:');
                $this->line('  Receipt Number: R-' . $result['payment_number']);
                $this->line('  Download JSON: GET /api/payments/' . $result['payment_id'] . '/receipt?format=json');
                $this->line('  Download PDF:  GET /api/payments/' . $result['payment_id'] . '/receipt?format=pdf');
            }

            return 0;

        } catch (\Throwable $e) {
            $this->error('Failed to record payment: ' . $e->getMessage());
            
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
     * Get company ID from environment or prompt.
     */
    private function getCompanyId(): ?string
    {
        return $_ENV['APP_COMPANY_ID'] ?? null;
    }

    /**
     * Get customer ID from name or ID.
     */
    private function getCustomerId(string $customerInput): ?string
    {
        // Try as UUID first
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $customerInput)) {
            return DB::table('hrm.customers')
                ->where('customer_id', $customerInput)
                ->value('customer_id');
        }

        // Try as customer name
        return DB::table('hrm.customers')
            ->where('name', 'ILIKE', $customerInput)
            ->value('customer_id');
    }

    /**
     * Get currency ID from code.
     */
    private function getCurrencyId(string $currencyCode, string $companyId): ?string
    {
        return DB::table('public.currencies')
            ->join('auth.company_currencies', 'public.currencies.id', '=', 'auth.company_currencies.currency_id')
            ->where('public.currencies.code', strtoupper($currencyCode))
            ->where('auth.company_currencies.company_id', $companyId)
            ->value('public.currencies.id');
    }

    /**
     * Get default currency for company.
     */
    private function getDefaultCurrency(string $companyId): string
    {
        return DB::table('auth.companies')
            ->where('id', $companyId)
            ->value('base_currency') ?? 'USD';
    }

    /**
     * Get current user ID.
     */
    private function getCurrentUserId(): ?string
    {
        // For CLI commands, try to get user from environment or use system user
        if ($userId = env('CLI_USER_ID')) {
            return $userId;
        }
        
        // Try to find a system user in the database
        $systemUser = \App\Models\User::where('email', 'system@haasib.app')->first();
        if ($systemUser) {
            return $systemUser->id;
        }
        
        // Fallback to first admin user
        $adminUser = \App\Models\User::where('email', 'like', '%admin%')->first();
        if ($adminUser) {
            return $adminUser->id;
        }
        
        // Last resort - create a system user
        $systemUser = \App\Models\User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'System User',
            'email' => 'system@haasib.app',
            'password' => bcrypt('system-only'),
            'email_verified_at' => now(),
        ]);
        
        return $systemUser->id;
    }
}