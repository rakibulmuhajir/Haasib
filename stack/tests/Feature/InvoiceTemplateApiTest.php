<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('Invoice Template API Endpoints', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user->id, ['role' => 'owner']);

        // Create a test customer
        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Customer',
        ]);

        Sanctum::actingAs($this->user);
    });

    describe('GET /api/templates', function () {
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
        });

        it('returns paginated templates', function () {
            $response = $this->getJson('/api/templates');

            $response->assertSuccessful()
                ->assertJsonStructure([
                    'templates' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'customer',
                            'currency',
                            'is_active',
                            'line_items_count',
                            'total_amount',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'statistics',
                ]);

            $responseData = $response->json();
            expect($responseData['templates'])->toHaveCount(2);
        });

        it('filters templates by active status', function () {
            $response = $this->getJson('/api/templates?is_active=1');

            $response->assertSuccessful();
            $responseData = $response->json();
            expect($responseData['templates'])->toHaveCount(1);
            expect($responseData['templates'][0]['is_active'])->toBeTrue();
        });

        it('filters templates by customer', function () {
            // Create template with customer
            InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'customer_id' => $this->customer->id,
                'name' => 'Customer Template',
            ]);

            $response = $this->getJson("/api/templates?customer_id={$this->customer->id}");

            $response->assertSuccessful();
            $responseData = $response->json();
            expect($responseData['templates'])->toHaveCount(1);
            expect($responseData['templates'][0]['customer']['id'])->toBe($this->customer->id);
        });

        it('searches templates by name', function () {
            $response = $this->getJson('/api/templates?search=Active');

            $response->assertSuccessful();
            $responseData = $response->json();
            expect($responseData['templates'])->toHaveCount(1);
            expect($responseData['templates'][0]['name'])->toBe('Active Template');
        });
    });

    describe('POST /api/templates', function () {
        it('creates a new template', function () {
            $templateData = [
                'name' => 'API Template',
                'description' => 'Created via API',
                'customer_id' => null,
                'currency' => 'USD',
                'template_data' => [
                    'notes' => 'API notes',
                    'terms' => 'API terms',
                    'payment_terms' => 30,
                    'line_items' => [
                        [
                            'description' => 'API Item',
                            'quantity' => 2,
                            'unit_price' => 150.00,
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

            $response = $this->postJson('/api/templates', $templateData);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'template' => [
                        'id',
                        'name',
                        'description',
                        'customer',
                        'currency',
                        'is_active',
                        'line_items_count',
                        'created_at',
                    ],
                ]);

            $responseData = $response->json();
            expect($responseData['message'])->toBe('Template created successfully');
            expect($responseData['template']['name'])->toBe('API Template');
        });

        it('validates required fields', function () {
            $invalidData = [
                'name' => '', // Invalid: empty name
                'currency' => 'INVALID', // Invalid: not 3 chars
                'template_data' => [], // Invalid: no line items
            ];

            $response = $this->postJson('/api/templates', $invalidData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'currency', 'template_data.line_items']);
        });

        it('validates customer exists in company', function () {
            $otherCompany = Company::factory()->create();
            $otherCustomer = Customer::factory()->create(['company_id' => $otherCompany->id]);

            $templateData = [
                'name' => 'Invalid Customer Template',
                'currency' => 'USD',
                'customer_id' => $otherCustomer->id,
                'template_data' => [
                    'line_items' => [
                        [
                            'description' => 'Item',
                            'quantity' => 1,
                            'unit_price' => 100,
                        ],
                    ],
                ],
            ];

            $response = $this->postJson('/api/templates', $templateData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['customer_id']);
        });
    });

    describe('GET /api/templates/{id}', function () {
        beforeEach(function () {
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Show Template',
                'description' => 'Template for show endpoint',
                'currency' => 'USD',
                'template_data' => [
                    'line_items' => [
                        [
                            'description' => 'Test Item',
                            'quantity' => 3,
                            'unit_price' => 75.00,
                            'tax_rate' => 8,
                        ],
                    ],
                ],
            ]);
        });

        it('returns template details', function () {
            $response = $this->getJson("/api/templates/{$this->template->id}");

            $response->assertSuccessful()
                ->assertJsonStructure([
                    'template' => [
                        'id',
                        'name',
                        'description',
                        'customer',
                        'currency',
                        'is_active',
                        'template_data',
                        'settings',
                        'creator',
                        'created_at',
                        'updated_at',
                        'summary',
                        'validation_errors',
                    ],
                ]);

            $responseData = $response->json();
            expect($responseData['template']['name'])->toBe('Show Template');
            expect($responseData['template']['template_data']['line_items'])->toHaveCount(1);
        });

        it('returns 404 for non-existent template', function () {
            $fakeUuid = '550e8400-e29b-41d4-a716-446655440000';
            $response = $this->getJson("/api/templates/{$fakeUuid}");

            $response->assertNotFound();
        });

        it('prevents access to templates from other companies', function () {
            $otherCompany = Company::factory()->create();
            $otherTemplate = InvoiceTemplate::factory()->create([
                'company_id' => $otherCompany->id,
            ]);

            $response = $this->getJson("/api/templates/{$otherTemplate->id}");

            $response->assertNotFound();
        });
    });

    describe('PUT /api/templates/{id}', function () {
        beforeEach(function () {
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Original Template',
                'description' => 'Original description',
            ]);
        });

        it('updates template', function () {
            $updateData = [
                'name' => 'Updated Template',
                'description' => 'Updated description',
                'currency' => 'EUR',
                'template_data' => [
                    'line_items' => [
                        [
                            'description' => 'Updated Item',
                            'quantity' => 1,
                            'unit_price' => 200.00,
                            'tax_rate' => 15,
                        ],
                    ],
                ],
            ];

            $response = $this->putJson("/api/templates/{$this->template->id}", $updateData);

            $response->assertSuccessful()
                ->assertJsonStructure([
                    'message',
                    'template' => [
                        'id',
                        'name',
                        'description',
                        'currency',
                        'updated_at',
                    ],
                ]);

            $responseData = $response->json();
            expect($responseData['message'])->toBe('Template updated successfully');
            expect($responseData['template']['name'])->toBe('Updated Template');

            $this->template->refresh();
            expect($this->template->name)->toBe('Updated Template');
        });

        it('validates update data', function () {
            $invalidData = [
                'currency' => 'INVALID', // Invalid: not 3 chars
                'template_data' => [
                    'line_items' => [], // Invalid: empty line items
                ],
            ];

            $response = $this->putJson("/api/templates/{$this->template->id}", $invalidData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['currency', 'template_data.line_items']);
        });

        it('prevents updating templates from other companies', function () {
            $otherCompany = Company::factory()->create();
            $otherTemplate = InvoiceTemplate::factory()->create([
                'company_id' => $otherCompany->id,
            ]);

            $response = $this->putJson("/api/templates/{$otherTemplate->id}", [
                'name' => 'Hacked Template',
            ]);

            $response->assertNotFound();
        });
    });

    describe('DELETE /api/templates/{id}', function () {
        it('deletes template', function () {
            $template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Template to Delete',
            ]);

            $response = $this->deleteJson("/api/templates/{$template->id}");

            $response->assertSuccessful()
                ->assertJson(['message' => 'Template deleted successfully']);

            expect(InvoiceTemplate::find($template->id))->toBeNull();
        });

        it('prevents deleting templates from other companies', function () {
            $otherCompany = Company::factory()->create();
            $otherTemplate = InvoiceTemplate::factory()->create([
                'company_id' => $otherCompany->id,
            ]);

            $response = $this->deleteJson("/api/templates/{$otherTemplate->id}");

            $response->assertNotFound();
        });
    });

    describe('POST /api/templates/{id}/apply', function () {
        beforeEach(function () {
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Apply Template',
                'currency' => 'USD',
                'template_data' => [
                    'line_items' => [
                        [
                            'description' => 'Service Item',
                            'quantity' => 4,
                            'unit_price' => 120.00,
                            'tax_rate' => 8,
                        ],
                    ],
                ],
            ]);
        });

        it('applies template to create invoice data', function () {
            $applyData = [
                'customer_id' => $this->customer->id,
                'overrides' => [
                    'currency' => 'EUR',
                    'notes' => 'Applied notes',
                ],
            ];

            $response = $this->postJson("/api/templates/{$this->template->id}/apply", $applyData);

            $response->assertSuccessful()
                ->assertJsonStructure([
                    'message',
                    'invoice_data',
                    'preview' => [
                        'template_name',
                        'customer',
                        'subtotal',
                        'tax',
                        'total',
                    ],
                ]);

            $responseData = $response->json();
            expect($responseData['message'])->toBe('Template applied successfully');
            expect($responseData['invoice_data']['customer_id'])->toBe($this->customer->id);
            expect($responseData['invoice_data']['currency'])->toBe('EUR');
            expect($responseData['preview']['template_name'])->toBe('Apply Template');
        });

        it('applies template without customer', function () {
            $applyData = [
                'overrides' => [
                    'notes' => 'General notes',
                ],
            ];

            $response = $this->postJson("/api/templates/{$this->template->id}/apply", $applyData);

            $response->assertSuccessful();
            $responseData = $response->json();
            expect($responseData['invoice_data']['customer_id'])->toBeNull();
        });

        it('validates customer exists', function () {
            $fakeUuid = '550e8400-e29b-41d4-a716-446655440000';
            $applyData = [
                'customer_id' => $fakeUuid,
            ];

            $response = $this->postJson("/api/templates/{$this->template->id}/apply", $applyData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['customer_id']);
        });
    });

    describe('POST /api/templates/{id}/duplicate', function () {
        beforeEach(function () {
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Original Template',
                'currency' => 'USD',
                'template_data' => [
                    'line_items' => [
                        [
                            'description' => 'Original Item',
                            'quantity' => 2,
                            'unit_price' => 85.00,
                        ],
                    ],
                ],
            ]);
        });

        it('duplicates template', function () {
            $duplicateData = [
                'name' => 'Duplicated Template',
                'modifications' => [
                    'currency' => 'EUR',
                ],
            ];

            $response = $this->postJson("/api/templates/{$this->template->id}/duplicate", $duplicateData);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'template' => [
                        'id',
                        'name',
                        'description',
                        'currency',
                        'is_active',
                        'created_at',
                    ],
                ]);

            $responseData = $response->json();
            expect($responseData['message'])->toBe('Template duplicated successfully');
            expect($responseData['template']['name'])->toBe('Duplicated Template');
            expect($responseData['template']['currency'])->toBe('EUR');

            $duplicate = InvoiceTemplate::find($responseData['template']['id']);
            expect($duplicate)->not->toBeNull();
            expect($duplicate->company_id)->toBe($this->company->id);
        });

        it('validates duplicate name', function () {
            $duplicateData = [
                'name' => '', // Invalid: empty name
            ];

            $response = $this->postJson("/api/templates/{$this->template->id}/duplicate", $duplicateData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });
    });

    describe('PATCH /api/templates/{id}/toggle-status', function () {
        beforeEach(function () {
            $this->template = InvoiceTemplate::factory()->create([
                'company_id' => $this->company->id,
                'is_active' => true,
            ]);
        });

        it('deactivates template', function () {
            $response = $this->patchJson("/api/templates/{$this->template->id}/toggle-status", [
                'is_active' => false,
            ]);

            $response->assertSuccessful()
                ->assertJson([
                    'message' => 'Template deactivated successfully',
                    'template' => [
                        'id' => $this->template->id,
                        'is_active' => false,
                    ],
                ]);

            $this->template->refresh();
            expect($this->template->is_active)->toBeFalse();
        });

        it('activates template', function () {
            $this->template->update(['is_active' => false]);

            $response = $this->patchJson("/api/templates/{$this->template->id}/toggle-status", [
                'is_active' => true,
            ]);

            $response->assertSuccessful()
                ->assertJson([
                    'message' => 'Template activated successfully',
                    'template' => [
                        'id' => $this->template->id,
                        'is_active' => true,
                    ],
                ]);

            $this->template->refresh();
            expect($this->template->is_active)->toBeTrue();
        });
    });

    describe('GET /api/templates/statistics', function () {
        it('returns template statistics', function () {
            // Create templates for statistics
            InvoiceTemplate::factory()->active()->count(4)->create([
                'company_id' => $this->company->id,
            ]);
            InvoiceTemplate::factory()->inactive()->count(2)->create([
                'company_id' => $this->company->id,
            ]);

            $response = $this->getJson('/api/templates/statistics');

            $response->assertSuccessful()
                ->assertJsonStructure([
                    'statistics' => [
                        'total_templates',
                        'active_templates',
                        'inactive_templates',
                        'templates_by_currency',
                        'recently_created',
                        'most_used',
                    ],
                ]);

            $responseData = $response->json();
            expect($responseData['statistics']['total_templates'])->toBe(6);
            expect($responseData['statistics']['active_templates'])->toBe(4);
            expect($responseData['statistics']['inactive_templates'])->toBe(2);
        });
    });

    describe('GET /api/templates/available-customers', function () {
        it('returns available customers', function () {
            // Create additional customers
            Customer::factory()->active()->count(3)->create([
                'company_id' => $this->company->id,
            ]);
            Customer::factory()->inactive()->create([
                'company_id' => $this->company->id,
            ]);

            $response = $this->getJson('/api/templates/available-customers');

            $response->assertSuccessful()
                ->assertJsonStructure([
                    'customers' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                        ],
                    ],
                    'general_option',
                ]);

            $responseData = $response->json();
            expect($responseData['customers'])->toHaveCount(4); // 1 from beforeEach + 3 active
            expect($responseData['general_option'])->toHaveKey('name', 'General (no specific customer)');
        });
    });

    describe('POST /api/templates/validate', function () {
        it('validates correct template structure', function () {
            $validData = [
                'name' => 'Valid Template',
                'currency' => 'USD',
                'template_data' => [
                    'line_items' => [
                        [
                            'description' => 'Valid Item',
                            'quantity' => 1,
                            'unit_price' => 100.00,
                            'tax_rate' => 10,
                        ],
                    ],
                ],
            ];

            $response = $this->postJson('/api/templates/validate', $validData);

            $response->assertSuccessful()
                ->assertJson([
                    'valid' => true,
                    'message' => 'Template structure is valid',
                ]);
        });

        it('returns validation errors for invalid structure', function () {
            $invalidData = [
                'name' => '',
                'currency' => 'INVALID',
                'template_data' => [
                    'line_items' => [],
                ],
            ];

            $response = $this->postJson('/api/templates/validate', $invalidData);

            $response->assertStatus(422)
                ->assertJsonStructure([
                    'valid',
                    'errors',
                ]);
        });
    });

    describe('Authorization', function () {
        it('prevents unauthorized access', function () {
            // Create user without permissions
            $unauthorizedUser = User::factory()->create();
            $this->company->users()->attach($unauthorizedUser->id, ['role' => 'member']);

            Sanctum::actingAs($unauthorizedUser);

            $response = $this->getJson('/api/templates');
            $response->assertForbidden();
        });

        it('allows access to authorized users', function () {
            // Give user permission
            $this->user->givePermissionTo('templates.view');

            $response = $this->getJson('/api/templates');
            $response->assertSuccessful();
        });
    });
});
