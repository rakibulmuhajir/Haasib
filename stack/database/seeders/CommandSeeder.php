<?php

namespace Database\Seeders;

use App\Models\Command;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding Command Palette commands...');

        try {
            DB::transaction(function () {
                $companies = Company::all();

                foreach ($companies as $company) {
                    $this->seedCommandsForCompany($company);
                }
            });

            $this->command->info('Command Palette commands seeded successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to seed Command Palette commands', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->command->error('Failed to seed Command Palette commands: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Seed commands for a specific company
     */
    private function seedCommandsForCompany(Company $company): void
    {
        $commands = $this->getCoreCommands();

        foreach ($commands as $commandData) {
            $command = Command::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $commandData['name'],
                ],
                array_merge($commandData, [
                    'company_id' => $company->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );

            $this->command->line("  - {$command->name} for company: {$company->name}");
        }
    }

    /**
     * Get core commands that should be available for all companies
     */
    private function getCoreCommands(): array
    {
        return [
            [
                'name' => 'invoice.create',
                'description' => 'Create a new invoice',
                'category' => 'invoice',
                'parameters' => json_encode([
                    'customer_id' => [
                        'type' => 'uuid',
                        'required' => true,
                        'description' => 'Customer ID to create invoice for',
                    ],
                    'items' => [
                        'type' => 'array',
                        'required' => true,
                        'description' => 'Array of invoice items',
                    ],
                    'due_date' => [
                        'type' => 'date',
                        'required' => false,
                        'description' => 'Invoice due date',
                    ],
                ]),
                'required_permissions' => json_encode(['create_invoices']),
                'is_active' => true,
                'execution_handler' => 'App\\Services\\CommandHandlers\\InvoiceHandler@create',
            ],

            [
                'name' => 'invoice.list',
                'description' => 'List all invoices',
                'category' => 'invoice',
                'parameters' => json_encode([
                    'status' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Filter by invoice status',
                    ],
                    'customer_id' => [
                        'type' => 'uuid',
                        'required' => false,
                        'description' => 'Filter by customer ID',
                    ],
                ]),
                'required_permissions' => json_encode(['view_invoices']),
                'is_active' => true,
                'execution_handler' => 'App\\Services\\CommandHandlers\\InvoiceHandler@index',
            ],

            [
                'name' => 'customer.create',
                'description' => 'Create a new customer',
                'category' => 'customer',
                'parameters' => json_encode([
                    'name' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Customer name',
                    ],
                    'email' => [
                        'type' => 'email',
                        'required' => false,
                        'description' => 'Customer email address',
                    ],
                    'phone' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Customer phone number',
                    ],
                    'currency' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Default currency (3-letter ISO code)',
                    ],
                    'payment_terms' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Payment terms (e.g., net_30, net_15)',
                    ],
                    'credit_limit' => [
                        'type' => 'decimal',
                        'required' => false,
                        'description' => 'Initial credit limit',
                    ],
                    'company' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Override company context',
                    ],
                ]),
                'required_permissions' => json_encode(['create_customers']),
                'is_active' => true,
                'execution_handler' => 'Modules\\Accounting\\CLI\\Commands\\CustomerCreate@handle',
            ],

            [
                'name' => 'customer.search',
                'description' => 'Search for customers',
                'category' => 'customer',
                'parameters' => json_encode([
                    'query' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Search query',
                    ],
                ]),
                'required_permissions' => json_encode(['view_customers']),
                'is_active' => true,
                'execution_handler' => 'App\\Services\\CommandHandlers\\CustomerHandler@search',
            ],

            [
                'name' => 'customer.list',
                'description' => 'List all customers with optional filtering',
                'category' => 'customer',
                'parameters' => json_encode([
                    'status' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Filter by customer status (active, inactive, blocked)',
                    ],
                    'search' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Search term for customer name or email',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'required' => false,
                        'description' => 'Maximum number of results to return',
                    ],
                    'format' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Output format (table, json)',
                    ],
                ]),
                'required_permissions' => json_encode(['view_customers']),
                'is_active' => true,
                'execution_handler' => 'Modules\\Accounting\\CLI\\Commands\\CustomerList@handle',
            ],

            [
                'name' => 'customer.update',
                'description' => 'Update an existing customer',
                'category' => 'customer',
                'parameters' => json_encode([
                    'id' => [
                        'type' => 'uuid',
                        'required' => true,
                        'description' => 'Customer ID to update',
                    ],
                    'name' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Customer name',
                    ],
                    'email' => [
                        'type' => 'email',
                        'required' => false,
                        'description' => 'Customer email address',
                    ],
                    'phone' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Customer phone number',
                    ],
                    'status' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Customer status (active, inactive, blocked)',
                    ],
                ]),
                'required_permissions' => json_encode(['update_customers']),
                'is_active' => true,
                'execution_handler' => 'Modules\\Accounting\\CLI\\Commands\\CustomerUpdate@handle',
            ],

            [
                'name' => 'customer.delete',
                'description' => 'Delete a customer (soft delete)',
                'category' => 'customer',
                'parameters' => json_encode([
                    'id' => [
                        'type' => 'uuid',
                        'required' => true,
                        'description' => 'Customer ID to delete',
                    ],
                    'force' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Force permanent delete (requires admin permissions)',
                    ],
                ]),
                'required_permissions' => json_encode(['delete_customers']),
                'is_active' => true,
                'execution_handler' => 'Modules\\Accounting\\CLI\\Commands\\CustomerDelete@handle',
            ],

            [
                'name' => 'customer.contact.add',
                'description' => 'Add a new contact to a customer',
                'category' => 'customer',
                'parameters' => json_encode([
                    'customer_id' => [
                        'type' => 'uuid',
                        'required' => true,
                        'description' => 'Customer ID to add contact to',
                    ],
                    'first_name' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Contact first name',
                    ],
                    'last_name' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Contact last name',
                    ],
                    'email' => [
                        'type' => 'email',
                        'required' => false,
                        'description' => 'Contact email address',
                    ],
                    'phone' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Contact phone number',
                    ],
                    'mobile' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Contact mobile number',
                    ],
                    'job_title' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Contact job title',
                    ],
                    'department' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Contact department',
                    ],
                    'primary' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Set as primary contact',
                    ],
                    'active' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Mark contact as active',
                    ],
                    'notes' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Additional notes',
                    ],
                ]),
                'required_permissions' => json_encode(['accounting.customers.manage_contacts']),
                'is_active' => true,
                'execution_handler' => 'Modules\\Accounting\\CLI\\Commands\\CustomerContactAdd@handle',
            ],

            [
                'name' => 'customer.contact.list',
                'description' => 'List contacts for a customer',
                'category' => 'customer',
                'parameters' => json_encode([
                    'customer_id' => [
                        'type' => 'uuid',
                        'required' => true,
                        'description' => 'Customer ID to list contacts for',
                    ],
                    'active' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Filter by active status',
                    ],
                    'primary' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Filter by primary status',
                    ],
                    'department' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Filter by department',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'required' => false,
                        'description' => 'Maximum number of results to return',
                    ],
                    'format' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Output format (table, json)',
                    ],
                ]),
                'required_permissions' => json_encode(['accounting.customers.manage_contacts']),
                'is_active' => true,
                'execution_handler' => 'Modules\\Accounting\\CLI\\Commands\\CustomerContactList@handle',
            ],

            [
                'name' => 'customer.address.add',
                'description' => 'Add a new address to a customer',
                'category' => 'customer',
                'parameters' => json_encode([
                    'customer_id' => [
                        'type' => 'uuid',
                        'required' => true,
                        'description' => 'Customer ID to add address to',
                    ],
                    'address_line_1' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Address line 1',
                    ],
                    'address_line_2' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Address line 2',
                    ],
                    'city' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'City',
                    ],
                    'state' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'State/Province',
                    ],
                    'postal_code' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Postal code',
                    ],
                    'country' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Country code (e.g., US, CA)',
                    ],
                    'type' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Address type (billing/shipping/both/other)',
                    ],
                    'default' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Set as default address',
                    ],
                    'active' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Mark address as active',
                    ],
                    'notes' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Additional notes',
                    ],
                ]),
                'required_permissions' => json_encode(['accounting.customers.manage_contacts']),
                'is_active' => true,
                'execution_handler' => 'Modules\\Accounting\\CLI\\Commands\\CustomerAddressAdd@handle',
            ],

            [
                'name' => 'customer.address.list',
                'description' => 'List addresses for a customer',
                'category' => 'customer',
                'parameters' => json_encode([
                    'customer_id' => [
                        'type' => 'uuid',
                        'required' => true,
                        'description' => 'Customer ID to list addresses for',
                    ],
                    'type' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Filter by address type',
                    ],
                    'active' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Filter by active status',
                    ],
                    'default' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Filter by default status',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'required' => false,
                        'description' => 'Maximum number of results to return',
                    ],
                    'format' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Output format (table, json)',
                    ],
                ]),
                'required_permissions' => json_encode(['accounting.customers.manage_contacts']),
                'is_active' => true,
                'execution_handler' => 'Modules\\Accounting\\CLI\\Commands\\CustomerAddressList@handle',
            ],

            [
                'name' => 'customer.communication.log',
                'description' => 'Log a communication with a customer',
                'category' => 'customer',
                'parameters' => json_encode([
                    'customer_id' => [
                        'type' => 'uuid',
                        'required' => true,
                        'description' => 'Customer ID to log communication for',
                    ],
                    'type' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Communication type (email/phone/meeting/letter/sms/note/other)',
                    ],
                    'direction' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Direction (inbound/outbound)',
                    ],
                    'subject' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Subject or topic',
                    ],
                    'content' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Content of the communication',
                    ],
                    'date' => [
                        'type' => 'datetime',
                        'required' => false,
                        'description' => 'Communication date (Y-m-d H:i:s)',
                    ],
                    'notes' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Internal notes',
                    ],
                ]),
                'required_permissions' => json_encode(['accounting.customers.manage_comms']),
                'is_active' => true,
                'execution_handler' => 'Modules\\Accounting\\CLI\\Commands\\CustomerCommunicationLog@handle',
            ],

            [
                'name' => 'customer.communication.list',
                'description' => 'List communications for a customer',
                'category' => 'customer',
                'parameters' => json_encode([
                    'customer_id' => [
                        'type' => 'uuid',
                        'required' => true,
                        'description' => 'Customer ID to list communications for',
                    ],
                    'type' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Filter by communication type',
                    ],
                    'direction' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Filter by direction',
                    ],
                    'from' => [
                        'type' => 'date',
                        'required' => false,
                        'description' => 'Filter communications from date (Y-m-d)',
                    ],
                    'to' => [
                        'type' => 'date',
                        'required' => false,
                        'description' => 'Filter communications to date (Y-m-d)',
                    ],
                    'search' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Search in subject, content, or notes',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'required' => false,
                        'description' => 'Maximum number of results to return',
                    ],
                    'format' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Output format (table, json)',
                    ],
                ]),
                'required_permissions' => json_encode(['accounting.customers.manage_comms']),
                'is_active' => true,
                'execution_handler' => 'Modules\\Accounting\\CLI\\Commands\\CustomerCommunicationList@handle',
            ],

            [
                'name' => 'customer.credit.adjust',
                'description' => 'Adjust a customer credit limit with approval workflow',
                'category' => 'customer',
                'parameters' => json_encode([
                    'customer_id' => [
                        'type' => 'uuid',
                        'required' => true,
                        'description' => 'Customer ID to adjust credit limit for',
                    ],
                    'amount' => [
                        'type' => 'decimal',
                        'required' => true,
                        'description' => 'New credit limit amount',
                    ],
                    'effective' => [
                        'type' => 'datetime',
                        'required' => false,
                        'description' => 'Effective date (Y-m-d H:i:s)',
                    ],
                    'expires' => [
                        'type' => 'datetime',
                        'required' => false,
                        'description' => 'Expiry date (Y-m-d H:i:s)',
                    ],
                    'reason' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Reason for adjustment',
                    ],
                    'approval' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Approval reference',
                    ],
                    'status' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Status (approved/pending)',
                    ],
                    'auto-expire' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Auto-expire conflicting limits',
                    ],
                    'format' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Output format (table, json)',
                    ],
                ]),
                'required_permissions' => json_encode(['accounting.customers.manage_credit']),
                'is_active' => true,
                'execution_handler' => 'Modules\\Accounting\\CLI\\Commands\\CustomerCreditAdjust@handle',
            ],

            [
                'name' => 'customer.aging.update',
                'description' => 'Update customer aging snapshots and analysis',
                'category' => 'customer',
                'parameters' => json_encode([
                    'company_id' => [
                        'type' => 'uuid',
                        'required' => false,
                        'description' => 'Company ID to update aging for (optional)',
                    ],
                    'customer_id' => [
                        'type' => 'uuid',
                        'required' => false,
                        'description' => 'Customer ID to update aging for (optional)',
                    ],
                    'date' => [
                        'type' => 'date',
                        'required' => false,
                        'description' => 'Date to update aging for (YYYY-MM-DD, default: today)',
                    ],
                    'via' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'How the aging was generated (scheduled|on_demand)',
                    ],
                    'queue' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Dispatch to queue instead of immediate execution',
                    ],
                    'batch_size' => [
                        'type' => 'integer',
                        'required' => false,
                        'description' => 'Batch size when using queue (default: 50)',
                    ],
                    'force' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Force update even if snapshot exists',
                    ],
                    'preview' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Show preview without making changes',
                    ],
                    'json' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Output results in JSON format',
                    ],
                ]),
                'required_permissions' => json_encode(['accounting.customers.generate_statements']),
                'is_active' => true,
                'execution_handler' => 'Modules\\Accounting\\CLI\\Commands\\CustomerAgingUpdateCommand@handle',
            ],

            [
                'name' => 'payment.create',
                'description' => 'Record a new payment',
                'category' => 'payment',
                'parameters' => json_encode([
                    'customer_id' => [
                        'type' => 'uuid',
                        'required' => true,
                        'description' => 'Customer ID making the payment',
                    ],
                    'amount' => [
                        'type' => 'decimal',
                        'required' => true,
                        'description' => 'Payment amount',
                    ],
                    'method' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Payment method (cash, card, transfer, etc.)',
                    ],
                ]),
                'required_permissions' => json_encode(['create_payments']),
                'is_active' => true,
                'execution_handler' => 'App\\Services\\CommandHandlers\\PaymentHandler@create',
            ],

            [
                'name' => 'report.generate',
                'description' => 'Generate financial reports',
                'category' => 'reporting',
                'parameters' => json_encode([
                    'type' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Report type (sales, tax, financial, etc.)',
                    ],
                    'date_range' => [
                        'type' => 'object',
                        'required' => false,
                        'description' => 'Date range for the report',
                    ],
                ]),
                'required_permissions' => json_encode(['view_reports']),
                'is_active' => true,
                'execution_handler' => 'App\\Services\\CommandHandlers\\ReportHandler@generate',
            ],

            [
                'name' => 'dashboard.show',
                'description' => 'Show dashboard overview',
                'category' => 'navigation',
                'parameters' => json_encode([]),
                'required_permissions' => json_encode(['view_dashboard']),
                'is_active' => true,
                'execution_handler' => 'App\\Services\\CommandHandlers\\NavigationHandler@dashboard',
            ],

            [
                'name' => 'settings.open',
                'description' => 'Open application settings',
                'category' => 'navigation',
                'parameters' => json_encode([
                    'section' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Settings section to open',
                    ],
                ]),
                'required_permissions' => json_encode(['manage_settings']),
                'is_active' => true,
                'execution_handler' => 'App\\Services\\CommandHandlers\\NavigationHandler@settings',
            ],

            [
                'name' => 'help.show',
                'description' => 'Show help and documentation',
                'category' => 'help',
                'parameters' => json_encode([
                    'topic' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Help topic to display',
                    ],
                ]),
                'required_permissions' => json_encode([]),
                'is_active' => true,
                'execution_handler' => 'App\\Services\\CommandHandlers\\HelpHandler@show',
            ],

            [
                'name' => 'user.profile',
                'description' => 'Show user profile',
                'category' => 'user',
                'parameters' => json_encode([]),
                'required_permissions' => json_encode(['view_profile']),
                'is_active' => true,
                'execution_handler' => 'App\\Services\\CommandHandlers\\UserHandler@profile',
            ],
        ];
    }
}
