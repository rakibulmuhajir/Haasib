<?php

namespace Modules\Accounting\CLI\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Modules\Accounting\Domain\Customers\Actions\CreateCustomerAction;
use Modules\Accounting\Domain\Customers\Exceptions\CustomerCreationException;

class CustomerCreate extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'customer:create
                            {--name= : Customer name (required)}
                            {--legal-name= : Legal name}
                            {--email= : Email address}
                            {--phone= : Phone number}
                            {--currency=USD : Default currency (ISO 4217)}
                            {--payment-terms= : Payment terms}
                            {--credit-limit= : Credit limit}
                            {--tax-id= : Tax ID}
                            {--website= : Website URL}
                            {--notes= : Notes}
                            {--status=active : Customer status}
                            {--company-id= : Company ID (auto-detected if not provided)}
                            {--json : Output in JSON format}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new customer';

    /**
     * Execute the console command.
     */
    public function handle(CreateCustomerAction $createCustomerAction): int
    {
        $name = $this->option('name');
        if (! $name) {
            $name = $this->ask('Customer name');
        }

        if (! $name) {
            $this->error('Customer name is required');

            return Command::FAILURE;
        }

        // Collect customer data
        $data = [
            'name' => $name,
            'legal_name' => $this->option('legal-name') ?: $this->ask('Legal name (optional)'),
            'email' => $this->option('email') ?: $this->ask('Email address (optional)'),
            'phone' => $this->option('phone') ?: $this->ask('Phone number (optional)'),
            'default_currency' => $this->option('currency') ?: $this->ask('Default currency (ISO 4217)', 'USD'),
            'payment_terms' => $this->option('payment-terms') ?: $this->ask('Payment terms (optional)'),
            'credit_limit' => $this->option('credit-limit') ?: $this->ask('Credit limit (optional)'),
            'tax_id' => $this->option('tax-id') ?: $this->ask('Tax ID (optional)'),
            'website' => $this->option('website') ?: $this->ask('Website URL (optional)'),
            'notes' => $this->option('notes') ?: $this->ask('Notes (optional)'),
            'status' => $this->option('status') ?: $this->choice('Status', ['active', 'inactive', 'blocked'], 0),
        ];

        // Validate data
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'default_currency' => 'required|string|size:3',
            'credit_limit' => 'nullable|numeric|min:0',
            'website' => 'nullable|url|max:255',
            'status' => 'required|in:active,inactive,blocked',
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error("  - {$error}");
            }

            return Command::FAILURE;
        }

        try {
            // Get company and user
            $company = $this->getCompany();
            $user = $this->getUser();

            $this->info("Creating customer '{$data['name']}'...");

            $customer = $createCustomerAction->execute($company, $data, $user);

            if ($this->option('json')) {
                $this->line(json_encode([
                    'success' => true,
                    'message' => 'Customer created successfully',
                    'data' => [
                        'id' => $customer->id,
                        'customer_number' => $customer->customer_number,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'status' => $customer->status,
                        'default_currency' => $customer->default_currency,
                        'credit_limit' => $customer->credit_limit,
                        'created_at' => $customer->created_at->toISOString(),
                    ],
                ], JSON_PRETTY_PRINT));
            } else {
                $this->info('✓ Customer created successfully');
                $this->info("  ID: {$customer->id}");
                $this->info("  Customer Number: {$customer->customer_number}");
                $this->info("  Name: {$customer->name}");
                $this->info('  Email: '.($customer->email ?: 'N/A'));
                $this->info("  Status: {$customer->status}");
                $this->info("  Currency: {$customer->default_currency}");
                $this->info('  Credit Limit: '.($customer->credit_limit ? '$'.number_format($customer->credit_limit, 2) : 'N/A'));
                $this->info("  Created: {$customer->created_at}");
            }

            return Command::SUCCESS;

        } catch (CustomerCreationException $e) {
            $this->error("Failed to create customer: {$e->getMessage()}");

            return Command::FAILURE;
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->error('Validation failed:');
            foreach ($e->errors() as $field => $errors) {
                foreach ($errors as $error) {
                    $this->error("  {$field}: {$error}");
                }
            }

            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error("Unexpected error: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Get the company for customer creation.
     */
    private function getCompany(): Company
    {
        $companyId = $this->option('company-id');

        if ($companyId) {
            $company = Company::find($companyId);
            if (! $company) {
                throw new \Exception("Company with ID {$companyId} not found");
            }

            return $company;
        }

        // Try to get current company from context or use first available
        $company = Company::first();
        if (! $company) {
            throw new \Exception('No companies found. Please create a company first.');
        }

        return $company;
    }

    /**
     * Get the user for customer creation.
     */
    private function getUser(): User
    {
        // Try to get authenticated user or use first available
        $user = User::first();
        if (! $user) {
            throw new \Exception('No users found. Please create a user first.');
        }

        return $user;
    }
}
