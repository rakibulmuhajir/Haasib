<?php

namespace Modules\Accounting\CLI\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Modules\Accounting\Domain\Customers\Services\CustomerQueryService;

class CustomerList extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'customer:list
                            {--status= : Filter by status (active, inactive, blocked)}
                            {--search= : Search by name, email, or customer number}
                            {--currency= : Filter by currency}
                            {--page=1 : Page number}
                            {--per-page=15 : Items per page}
                            {--company-id= : Company ID (auto-detected if not provided)}
                            {--json : Output in JSON format}';

    /**
     * The console command description.
     */
    protected $description = 'List customers with pagination and filtering';

    /**
     * Execute the console command.
     */
    public function handle(CustomerQueryService $customerQueryService): int
    {
        try {
            $company = $this->getCompany();

            // Build filters
            $filters = [
                'status' => $this->option('status'),
                'search' => $this->option('search'),
                'currency' => $this->option('currency'),
            ];

            $page = (int) $this->option('page');
            $perPage = (int) $this->option('per-page');

            $customers = $customerQueryService->getCustomers(
                $company,
                array_filter($filters),
                $perPage,
                $page
            );

            if ($this->option('json')) {
                $this->line(json_encode([
                    'success' => true,
                    'data' => $customers->items(),
                    'pagination' => [
                        'current_page' => $customers->currentPage(),
                        'last_page' => $customers->lastPage(),
                        'per_page' => $customers->perPage(),
                        'total' => $customers->total(),
                        'from' => $customers->firstItem(),
                        'to' => $customers->lastItem(),
                    ],
                    'filters' => array_filter($filters),
                ], JSON_PRETTY_PRINT));
            } else {
                $this->displayCustomersTable($customers, $filters);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Display customers in a table format.
     */
    private function displayCustomersTable($customers, array $filters): void
    {
        if ($customers->isEmpty()) {
            $this->info('No customers found.');

            return;
        }

        $headers = ['ID', 'Customer #', 'Name', 'Email', 'Status', 'Currency', 'Credit Limit', 'Created'];
        $rows = [];

        foreach ($customers->items() as $customer) {
            $rows[] = [
                substr($customer->id, 0, 8),
                $customer->customer_number,
                $customer->name,
                $customer->email ?: 'N/A',
                $customer->status,
                $customer->default_currency,
                $customer->credit_limit ? '$'.number_format($customer->credit_limit, 2) : 'N/A',
                $customer->created_at->format('Y-m-d'),
            ];
        }

        $this->table($headers, $rows);

        // Display pagination info
        $this->info("\nPage {$customers->currentPage()} of {$customers->lastPage()}");
        $this->info("Showing {$customers->firstItem()} to {$customers->lastItem()} of {$customers->total()} customers");

        // Display active filters
        $activeFilters = array_filter($filters);
        if (! empty($activeFilters)) {
            $this->info("\nActive filters:");
            foreach ($activeFilters as $key => $value) {
                $this->info("  {$key}: {$value}");
            }
        }

        // Show navigation hints
        if ($customers->hasMorePages()) {
            $this->info("\nTo see more results, use: --page=".($customers->currentPage() + 1));
        }
    }

    /**
     * Get the company for customer listing.
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
}
