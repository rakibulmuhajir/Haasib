<?php

namespace Tests\Feature\Ledger\PeriodClose;

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Domain\AccountingPeriod\Models\AccountingPeriod;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTemplate;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTemplateTask;
use Tests\TestCase;

class PeriodCloseTemplateTest extends TestCase
{
    private User $user;

    private Company $company;

    private Company $otherCompany;

    private AccountingPeriod $period;

    private AccountingPeriod $period2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->otherCompany = Company::factory()->create();

        // Give user required permissions
        $this->user->givePermissionTo('period-close.view');
        $this->user->givePermissionTo('period-close.templates.manage');

        // Add user to company
        $this->company->users()->attach($this->user->id, ['role' => 'controller']);

        $this->actingAs($this->user);
        $this->withHeader('X-Company-Id', $this->company->id);

        // Create test periods
        $this->period = AccountingPeriod::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'open',
        ]);

        $this->period2 = AccountingPeriod::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'open',
        ]);
    }

    /** @test */
    public function it_can_create_period_close_templates()
    {
        $templateData = [
            'name' => 'Monthly Close Template v1',
            'frequency' => 'monthly',
            'description' => 'Standard monthly closing checklist',
            'is_default' => false,
            'tasks' => [
                [
                    'code' => 'tb_validate',
                    'title' => 'Validate Trial Balance',
                    'category' => 'trial_balance',
                    'sequence' => 1,
                    'is_required' => true,
                    'default_notes' => 'Ensure trial balance is accurate',
                ],
                [
                    'code' => 'gl_reconcile',
                    'title' => 'Reconcile General Ledger',
                    'category' => 'compliance',
                    'sequence' => 2,
                    'is_required' => true,
                    'default_notes' => 'Reconcile all ledger accounts',
                ],
                [
                    'code' => 'reports_generate',
                    'title' => 'Generate Financial Reports',
                    'category' => 'reporting',
                    'sequence' => 3,
                    'is_required' => false,
                    'default_notes' => 'Generate standard financial statements',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/ledger/period-close/templates', $templateData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Template created successfully',
                'data' => [
                    'name' => 'Monthly Close Template v1',
                    'frequency' => 'monthly',
                    'description' => 'Standard monthly closing checklist',
                    'is_default' => false,
                    'active' => true,
                    'company_id' => $this->company->id,
                ],
            ]);

        // Verify template was created
        $this->assertDatabaseHas('ledger.period_close_templates', [
            'company_id' => $this->company->id,
            'name' => 'Monthly Close Template v1',
            'frequency' => 'monthly',
        ]);

        // Verify tasks were created
        $template = PeriodCloseTemplate::where('name', 'Monthly Close Template v1')->first();
        $this->assertDatabaseCount('ledger.period_close_template_tasks', 3);
        $this->assertDatabaseHas('ledger.period_close_template_tasks', [
            'template_id' => $template->id,
            'code' => 'tb_validate',
            'sequence' => 1,
            'is_required' => true,
        ]);
    }

    /** @test */
    public function it_validates_template_creation_requirements()
    {
        // Test missing name
        $response = $this->postJson('/api/v1/ledger/period-close/templates', [
            'frequency' => 'monthly',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Test invalid frequency
        $response = $this->postJson('/api/v1/ledger/period-close/templates', [
            'name' => 'Test Template',
            'frequency' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['frequency']);

        // Test tasks array is required
        $response = $this->postJson('/api/v1/ledger/period-close/templates', [
            'name' => 'Test Template',
            'frequency' => 'monthly',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tasks']);

        // Test tasks must be an array
        $response = $this->postJson('/api/v1/ledger/period-close/templates', [
            'name' => 'Test Template',
            'frequency' => 'monthly',
            'tasks' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tasks']);
    }

    /** @test */
    public function it_enforces_company_scoping_for_templates()
    {
        $templateData = [
            'name' => 'Company A Template',
            'frequency' => 'monthly',
            'tasks' => [
                [
                    'code' => 'test_task',
                    'title' => 'Test Task',
                    'category' => 'misc',
                    'sequence' => 1,
                    'is_required' => true,
                ],
            ],
        ];

        // Create template for current company - should succeed
        $response = $this->postJson('/api/v1/ledger/period-close/templates', $templateData);
        $response->assertStatus(201);

        // Switch to other company context
        $this->withHeader('X-Company-Id', $this->otherCompany->id);

        // Try to access template from different company - should fail
        $response = $this->getJson('/api/v1/ledger/period-close/templates');
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('total'));
    }

    /** @test */
    public function it_can_list_templates_for_a_company()
    {
        // Create some templates
        PeriodCloseTemplate::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'active' => true,
        ]);

        PeriodCloseTemplate::factory()->create([
            'company_id' => $this->company->id,
            'active' => false,
        ]);

        PeriodCloseTemplate::factory()->count(2)->create([
            'company_id' => $this->otherCompany->id,
        ]);

        $response = $this->getJson('/api/v1/ledger/period-close/templates');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total' => 3,
                    'active_templates' => 3,
                ],
            ]);

        // Should only return active templates from current company
        $templateIds = collect($response->json('data.templates'))->pluck('id');
        $this->assertEquals(3, $templateIds->count());
    }

    /** @test */
    public function it_can_update_template_details()
    {
        $template = PeriodCloseTemplate::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Original Template',
            'frequency' => 'monthly',
        ]);

        $updateData = [
            'name' => 'Updated Template',
            'description' => 'Updated description',
            'is_default' => true,
            'active' => true,
        ];

        $response = $this->putJson("/api/v1/ledger/period-close/templates/{$template->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Template updated successfully',
                'data' => [
                    'id' => $template->id,
                    'name' => 'Updated Template',
                    'description' => 'updated description',
                    'is_default' => true,
                    'active' => true,
                ],
            ]);

        $this->assertDatabaseHas('ledger.period_close_templates', [
            'id' => $template->id,
            'name' => 'Updated Template',
            'description' => 'Updated description',
            'is_default' => true,
        ]);
    }

    /** @test */
    public function it_can_archive_templates()
    {
        $template = PeriodCloseTemplate::factory()->create([
            'company_id' => $this->company->id,
            'active' => true,
        ]);

        $response = $this->postJson("/api/v1/ledger/period-close/templates/{$template->id}/archive");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Template archived successfully',
            ]);

        $this->assertDatabaseHas('ledger.period_close_templates', [
            'id' => $template->id,
            'active' => false,
        ]);
    }

    /** @test */
    public function it_can_sync_template_to_period_close()
    {
        // Create a template with tasks
        $template = PeriodCloseTemplate::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Sync Template',
            'frequency' => 'monthly',
        ]);

        // Create template tasks
        PeriodCloseTemplateTask::factory()->count(3)->create([
            'template_id' => $template->id,
        ]);

        // Create a period close
        $periodClose = PeriodClose::factory()->create([
            'company_id' => $this->company->id,
            'accounting_period_id' => $this->period->id,
            'status' => 'in_review',
        ]);

        // Sync template to period close
        $response = $this->postJson("/api/v1/ledger/period-close/templates/{$template->id}/sync", [
            'period_close_id' => $periodClose->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Template synced to period close successfully',
                'data' => [
                    'synced_tasks_count' => 3,
                    'template_id' => $template->id,
                    'period_close_id' => $periodClose->id,
                ],
            ]);

        // Verify tasks were created in period close
        $this->assertDatabaseCount('ledger.period_close_tasks', 3);
        $this->assertDatabaseHas('ledger.period_close_tasks', [
            'period_close_id' => $periodClose->id,
            'template_task_id' => $template->templateTasks->first()->id,
        ]);
    }

    /** @test */
    public function it_validates_template_task_requirements()
    {
        $template = PeriodCloseTemplate::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $invalidTasks = [
            [
                // Missing required fields
                'title' => 'Invalid Task',
            ],
            [
                // Invalid sequence
                'code' => 'task_2',
                'title' => 'Task 2',
                'category' => 'misc',
                'sequence' => 2,
                'is_required' => true,
            ],
            [
                // Invalid category
                'code' => 'task_3',
                'title' => 'Task 3',
                'category' => 'invalid_category',
                'sequence' => 3,
                'is_required' => true,
            ],
        ];

        foreach ($invalidTasks as $taskData) {
            $response = $this->postJson('/api/v1/ledger/period-close/templates', [
                'name' => 'Test Template',
                'frequency' => 'monthly',
                'tasks' => [$taskData],
            ]);

            $response->assertStatus(422);
        }
    }

    /** @test */
    public function it_enforces_unique_template_names_per_company()
    {
        // Create first template
        PeriodCloseTemplate::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Duplicate Name',
        ]);

        // Try to create second template with same name
        $response = $this->postJson('/api/v1/ledger/period-close/templates', [
            'name' => 'Duplicate Name',
            'frequency' => 'monthly',
            'tasks' => [
                [
                    'code' => 'test_task',
                    'title' => 'Test Task',
                    'category' => 'misc',
                    'sequence' => 1,
                    'is_required' => true,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function it_supports_template_task_sequences()
    {
        $tasks = [
            [
                'code' => 'task_a',
                'title' => 'Task A',
                'category' => 'trial_balance',
                'sequence' => 1,
                'is_required' => true,
            ],
            [
                'code' => 'task_b',
                'title' => 'Task B',
                'category' => 'compliance',
                'sequence' => 2,
                'is_required' => false,
            ],
            [
                'code' => 'task_c',
                'title' => 'Task C',
                'category' => 'reporting',
                'sequence' => 1, // Duplicate sequence
                'is_required' => true,
            ],
        ];

        $response = $this->postJson('/api/v1/ledger/period-close/templates', [
            'name' => 'Sequence Test Template',
            'frequency' => 'monthly',
            'tasks' => $tasks,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tasks.2.sequence']);
    }

    /** @test */
    public function it_can_set_default_template()
    {
        // Create existing default template
        $existingDefault = PeriodCloseTemplate::factory()->create([
            'company_id' => $this->company->id,
            'is_default' => true,
        ]);

        // Create new template and set as default
        $response = $this->postJson('/api/v1/ledger/period-close/templates', [
            'name' => 'New Default Template',
            'frequency' => 'monthly',
            'is_default' => true,
            'tasks' => [
                [
                    'code' => 'test_task',
                    'title' => 'Test Task',
                    'category' => 'misc',
                    'sequence' => 1,
                    'is_required' => true,
                ],
            ],
        ]);

        $response->assertStatus(201);

        // Verify old template is no longer default
        $this->assertDatabaseHas('ledger.period_close_templates', [
            'id' => $existingDefault->id,
            'is_default' => false,
        ]);

        // Verify new template is default
        $newTemplate = PeriodCloseTemplate::where('name', 'New Default Template')->first();
        $this->assertTrue($newTemplate->is_default);
    }

    /** @test */
    public function it_prevents_unauthorized_template_access()
    {
        $unauthorizedUser = User::factory()->create();
        $this->actingAs($unauthorizedUser);
        $this->withHeader('X-Company-Id', $this->company->id);

        $response = $this->postJson('/api/v1/ledger/period-close/templates', [
            'name' => 'Unauthorized Template',
            'frequency' => 'monthly',
            'tasks' => [
                [
                    'code' => 'test_task',
                    'title' => 'Test Task',
                    'category' => 'misc',
                    'sequence' => 1,
                    'is_required' => true,
                ],
            ],
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_provides_template_statistics()
    {
        // Create templates with different statuses
        PeriodCloseTemplate::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'active' => true,
            'frequency' => 'monthly',
        ]);

        PeriodCloseTemplate::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'active' => false,
            'frequency' => 'quarterly',
        ]);

        PeriodCloseTemplate::factory()->create([
            'company_id' => $this->company->id,
            'is_default' => true,
        ]);

        $response = $this->getJson('/api/v1/ledger/period-close/templates/statistics');

        $response->assertStatus(200)
            ->assertJson([
                'total_templates' => 8,
                'active_templates' => 5,
                'archived_templates' => 2,
                'default_template' => true,
                'monthly_templates' => 5,
                'quarterly_templates' => 2,
            ]);
    }

    /** @test */
    public function it_can_duplicate_existing_templates()
    {
        // Create source template with tasks
        $sourceTemplate = PeriodCloseTemplate::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Source Template',
            'description' => 'Original template',
            'is_default' => false,
        ]);

        PeriodCloseTemplateTask::factory()->count(3)->create([
            'template_id' => $sourceTemplate->id,
        ]);

        $response = $this->postJson("/api/v1/ledger/period-close/templates/{$sourceTemplate->id}/duplicate", [
            'name' => 'Duplicated Template',
            'description' => 'Copy of source template',
        ]);

        $response->assertStatus(201);

        // Verify duplicated template exists
        $this->assertDatabaseHas('ledger.period_close_templates', [
            'company_id' => $this->company->id,
            'name' => 'Duplicated Template',
            'description' => 'Copy of source template',
        ]);

        // Verify tasks were duplicated
        $duplicatedTemplate = PeriodCloseTemplate::where('name', 'Duplicated Template')->first();
        $this->assertDatabaseCount('ledger.period_close_template_tasks', 6); // 3 original + 3 duplicated

        $this->assertDatabaseHas('ledger.period_close_template_tasks', [
            'template_id' => $duplicatedTemplate->id,
        ]);
    }
}
