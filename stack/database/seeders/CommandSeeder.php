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
                        'required' => true,
                        'description' => 'Customer email address',
                    ],
                ]),
                'required_permissions' => json_encode(['create_customers']),
                'is_active' => true,
                'execution_handler' => 'App\\Services\\CommandHandlers\\CustomerHandler@create',
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
