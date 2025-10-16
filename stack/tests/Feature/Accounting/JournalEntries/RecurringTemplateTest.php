<?php

use App\Models\Account;
use App\Models\JournalBatch;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\RecurringJournalTemplate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Modules\Accounting\Domain\Ledgers\Actions\Recurring\CreateRecurringTemplateAction;
use Modules\Accounting\Events\JournalBatchCreated;
use Modules\Accounting\Events\RecurringJournalTemplateCreated;
use Modules\Accounting\Jobs\GenerateRecurringJournalEntries;

beforeEach(function () {
    $this->company = createCompany();
    $this->user = createUser(['company_id' => $this->company->id]);

    // Create test accounts
    $this->cashAccount = Account::factory()->create([
        'company_id' => $this->company->id,
        'code' => '10000',
        'name' => 'Cash',
        'normal_balance' => 'debit',
    ]);

    $this->receivablesAccount = Account::factory()->create([
        'company_id' => $this->company->id,
        'code' => '12000',
        'name' => 'Accounts Receivable',
        'normal_balance' => 'debit',
    ]);

    $this->revenueAccount = Account::factory()->create([
        'company_id' => $this->company->id,
        'code' => '40000',
        'name' => 'Sales Revenue',
        'normal_balance' => 'credit',
    ]);

    $this->expenseAccount = Account::factory()->create([
        'company_id' => $this->company->id,
        'code' => '50000',
        'name' => 'Office Expense',
        'normal_balance' => 'debit',
    ]);
});

describe('Recurring Templates & Batch Processing', function () {

    it('creates a recurring journal template with validation', function () {
        // Arrange: Template data
        $templateData = [
            'name' => 'Monthly Rent Payment',
            'description' => 'Monthly rent expense for office space',
            'company_id' => $this->company->id,
            'frequency' => 'monthly',
            'interval' => 1,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'currency' => 'USD',
            'lines' => [
                [
                    'account_id' => $this->expenseAccount->id,
                    'debit_credit' => 'debit',
                    'amount' => 5000,
                    'description' => 'Monthly rent payment',
                ],
                [
                    'account_id' => $this->cashAccount->id,
                    'debit_credit' => 'credit',
                    'amount' => 5000,
                    'description' => 'Cash payment for rent',
                ],
            ],
        ];

        // Act: Create template
        $action = new CreateRecurringTemplateAction;
        $template = $action->execute($templateData);

        // Assert: Template created successfully
        expect($template)->toBeInstanceOf(RecurringJournalTemplate::class);
        expect($template->name)->toBe('Monthly Rent Payment');
        expect($template->frequency)->toBe('monthly');
        expect($template->is_active)->toBe(true);
        expect($template->next_generation_date)->toBe(now()->addMonth()->toDateString());

        // Check that lines were created
        expect($template->lines()->count())->toBe(2);
    });

    it('validates template date ranges and prevents invalid configurations', function () {
        // Arrange: Invalid template with end date before start date
        $templateData = [
            'name' => 'Invalid Template',
            'company_id' => $this->company->id,
            'frequency' => 'monthly',
            'start_date' => now()->addMonth()->format('Y-m-d'),
            'end_date' => now()->subMonth()->format('Y-m-d'),
            'lines' => [],
        ];

        // Act & Assert: Should throw validation exception
        expect(fn () => (new CreateRecurringTemplateAction)->execute($templateData))
            ->toThrow('End date must be after start date');
    });

    it('calculates next generation date correctly for different frequencies', function () {
        $testCases = [
            [
                'frequency' => 'daily',
                'expected_next' => now()->addDay()->toDateString(),
            ],
            [
                'frequency' => 'weekly',
                'expected_next' => now()->addWeek()->toDateString(),
            ],
            [
                'frequency' => 'monthly',
                'expected_next' => now()->addMonth()->toDateString(),
            ],
            [
                'frequency' => 'quarterly',
                'expected_next' => now()->addMonths(3)->toDateString(),
            ],
            [
                'frequency' => 'yearly',
                'expected_next' => now()->addYear()->toDateString(),
            ],
        ];

        foreach ($testCases as $testCase) {
            $templateData = [
                'name' => 'Test Template',
                'company_id' => $this->company->id,
                'frequency' => $testCase['frequency'],
                'start_date' => now()->format('Y-m-d'),
                'lines' => [
                    [
                        'account_id' => $this->expenseAccount->id,
                        'debit_credit' => 'debit',
                        'amount' => 100,
                    ],
                    [
                        'account_id' => $this->cashAccount->id,
                        'debit_credit' => 'credit',
                        'amount' => 100,
                    ],
                ],
            ];

            $action = new CreateRecurringTemplateAction;
            $template = $action->execute($templateData);

            expect($template->next_generation_date)->toBe($testCase['expected_next']);
        }
    });

    it('generates journal entries from recurring template via job', function () {
        // Arrange: Create active template
        $template = RecurringJournalTemplate::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Monthly Template',
            'frequency' => 'monthly',
            'next_generation_date' => now()->subDay()->toDateString(), // Should generate today
            'is_active' => true,
        ]);

        // Create template lines
        $template->lines()->createMany([
            [
                'account_id' => $this->expenseAccount->id,
                'debit_credit' => 'debit',
                'amount' => 1000,
                'description' => 'Monthly expense',
            ],
            [
                'account_id' => $this->cashAccount->id,
                'debit_credit' => 'credit',
                'amount' => 1000,
                'description' => 'Cash payment',
            ],
        ]);

        // Act: Execute the job
        $job = new GenerateRecurringJournalEntries;
        $job->handle();

        // Assert: Journal entry was created
        expect(JournalEntry::where('template_id', $template->id)->count())->toBe(1);

        $journalEntry = JournalEntry::where('template_id', $template->id)->first();
        expect($journalEntry->description)->toContain('Monthly Template');
        expect($journalEntry->status)->toBe('draft');
        expect($journalEntry->transactions()->count())->toBe(2);

        // Assert: Template next generation date was updated
        $template->refresh();
        expect($template->next_generation_date)->toBe(now()->addMonth()->toDateString());
    });

    it('creates and processes journal batches correctly', function () {
        // Arrange: Create some draft journal entries
        $entries = JournalEntry::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'status' => 'draft',
        ]);

        // Create transactions for each entry
        foreach ($entries as $entry) {
            JournalTransaction::factory()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => $this->receivablesAccount->id,
                'debit_credit' => 'debit',
                'amount' => 1000,
            ]);
            JournalTransaction::factory()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => $this->revenueAccount->id,
                'debit_credit' => 'credit',
                'amount' => 1000,
            ]);
        }

        // Act: Create batch
        $batch = JournalBatch::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Monthly Closing Batch',
            'status' => 'pending',
        ]);

        // Associate entries with batch
        $batch->journalEntries()->attach($entries->pluck('id'));

        // Assert: Batch created with correct relationships
        expect($batch->journalEntries()->count())->toBe(3);
        expect($batch->status)->toBe('pending');
        expect($batch->total_entries)->toBe(3);
    });

    it('processes batch approval workflow correctly', function () {
        // Arrange: Create batch with draft entries
        $entries = JournalEntry::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'status' => 'draft',
        ]);

        $batch = JournalBatch::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Batch',
            'status' => 'pending',
        ]);

        $batch->journalEntries()->attach($entries->pluck('id'));

        // Act: Approve batch
        $batch->approve($this->user->id);

        // Assert: Batch and entries updated
        $batch->refresh();
        expect($batch->status)->toBe('approved');
        expect($batch->approved_at)->toBeTruthy();
        expect($batch->approved_by)->toBe($this->user->id);

        foreach ($entries as $entry) {
            $entry->refresh();
            expect($entry->status)->toBe('approved');
        }
    });

    it('processes batch posting workflow correctly', function () {
        // Arrange: Create approved batch with approved entries
        $entries = JournalEntry::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'status' => 'approved',
        ]);

        foreach ($entries as $entry) {
            JournalTransaction::factory()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => $this->receivablesAccount->id,
                'debit_credit' => 'debit',
                'amount' => 1000,
            ]);
            JournalTransaction::factory()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => $this->revenueAccount->id,
                'debit_credit' => 'credit',
                'amount' => 1000,
            ]);
        }

        $batch = JournalBatch::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Batch',
            'status' => 'approved',
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        $batch->journalEntries()->attach($entries->pluck('id'));

        // Act: Post batch
        $batch->post($this->user->id);

        // Assert: Batch and entries posted
        $batch->refresh();
        expect($batch->status)->toBe('posted');
        expect($batch->posted_at)->toBeTruthy();
        expect($batch->posted_by)->toBe($this->user->id);

        foreach ($entries as $entry) {
            $entry->refresh();
            expect($entry->status)->toBe('posted');
        }
    });

    it('validates batch transitions and prevents invalid operations', function () {
        // Arrange: Create posted batch
        $batch = JournalBatch::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'posted',
            'posted_by' => $this->user->id,
            'posted_at' => now(),
        ]);

        // Act & Assert: Cannot approve already posted batch
        expect(fn () => $batch->approve($this->user->id))
            ->toThrow('Cannot approve a batch that is not pending');
    });

    it('emits events for template and batch lifecycle', function () {
        // Arrange: Set up event fake
        Event::fake();

        // Act: Create template
        $template = RecurringJournalTemplate::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // Create batch
        $batch = JournalBatch::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // Assert: Events were dispatched
        Event::assertDispatched(RecurringJournalTemplateCreated::class, function ($event) use ($template) {
            return $event->template->id === $template->id;
        });

        Event::assertDispatched(JournalBatchCreated::class, function ($event) use ($batch) {
            return $event->batch->id === $batch->id;
        });
    });

    it('schedules recurring template generation correctly', function () {
        // Arrange: Create multiple templates
        $templates = RecurringJournalTemplate::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'next_generation_date' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        // Act: Execute the scheduled job
        Queue::fake();

        $job = new GenerateRecurringJournalEntries;
        $job->handle();

        // Assert: Journal entries created for all templates
        expect(JournalEntry::whereIn('template_id', $templates->pluck('id'))->count())->toBe(3);
    });

    it('handles template deactivation and prevents generation', function () {
        // Arrange: Create inactive template
        $template = RecurringJournalTemplate::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => false,
            'next_generation_date' => now()->subDay()->toDateString(),
        ]);

        // Act: Execute generation job
        $job = new GenerateRecurringJournalEntries;
        $job->handle();

        // Assert: No journal entry created
        expect(JournalEntry::where('template_id', $template->id)->count())->toBe(0);
    });

    it('calculates batch statistics correctly', function () {
        // Arrange: Create batch with different entry statuses
        $draftEntries = JournalEntry::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'status' => 'draft',
        ]);
        $postedEntries = JournalEntry::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'status' => 'posted',
        ]);

        $batch = JournalBatch::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'pending',
        ]);

        $batch->journalEntries()->attach(
            $draftEntries->pluck('id')->merge($postedEntries->pluck('id'))
        );

        // Act: Calculate statistics
        $stats = $batch->getStatistics();

        // Assert: Statistics calculated correctly
        expect($stats['total_entries'])->toBe(5);
        expect($stats['draft_entries'])->toBe(2);
        expect($stats['posted_entries'])->toBe(3);
        expect($stats['pending_entries'])->toBe(0);
    });
});
