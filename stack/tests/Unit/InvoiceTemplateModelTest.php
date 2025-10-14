<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('InvoiceTemplate Model', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    });

    describe('Relationships', function () {
        it('belongs to company', function () {
            $template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
            ]);

            expect($template->company)->toBeInstanceOf(Company::class);
            expect($template->company->id)->toBe($this->company->id);
        });

        it('belongs to customer', function () {
            $template = InvoiceTemplate::factory()->create([
                'customer_id' => $this->customer->id,
            ]);

            expect($template->customer)->toBeInstanceOf(Customer::class);
            expect($template->customer->id)->toBe($this->customer->id);
        });

        it('can have null customer', function () {
            $template = InvoiceTemplate::factory()->create([
                'customer_id' => null,
            ]);

            expect($template->customer)->toBeNull();
        });

        it('belongs to creator', function () {
            $template = InvoiceTemplate::factory()->create([
                'creator_id' => $this->user->id,
            ]);

            expect($template->creator)->toBeInstanceOf(User::class);
            expect($template->creator->id)->toBe($this->user->id);
        });
    });

    describe('Scopes', function () {
        beforeEach(function () {
            InvoiceTemplate::factory()->active()->create([
                'company_id' => $this->company->id,
            ]);
            InvoiceTemplate::factory()->inactive()->create([
                'company_id' => $this->company->id,
            ]);
        });

        it('scopes to active templates', function () {
            $activeTemplates = InvoiceTemplate::active()->get();
            expect($activeTemplates)->toHaveCount(1);
            expect($activeTemplates->first()->is_active)->toBeTrue();
        });

        it('scopes to inactive templates', function () {
            $inactiveTemplates = InvoiceTemplate::inactive()->get();
            expect($inactiveTemplates)->toHaveCount(1);
            expect($inactiveTemplates->first()->is_active)->toBeFalse();
        });

        it('scopes by customer', function () {
            $customerTemplate = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'customer_id' => $this->customer->id,
            ]);

            $customerTemplates = InvoiceTemplate::forCustomer($this->customer->id)->get();
            expect($customerTemplates)->toHaveCount(1);
            expect($customerTemplates->first()->id)->toBe($customerTemplate->id);
        });

        it('scopes to general templates (no customer)', function () {
            $generalTemplate = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'customer_id' => null,
            ]);

            $generalTemplates = InvoiceTemplate::general()->get();
            expect($generalTemplates)->toHaveCount(1);
            expect($generalTemplates->first()->id)->toBe($generalTemplate->id);
        });

        it('scopes by currency', function () {
            InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'currency' => 'USD',
            ]);
            InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'currency' => 'EUR',
            ]);

            $usdTemplates = InvoiceTemplate::inCurrency('USD')->get();
            expect($usdTemplates)->toHaveCount(1);
            expect($usdTemplates->first()->currency)->toBe('USD');
        });

        it('chains scopes', function () {
            $activeUsdTemplate = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'currency' => 'USD',
                'is_active' => true,
            ]);

            $templates = InvoiceTemplate::active()->inCurrency('USD')->get();
            expect($templates)->toHaveCount(1);
            expect($templates->first()->id)->toBe($activeUsdTemplate->id);
        });
    });

    describe('Attributes', function () {
        it('casts template_data as array', function () {
            $templateData = [
                'line_items' => [
                    ['description' => 'Test Item'],
                ],
            ];

            $template = InvoiceTemplate::factory()->create([
                'template_data' => $templateData,
            ]);

            expect($template->template_data)->toBeArray();
            expect($template->template_data['line_items'])->toHaveCount(1);
        });

        it('casts settings as array', function () {
            $settings = [
                'auto_apply_tax' => true,
                'default_terms' => 30,
            ];

            $template = InvoiceTemplate::factory()->create([
                'settings' => $settings,
            ]);

            expect($template->settings)->toBeArray();
            expect($template->settings['auto_apply_tax'])->toBeTrue();
        });

        it('casts is_active as boolean', function () {
            $template = InvoiceTemplate::factory()->create(['is_active' => true]);
            expect($template->is_active)->toBeBool();
            expect($template->is_active)->toBeTrue();

            $inactiveTemplate = InvoiceTemplate::factory()->create(['is_active' => false]);
            expect($inactiveTemplate->is_active)->toBeBool();
            expect($inactiveTemplate->is_active)->toBeFalse();
        });
    });

    describe('Business Logic', function () {
        describe('applyToInvoice', function () {
            beforeEach(function () {
                $this->template = InvoiceTemplate::factory()->create([
                    'company_id' => $this->company->id,
                    'currency' => 'USD',
                    'template_data' => [
                        'notes' => 'Template notes',
                        'terms' => 'Template terms',
                        'payment_terms' => 30,
                        'line_items' => [
                            [
                                'description' => 'Service Item',
                                'quantity' => 5,
                                'unit_price' => 100.00,
                                'tax_rate' => 10,
                                'discount_amount' => 50,
                            ],
                        ],
                    ],
                ]);
            });

            it('applies template without customer or overrides', function () {
                $invoiceData = $this->template->applyToInvoice();

                expect($invoiceData)->toHaveKey('currency');
                expect($invoiceData['currency'])->toBe('USD');
                expect($invoiceData)->toHaveKey('line_items');
                expect($invoiceData['line_items'])->toHaveCount(1);
                expect($invoiceData['line_items'][0]['description'])->toBe('Service Item');
                expect($invoiceData['line_items'][0]['quantity'])->toBe(5);
                expect($invoiceData['line_items'][0]['unit_price'])->toBe(100.00);
                expect($invoiceData)->toHaveKey('notes');
                expect($invoiceData['notes'])->toBe('Template notes');
                expect($invoiceData)->toHaveKey('terms');
                expect($invoiceData['terms'])->toBe('Template terms');
                expect($invoiceData)->toHaveKey('payment_terms');
                expect($invoiceData['payment_terms'])->toBe(30);
            });

            it('applies template with customer', function () {
                $invoiceData = $this->template->applyToInvoice($this->customer);

                expect($invoiceData['customer_id'])->toBe($this->customer->id);
            });

            it('applies template with overrides', function () {
                $overrides = [
                    'currency' => 'EUR',
                    'notes' => 'Overridden notes',
                    'payment_terms' => 60,
                ];

                $invoiceData = $this->template->applyToInvoice(null, $overrides);

                expect($invoiceData['currency'])->toBe('EUR');
                expect($invoiceData['notes'])->toBe('Overridden notes');
                expect($invoiceData['payment_terms'])->toBe(60);
                // Non-overridden values should remain
                expect($invoiceData['terms'])->toBe('Template terms');
            });

            it('applies line item overrides', function () {
                $overrides = [
                    'line_items_overrides' => [
                        0 => [
                            'unit_price' => 150.00,
                            'tax_rate' => 15,
                        ],
                    ],
                ];

                $invoiceData = $this->template->applyToInvoice(null, $overrides);

                expect($invoiceData['line_items'][0]['unit_price'])->toBe(150.00);
                expect($invoiceData['line_items'][0]['tax_rate'])->toBe(15);
                // Non-overridden fields should remain
                expect($invoiceData['line_items'][0]['description'])->toBe('Service Item');
                expect($invoiceData['line_items'][0]['quantity'])->toBe(5);
            });

            it('adds additional line items', function () {
                $overrides = [
                    'additional_line_items' => [
                        [
                            'description' => 'Additional Service',
                            'quantity' => 2,
                            'unit_price' => 75.00,
                            'tax_rate' => 5,
                            'discount_amount' => 0,
                        ],
                    ],
                ];

                $invoiceData = $this->template->applyToInvoice(null, $overrides);

                expect($invoiceData['line_items'])->toHaveCount(2);
                expect($invoiceData['line_items'][1]['description'])->toBe('Additional Service');
                expect($invoiceData['line_items'][1]['quantity'])->toBe(2);
                expect($invoiceData['line_items'][1]['unit_price'])->toBe(75.00);
            });

            it('calculates line item totals correctly', function () {
                $invoiceData = $this->template->applyToInvoice();
                $lineItem = $invoiceData['line_items'][0];

                // 5 * 100 = 500 subtotal
                // 500 * 0.10 = 50 tax
                // 500 + 50 - 50 = 500 total
                expect($lineItem['subtotal'])->toBe(500.0);
                expect($lineItem['tax_amount'])->toBe(50.0);
                expect($lineItem['total'])->toBe(500.0);
            });
        });

        describe('getSummary', function () {
            beforeEach(function () {
                $this->template = InvoiceTemplate::factory()->create([
                    'name' => 'Test Template',
                    'description' => 'Test Description',
                    'currency' => 'USD',
                    'customer_id' => $this->customer->id,
                    'template_data' => [
                        'line_items' => [
                            [
                                'description' => 'Item 1',
                                'quantity' => 2,
                                'unit_price' => 100.00,
                                'tax_rate' => 10,
                            ],
                            [
                                'description' => 'Item 2',
                                'quantity' => 1,
                                'unit_price' => 50.00,
                                'tax_rate' => 5,
                            ],
                        ],
                    ],
                ]);
            });

            it('returns template summary', function () {
                $summary = $this->template->getSummary();

                expect($summary)->toHaveKey('name');
                expect($summary)->toHaveKey('description');
                expect($summary)->toHaveKey('currency');
                expect($summary)->toHaveKey('customer_name');
                expect($summary)->toHaveKey('line_items_count');
                expect($summary)->toHaveKey('subtotal');
                expect($summary)->toHaveKey('tax_amount');
                expect($summary)->toHaveKey('total_amount');
                expect($summary)->toHaveKey('is_active');

                expect($summary['name'])->toBe('Test Template');
                expect($summary['description'])->toBe('Test Description');
                expect($summary['currency'])->toBe('USD');
                expect($summary['customer_name'])->toBe($this->customer->name);
                expect($summary['line_items_count'])->toBe(2);
                expect($summary['is_active'])->toBeTrue();
            });

            it('calculates totals correctly', function () {
                $summary = $this->template->getSummary();

                // Item 1: 2 * 100 = 200, tax = 20
                // Item 2: 1 * 50 = 50, tax = 2.5
                // Subtotal: 250, Tax: 22.5, Total: 272.5
                expect($summary['subtotal'])->toBe(250.0);
                expect($summary['tax_amount'])->toBe(22.5);
                expect($summary['total_amount'])->toBe(272.5);
            });

            it('handles template without customer', function () {
                $generalTemplate = InvoiceTemplate::factory()->create([
                    'customer_id' => null,
                    'template_data' => [
                        'line_items' => [
                            [
                                'description' => 'Item',
                                'quantity' => 1,
                                'unit_price' => 100,
                                'tax_rate' => 0,
                            ],
                        ],
                    ],
                ]);

                $summary = $generalTemplate->getSummary();
                expect($summary['customer_name'])->toBe('General');
            });
        });

        describe('validateTemplate', function () {
            it('validates correct template', function () {
                $template = InvoiceTemplate::factory()->create([
                    'name' => 'Valid Template',
                    'currency' => 'USD',
                    'template_data' => [
                        'line_items' => [
                            [
                                'description' => 'Valid Item',
                                'quantity' => 1,
                                'unit_price' => 100,
                                'tax_rate' => 10,
                            ],
                        ],
                    ],
                ]);

                $errors = $template->validateTemplate();
                expect($errors)->toBeEmpty();
            });

            it('returns errors for invalid template', function () {
                $template = InvoiceTemplate::factory()->create([
                    'name' => '', // Invalid empty name
                    'currency' => 'INVALID', // Invalid currency
                    'template_data' => [
                        'line_items' => [], // Invalid empty line items
                    ],
                ]);

                $errors = $template->validateTemplate();
                expect($errors)->not->toBeEmpty();
                expect($errors)->toHaveKey('name');
                expect($errors)->toHaveKey('currency');
                expect($errors)->toHaveKey('line_items');
            });

            it('validates line item structure', function () {
                $template = InvoiceTemplate::factory()->create([
                    'name' => 'Test Template',
                    'currency' => 'USD',
                    'template_data' => [
                        'line_items' => [
                            [
                                'description' => '', // Invalid empty description
                                'quantity' => -1, // Invalid negative quantity
                                'unit_price' => -100, // Invalid negative price
                                'tax_rate' => 150, // Invalid tax rate > 100
                            ],
                        ],
                    ],
                ]);

                $errors = $template->validateTemplate();
                expect($errors)->toHaveKey('line_items.0.description');
                expect($errors)->toHaveKey('line_items.0.quantity');
                expect($errors)->toHaveKey('line_items.0.unit_price');
                expect($errors)->toHaveKey('line_items.0.tax_rate');
            });
        });

        describe('Factory States', function () {
            it('creates active template', function () {
                $template = InvoiceTemplate::factory()->active()->create();
                expect($template->is_active)->toBeTrue();
            });

            it('creates inactive template', function () {
                $template = InvoiceTemplate::factory()->inactive()->create();
                expect($template->is_active)->toBeFalse();
            });

            it('creates template with customer', function () {
                $template = InvoiceTemplate::factory()->withCustomer($this->customer)->create();
                expect($template->customer_id)->toBe($this->customer->id);
            });

            it('creates template with line items', function () {
                $template = InvoiceTemplate::factory()->withLineItems(3)->create();
                $lineItems = $template->template_data['line_items'] ?? [];
                expect($lineItems)->toHaveCount(3);
            });
        });
    });
});
