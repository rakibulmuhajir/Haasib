<?php

namespace Tests\Feature\Ledger\PeriodClose;

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Services\PeriodCloseService;
use Tests\TestCase;

class PeriodCloseChecklistTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private AccountingPeriod $period;

    private PeriodCloseService $periodCloseService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->period = AccountingPeriod::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'open',
        ]);
        $this->periodCloseService = new PeriodCloseService;

        // Give user period close permissions
        $this->user->givePermissionTo('period-close.view', 'period-close.start', 'period-close.validate');
        $this->user->companies()->attach($this->company->id, ['role' => 'owner']);
    }

    /** @test */
    public function it_can_view_period_close_dashboard()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/ledger/periods/{$this->period->id}/close");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Ledger/PeriodClose/Index')
            ->where('period.id', $this->period->id)
        );
    }

    /** @test */
    public function it_requires_period_close_view_permission()
    {
        $unauthorizedUser = User::factory()->create();
        $unauthorizedUser->companies()->attach($this->company->id, ['role' => 'member']);

        $response = $this->actingAs($unauthorizedUser)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/ledger/periods/{$this->period->id}/close");

        $response->assertForbidden();
    }

    /** @test */
    public function it_cannot_access_other_companies_periods()
    {
        $otherCompany = Company::factory()->create();
        $otherPeriod = AccountingPeriod::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/ledger/periods/{$otherPeriod->id}/close");

        $response->assertNotFound();
    }

    /** @test */
    public function it_can_start_period_close_workflow()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'id',
            'accounting_period_id',
            'status',
            'started_at',
            'tasks',
        ]);

        $this->assertDatabaseHas('ledger.period_closes', [
            'accounting_period_id' => $this->period->id,
            'status' => 'in_review',
            'started_by' => $this->user->id,
        ]);

        // Check that tasks were created
        $this->assertDatabaseHas('ledger.period_close_tasks', [
            'period_close_id' => PeriodClose::where('accounting_period_id', $this->period->id)->first()->id,
        ]);
    }

    /** @test */
    public function it_can_refresh_period_close_checklist()
    {
        // First start a close
        $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->getJson("/api/v1/ledger/periods/{$this->period->id}/close");

        $response->assertOk();
        $response->assertJsonStructure([
            'id',
            'accounting_period_id',
            'status',
            'trial_balance_variance',
            'unposted_documents',
            'tasks' => [
                '*' => [
                    'id',
                    'code',
                    'title',
                    'category',
                    'status',
                    'sequence',
                    'is_required',
                ],
            ],
        ]);
    }

    /** @test */
    public function it_can_run_period_close_validations()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/validate");

        $response->assertOk();
        $response->assertJsonStructure([
            'period_id',
            'trial_balance_variance',
            'unposted_documents',
            'warnings',
        ]);
    }

    /** @test */
    public function it_returns_default_checklist_tasks_when_starting()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        $response->assertStatus(202);

        $data = $response->json();
        $this->assertArrayHasKey('tasks', $data);
        $this->assertCount(5, $data['tasks']); // Default monthly tasks

        // Verify default task codes
        $taskCodes = collect($data['tasks'])->pluck('code');
        $this->assertTrue($taskCodes->contains('tb-validate'));
        $this->assertTrue($taskCodes->contains('subledger-ap'));
        $this->assertTrue($taskCodes->contains('subledger-ar'));
        $this->assertTrue($taskCodes->contains('bank-reconcile'));
        $this->assertTrue($taskCodes->contains('management-reports'));
    }

    /** @test */
    public function it_updates_accounting_period_status_when_starting_close()
    {
        $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        $this->period->refresh();
        $this->assertEquals('closing', $this->period->status);
    }

    /** @test */
    public function it_prevents_duplicate_period_close_workflows()
    {
        // Start first close
        $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        // Try to start again
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        $response->assertStatus(409);
        $response->assertJson(['message' => 'Period close already in progress']);
    }

    /** @test */
    public function it_validates_task_completion_requirements()
    {
        // Start a close
        $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        $periodClose = PeriodClose::where('accounting_period_id', $this->period->id)->first();
        $requiredTasks = $periodClose->requiredTasks;

        // Initially, required tasks should not be completed
        $this->assertFalse($periodClose->allRequiredTasksCompleted());

        // Complete all required tasks
        foreach ($requiredTasks as $task) {
            $this->actingAs($this->user)
                ->withSession(['current_company_id' => $this->company->id])
                ->patchJson("/api/v1/ledger/periods/{$this->period->id}/close/tasks/{$task->id}", [
                    'status' => 'completed',
                    'notes' => 'Task completed for testing',
                ]);
        }

        $periodClose->refresh();
        $this->assertTrue($periodClose->allRequiredTasksCompleted());
        $this->assertEquals('awaiting_approval', $periodClose->status);
    }

    /** @test */
    public function it_handles_trial_balance_validation()
    {
        // Start a close
        $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/validate");

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('trial_balance_variance', $data);
        $this->assertIsNumeric($data['trial_balance_variance']);
    }

    /** @test */
    public function it_detects_unposted_documents()
    {
        // Create some unposted invoices for the period
        \App\Models\Invoice::factory()->count(3)->create([
            'accounting_period_id' => $this->period->id,
            'status' => 'draft',
            'company_id' => $this->company->id,
        ]);

        // Start a close
        $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/validate");

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('unposted_documents', $data);
        $this->assertIsArray($data['unposted_documents']);
        $this->assertNotEmpty($data['unposted_documents']);

        // Should detect the unposted invoices
        $invoicingDocs = collect($data['unposted_documents'])->firstWhere('module', 'invoicing');
        $this->assertNotNull($invoicingDocs);
        $this->assertEquals(3, $invoicingDocs['count']);
    }

    /** @test */
    public function it_provides_validation_warnings()
    {
        // Start a close
        $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/validate");

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('warnings', $data);
        $this->assertIsArray($data['warnings']);
    }

    /** @test */
    public function it_cannot_start_close_for_already_closed_period()
    {
        $this->period->update(['status' => 'closed']);

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        $response->assertStatus(409);
    }

    /** @test */
    public function it_validates_checklist_tasks_are_present()
    {
        $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->getJson("/api/v1/ledger/periods/{$this->period->id}/close");

        // Should have default checklist tasks
        $response->assertJsonCount(5, 'tasks'); // Default template tasks

        $tasks = $response->json('tasks');
        $taskCodes = collect($tasks)->pluck('code');

        $this->assertTrue($taskCodes->contains('tb-validate'));
        $this->assertTrue($taskCodes->contains('subledger-ap'));
        $this->assertTrue($taskCodes->contains('subledger-ar'));
        $this->assertTrue($taskCodes->contains('bank-reconcile'));
        $this->assertTrue($taskCodes->contains('management-reports'));
    }

    /** @test */
    public function it_prevents_duplicate_close_workflows()
    {
        // Start first close
        $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        // Try to start another
        $response = $this->actingAs($this->user)
            ->withSession(['current_company_id' => $this->company->id])
            ->postJson("/api/v1/ledger/periods/{$this->period->id}/close/start");

        $response->assertStatus(409);
        $response->assertJson(['message' => 'Period close already in progress']);
    }
}
