<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Modules\Accounting\Domain\Customers\Actions\AdjustCustomerCreditLimitAction;
use Modules\Accounting\Domain\Customers\Models\Customer;

class CustomerCreditAdjust extends Command
{
    use AuthorizesRequests;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:credit:adjust 
                           {customer_id : The ID of the customer}
                           {--amount= : New credit limit amount (required)}
                           {--effective= : Effective date (Y-m-d H:i:s, defaults to now)}
                           {--expires= : Expiry date (Y-m-d H:i:s, optional)}
                           {--reason= : Reason for adjustment (optional)}
                           {--approval= : Approval reference (optional)}
                           {--status= : Status (approved/pending, default: approved)}
                           {--auto-expire= : Auto-expire conflicting limits (true/false)}
                           {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adjust a customer credit limit with approval workflow';

    /**
     * Execute the console command.
     */
    public function handle(AdjustCustomerCreditLimitAction $action): int
    {
        $customerId = $this->argument('customer_id');
        $isJson = $this->option('json');

        // Check permissions
        $user = $this->laravel->make('auth.driver')->user();
        if (! $user || ! $user->can('accounting.customers.manage_credit')) {
            $error = 'You do not have permission to manage customer credit limits.';

            if ($isJson) {
                $this->line(json_encode([
                    'success' => false,
                    'error' => $error,
                ], JSON_PRETTY_PRINT));
            } else {
                $this->error($error);
            }

            return 1;
        }

        // Validate customer exists
        $customer = Customer::find($customerId);
        if (! $customer) {
            $error = "Customer with ID {$customerId} not found.";

            if ($isJson) {
                $this->line(json_encode([
                    'success' => false,
                    'error' => $error,
                ], JSON_PRETTY_PRINT));
            } else {
                $this->error($error);
            }

            return 1;
        }

        // Collect credit limit data
        $amount = $this->option('amount');
        if ($amount === null) {
            $amount = $this->ask('What is the new credit limit amount?');
        }

        if (! is_numeric($amount) || $amount < 0) {
            $error = 'Credit limit amount must be a non-negative number.';

            if ($isJson) {
                $this->line(json_encode([
                    'success' => false,
                    'error' => $error,
                ], JSON_PRETTY_PRINT));
            } else {
                $this->error($error);
            }

            return 1;
        }

        $effectiveDate = $this->option('effective')
            ? new \DateTime($this->option('effective'))
            : now();

        $data = [
            'expires_at' => $this->option('expires') ? new \DateTime($this->option('expires')) : null,
            'reason' => $this->option('reason') ?? $this->ask('Reason for adjustment (optional)'),
            'approval_reference' => $this->option('approval') ?? $this->ask('Approval reference (optional)'),
            'status' => $this->option('status') ?? 'approved',
            'auto_expire_conflicts' => $this->option('auto-expire') === 'true',
            'changed_by_user_id' => $user->id,
        ];

        try {
            $creditLimit = $action->execute(
                $customer,
                (float) $amount,
                $effectiveDate,
                $data
            );

            if ($isJson) {
                $this->line(json_encode([
                    'success' => true,
                    'data' => [
                        'id' => $creditLimit->id,
                        'customer_id' => $creditLimit->customer_id,
                        'limit_amount' => $creditLimit->limit_amount,
                        'effective_at' => $creditLimit->effective_at,
                        'expires_at' => $creditLimit->expires_at,
                        'status' => $creditLimit->status,
                        'reason' => $creditLimit->reason,
                        'approval_reference' => $creditLimit->approval_reference,
                        'customer_updated' => [
                            'credit_limit' => $customer->fresh()->credit_limit,
                            'credit_limit_effective_at' => $customer->fresh()->credit_limit_effective_at,
                        ],
                    ],
                ], JSON_PRETTY_PRINT));
            } else {
                $this->info('Credit limit adjusted successfully!');
                $this->info("Customer: {$customer->name}");
                $this->info("New Limit: {$creditLimit->limit_amount}");
                $this->info('Effective: '.$creditLimit->effective_at->format('Y-m-d H:i:s'));

                if ($creditLimit->expires_at) {
                    $this->info('Expires: '.$creditLimit->expires_at->format('Y-m-d H:i:s'));
                }

                $this->info('Status: '.ucfirst($creditLimit->status));

                if ($creditLimit->approval_reference) {
                    $this->info("Approval Reference: {$creditLimit->approval_reference}");
                }

                if ($creditLimit->reason) {
                    $this->info("Reason: {$creditLimit->reason}");
                }

                // Show updated customer info
                $customer->refresh();
                $this->info("Customer Credit Limit: {$customer->credit_limit}");
                $this->info('Customer Credit Limit Effective: '.$customer->credit_limit_effective_at->format('Y-m-d H:i:s'));
            }

            return 0;
        } catch (\Exception $e) {
            $error = $e->getMessage();

            if ($isJson) {
                $this->line(json_encode([
                    'success' => false,
                    'error' => $error,
                ], JSON_PRETTY_PRINT));
            } else {
                $this->error("Failed to adjust credit limit: {$error}");
            }

            return 1;
        }
    }
}
