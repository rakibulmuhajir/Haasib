<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Invoice Template CLI Commands', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user->id, ['role' => 'owner']);

        // Create a test customer
        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Customer',
        ]);
    });

    describe('invoice:template:create', function () {
        it('creates a template with basic parameters', function () {
            $this->artisan('invoice:template:create', [
                'name' => 'Test Template',
                '--currency' => 'USD',
                '--description' => 'Test Description',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('Template created successfully!');

            $template = InvoiceTemplate::where('name', 'Test Template')->first();
            expect($template)->not->toBeNull();
            expect($template->currency)->toBe('USD');
            expect($template->description)->toBe('Test Description');
        });

        it('creates a template with customer', function () {
            $this->artisan('invoice:template:create', [
                'name' => 'Customer Template',
                '--currency' => 'EUR',
                '--customer' => $this->customer->id,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);

            $template = InvoiceTemplate::where('name', 'Customer Template')->first();
            expect($template->customer_id)->toBe($this->customer->id);
            expect($template->currency)->toBe('EUR');
        });

        it('creates a template with line items', function () {
            $this->artisan('invoice:template:create', [
                'name' => 'Template with Items',
                '--currency' => 'USD',
                '--items' => 'Web Design:10:50:10,Consulting:5:100:0',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);

            $template = InvoiceTemplate::where('name', 'Template with Items')->first();
            $lineItems = $template->template_data['line_items'] ?? [];
            expect($lineItems)->toHaveCount(2);
            expect($lineItems[0]['description'])->toBe('Web Design');
            expect($lineItems[0]['quantity'])->toBe(10);
            expect($lineItems[0]['unit_price'])->toBe(50.0);
            expect($lineItems[0]['tax_rate'])->toBe(10.0);
        });

        it('validates required parameters', function () {
            $this->artisan('invoice:template:create', [
                'name' => '', // Empty name should fail
                '--company' => $this->company->id,
            ])
                ->assertExitCode(1);
        });
    });

    describe('invoice:template:list', function () {
        beforeEach(function () {
            // Create test templates
            InvoiceTemplate::factory()->active()->create([
                'company_id' => $this->company->id,
                'name' => 'Active Template',
                'currency' => 'USD',
            ]);
            InvoiceTemplate::factory()->inactive()->create([
                'company_id' => $this->company->id,
                'name' => 'Inactive Template',
                'currency' => 'EUR',
            ]);
            InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Customer Template',
                'customer_id' => $this->customer->id,
                'currency' => 'GBP',
            ]);
        });

        it('lists all templates', function () {
            $this->artisan('invoice:template:list', [
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Active Template')
                ->expectsOutputToContain('Inactive Template')
                ->expectsOutputToContain('Customer Template');
        });

        it('filters templates by active status', function () {
            $this->artisan('invoice:template:list', [
                '--active' => true,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Active Template')
                ->doesntExpectOutputToContain('Inactive Template');
        });

        it('filters templates by customer', function () {
            $this->artisan('invoice:template:list', [
                '--customer' => $this->customer->id,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Customer Template')
                ->doesntExpectOutputToContain('Active Template');
        });

        it('exports templates as JSON', function () {
            $this->artisan('invoice:template:list', [
                '--format' => 'json',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);
        });

        it('shows summary statistics', function () {
            $this->artisan('invoice:template:list', [
                '--summary' => true,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Total Templates: 3')
                ->expectsOutputToContain('Active Templates: 2')
                ->expectsOutputToContain('Inactive Templates: 1');
        });
    });

    describe('invoice:template:show', function () {
        beforeEach(function () {
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Show Template',
                'description' => 'Template for show command',
                'currency' => 'USD',
                'template_data' => [
                    'notes' => 'Test notes',
                    'terms' => 'Test terms',
                    'line_items' => [
                        [
                            'description' => 'Test Item',
                            'quantity' => 2,
                            'unit_price' => 100.00,
                            'tax_rate' => 10,
                        ],
                    ],
                ],
            ]);
        });

        it('shows template details', function () {
            $this->artisan('invoice:template:show', [
                'template' => $this->template->id,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Show Template')
                ->expectsOutputToContain('Template for show command')
                ->expectsOutputToContain('Test Item');
        });

        it('shows template by name', function () {
            $this->artisan('invoice:template:show', [
                'template' => 'Show Template',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Show Template');
        });

        it('shows summary only', function () {
            $this->artisan('invoice:template:show', [
                'template' => $this->template->id,
                '--summary' => true,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Show Template')
                ->expectsOutputToContain('Total: $220.00'); // 2 * 100 + 20 tax
        });

        it('exports as JSON', function () {
            $this->artisan('invoice:template:show', [
                'template' => $this->template->id,
                '--format' => 'json',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);
        });

        it('handles template not found', function () {
            $this->artisan('invoice:template:show', [
                'template' => 'non-existent-template',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(1);
        });
    });

    describe('invoice:template:apply', function () {
        beforeEach(function () {
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Apply Template',
                'currency' => 'USD',
                'template_data' => [
                    'line_items' => [
                        [
                            'description' => 'Service Item',
                            'quantity' => 5,
                            'unit_price' => 80.00,
                            'tax_rate' => 8,
                        ],
                    ],
                ],
            ]);
        });

        it('applies template to create invoice', function () {
            $this->artisan('invoice:template:apply', [
                'template' => $this->template->id,
                '--customer' => $this->customer->id,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Template applied successfully!')
                ->expectsOutputToContain('Service Item')
                ->expectsOutputToContain('$432.00'); // 5 * 80 + 32 tax
        });

        it('applies template with overrides', function () {
            $this->artisan('invoice:template:apply', [
                'template' => $this->template->id,
                '--customer' => $this->customer->id,
                '--overrides' => 'currency=EUR,notes=Custom notes',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Currency: EUR')
                ->expectsOutputToContain('Custom notes');
        });

        it('performs dry run', function () {
            $this->artisan('invoice:template:apply', [
                'template' => $this->template->id,
                '--customer' => $this->customer->id,
                '--dry-run' => true,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('[DRY RUN] Template application preview');
        });

        it('saves invoice to draft', function () {
            $this->artisan('invoice:template:apply', [
                'template' => $this->template->id,
                '--customer' => $this->customer->id,
                '--save-draft' => true,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Invoice saved as draft');
        });
    });

    describe('invoice:template:update', function () {
        beforeEach(function () {
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Original Template',
                'description' => 'Original description',
            ]);
        });

        it('updates template name and description', function () {
            $this->artisan('invoice:template:update', [
                'template' => $this->template->id,
                '--name' => 'Updated Template',
                '--description' => 'Updated description',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Template updated successfully!');

            $this->template->refresh();
            expect($this->template->name)->toBe('Updated Template');
            expect($this->template->description)->toBe('Updated description');
        });

        it('toggles template status', function () {
            expect($this->template->is_active)->toBeTrue();

            $this->artisan('invoice:template:update', [
                'template' => $this->template->id,
                '--deactivate' => true,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);

            $this->template->refresh();
            expect($this->template->is_active)->toBeFalse();
        });

        it('updates line items', function () {
            $this->artisan('invoice:template:update', [
                'template' => $this->template->id,
                '--items' => 'New Item:3:150:12',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);

            $this->template->refresh();
            $lineItems = $this->template->template_data['line_items'] ?? [];
            expect($lineItems)->toHaveCount(1);
            expect($lineItems[0]['description'])->toBe('New Item');
        });
    });

    describe('invoice:template:delete', function () {
        it('deletes template with confirmation', function () {
            $template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Template to Delete',
            ]);

            $this->artisan('invoice:template:delete', [
                'template' => $template->id,
                '--force' => true, // Skip confirmation for testing
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Template deleted successfully!');

            expect(InvoiceTemplate::find($template->id))->toBeNull();
        });

        it('handles template not found', function () {
            $this->artisan('invoice:template:delete', [
                'template' => 'non-existent-template',
                '--force' => true,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(1);
        });
    });

    describe('invoice:template:duplicate', function () {
        beforeEach(function () {
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Original Template',
                'currency' => 'USD',
                'template_data' => [
                    'line_items' => [
                        [
                            'description' => 'Original Item',
                            'quantity' => 1,
                            'unit_price' => 100.00,
                        ],
                    ],
                ],
            ]);
        });

        it('duplicates template with new name', function () {
            $this->artisan('invoice:template:duplicate', [
                'template' => $this->template->id,
                'name' => 'Duplicated Template',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutputToContain('Template duplicated successfully!');

            $duplicate = InvoiceTemplate::where('name', 'Duplicated Template')->first();
            expect($duplicate)->not->toBeNull();
            expect($duplicate->company_id)->toBe($this->company->id);
            expect($duplicate->currency)->toBe('USD');
        });

        it('duplicates template with modifications', function () {
            $this->artisan('invoice:template:duplicate', [
                'template' => $this->template->id,
                'name' => 'Modified Duplicate',
                '--currency' => 'EUR',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);

            $duplicate = InvoiceTemplate::where('name', 'Modified Duplicate')->first();
            expect($duplicate->currency)->toBe('EUR');
        });
    });

    describe('Command error handling', function () {
        it('handles invalid company ID', function () {
            $this->artisan('invoice:template:list', [
                '--company' => 'invalid-uuid',
            ])
                ->assertExitCode(1);
        });

        it('handles missing company context', function () {
            $this->artisan('invoice:template:list')
                ->assertExitCode(1);
        });

        it('handles permission denied', function () {
            // Create user without company access
            $otherUser = User::factory()->create();

            $this->artisan('invoice:template:list', [
                '--company' => $this->company->id,
                '--user' => $otherUser->id,
            ])
                ->assertExitCode(1);
        });
    });

    describe('Natural language processing', function () {
        it('processes natural language input', function () {
            $this->artisan('invoice:template:create', [
                'name' => 'NLP Template',
                '--input' => 'Create a web design template for $5000 with 10 items',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);
        });
    });
});
