<?php

namespace Modules\Accounting\CLI\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Modules\Accounting\Domain\Customers\Actions\DeleteCustomerAction;

class CustomerDelete extends Command
{
    protected $signature = 'customer:delete {id : Customer ID}
                           {--force : Force delete without confirmation}
                           {--company-id= : Company ID}
                           {--json : Output in JSON format}';

    protected $description = 'Delete a customer';

    public function handle(DeleteCustomerAction $deleteCustomerAction): int
    {
        try {
            $company = $this->getCompany();
            $user = $this->getUser();
            $customerId = $this->argument('id');

            if (! $this->option('force')) {
                if (! $this->confirm("Are you sure you want to delete customer {$customerId}?")) {
                    $this->info('Operation cancelled');

                    return Command::SUCCESS;
                }
            }

            $deleteCustomerAction->execute($company, $customerId, $user);

            if ($this->option('json')) {
                $this->line(json_encode([
                    'success' => true,
                    'message' => 'Customer deleted successfully',
                ], JSON_PRETTY_PRINT));
            } else {
                $this->info('✓ Customer deleted successfully');
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
