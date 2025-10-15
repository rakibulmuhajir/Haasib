<?php

namespace Modules\Accounting\CLI\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Modules\Accounting\Domain\Customers\Actions\UpdateCustomerAction;

class CustomerUpdate extends Command
{
    protected $signature = 'customer:update {id : Customer ID}
                           {--name= : Customer name}
                           {--email= : Email address}
                           {--status= : Customer status}
                           {--credit-limit= : Credit limit}
                           {--company-id= : Company ID}
                           {--json : Output in JSON format}';

    protected $description = 'Update an existing customer';

    public function handle(UpdateCustomerAction $updateCustomerAction): int
    {
        try {
            $company = $this->getCompany();
            $user = $this->getUser();
            $customerId = $this->argument('id');

            $data = array_filter([
                'name' => $this->option('name'),
                'email' => $this->option('email'),
                'status' => $this->option('status'),
                'credit_limit' => $this->option('credit-limit'),
            ]);

            if (empty($data)) {
                $this->error('At least one field must be provided for update');

                return Command::FAILURE;
            }

            $customer = $updateCustomerAction->execute($company, $customerId, $data, $user);

            if ($this->option('json')) {
                $this->line(json_encode([
                    'success' => true,
                    'message' => 'Customer updated successfully',
                    'data' => $customer->toArray(),
                ], JSON_PRETTY_PRINT));
            } else {
                $this->info('✓ Customer updated successfully');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    private function getCompany(): Company
    {
        $companyId = $this->option('company-id');

        return $companyId ? Company::findOrFail($companyId) : Company::first();
    }

    private function getUser(): User
    {
        return User::first();
    }
}
