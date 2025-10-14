<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\InvoiceTemplate;
use App\Models\User;
use App\Services\InvoiceTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('InvoiceTemplateService', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user->id, ['role' => 'owner']);
        $this->service = new InvoiceTemplateService;
    });

    describe('getTemplatesForCompany', function () {
        it('returns paginated templates for company', function () {
            $templates = InvoiceTemplate::factory()->count(5)->create([
                'company_id' => $this->company->id,
            ]);

            $result = $this->service->getTemplatesForCompany($this->company, $this->user);

            expect($result)->toHaveCount(5);
            expect($result->first())->toBeInstanceOf(InvoiceTemplate);
        });

        it('filters templates by active status', function () {
            InvoiceTemplate::factory()->active()->create([
                'company_id' => $this->company->id,
            ]);
            InvoiceTemplate::factory()->inactive()->create([
                'company_id' => $this->company->id,
            ]);

            $result = $this->service->getTemplatesForCompany($this->company, $this->user, [
                'is_active' => true,
            ]);

            expect($result)->toHaveCount(1);
            expect($result->first()->is_active)->toBeTrue();
        });

        it('filters templates by customer', function () {
            $customer = Customer::factory()->create(['company_id' => $this->company->id]);

            InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'customer_id' => $customer->id,
            ]);
            InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'customer_id' => null,
            ]);

            $result = $this->service->getTemplatesForCompany($this->company, $this->user, [
                'customer_id' => $customer->id,
            ]);

            expect($result)->toHaveCount(1);
            expect($result->first()->customer_id)->toBe($customer->id);
        });

        it('searches templates by name and description', function () {
            InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Web Design Services',
                'description' => 'Template for web design projects',
            ]);
            InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Consulting Services',
                'description' => 'Template for consulting work',
            ]);

            $result = $this->service->getTemplatesForCompany($this->company, $this->user, [
                'search' => 'web design',
            ]);

            expect($result)->toHaveCount(1);
            expect($result->first()->name)->toBe('Web Design Services');
        });

        it('does not return templates from other companies', function () {
            $otherCompany = Company::factory()->create();
            InvoiceTemplate::factory()->create([
                'company_id' => $otherCompany->id,
            ]);

            $result = $this->service->getTemplatesForCompany($this->company, $this->user);

            expect($result)->toHaveCount(0);
        });
    });

    describe('createTemplate', function () {
        it('creates a new template with valid data', function () {
            $data = [
                'name' => 'Test Template',
                'description' => 'Test Description',
                'customer_id' => null,
                'currency' => 'USD',
                'template_data' => [
                    'notes' => 'Test notes',
                    'terms' => 'Test terms',
                    'payment_terms' => 30,
                    'line_items' => [
                        [
                            'description' => 'Test Item',
                            'quantity' => 1,
                            'unit_price' => 100.00,
                            'tax_rate' => 10,
                            'discount_amount' => 0,
                        ],
                    ],
                ],
                'settings' => [
                    'auto_apply_tax' => true,
                ],
                'is_active' => true,
            ];

            $template = $this->service->createTemplate($this->company, $data, $this->user);

            expect($template)->toBeInstanceOf(InvoiceTemplate);
            expect($template->name)->toBe('Test Template');
            expect($template->company_id)->toBe($this->company->id);
            expect($template->creator_id)->toBe($this->user->id);
            expect($template->is_active)->toBeTrue();
        });

        it('throws exception for invalid data', function () {
            $invalidData = [
                'name' => '', // Invalid: empty name
                'currency' => 'INVALID', // Invalid: not 3 chars
                'template_data' => [], // Invalid: no line items
            ];

            expect(fn () => $this->service->createTemplate($this->company, $invalidData, $this->user))
                ->toThrow('Validation failed');
        });
    });

    describe('findTemplateByIdentifier', function () {
        beforeEach(function () {
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Test Template',
            ]);
        });

        it('finds template by UUID', function () {
            $found = $this->service->findTemplateByIdentifier($this->template->id, $this->company);
            expect($found->id)->toBe($this->template->id);
        });

        it('finds template by name', function () {
            $found = $this->service->findTemplateByIdentifier('Test Template', $this->company);
            expect($found->id)->toBe($this->template->id);
        });

        it('finds template by partial name', function () {
            $found = $this->service->findTemplateByIdentifier('Test', $this->company);
            expect($found->id)->toBe($this->template->id);
        });

        it('throws exception when template not found', function () {
            expect(fn () => $this->service->findTemplateByIdentifier('Non-existent', $this->company))
                ->toThrow('Template not found');
        });
    });

    describe('applyTemplate', function () {
        beforeEach(function () {
            $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'currency' => 'USD',
                'template_data' => [
                    'notes' => 'Template notes',
                    'terms' => 'Template terms',
                    'payment_terms' => 30,
                    'line_items' => [
                        [
                            'description' => 'Web Design',
                            'quantity' => 10,
                            'unit_price' => 50.00,
                            'tax_rate' => 10,
                            'discount_amount' => 0,
                        ],
                    ],
                ],
            ]);
        });

        it('applies template to create invoice data', function () {
            $invoiceData = $this->service->applyTemplate(
                $this->template,
                $this->customer,
                [],
                $this->user
            );

            expect($invoiceData)->toHaveKey('customer_id');
            expect($invoiceData)->toHaveKey('currency');
            expect($invoiceData)->toHaveKey('line_items');
            expect($invoiceData)->toHaveKey('notes');
            expect($invoiceData)->toHaveKey('terms');
            expect($invoiceData['customer_id'])->toBe($this->customer->id);
            expect($invoiceData['currency'])->toBe('USD');
            expect($invoiceData['line_items'])->toHaveCount(1);
            expect($invoiceData['line_items'][0]['description'])->toBe('Web Design');
        });

        it('applies template with overrides', function () {
            $overrides = [
                'currency' => 'EUR',
                'notes' => 'Overridden notes',
                'line_items_overrides' => [
                    0 => ['unit_price' => 75.00],
                ],
            ];

            $invoiceData = $this->service->applyTemplate(
                $this->template,
                $this->customer,
                $overrides,
                $this->user
            );

            expect($invoiceData['currency'])->toBe('EUR');
            expect($invoiceData['notes'])->toBe('Overridden notes');
            expect($invoiceData['line_items'][0]['unit_price'])->toBe(75.00);
        });

        it('adds additional line items', function () {
            $overrides = [
                'additional_line_items' => [
                    [
                        'description' => 'Additional Service',
                        'quantity' => 5,
                        'unit_price' => 25.00,
                        'tax_rate' => 5,
                        'discount_amount' => 0,
                    ],
                ],
            ];

            $invoiceData = $this->service->applyTemplate(
                $this->template,
                $this->customer,
                $overrides,
                $this->user
            );

            expect($invoiceData['line_items'])->toHaveCount(2);
            expect($invoiceData['line_items'][1]['description'])->toBe('Additional Service');
        });
    });

    describe('updateTemplate', function () {
        beforeEach(function () {
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Original Name',
            ]);
        });

        it('updates template with valid data', function () {
            $updateData = [
                'name' => 'Updated Name',
                'description' => 'Updated Description',
            ];

            $updatedTemplate = $this->service->updateTemplate($this->template, $updateData, $this->user);

            expect($updatedTemplate->name)->toBe('Updated Name');
            expect($updatedTemplate->description)->toBe('Updated Description');
        });

        it('throws exception for invalid updates', function () {
            $invalidData = [
                'currency' => 'INVALID', // Invalid: not 3 chars
            ];

            expect(fn () => $this->service->updateTemplate($this->template, $invalidData, $this->user))
                ->toThrow('Validation failed');
        });
    });

    describe('deleteTemplate', function () {
        it('deletes template successfully', function () {
            $template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
            ]);

            $this->service->deleteTemplate($template, $this->user);

            expect(InvoiceTemplate::find($template->id))->toBeNull();
        });
    });

    describe('duplicateTemplate', function () {
        beforeEach(function () {
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Original Template',
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
            $duplicate = $this->service->duplicateTemplate(
                $this->template,
                'Duplicate Template',
                [],
                $this->user
            );

            expect($duplicate->name)->toBe('Duplicate Template');
            expect($duplicate->company_id)->toBe($this->company->id);
            expect($duplicate->creator_id)->toBe($this->user->id);
            expect($duplicate->template_data['line_items'])->toHaveCount(1);
            expect($duplicate->template_data['line_items'][0]['description'])->toBe('Original Item');
        });

        it('applies modifications during duplication', function () {
            $modifications = [
                'currency' => 'EUR',
                'line_items_overrides' => [
                    0 => ['unit_price' => 150.00],
                ],
            ];

            $duplicate = $this->service->duplicateTemplate(
                $this->template,
                'Modified Duplicate',
                $modifications,
                $this->user
            );

            expect($duplicate->currency)->toBe('EUR');
            expect($duplicate->template_data['line_items'][0]['unit_price'])->toBe(150.00);
        });
    });

    describe('getTemplateStatistics', function () {
        it('returns template statistics', function () {
            InvoiceTemplate::factory()->active()->count(3)->create([
                'company_id' => $this->company->id,
            ]);
            InvoiceTemplate::factory()->inactive()->count(2)->create([
                'company_id' => $this->company->id,
            ]);

            $stats = $this->service->getTemplateStatistics($this->company, $this->user);

            expect($stats['total_templates'])->toBe(5);
            expect($stats['active_templates'])->toBe(3);
            expect($stats['inactive_templates'])->toBe(2);
        });
    });

    describe('validateTemplateStructure', function () {
        it('validates correct template structure', function () {
            $validData = [
                'name' => 'Test Template',
                'currency' => 'USD',
                'template_data' => [
                    'line_items' => [
                        [
                            'description' => 'Test Item',
                            'quantity' => 1,
                            'unit_price' => 100.00,
                        ],
                    ],
                ],
            ];

            $result = $this->service->validateTemplateStructure($validData);

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });

        it('returns errors for invalid structure', function () {
            $invalidData = [
                'name' => '',
                'currency' => 'INVALID',
                'template_data' => [
                    'line_items' => [], // Empty line items
                ],
            ];

            $result = $this->service->validateTemplateStructure($invalidData);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
        });
    });
});
