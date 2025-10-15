<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerAddress;

class CustomerAddressList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:address:list {customer_id : The ID of the customer}
                           {--type= : Filter by address type (billing/shipping/both/other)}
                           {--active= : Filter by active status (true/false)}
                           {--default= : Filter by default status (true/false)}
                           {--limit= : Limit number of results (default: 50)}
                           {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List addresses for a customer';

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
        $query = CustomerAddress::where('customer_id', $customerId);

        // Apply filters
        if ($this->option('type')) {
            $query->where('address_type', $this->option('type'));
        }

        if ($this->option('active') !== null) {
            $active = filter_var($this->option('active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $active);
        }

        if ($this->option('default') !== null) {
            $default = filter_var($this->option('default'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_default', $default);
        }

        // Get addresses with pagination
        $addresses = $query->orderBy('is_default', 'desc')
            ->orderBy('address_type')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($isJson) {
            $this->line(json_encode([
                'success' => true,
                'data' => [
                    'customer_id' => $customerId,
                    'customer_name' => $customer->name,
                    'addresses' => $addresses->map(function ($address) {
                        return [
                            'id' => $address->id,
                            'address_line_1' => $address->address_line_1,
                            'address_line_2' => $address->address_line_2,
                            'city' => $address->city,
                            'state_province' => $address->state_province,
                            'postal_code' => $address->postal_code,
                            'country' => $address->country,
                            'address_type' => $address->address_type,
                            'is_default' => $address->is_default,
                            'is_active' => $address->is_active,
                            'notes' => $address->notes,
                            'created_at' => $address->created_at,
                            'updated_at' => $address->updated_at,
                        ];
                    })->toArray(),
                    'pagination' => [
                        'limit' => $limit,
                        'count' => $addresses->count(),
                        'total' => CustomerAddress::where('customer_id', $customerId)->count(),
                    ],
                ],
            ], JSON_PRETTY_PRINT));
        } else {
            if ($addresses->isEmpty()) {
                $this->info("No addresses found for customer {$customer->name}.");

                return 0;
            }

            $this->info("Addresses for {$customer->name} (Customer ID: {$customerId}):");
            $this->info(str_repeat('-', 80));

            foreach ($addresses as $address) {
                $default = $address->is_default ? ' [DEFAULT]' : '';
                $status = $address->is_active ? 'Active' : 'Inactive';
                $type = strtoupper($address->address_type);

                $this->info("{$type} Address{$default} (ID: {$address->id}) - {$status}");
                $this->info("  {$address->address_line_1}");

                if ($address->address_line_2) {
                    $this->info("  {$address->address_line_2}");
                }

                $this->info("  {$address->city}, {$address->state_province} {$address->postal_code}");
                $this->info("  {$address->country}");

                if ($address->notes) {
                    $this->info("  Notes: {$address->notes}");
                }

                $this->info('  Created: '.$address->created_at->format('Y-m-d H:i:s'));
                $this->info('');
            }

            $total = CustomerAddress::where('customer_id', $customerId)->count();
            $this->info("Showing {$addresses->count()} of {$total} addresses");
        }

        return 0;
    }
}
