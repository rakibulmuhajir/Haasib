<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Domain\Customers\Actions\CreateCustomerAddressAction;
use Modules\Accounting\Domain\Customers\Models\Customer;

class CustomerAddressAdd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:address:add {customer_id : The ID of the customer}
                           {--address-line-1= : Address line 1}
                           {--address-line-2= : Address line 2 (optional)}
                           {--city= : City}
                           {--state= : State/Province}
                           {--postal-code= : Postal code}
                           {--country= : Country code (e.g., US, CA)}
                           {--type= : Address type (billing/shipping/both/other)}
                           {--default= : Set as default address (true/false)}
                           {--active= : Set address as active (true/false)}
                           {--notes= : Additional notes}
                           {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a new address to a customer';

    /**
     * Execute the console command.
     */
    public function handle(CreateCustomerAddressAction $action): int
    {
        $customerId = $this->argument('customer_id');
        $isJson = $this->option('json');

        // Validate customer exists
        $customer = Customer::find($customerId);
        if (! $customer) {
            $this->error("Customer with ID {$customerId} not found.");

            return 1;
        }

        // Collect address data
        $data = [
            'address_line_1' => $this->option('address-line-1') ?? $this->ask('Address line 1'),
            'address_line_2' => $this->option('address-line-2') ?? $this->ask('Address line 2 (optional)'),
            'city' => $this->option('city') ?? $this->ask('City'),
            'state_province' => $this->option('state') ?? $this->ask('State/Province'),
            'postal_code' => $this->option('postal-code') ?? $this->ask('Postal code'),
            'country' => $this->option('country') ?? $this->ask('Country code (e.g., US, CA)'),
            'address_type' => $this->option('type') ?? $this->choice(
                'Address type',
                ['billing', 'shipping', 'both', 'other'],
                0
            ),
            'is_default' => $this->option('default') !== null
                ? filter_var($this->option('default'), FILTER_VALIDATE_BOOLEAN)
                : $this->confirm('Set as default address?', false),
            'is_active' => $this->option('active') !== null
                ? filter_var($this->option('active'), FILTER_VALIDATE_BOOLEAN)
                : $this->confirm('Mark address as active?', true),
            'notes' => $this->option('notes') ?? $this->ask('Additional notes (optional)'),
        ];

        try {
            $address = $action->execute($customer, $data);

            if ($isJson) {
                $this->line(json_encode([
                    'success' => true,
                    'data' => [
                        'id' => $address->id,
                        'customer_id' => $address->customer_id,
                        'address_line_1' => $address->address_line_1,
                        'address_line_2' => $address->address_line_2,
                        'city' => $address->city,
                        'state_province' => $address->state_province,
                        'postal_code' => $address->postal_code,
                        'country' => $address->country,
                        'address_type' => $address->address_type,
                        'is_default' => $address->is_default,
                        'is_active' => $address->is_active,
                        'created_at' => $address->created_at,
                    ],
                ], JSON_PRETTY_PRINT));
            } else {
                $this->info('Address created successfully!');
                $this->info("ID: {$address->id}");
                $this->info("Address: {$address->address_line_1}");
                if ($address->address_line_2) {
                    $this->info("         {$address->address_line_2}");
                }
                $this->info("         {$address->city}, {$address->state_province} {$address->postal_code}");
                $this->info("         {$address->country}");
                $this->info("Type: {$address->address_type}");
                $this->info('Default: '.($address->is_default ? 'Yes' : 'No'));
                $this->info('Active: '.($address->is_active ? 'Yes' : 'No'));
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
                $this->error("Failed to create address: {$error}");
            }

            return 1;
        }
    }
}
