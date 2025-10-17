<?php

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTemplate;
use Modules\Ledger\Services\PeriodCloseService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PeriodCloseService;
    $this->user = User::factory()->create();
    $this->company = Company::factory()->create();
    $this->period = AccountingPeriod::factory()->create([
        'company_id' => $this->company->id,
        'status' => 'open',
    ]);

    // Give user necessary permissions
    $this->user->givePermissionTo('period-close.start', 'period-close.validate');
    $this->user->companies()->attach($this->company->id, ['role' => 'owner']);
});

test('it can start period close workflow', function () {
    $periodClose = $this->service->startPeriodClose(
        $this->period->id,
        $this->user,
        'Test period close notes'
    );

    expect($periodClose)->toBeInstanceOf(PeriodClose::class);
    expect($periodClose->accounting_period_id)->toBe($this->period->id);
    expect($periodClose->status)->toBe('in_review');
    expect($periodClose->started_by)->toBe($this->user->id);
    expect($periodClose->closing_summary)->toBe('Test period close notes');

    // Check that accounting period status was updated
    $this->period->refresh();
    expect($this->period->status)->toBe('closing');

    // Check that default tasks were created
    expect($periodClose->tasks->count())->toBeGreaterThan(0);
    expect($periodClose->tasks->count())->toBe(5); // Default monthly tasks
});

test('it throws exception when starting close for non-closable period', function () {
    $this->period->update(['status' => 'closed']);

    expect(fn () => $this->service->startPeriodClose($this->period->id, $this->user))
        ->toThrow(\InvalidArgumentException::class, "Period {$this->period->id} cannot be closed");
});

test('it prevents duplicate period close workflows', function () {
    // Start first close
    $this->service->startPeriodClose($this->period->id, $this->user);

    // Try to start again
    expect(fn () => $this->service->startPeriodClose($this->period->id, $this->user))
        ->toThrow(\Exception::class);
});

test('it can get period close snapshot for non started period', function () {
    $snapshot = $this->service->getPeriodCloseSnapshot($this->period->id);

    expect($snapshot['period']->id)->toBe($this->period->id);
    expect($snapshot['period_close'])->toBeNull();
    expect($snapshot['status'])->toBe('not_started');
    expect($snapshot['tasks'])->toBeEmpty();
    expect($snapshot['validation_summary'])->toBeEmpty();
});

test('it can get period close snapshot for started period', function () {
    $periodClose = $this->service->startPeriodClose($this->period->id, $this->user);

    $snapshot = $this->service->getPeriodCloseSnapshot($this->period->id);

    expect($snapshot['period_close']->id)->toBe($periodClose->id);
    expect($snapshot['status'])->toBe('in_review');
    expect($snapshot['tasks'])->toHaveCount(5);

    // Check task structure
    $task = $snapshot['tasks']->first();
    expect($task)->toHaveKey('id');
    expect($task)->toHaveKey('code');
    expect($task)->toHaveKey('title');
    expect($task)->toHaveKey('category');
    expect($task)->toHaveKey('status');
    expect($task)->toHaveKey('is_required');
});

test('it can validate period close with no unposted documents', function () {
    $periodClose = $this->service->startPeriodClose($this->period->id, $this->user);

    $validation = $this->service->validatePeriodClose($this->period->id);

    expect($validation['period_id'])->toBe($this->period->id);
    expect($validation['trial_balance_variance'])->toBe(0.0);
    expect($validation['unposted_documents'])->toBeArray()->toBeEmpty();
    expect($validation['warnings'])->toBeArray();
});

test('it detects unposted invoices in validation', function () {
    // Create unposted invoices
    Invoice::factory()->count(3)->create([
        'accounting_period_id' => $this->period->id,
        'status' => 'draft',
        'company_id' => $this->company->id,
    ]);

    $periodClose = $this->service->startPeriodClose($this->period->id, $this->user);

    $validation = $this->service->validatePeriodClose($this->period->id);

    expect($validation['unposted_documents'])->not->toBeEmpty();

    $invoicingDocs = collect($validation['unposted_documents'])->firstWhere('module', 'invoicing');
    expect($invoicingDocs)->not->toBeNull();
    expect($invoicingDocs['count'])->toBe(3);
    expect($invoicingDocs['blocking'])->toBeTrue();
    expect($invoicingDocs['details'])->toBe(['Unposted invoices need to be posted']);
});

test('it detects unposted journal entries in validation', function () {
    // Create unposted journal entries
    JournalEntry::factory()->count(2)->create([
        'accounting_period_id' => $this->period->id,
        'status' => 'draft',
    ]);

    $periodClose = $this->service->startPeriodClose($this->period->id, $this->user);

    $validation = $this->service->validatePeriodClose($this->period->id);

    expect($validation['unposted_documents'])->not->toBeEmpty();

    $ledgerDocs = collect($validation['unposted_documents'])->firstWhere('module', 'ledger');
    expect($ledgerDocs)->not->toBeNull();
    expect($ledgerDocs['count'])->toBe(2);
    expect($ledgerDocs['blocking'])->toBeTrue();
    expect($ledgerDocs['details'])->toBe(['Unposted journal entries need to be posted']);
});

test('it detects pending payments in validation', function () {
    // Create invoice with pending payment
    $invoice = Invoice::factory()->create([
        'accounting_period_id' => $this->period->id,
        'status' => 'posted',
        'company_id' => $this->company->id,
    ]);

    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => 'pending',
    ]);

    $periodClose = $this->service->startPeriodClose($this->period->id, $this->user);

    $validation = $this->service->validatePeriodClose($this->period->id);

    expect($validation['unposted_documents'])->not->toBeEmpty();

    $paymentDocs = collect($validation['unposted_documents'])->firstWhere('module', 'payments');
    expect($paymentDocs)->not->toBeNull();
    expect($paymentDocs['count'])->toBe(1);
    expect($paymentDocs['blocking'])->toBeFalse(); // Pending payments are not blocking
    expect($paymentDocs['details'])->toBe(['Pending payments need allocation']);
});

test('it throws exception when validating nonexistent period close', function () {
    expect(fn () => $this->service->validatePeriodClose($this->period->id))
        ->toThrow(\InvalidArgumentException::class, "No period close found for period {$this->period->id}");
});

test('it can complete period close task', function () {
    $periodClose = $this->service->startPeriodClose($this->period->id, $this->user);
    $task = $periodClose->tasks->first();

    $completedTask = $this->service->completeTask(
        $task->id,
        $this->user,
        'Task completed successfully',
        ['attachment1.pdf']
    );

    expect($completedTask->id)->toBe($task->id);
    expect($completedTask->status)->toBe('completed');
    expect($completedTask->completed_by)->toBe($this->user->id);
    expect($completedTask->completed_at)->not->toBeNull();
    expect($completedTask->notes)->toBe('Task completed successfully');
});

test('it submits for approval when all required tasks completed', function () {
    $periodClose = $this->service->startPeriodClose($this->period->id, $this->user);
    $requiredTasks = $periodClose->requiredTasks;

    // Complete all required tasks
    foreach ($requiredTasks as $task) {
        $this->service->completeTask(
            $task->id,
            $this->user,
            'Task completed for testing'
        );
    }

    $periodClose->refresh();
    expect($periodClose->status)->toBe('awaiting_approval');
    expect($periodClose->allRequiredTasksCompleted())->toBeTrue();
});

test('it can create period close template', function () {
    $templateData = [
        'name' => 'Custom Monthly Template',
        'frequency' => 'monthly',
        'description' => 'Custom template for monthly closing',
        'is_default' => false,
        'active' => true,
    ];

    $template = $this->service->createTemplate($this->company, $templateData);

    expect($template)->toBeInstanceOf(PeriodCloseTemplate::class);
    expect($template->company_id)->toBe($this->company->id);
    expect($template->name)->toBe($templateData['name']);
    expect($template->frequency)->toBe($templateData['frequency']);
    expect($template->description)->toBe($templateData['description']);
    expect($template->is_default)->toBeFalse();
    expect($template->active)->toBeTrue();
});

test('it can create template with tasks', function () {
    $templateData = [
        'name' => 'Template with Tasks',
        'frequency' => 'monthly',
        'tasks' => [
            [
                'code' => 'custom-task-1',
                'title' => 'Custom Task 1',
                'category' => 'trial_balance',
                'sequence' => 1,
                'is_required' => true,
                'default_notes' => 'Custom notes for task 1',
            ],
            [
                'code' => 'custom-task-2',
                'title' => 'Custom Task 2',
                'category' => 'reporting',
                'sequence' => 2,
                'is_required' => false,
            ],
        ],
    ];

    $template = $this->service->createTemplate($this->company, $templateData);

    expect($template->tasks)->toHaveCount(2);

    $task1 = $template->tasks->firstWhere('code', 'custom-task-1');
    expect($task1->title)->toBe('Custom Task 1');
    expect($task1->is_required)->toBeTrue();
    expect($task1->default_notes)->toBe('Custom notes for task 1');
});

test('it can set template as default', function () {
    $templateData = [
        'name' => 'Default Template',
        'frequency' => 'monthly',
        'is_default' => true,
    ];

    $template = $this->service->createTemplate($this->company, $templateData);

    expect($template->is_default)->toBeTrue();

    // Verify it's actually the default
    $defaultTemplate = $this->service->getDefaultTemplate($this->company->id, 'monthly');
    expect($defaultTemplate->id)->toBe($template->id);
});

test('it can update period close template', function () {
    $template = $this->service->createTemplate($this->company, [
        'name' => 'Original Template',
        'frequency' => 'monthly',
    ]);

    $updateData = [
        'name' => 'Updated Template',
        'description' => 'Updated description',
        'active' => false,
    ];

    $updatedTemplate = $this->service->updateTemplate($template, $updateData);

    expect($updatedTemplate->name)->toBe('Updated Template');
    expect($updatedTemplate->description)->toBe('Updated description');
    expect($updatedTemplate->active)->toBeFalse();
});

test('it can archive template', function () {
    $template = $this->service->createTemplate($this->company, [
        'name' => 'Template to Archive',
        'frequency' => 'monthly',
        'is_default' => true,
    ]);

    $result = $this->service->archiveTemplate($template);

    expect($result)->toBeTrue();
    $template->refresh();
    expect($template->active)->toBeFalse();
    expect($template->is_default)->toBeFalse();
});

test('it can get default template for company', function () {
    // Create default template
    $defaultTemplate = $this->service->createTemplate($this->company, [
        'name' => 'Default Template',
        'frequency' => 'monthly',
        'is_default' => true,
    ]);

    // Create non-default template
    $this->service->createTemplate($this->company, [
        'name' => 'Other Template',
        'frequency' => 'monthly',
        'is_default' => false,
    ]);

    $retrievedTemplate = $this->service->getDefaultTemplate($this->company->id, 'monthly');

    expect($retrievedTemplate->id)->toBe($defaultTemplate->id);
    expect($retrievedTemplate->is_default)->toBeTrue();
});

test('it returns null for nonexistent default template', function () {
    $template = $this->service->getDefaultTemplate($this->company->id, 'monthly');
    expect($template)->toBeNull();
});

test('it can sync template tasks to period close', function () {
    // Create template with tasks
    $template = $this->service->createTemplate($this->company, [
        'name' => 'Template with Tasks',
        'frequency' => 'monthly',
        'tasks' => [
            [
                'code' => 'sync-task-1',
                'title' => 'Sync Task 1',
                'category' => 'trial_balance',
                'sequence' => 1,
                'is_required' => true,
            ],
            [
                'code' => 'sync-task-2',
                'title' => 'Sync Task 2',
                'category' => 'reporting',
                'sequence' => 2,
                'is_required' => false,
            ],
        ],
    ]);

    // Create period close with template
    $periodClose = PeriodClose::create([
        'accounting_period_id' => $this->period->id,
        'company_id' => $this->company->id,
        'template_id' => $template->id,
        'status' => 'in_review',
        'started_by' => $this->user->id,
        'started_at' => now(),
    ]);

    // Sync tasks
    $this->service->syncTemplateTasks($periodClose, $template);

    $periodClose->refresh();
    expect($periodClose->tasks)->toHaveCount(2);

    $task1 = $periodClose->tasks->firstWhere('code', 'sync-task-1');
    expect($task1->title)->toBe('Sync Task 1');
    expect($task1->template_task_id)->toBe($template->id);
});

test('it replaces non template tasks when syncing', function () {
    // Create period close with default tasks
    $periodClose = $this->service->startPeriodClose($this->period->id, $this->user);
    expect($periodClose->tasks)->toHaveCount(5);

    // Create template with different tasks
    $template = $this->service->createTemplate($this->company, [
        'name' => 'Replacement Template',
        'frequency' => 'monthly',
        'tasks' => [
            [
                'code' => 'replacement-task',
                'title' => 'Replacement Task',
                'category' => 'trial_balance',
                'sequence' => 1,
                'is_required' => true,
            ],
        ],
    ]);

    // Update period close to use template
    $periodClose->update(['template_id' => $template->id]);

    // Sync tasks
    $this->service->syncTemplateTasks($periodClose, $template);

    $periodClose->refresh();
    expect($periodClose->tasks)->toHaveCount(1);
    expect($periodClose->tasks->first()->code)->toBe('replacement-task');
});

test('it logs period close start action', function () {
    Log::shouldReceive('info')
        ->once()
        ->with('Period close started', [
            'period_id' => $this->period->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

    $this->service->startPeriodClose($this->period->id, $this->user);
});

test('it uses database transactions for start period close', function () {
    // Mock a database failure during task creation
    DB::shouldReceive('transaction')
        ->once()
        ->andThrow(new \Exception('Database error'));

    expect(fn () => $this->service->startPeriodClose($this->period->id, $this->user))
        ->toThrow(\Exception::class);

    // Verify period status was not changed due to transaction rollback
    $this->period->refresh();
    expect($this->period->status)->toBe('open');
});
