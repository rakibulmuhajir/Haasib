<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Modules\Accounting\Domain\Customers\Actions\CreateCustomerContactAction;
use Modules\Accounting\Domain\Customers\Models\Customer;

class CustomerContactAdd extends Command
{
    use AuthorizesRequests;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:contact:add {customer_id : The ID of the customer} 
                           {--first-name= : First name of the contact}
                           {--last-name= : Last name of the contact}
                           {--email= : Email address}
                           {--phone= : Phone number}
                           {--mobile= : Mobile number}
                           {--job-title= : Job title}
                           {--department= : Department}
                           {--primary= : Set as primary contact (true/false)}
                           {--active= : Set contact as active (true/false)}
                           {--notes= : Additional notes}
                           {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a new contact to a customer';

    /**
     * Execute the console command.
     */
    public function handle(CreateCustomerContactAction $action): int
    {
        $customerId = $this->argument('customer_id');
        $isJson = $this->option('json');

        // Check permissions - for CLI, we need to get the current user differently
        $user = $this->laravel->make('auth.driver')->user();
        if (! $user || ! $user->can('accounting.customers.manage_contacts')) {
            $error = 'You do not have permission to manage customer contacts.';

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
            $this->error("Customer with ID {$customerId} not found.");

            return 1;
        }

        // Collect contact data
        $data = [
            'first_name' => $this->option('first-name') ?? $this->ask('First name'),
            'last_name' => $this->option('last-name') ?? $this->ask('Last name'),
            'email' => $this->option('email') ?? $this->ask('Email (optional)'),
            'phone' => $this->option('phone') ?? $this->ask('Phone (optional)'),
            'mobile' => $this->option('mobile') ?? $this->ask('Mobile (optional)'),
            'job_title' => $this->option('job-title') ?? $this->ask('Job title (optional)'),
            'department' => $this->option('department') ?? $this->ask('Department (optional)'),
            'is_primary' => $this->option('primary') !== null
                ? filter_var($this->option('primary'), FILTER_VALIDATE_BOOLEAN)
                : $this->confirm('Set as primary contact?', false),
            'is_active' => $this->option('active') !== null
                ? filter_var($this->option('active'), FILTER_VALIDATE_BOOLEAN)
                : $this->confirm('Mark contact as active?', true),
            'notes' => $this->option('notes') ?? $this->ask('Additional notes (optional)'),
        ];

        try {
            $contact = $action->execute($customer, $data);

            if ($isJson) {
                $this->line(json_encode([
                    'success' => true,
                    'data' => [
                        'id' => $contact->id,
                        'customer_id' => $contact->customer_id,
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
                    ],
                ], JSON_PRETTY_PRINT));
            } else {
                $this->info('Contact created successfully!');
                $this->info("ID: {$contact->id}");
                $this->info("Name: {$contact->first_name} {$contact->last_name}");
                if ($contact->email) {
                    $this->info("Email: {$contact->email}");
                }
                if ($contact->phone) {
                    $this->info("Phone: {$contact->phone}");
                }
                $this->info('Primary: '.($contact->is_primary ? 'Yes' : 'No'));
                $this->info('Active: '.($contact->is_active ? 'Yes' : 'No'));
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
                $this->error("Failed to create contact: {$error}");
            }

            return 1;
        }
    }
}
