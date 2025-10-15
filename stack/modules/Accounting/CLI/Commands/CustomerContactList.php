<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerContact;

class CustomerContactList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:contact:list {customer_id : The ID of the customer}
                           {--active= : Filter by active status (true/false)}
                           {--primary= : Filter by primary status (true/false)}
                           {--department= : Filter by department}
                           {--limit= : Limit number of results (default: 50)}
                           {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List contacts for a customer';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $customerId = $this->argument('customer_id');
        $isJson = $this->option('json');
        $limit = $this->option('limit') ?? 50;

        // Validate customer exists
        $customer = Customer::find($customerId);
        if (! $customer) {
            $this->error("Customer with ID {$customerId} not found.");

            return 1;
        }

        // Build query
        $query = CustomerContact::where('customer_id', $customerId);

        // Apply filters
        if ($this->option('active') !== null) {
            $active = filter_var($this->option('active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $active);
        }

        if ($this->option('primary') !== null) {
            $primary = filter_var($this->option('primary'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_primary', $primary);
        }

        if ($this->option('department')) {
            $query->where('department', $this->option('department'));
        }

        // Get contacts with pagination
        $contacts = $query->orderBy('is_primary', 'desc')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get();

        if ($isJson) {
            $this->line(json_encode([
                'success' => true,
                'data' => [
                    'customer_id' => $customerId,
                    'customer_name' => $customer->name,
                    'contacts' => $contacts->map(function ($contact) {
                        return [
                            'id' => $contact->id,
                            'first_name' => $contact->first_name,
                            'last_name' => $contact->last_name,
                            'email' => $contact->email,
                            'phone' => $contact->phone,
                            'mobile' => $contact->mobile,
                            'job_title' => $contact->job_title,
                            'department' => $contact->department,
                            'is_primary' => $contact->is_primary,
                            'is_active' => $contact->is_active,
                            'created_at' => $contact->created_at,
                            'updated_at' => $contact->updated_at,
                        ];
                    })->toArray(),
                    'pagination' => [
                        'limit' => $limit,
                        'count' => $contacts->count(),
                        'total' => CustomerContact::where('customer_id', $customerId)->count(),
                    ],
                ],
            ], JSON_PRETTY_PRINT));
        } else {
            if ($contacts->isEmpty()) {
                $this->info("No contacts found for customer {$customer->name}.");

                return 0;
            }

            $this->info("Contacts for {$customer->name} (Customer ID: {$customerId}):");
            $this->info(str_repeat('-', 80));

            foreach ($contacts as $contact) {
                $primary = $contact->is_primary ? ' [PRIMARY]' : '';
                $status = $contact->is_active ? 'Active' : 'Inactive';
                $name = "{$contact->first_name} {$contact->last_name}{$primary}";

                $this->info("{$name} (ID: {$contact->id}) - {$status}");

                if ($contact->job_title) {
                    $this->info("  Title: {$contact->job_title}");
                }

                if ($contact->department) {
                    $this->info("  Department: {$contact->department}");
                }

                if ($contact->email) {
                    $this->info("  Email: {$contact->email}");
                }

                if ($contact->phone) {
                    $this->info("  Phone: {$contact->phone}");
                }

                if ($contact->mobile) {
                    $this->info("  Mobile: {$contact->mobile}");
                }

                $this->info('  Created: '.$contact->created_at->format('Y-m-d H:i:s'));
                $this->info('');
            }

            $total = CustomerContact::where('customer_id', $customerId)->count();
            $this->info("Showing {$contacts->count()} of {$total} contacts");
        }

        return 0;
    }
}
